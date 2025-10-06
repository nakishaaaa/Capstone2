<?php
// Disable error display to prevent HTML output in JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

session_start();
require_once '../config/database.php';
require_once '../includes/csrf.php';

// Include PhpSpreadsheet
require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;

header('Content-Type: application/json');

// Check if user is logged in and has appropriate role (admin, cashier, or super_admin/developer)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'cashier', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !CSRFToken::validate($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    // Debug logging
    error_log("Sales Report API: Action = " . $action);
    error_log("Sales Report API: POST data = " . print_r($_POST, true));
    
    $pdo = Database::getConnection();
    
    switch ($action) {
        case 'generate_report':
            $startDate = $_POST['startDate'] ?? date('Y-m-d');
            $endDate = $_POST['endDate'] ?? date('Y-m-d');
            $category = $_POST['category'] ?? 'all';
            $paymentMethod = $_POST['paymentMethod'] ?? 'all';
            
            error_log("Sales Report API: Generating report for $startDate to $endDate");
            
            $reportData = generateSalesReport($pdo, $startDate, $endDate, $category, $paymentMethod);
            echo json_encode(['success' => true, 'data' => $reportData]);
            break;
            
        case 'export_report':
            $startDate = $_POST['startDate'] ?? date('Y-m-d');
            $endDate = $_POST['endDate'] ?? date('Y-m-d');
            $category = $_POST['category'] ?? 'all';
            $paymentMethod = $_POST['paymentMethod'] ?? 'all';
            
            $exportResult = exportSalesReport($pdo, $startDate, $endDate, $category, $paymentMethod);
            echo json_encode(['success' => true, 'data' => $exportResult]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    error_log("Sales Report API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing the request']);
} catch (Error $e) {
    error_log("Sales Report API Fatal Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A system error occurred']);
}

function generateSalesReport($pdo, $startDate, $endDate, $category, $paymentMethod) {
    error_log("generateSalesReport called with: $startDate, $endDate, $category, $paymentMethod");
    
    // Build WHERE clause for filters
    $whereConditions = [];
    $params = [];
    
    // Date range filter
    $whereConditions[] = "DATE(s.created_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    
    // Category filter (if we have category data in inventory table)
    if ($category !== 'all') {
        $whereConditions[] = "i.category = ?";
        $params[] = $category;
    }
    
    // Payment method filter
    if ($paymentMethod !== 'all') {
        $whereConditions[] = "s.payment_method = ?";
        $params[] = $paymentMethod;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get summary data - Fixed to avoid JOIN multiplication issues
    $summaryQuery = "
        SELECT 
            COUNT(*) as total_transactions,
            COALESCE(SUM(total_amount), 0) as total_sales,
            COALESCE(AVG(total_amount), 0) as avg_transaction
        FROM sales s
        WHERE " . str_replace(['i.category = ?'], ['s.id IN (SELECT DISTINCT si.sale_id FROM sales_items si JOIN inventory i ON si.product_id = i.id WHERE i.category = ?)'], $whereClause) . "
    ";
    
    $stmt = $pdo->prepare($summaryQuery);
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get previous period for comparison
    $daysDiff = (strtotime($endDate) - strtotime($startDate)) / (24 * 60 * 60) + 1;
    $prevStartDate = date('Y-m-d', strtotime($startDate . ' -' . $daysDiff . ' days'));
    $prevEndDate = date('Y-m-d', strtotime($startDate . ' -1 day'));
    
    $prevParams = $params;
    $prevParams[0] = $prevStartDate;
    $prevParams[1] = $prevEndDate;
    
    $stmt = $pdo->prepare($summaryQuery);
    $stmt->execute($prevParams);
    $prevSummary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate changes
    $salesChange = calculatePercentageChange($prevSummary['total_sales'], $summary['total_sales']);
    $transactionsChange = calculatePercentageChange($prevSummary['total_transactions'], $summary['total_transactions']);
    $avgChange = calculatePercentageChange($prevSummary['avg_transaction'], $summary['avg_transaction']);
    
    // Get top product
    $topProductQuery = "
        SELECT 
            i.name as product_name,
            SUM(si.quantity) as total_quantity
        FROM sales s
        JOIN sales_items si ON s.id = si.sale_id
        JOIN inventory i ON si.product_id = i.id
        WHERE $whereClause
        GROUP BY si.product_id, i.name
        ORDER BY total_quantity DESC
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($topProductQuery);
    $stmt->execute($params);
    $topProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get sales trend data
    $salesTrend = getSalesTrendData($pdo, $startDate, $endDate, $category, $paymentMethod);
    
    // Get category breakdown
    $categoryBreakdown = getCategoryBreakdown($pdo, $startDate, $endDate, $paymentMethod);
    
    // Get payment method breakdown
    $paymentBreakdown = getPaymentMethodBreakdown($pdo, $startDate, $endDate, $category);
    
    // Get hourly sales distribution
    $hourlySales = getHourlySalesDistribution($pdo, $startDate, $endDate, $category, $paymentMethod);
    
    // Get top products
    $topProducts = getTopProducts($pdo, $startDate, $endDate, $category, $paymentMethod);
    
    // Get detailed transactions
    $transactions = getDetailedTransactions($pdo, $startDate, $endDate, $category, $paymentMethod);
    
    return [
        'summary' => [
            'totalSales' => floatval($summary['total_sales'] ?? 0),
            'totalTransactions' => intval($summary['total_transactions'] ?? 0),
            'avgTransaction' => floatval($summary['avg_transaction'] ?? 0),
            'salesChange' => $salesChange,
            'transactionsChange' => $transactionsChange,
            'avgChange' => $avgChange,
            'topProduct' => [
                'name' => $topProduct['product_name'] ?? 'No sales data',
                'quantity' => intval($topProduct['total_quantity'] ?? 0)
            ]
        ],
        'charts' => [
            'salesTrend' => $salesTrend,
            'categoryBreakdown' => $categoryBreakdown,
            'paymentMethods' => $paymentBreakdown,
            'hourlySales' => $hourlySales,
            'topProducts' => $topProducts
        ],
        'transactions' => $transactions
    ];
}

function calculatePercentageChange($oldValue, $newValue) {
    if ($oldValue == 0) {
        return $newValue > 0 ? 100 : 0;
    }
    return round((($newValue - $oldValue) / $oldValue) * 100, 1);
}

function getSalesTrendData($pdo, $startDate, $endDate, $category, $paymentMethod) {
    $whereConditions = ["DATE(s.created_at) BETWEEN ? AND ?"];
    $params = [$startDate, $endDate];
    
    if ($category !== 'all') {
        $whereConditions[] = "i.category = ?";
        $params[] = $category;
    }
    
    if ($paymentMethod !== 'all') {
        $whereConditions[] = "s.payment_method = ?";
        $params[] = $paymentMethod;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $dailySalesQuery = "
        SELECT 
            DATE(s.created_at) as sale_date,
            COALESCE(SUM(DISTINCT s.total_amount), 0) as daily_sales
        FROM sales s
        LEFT JOIN sales_items si ON s.id = si.sale_id
        LEFT JOIN inventory i ON si.product_id = i.id
        WHERE $whereClause
        GROUP BY DATE(s.created_at)
        ORDER BY sale_date ASC
    ";
    
    $stmt = $pdo->prepare($dailySalesQuery);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $data = [];
    
    foreach ($results as $row) {
        $labels[] = date('M j', strtotime($row['sale_date']));
        $data[] = floatval($row['daily_sales']);
    }
    
    return ['labels' => $labels, 'data' => $data];
}

function getCategoryBreakdown($pdo, $startDate, $endDate, $paymentMethod) {
    $whereConditions = ["DATE(s.created_at) BETWEEN ? AND ?"];
    $params = [$startDate, $endDate];
    
    if ($paymentMethod !== 'all') {
        $whereConditions[] = "s.payment_method = ?";
        $params[] = $paymentMethod;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $categoryQuery = "
        SELECT 
            COALESCE(i.category, 'Uncategorized') as category,
            COALESCE(SUM(DISTINCT s.total_amount), 0) as category_sales
        FROM sales s
        LEFT JOIN sales_items si ON s.id = si.sale_id
        LEFT JOIN inventory i ON si.product_id = i.id
        WHERE $whereClause
        GROUP BY i.category
    ";
    
    $stmt = $pdo->prepare($categoryQuery);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $data = [];
    
    foreach ($results as $row) {
        $labels[] = $row['category'];
        $data[] = floatval($row['category_sales']);
    }
    
    return ['labels' => $labels, 'data' => $data];
}

function getPaymentMethodBreakdown($pdo, $startDate, $endDate, $category) {
    $whereConditions = ["DATE(s.created_at) BETWEEN ? AND ?"];
    $params = [$startDate, $endDate];
    
    if ($category !== 'all') {
        $whereConditions[] = "i.category = ?";
        $params[] = $category;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $paymentQuery = "
        SELECT 
            s.payment_method,
            COALESCE(SUM(DISTINCT s.total_amount), 0) as payment_sales
        FROM sales s
        LEFT JOIN sales_items si ON s.id = si.sale_id
        LEFT JOIN inventory i ON si.product_id = i.id
        WHERE $whereClause
        GROUP BY s.payment_method
    ";
    
    $stmt = $pdo->prepare($paymentQuery);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $data = [];
    
    foreach ($results as $row) {
        $labels[] = strtoupper($row['payment_method']);
        $data[] = floatval($row['payment_sales']);
    }
    
    return ['labels' => $labels, 'data' => $data];
}

function getHourlySalesDistribution($pdo, $startDate, $endDate, $category, $paymentMethod) {
    $whereConditions = ["DATE(s.created_at) BETWEEN ? AND ?"];
    $params = [$startDate, $endDate];
    
    if ($category !== 'all') {
        $whereConditions[] = "i.category = ?";
        $params[] = $category;
    }
    
    if ($paymentMethod !== 'all') {
        $whereConditions[] = "s.payment_method = ?";
        $params[] = $paymentMethod;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $hourlyQuery = "
        SELECT 
            HOUR(s.created_at) as sale_hour,
            COALESCE(SUM(DISTINCT s.total_amount), 0) as hourly_sales
        FROM sales s
        LEFT JOIN sales_items si ON s.id = si.sale_id
        LEFT JOIN inventory i ON si.product_id = i.id
        WHERE $whereClause
        GROUP BY HOUR(s.created_at)
        ORDER BY sale_hour ASC
    ";
    
    $stmt = $pdo->prepare($hourlyQuery);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $data = [];
    
    foreach ($results as $row) {
        $labels[] = sprintf('%02d:00', $row['sale_hour']);
        $data[] = floatval($row['hourly_sales']);
    }
    
    return ['labels' => $labels, 'data' => $data];
}

function getTopProducts($pdo, $startDate, $endDate, $category, $paymentMethod) {
    $whereConditions = ["DATE(s.created_at) BETWEEN ? AND ?"];
    $params = [$startDate, $endDate];
    
    if ($category !== 'all') {
        $whereConditions[] = "i.category = ?";
        $params[] = $category;
    }
    
    if ($paymentMethod !== 'all') {
        $whereConditions[] = "s.payment_method = ?";
        $params[] = $paymentMethod;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "
        SELECT 
            i.name as product_name,
            SUM(si.quantity) as total_quantity
        FROM sales s
        JOIN sales_items si ON s.id = si.sale_id
        JOIN inventory i ON si.product_id = i.id
        WHERE $whereClause
        GROUP BY si.product_id, i.name
        ORDER BY total_quantity DESC
        LIMIT 5
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $data = [];
    
    foreach ($results as $row) {
        $labels[] = $row['product_name'];
        $data[] = intval($row['total_quantity']);
    }
    
    return ['labels' => $labels, 'data' => $data];
}

function getDetailedTransactions($pdo, $startDate, $endDate, $category, $paymentMethod) {
    try {
        error_log("getDetailedTransactions called with: $startDate, $endDate, $category, $paymentMethod");
        
        $whereConditions = ["DATE(s.created_at) BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        if ($category !== 'all') {
            $whereConditions[] = "i.category = ?";
            $params[] = $category;
        }
        
        if ($paymentMethod !== 'all') {
            $whereConditions[] = "s.payment_method = ?";
            $params[] = $paymentMethod;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        error_log("WHERE clause: $whereClause");
        error_log("Parameters: " . json_encode($params));
    
        // Updated query to avoid JOIN multiplication issues
        $detailWhereClause = str_replace(['i.category = ?'], ['s.id IN (SELECT DISTINCT si.sale_id FROM sales_items si JOIN inventory i ON si.product_id = i.id WHERE i.category = ?)'], $whereClause);
        
        $query = "
            SELECT 
                s.transaction_id,
                s.created_at,
                s.payment_method,
                s.total_amount,
                (SELECT COUNT(*) FROM sales_items si WHERE si.sale_id = s.id) as items_count,
                COALESCE(u.username, 'System User') as cashier_name
            FROM sales s
            LEFT JOIN users u ON s.cashier_id = u.id
            WHERE $detailWhereClause
            ORDER BY s.created_at DESC
            LIMIT 100
        ";
        
        error_log("SQL Query: " . $query);
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Query executed successfully, found " . count($results) . " results");
        
        // Now get cashier names in a separate query to avoid JOIN complexity
        foreach ($results as &$result) {
            if (isset($result['transaction_id'])) {
                $cashierQuery = "SELECT s.cashier_id, u.firstname, u.lastname, u.username 
                               FROM sales s 
                               LEFT JOIN users u ON s.cashier_id = u.id 
                               WHERE s.transaction_id = ?";
                $cashierStmt = $pdo->prepare($cashierQuery);
                $cashierStmt->execute([$result['transaction_id']]);
                $cashier = $cashierStmt->fetch(PDO::FETCH_ASSOC);
                
                error_log("Transaction {$result['transaction_id']}: cashier_id = " . ($cashier['cashier_id'] ?? 'NULL') . ", username = " . ($cashier['username'] ?? 'NULL'));
                
                if ($cashier && $cashier['cashier_id'] && ($cashier['firstname'] || $cashier['lastname'])) {
                    $result['cashier_name'] = trim($cashier['firstname'] . ' ' . $cashier['lastname']);
                } elseif ($cashier && $cashier['cashier_id'] && $cashier['username']) {
                     $result['cashier_name'] = $cashier['username'];
                }
            }
        }
        
        return $results;
        
    } catch (Exception $e) {
        error_log("Error in getDetailedTransactions: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw $e;
    }
}

function exportSalesReport($pdo, $startDate, $endDate, $category, $paymentMethod) {
    $reportData = generateSalesReport($pdo, $startDate, $endDate, $category, $paymentMethod);
    
    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Sales Report');
    
    $row = 1;
    
    // Title
    $sheet->setCellValue('A' . $row, 'Sales Report - ' . $startDate . ' to ' . $endDate);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(16);
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $row += 2;
    
    // Summary section
    $sheet->setCellValue('A' . $row, 'SUMMARY');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('CCCCCC');
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Total Sales');
    $sheet->setCellValue('B' . $row, '₱' . number_format($reportData['summary']['totalSales'], 2));
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Total Transactions');
    $sheet->setCellValue('B' . $row, $reportData['summary']['totalTransactions']);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Average Transaction');
    $sheet->setCellValue('B' . $row, '₱' . number_format($reportData['summary']['avgTransaction'], 2));
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Top Product');
    $sheet->setCellValue('B' . $row, $reportData['summary']['topProduct']['name']);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row += 3;
    
    // Detailed transactions header
    $sheet->setCellValue('A' . $row, 'DETAILED TRANSACTIONS');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('CCCCCC');
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $row++;
    
    // Table headers
    $headers = ['Transaction ID', 'Date', 'Time', 'Payment Method', 'Amount', 'Items Count', 'Cashier'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $row, $header);
        $sheet->getStyle($col . $row)->getFont()->setBold(true);
        $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F0F0');
        $col++;
    }
    $row++;
    
    // Transaction data
    foreach ($reportData['transactions'] as $transaction) {
        $date = date('Y-m-d', strtotime($transaction['created_at']));
        $time = date('H:i:s', strtotime($transaction['created_at']));
        
        $sheet->setCellValue('A' . $row, $transaction['transaction_id']);
        $sheet->setCellValue('B' . $row, $date);
        $sheet->setCellValue('C' . $row, $time);
        $sheet->setCellValue('D' . $row, strtoupper($transaction['payment_method']));
        $sheet->setCellValue('E' . $row, '₱' . number_format($transaction['total_amount'], 2));
        $sheet->setCellValue('F' . $row, $transaction['items_count']);
        $sheet->setCellValue('G' . $row, $transaction['cashier_name'] ?? 'N/A');
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Save as XLSX
    $filename = 'sales_report_' . $startDate . '_to_' . $endDate . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    $filepath = '../exports/' . $filename;
    
    // Create exports directory if it doesn't exist
    if (!file_exists('../exports/')) {
        mkdir('../exports/', 0755, true);
    }
    
    $writer = new Xlsx($spreadsheet);
    $writer->save($filepath);
    
    return [
        'filename' => $filename,
        'downloadUrl' => 'exports/' . $filename
    ];
}
?>
