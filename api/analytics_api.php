<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session_helper.php';

// Check if user is super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? $_GET['type'] ?? '';

try {
    switch ($action) {
        case 'get_sales_analytics':
        case 'sales':
            $salesData = getSalesAnalytics($conn);
            echo json_encode(formatSalesResponse($salesData));
            break;
            
        case 'get_inventory_analytics':
        case 'inventory':
            $inventoryData = getInventoryAnalytics($conn);
            echo json_encode(formatInventoryResponse($inventoryData));
            break;
            
        case 'get_user_analytics':
        case 'users':
            $userData = getUserAnalytics($conn);
            echo json_encode(formatUserResponse($userData));
            break;
            
        case 'get_system_performance':
        case 'performance':
            $performanceData = getSystemPerformance($conn);
            echo json_encode(formatPerformanceResponse($performanceData));
            break;
            
        case 'get_error_analytics':
            $errorData = getErrorAnalytics($conn);
            echo json_encode(['success' => true, 'data' => $errorData]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function getSalesAnalytics($conn) {
    $data = [];
    
    // Get time range from request parameter
    $timeRange = $_GET['time_range'] ?? $_GET['range'] ?? '30d';
    $interval = '30 DAY';
    
    switch($timeRange) {
        case '7d':
            $interval = '7 DAY';
            break;
        case '90d':
            $interval = '90 DAY';
            break;
        case '1y':
            $interval = '365 DAY';
            break;
        default:
            $interval = '30 DAY';
    }
    
    // Daily sales for specified time range - using sales table
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, 
               COUNT(*) as order_count,
               SUM(total_amount) as total_sales,
               SUM(tax_amount) as total_tax,
               AVG(total_amount) as avg_order_value
        FROM sales 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL $interval)
          AND status = 'completed'
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['daily_sales'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Hourly sales for today to show intraday patterns
    $stmt = $conn->prepare("
        SELECT HOUR(created_at) as hour,
               COUNT(*) as order_count,
               SUM(total_amount) as total_sales
        FROM sales 
        WHERE DATE(created_at) = CURDATE()
          AND status = 'completed'
        GROUP BY HOUR(created_at)
        ORDER BY hour ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['hourly_sales'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Monthly sales for last 12 months - using sales table
    $stmt = $conn->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
               DATE_FORMAT(created_at, '%M %Y') as month_name,
               COUNT(*) as order_count,
               SUM(total_amount) as total_sales,
               SUM(tax_amount) as total_tax,
               AVG(total_amount) as avg_order_value
        FROM sales 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
          AND status = 'completed'
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['monthly_sales'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Sales by payment method
    $stmt = $conn->prepare("
        SELECT payment_method, 
               COUNT(*) as count,
               SUM(total_amount) as total_amount,
               AVG(total_amount) as avg_amount
        FROM sales
        WHERE status = 'completed'
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['sales_by_payment_method'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Sales by status
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) as count, SUM(total_amount) as total_amount
        FROM sales
        GROUP BY status
        ORDER BY count DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['sales_by_status'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Top customers - using sales table
    $stmt = $conn->prepare("
        SELECT customer_name, 
               COUNT(*) as order_count, 
               SUM(total_amount) as total_spent,
               AVG(total_amount) as avg_order_value,
               MAX(created_at) as last_purchase
        FROM sales
        WHERE customer_name IS NOT NULL AND customer_name != '' AND customer_name != 'Walk-in Customer'
          AND status = 'completed'
        GROUP BY customer_name
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['top_customers'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Sales summary - using sales table
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_revenue,
            SUM(tax_amount) as total_tax,
            AVG(total_amount) as avg_order_value,
            COUNT(DISTINCT customer_name) as unique_customers,
            MIN(total_amount) as min_order_value,
            MAX(total_amount) as max_order_value
        FROM sales
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL $interval)
          AND status = 'completed'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['summary'] = $result->fetch_assoc();
    
    // Get previous period summary for comparison
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_revenue,
            SUM(tax_amount) as total_tax,
            AVG(total_amount) as avg_order_value,
            COUNT(DISTINCT customer_name) as unique_customers
        FROM sales
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL " . ($interval === '7 DAY' ? '14 DAY' : 
                                                         ($interval === '30 DAY' ? '60 DAY' : 
                                                         ($interval === '90 DAY' ? '180 DAY' : '24 MONTH'))) . ")
          AND created_at < DATE_SUB(NOW(), INTERVAL $interval)
          AND status = 'completed'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['previous_summary'] = $result->fetch_assoc();
    
    // Get top products by sales from sales_items table
    $stmt = $conn->prepare("
        SELECT 
            si.product_name,
            COUNT(*) as times_sold,
            SUM(si.quantity) as total_quantity,
            SUM(si.total_price) as total_revenue,
            AVG(si.unit_price) as avg_unit_price
        FROM sales_items si
        JOIN sales s ON si.sale_id = s.id
        WHERE s.status = 'completed'
          AND s.created_at >= DATE_SUB(NOW(), INTERVAL $interval)
        GROUP BY si.product_name
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['top_products_by_value'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Sales performance by cashier
    $stmt = $conn->prepare("
        SELECT 
            u.username as cashier_name,
            COUNT(s.id) as total_sales,
            SUM(s.total_amount) as total_revenue,
            AVG(s.total_amount) as avg_sale_amount
        FROM sales s
        LEFT JOIN users u ON s.cashier_id = u.id
        WHERE s.status = 'completed'
          AND s.created_at >= DATE_SUB(NOW(), INTERVAL $interval)
        GROUP BY s.cashier_id, u.username
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['cashier_performance'] = $result->fetch_all(MYSQLI_ASSOC);
    
    return $data;
}

function getInventoryAnalytics($conn) {
    $data = [];
    
    // Stock levels by category - using inventory table
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(category, 'Uncategorized') as category,
            COUNT(*) as product_count,
            SUM(stock) as total_stock,
            AVG(stock) as avg_stock
        FROM inventory
        WHERE status = 'active'
        GROUP BY category
        ORDER BY total_stock DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['stock_by_category'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Low stock items - using inventory table
    $stmt = $conn->prepare("
        SELECT 
            name as product_name, 
            COALESCE(category, 'Uncategorized') as category, 
            stock as stock_quantity,
            min_stock as reorder_level
        FROM inventory
        WHERE stock <= min_stock AND status = 'active'
        ORDER BY stock ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['low_stock_items'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Top products by value - using inventory table
    $stmt = $conn->prepare("
        SELECT 
            name as product_name, 
            COALESCE(category, 'Uncategorized') as category, 
            stock as stock_quantity, 
            price as unit_price,
            (stock * price) as total_value
        FROM inventory
        WHERE status = 'active'
        ORDER BY total_value DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['top_products_by_value'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Inventory summary - using inventory table
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_products,
            SUM(stock) as total_stock,
            SUM(stock * price) as total_inventory_value,
            COUNT(CASE WHEN stock > 0 AND stock <= min_stock THEN 1 END) as low_stock_count,
            COUNT(CASE WHEN stock = 0 THEN 1 END) as out_of_stock_count
        FROM inventory
        WHERE status = 'active'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['summary'] = $result->fetch_assoc();
    
    return $data;
}

function getUserAnalytics($conn) {
    $data = [];
    
    // User registrations over time
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as registrations
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['daily_registrations'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Users by role
    $stmt = $conn->prepare("
        SELECT role, COUNT(*) as count
        FROM users
        GROUP BY role
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['users_by_role'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Active users (logged in within last 30 days)
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as active_today,
            COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_week,
            COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as active_month
        FROM users
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['activity_summary'] = $result->fetch_assoc();
    
    return $data;
}

function getSystemPerformance($conn) {
    $data = [];
    
    // Database size
    $stmt = $conn->prepare("
        SELECT 
            table_name,
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
        ORDER BY size_mb DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['table_sizes'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // System activity (from audit logs)
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as activity_count
        FROM audit_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['daily_activity'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Resource usage simulation
    $data['resource_usage'] = [
        'cpu_usage' => rand(20, 80),
        'memory_usage' => rand(30, 70),
        'disk_usage' => rand(40, 85),
        'network_usage' => rand(10, 60)
    ];
    
    return $data;
}

function getErrorAnalytics($conn) {
    $data = [];
    
    // Error trends over time
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, 
               error_type,
               COUNT(*) as error_count
        FROM console_errors
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at), error_type
        ORDER BY date ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['error_trends'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Error types distribution
    $stmt = $conn->prepare("
        SELECT error_type, COUNT(*) as count
        FROM console_errors
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY error_type
        ORDER BY count DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['error_types'] = $result->fetch_all(MYSQLI_ASSOC);
    
    return $data;
}

// Format response functions to match frontend expectations
function formatSalesResponse($data) {
    $labels = [];
    $chartData = [];
    $orderCountData = [];
    $totalRevenue = 0;
    $totalOrders = 0;
    $totalTax = 0;
    $avgOrderValue = 0;
    
    // Get the time range to determine how many days to show
    $timeRange = $_GET['time_range'] ?? $_GET['range'] ?? '30d';
    $daysToShow = 30;
    
    switch($timeRange) {
        case '7d':
            $daysToShow = 7;
            break;
        case '90d':
            $daysToShow = 90;
            break;
        case '1y':
            $daysToShow = 365;
            break;
        default:
            $daysToShow = 30;
    }
    
    // Create a complete date range
    $dateMap = [];
    $orderCountMap = [];
    for ($i = $daysToShow - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dateMap[$date] = 0;
        $orderCountMap[$date] = 0;
    }
    
    // Fill in actual sales data
    if (isset($data['daily_sales']) && !empty($data['daily_sales'])) {
        foreach ($data['daily_sales'] as $sale) {
            $saleDate = date('Y-m-d', strtotime($sale['date']));
            if (isset($dateMap[$saleDate])) {
                $dateMap[$saleDate] = floatval($sale['total_sales'] ?? 0);
                $orderCountMap[$saleDate] = intval($sale['order_count'] ?? 0);
            }
        }
    }
    
    // Convert to arrays for chart
    foreach ($dateMap as $date => $amount) {
        $labels[] = date('M j', strtotime($date));
        $chartData[] = $amount;
        $orderCountData[] = $orderCountMap[$date];
    }
    
    // Get summary data
    if (isset($data['summary'])) {
        $totalRevenue = floatval($data['summary']['total_revenue'] ?? 0);
        $totalOrders = intval($data['summary']['total_orders'] ?? 0);
        $totalTax = floatval($data['summary']['total_tax'] ?? 0);
        $avgOrderValue = floatval($data['summary']['avg_order_value'] ?? 0);
    }
    
    // Calculate percentage changes (comparing to previous period)
    $revenueChange = 0;
    $ordersChange = 0;
    $avgOrderChange = 0;
    
    if (isset($data['previous_summary'])) {
        $prevRevenue = floatval($data['previous_summary']['total_revenue'] ?? 0);
        $prevOrders = intval($data['previous_summary']['total_orders'] ?? 0);
        $prevAvgOrder = floatval($data['previous_summary']['avg_order_value'] ?? 0);
        
        if ($prevRevenue > 0) {
            $revenueChange = (($totalRevenue - $prevRevenue) / $prevRevenue) * 100;
        }
        if ($prevOrders > 0) {
            $ordersChange = (($totalOrders - $prevOrders) / $prevOrders) * 100;
        }
        if ($prevAvgOrder > 0) {
            $avgOrderChange = (($avgOrderValue - $prevAvgOrder) / $prevAvgOrder) * 100;
        }
    }
    
    // Format payment method data for charts
    $paymentMethodLabels = [];
    $paymentMethodData = [];
    if (isset($data['sales_by_payment_method'])) {
        foreach ($data['sales_by_payment_method'] as $method) {
            $paymentMethodLabels[] = ucfirst($method['payment_method']);
            $paymentMethodData[] = floatval($method['total_amount']);
        }
    }
    
    return [
        'success' => true,
        'labels' => $labels,
        'data' => $chartData,
        'order_count_data' => $orderCountData,
        'datasets' => [
            [
                'label' => 'Sales Revenue (₱)',
                'data' => $chartData,
                'borderColor' => '#3b82f6',
                'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                'tension' => 0.4
            ],
            [
                'label' => 'Order Count',
                'data' => $orderCountData,
                'borderColor' => '#10b981',
                'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                'tension' => 0.4,
                'yAxisID' => 'y1'
            ]
        ],
        'total_revenue' => $totalRevenue,
        'total_orders' => $totalOrders,
        'total_tax' => $totalTax,
        'avg_order_value' => $avgOrderValue,
        'revenue_change' => round($revenueChange, 2),
        'orders_change' => round($ordersChange, 2),
        'avg_order_change' => round($avgOrderChange, 2),
        'top_products_by_value' => $data['top_products_by_value'] ?? [],
        'top_customers' => $data['top_customers'] ?? [],
        'cashier_performance' => $data['cashier_performance'] ?? [],
        'payment_method_labels' => $paymentMethodLabels,
        'payment_method_data' => $paymentMethodData,
        'sales_by_status' => $data['sales_by_status'] ?? [],
        'hourly_sales' => $data['hourly_sales'] ?? [],
        'monthly_sales' => $data['monthly_sales'] ?? []
    ];
}

function formatInventoryResponse($data) {
    $inStock = 0;
    $lowStock = 0;
    $outOfStock = 0;
    
    if (isset($data['summary'])) {
        $totalProducts = intval($data['summary']['total_products'] ?? 0);
        $lowStockCount = intval($data['summary']['low_stock_count'] ?? 0);
        $outOfStockCount = intval($data['summary']['out_of_stock_count'] ?? 0);
        
        $inStock = $totalProducts - $lowStockCount - $outOfStockCount;
        $lowStock = $lowStockCount;
        $outOfStock = $outOfStockCount;
    }
    
    return [
        'success' => true,
        'in_stock' => $inStock,
        'low_stock' => $lowStock,
        'out_of_stock' => $outOfStock
    ];
}

function formatUserResponse($data) {
    $labels = [];
    $chartData = [];
    $activeUsers = 0;
    $usersChange = 0;
    
    // Process daily registrations for chart
    if (isset($data['daily_registrations']) && !empty($data['daily_registrations'])) {
        foreach ($data['daily_registrations'] as $reg) {
            $labels[] = date('M j', strtotime($reg['date']));
            $chartData[] = intval($reg['registrations'] ?? 0);
        }
    }
    
    if (isset($data['activity_summary'])) {
        $activeUsers = intval($data['activity_summary']['active_month'] ?? 0);
    }
    
    // Calculate user growth percentage
    if (isset($data['users_by_role'])) {
        $totalUsers = 0;
        foreach ($data['users_by_role'] as $role) {
            $totalUsers += intval($role['count'] ?? 0);
        }
        
        // Simple growth calculation (could be enhanced with historical data)
        if ($totalUsers > 0 && count($chartData) > 0) {
            $recentGrowth = array_sum(array_slice($chartData, -7)); // Last 7 days
            $usersChange = ($recentGrowth / max(1, $totalUsers)) * 100;
        }
    }
    
    return [
        'success' => true,
        'labels' => $labels,
        'data' => $chartData,
        'active_users' => $activeUsers,
        'users_change' => $usersChange
    ];
}

function formatPerformanceResponse($data) {
    $labels = [];
    $chartData = [];
    
    // Process daily activity for chart
    if (isset($data['daily_activity']) && !empty($data['daily_activity'])) {
        foreach ($data['daily_activity'] as $activity) {
            $labels[] = date('M j', strtotime($activity['date']));
            // Simulate response time based on activity count
            $responseTime = max(50, min(500, intval($activity['activity_count']) * 2));
            $chartData[] = $responseTime;
        }
    }
    
    return [
        'success' => true,
        'labels' => $labels,
        'data' => $chartData
    ];
}

// Removed sample data insertion functions - using real database data
?>
