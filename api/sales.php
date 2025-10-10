<?php
// Start session for user tracking
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Log the request for debugging
error_log("Sales API Request: $method " . $_SERVER['REQUEST_URI']);

try {
    switch($method) {
        case 'GET':
            if (isset($_GET['stats'])) {
                getSalesStats();
            } elseif (isset($_GET['chart'])) {
                getChartData($_GET['period'] ?? 'daily');
            } elseif (isset($_GET['report'])) {
                getSalesReport($_GET['start_date'] ?? null, $_GET['end_date'] ?? null);
            } elseif (isset($_GET['transaction_id'])) {
                getTransactionDetails($_GET['transaction_id']);
            } else {
                getAllSales();
            }
            break;
        case 'POST':
            processSale($input);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getSalesStats() {
    global $pdo;
    try {
        // Today's stats
        $today = date('Y-m-d');
        
        // Get today's sales
        $stmt = $pdo->prepare("SELECT 
            COALESCE(SUM(total_amount), 0) as total_sales,
            COUNT(*) as total_orders
            FROM sales WHERE DATE(created_at) = ?");
        $stmt->execute([$today]);
        $todayStats = $stmt->fetch();
        
        // Total products
        $stmt = $pdo->query("SELECT COUNT(*) as total_products FROM inventory WHERE status = 'active'");
        $productStats = $stmt->fetch();
        
        // Low stock items
        $stmt = $pdo->query("SELECT COUNT(*) as low_stock FROM inventory WHERE stock <= min_stock AND status = 'active'");
        $stockStats = $stmt->fetch();
        
        // Pending requests count
        $stmt = $pdo->query("SELECT COUNT(*) as pending_requests FROM customer_requests WHERE status = 'pending'");
        $requestStats = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_sales' => floatval($todayStats['total_sales']),
                'total_orders' => intval($todayStats['total_orders']),
                'total_products' => intval($productStats['total_products']),
                'low_stock' => intval($stockStats['low_stock']),
                'pending_requests' => intval($requestStats['pending_requests'])
            ]
        ]);
    } catch(PDOException $e) {
        error_log("Database error in getSalesStats: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getChartData($period = 'daily') {
    global $pdo;
    try {
        $salesData = [];
        $labels = [];
        
        switch($period) {
            case 'daily':
                // Last 7 days
                $stmt = $pdo->prepare("
                    SELECT 
                        DATE(created_at) as date,
                        COALESCE(SUM(total_amount), 0) as total_sales,
                        COUNT(*) as total_orders
                    FROM sales 
                    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date ASC
                ");
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Fill in missing days with 0
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $dayName = date('D', strtotime("-$i days"));
                    $labels[] = $dayName;
                    
                    $found = false;
                    foreach ($results as $result) {
                        if ($result['date'] === $date) {
                            $salesData[] = floatval($result['total_sales']);
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $salesData[] = 0;
                    }
                }
                break;
                
            case 'weekly':
                // Last 4 weeks
                $stmt = $pdo->prepare("
                    SELECT 
                        YEARWEEK(created_at, 1) as week,
                        COALESCE(SUM(total_amount), 0) as total_sales,
                        COUNT(*) as total_orders
                    FROM sales 
                    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK)
                    GROUP BY YEARWEEK(created_at, 1)
                    ORDER BY week ASC
                ");
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                for ($i = 3; $i >= 0; $i--) {
                    $weekStart = date('Y-m-d', strtotime("-$i weeks"));
                    $weekNum = date('W', strtotime("-$i weeks"));
                    $labels[] = "Week $weekNum";
                    
                    $yearWeek = date('oW', strtotime("-$i weeks"));
                    $found = false;
                    foreach ($results as $result) {
                        if ($result['week'] == $yearWeek) {
                            $salesData[] = floatval($result['total_sales']);
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $salesData[] = 0;
                    }
                }
                break;
                
            case 'monthly':
                // Last 6 months
                $stmt = $pdo->prepare("
                    SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        COALESCE(SUM(total_amount), 0) as total_sales,
                        COUNT(*) as total_orders
                    FROM sales 
                    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                    ORDER BY month ASC
                ");
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                for ($i = 5; $i >= 0; $i--) {
                    $monthDate = date('Y-m', strtotime("-$i months"));
                    $monthName = date('M Y', strtotime("-$i months"));
                    $labels[] = $monthName;
                    
                    $found = false;
                    foreach ($results as $result) {
                        if ($result['month'] === $monthDate) {
                            $salesData[] = floatval($result['total_sales']);
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $salesData[] = 0;
                    }
                }
                break;
                
            case 'annually':
                // Last 3 years
                $stmt = $pdo->prepare("
                    SELECT 
                        YEAR(created_at) as year,
                        COALESCE(SUM(total_amount), 0) as total_sales,
                        COUNT(*) as total_orders
                    FROM sales 
                    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 3 YEAR)
                    GROUP BY YEAR(created_at)
                    ORDER BY year ASC
                ");
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                for ($i = 2; $i >= 0; $i--) {
                    $year = date('Y', strtotime("-$i years"));
                    $labels[] = $year;
                    
                    $found = false;
                    foreach ($results as $result) {
                        if ($result['year'] == $year) {
                            $salesData[] = floatval($result['total_sales']);
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $salesData[] = 0;
                    }
                }
                break;
        }
        
        // Get top products data - simplified version without sales_items table
        // For now, we'll show products by stock level as a placeholder
        $stmt = $pdo->prepare("
            SELECT 
                name,
                stock as total_sold
            FROM inventory 
            WHERE status = 'active'
            ORDER BY stock DESC
            LIMIT 5
        ");
        $stmt->execute();
        $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $productLabels = [];
        $productData = [];
        
        foreach ($topProducts as $product) {
            $productLabels[] = $product['name'] ?: 'Unknown Product';
            $productData[] = intval($product['total_sold']);
        }
        
        // If no products found, use default data
        if (empty($productLabels)) {
            $productLabels = ['No Sales Data'];
            $productData = [0];
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'period' => $period,
                'sales_chart' => [
                    'labels' => $labels,
                    'data' => $salesData
                ],
                'products_chart' => [
                    'labels' => $productLabels,
                    'data' => $productData
                ]
            ]
        ]);
        
    } catch(PDOException $e) {
        error_log("Database error in getChartData: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function processSale($data) {
    global $pdo;
    try {
        // Validate required data
        if (!isset($data['transaction_id']) || !isset($data['items']) || empty($data['items'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid sale data']);
            return;
        }
        
        $pdo->beginTransaction();
        
        // Insert sale with cashier_id
        $stmt = $pdo->prepare("INSERT INTO sales (transaction_id, customer_name, cashier_id, total_amount, tax_amount, payment_method, amount_received, change_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['transaction_id'],
            $data['customer_name'] ?? 'Walk-in Customer',
            $_SESSION['user_id'] ?? null, // Track who processed the transaction
            floatval($data['total_amount']),
            floatval($data['tax_amount']),
            $data['payment_method'],
            floatval($data['amount_received']),
            floatval($data['change_amount'])
        ]);
        
        $saleId = $pdo->lastInsertId();
        
        // Insert sale items and update inventory
        foreach ($data['items'] as $item) {
            // Validate item data
            if (!isset($item['id']) || !isset($item['quantity'])) {
                throw new Exception('Invalid item data');
            }

            // Check if product exists and has enough stock
            $stmt = $pdo->prepare("SELECT stock, name, price FROM inventory WHERE id = ? AND status = 'active'");
            $stmt->execute([$item['id']]);
            $product = $stmt->fetch();

            if (!$product) {
                throw new Exception("Product with ID {$item['id']} not found");
            }

            // Enhanced stock validation
            if ($product['stock'] <= 0) {
                throw new Exception("Product '{$product['name']}' is out of stock");
            }

            if ($product['stock'] < $item['quantity']) {
                throw new Exception("Insufficient stock for '{$product['name']}'. Available: {$product['stock']}, Requested: {$item['quantity']}");
            }

            // Double-check stock hasn't changed since cart was loaded
            if ($item['quantity'] <= 0) {
                throw new Exception("Invalid quantity for '{$product['name']}'");
            }

            // Insert sale item
            $stmt = $pdo->prepare("INSERT INTO sales_items (sale_id, product_id, product_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $saleId,
                $item['id'],
                $product['name'],
                intval($item['quantity']),
                floatval($product['price']),
                floatval($product['price']) * intval($item['quantity'])
            ]);

            // Update inventory stock with additional validation
            $stmt = $pdo->prepare("UPDATE inventory SET stock = stock - ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND stock >= ?");
            $result = $stmt->execute([intval($item['quantity']), $item['id'], intval($item['quantity'])]);
            
            // Check if the update actually affected any rows (stock was sufficient)
            if ($stmt->rowCount() === 0) {
                throw new Exception("Failed to update stock for '{$product['name']}' - insufficient stock or product not found");
            }
        }
        
        // Check for low stock and create notifications for items that were sold
        foreach ($data['items'] as $item) {
            // Get updated stock after the sale
            $stmt = $pdo->prepare("SELECT name, stock, min_stock FROM inventory WHERE id = ? AND status = 'active'");
            $stmt->execute([$item['id']]);
            $product = $stmt->fetch();
            
            if ($product && $product['stock'] <= $product['min_stock']) {
                // Always create a new notification when stock decreases and is below minimum
                $notificationTitle = "Low Stock Alert: " . $product['name'];
                $message = $product['stock'] == 0 ? 
                    "{$product['name']} is out of stock" : 
                    "{$product['name']} stock is low ({$product['stock']} remaining)";
                
                $notificationType = $product['stock'] == 0 ? 'error' : 'warning';
                
                $stmt = $pdo->prepare("INSERT INTO notifications (title, message, type) VALUES (?, ?, ?)");
                $stmt->execute([
                    $notificationTitle,
                    $message,
                    $notificationType
                ]);
                
                // Send email notification to admins for low stock (non-blocking)
                try {
                    require_once '../includes/email_notifications.php';
                    EmailNotifications::sendAdminNotification($notificationTitle, $message, $notificationType);
                } catch (Exception $emailError) {
                    error_log("Email notification error: " . $emailError->getMessage());
                }
            }
        }
        
        $pdo->commit();
        
        // Trigger real-time stats update via Pusher
        try {
            require_once '../includes/pusher_config.php';
            triggerAdminStatsUpdate($conn);
        } catch (Exception $pusherError) {
            error_log("Pusher trigger error: " . $pusherError->getMessage());
        }
        
        echo json_encode(['success' => true, 'sale_id' => $saleId, 'message' => 'Sale processed successfully']);
        
    } catch(Exception $e) {
        $pdo->rollBack();
        error_log("Error in processSale: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getAllSales() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT s.*, 
            GROUP_CONCAT(CONCAT(si.product_name, ' x', si.quantity) SEPARATOR ', ') as products,
            SUM(si.quantity) as total_quantity,
            COALESCE(u.username, 'System User') as cashier_name
            FROM sales s
            LEFT JOIN sales_items si ON s.id = si.sale_id
            LEFT JOIN users u ON s.cashier_id = u.id
            GROUP BY s.id
            ORDER BY s.created_at DESC
            LIMIT 100");
        $sales = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $sales]);
    } catch(PDOException $e) {
        error_log("Database error in getAllSales: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getSalesReport($startDate, $endDate) {
    global $pdo;
    try {
        $whereClause = "";
        $params = [];
        
        if ($startDate && $endDate) {
            $whereClause = "WHERE DATE(s.created_at) BETWEEN ? AND ?";
            $params = [$startDate, $endDate];
        }
        
        $stmt = $pdo->prepare("SELECT s.*, 
            GROUP_CONCAT(CONCAT(si.product_name, ' x', si.quantity) SEPARATOR ', ') as products,
            SUM(si.quantity) as total_quantity,
            COALESCE(u.username, 'System User') as cashier_name
            FROM sales s
            LEFT JOIN sales_items si ON s.id = si.sale_id
            LEFT JOIN users u ON s.cashier_id = u.id
            $whereClause
            GROUP BY s.id
            ORDER BY s.created_at DESC");
        $stmt->execute($params);
        $sales = $stmt->fetchAll();
        
        // Calculate summary
        $totalRevenue = array_sum(array_column($sales, 'total_amount'));
        $totalOrders = count($sales);
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_revenue' => $totalRevenue,
                    'total_orders' => $totalOrders,
                    'average_order_value' => $avgOrderValue
                ],
                'transactions' => $sales
            ]
        ]);
    } catch(PDOException $e) {
        error_log("Database error in getSalesReport: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getTransactionDetails($transactionId) {
    global $pdo;
    try {
        // Get transaction details with cashier info
        $stmt = $pdo->prepare("SELECT s.*, 
            COALESCE(u.username, 'System User') as cashier_name
            FROM sales s
            LEFT JOIN users u ON s.cashier_id = u.id
            WHERE s.transaction_id = ?");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            echo json_encode(['success' => false, 'error' => 'Transaction not found']);
            return;
        }
        
        // Get transaction items
        $stmt = $pdo->prepare("SELECT si.*
            FROM sales_items si
            WHERE si.sale_id = ?
            ORDER BY si.id");
        $stmt->execute([$transaction['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $transaction['items'] = $items;
        
        echo json_encode(['success' => true, 'data' => $transaction]);
        
    } catch(PDOException $e) {
        error_log("Database error in getTransactionDetails: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
