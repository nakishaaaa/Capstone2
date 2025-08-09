<?php
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
            } elseif (isset($_GET['report'])) {
                getSalesReport($_GET['start_date'] ?? null, $_GET['end_date'] ?? null);
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
        $stmt = $pdo->query("SELECT COUNT(*) as pending_requests FROM user_requests WHERE status = 'pending'");
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

function processSale($data) {
    global $pdo;
    try {
        // Validate required data
        if (!isset($data['transaction_id']) || !isset($data['items']) || empty($data['items'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid sale data']);
            return;
        }
        
        $pdo->beginTransaction();
        
        // Insert sale
        $stmt = $pdo->prepare("INSERT INTO sales (transaction_id, customer_name, total_amount, tax_amount, payment_method, amount_received, change_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['transaction_id'],
            $data['customer_name'] ?? 'Walk-in Customer',
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
        
        // Check for low stock and create notifications
        $stmt = $pdo->query("SELECT id, name, stock, min_stock FROM inventory WHERE stock <= min_stock AND status = 'active'");
        $lowStockItems = $stmt->fetchAll();
        
        foreach ($lowStockItems as $item) {
            // Check if notification already exists
            $stmt = $pdo->prepare("SELECT id FROM notifications WHERE title = ? AND is_read = FALSE");
            $notificationTitle = "Low Stock Alert: " . $item['name'];
            $stmt->execute([$notificationTitle]);
            
            if (!$stmt->fetch()) {
                // Create new notification
                $message = $item['stock'] == 0 ? 
                    "{$item['name']} is out of stock" : 
                    "{$item['name']} stock is low ({$item['stock']} remaining)";
                
                $stmt = $pdo->prepare("INSERT INTO notifications (title, message, type) VALUES (?, ?, ?)");
                $stmt->execute([
                    $notificationTitle,
                    $message,
                    $item['stock'] == 0 ? 'error' : 'warning'
                ]);
            }
        }
        
        $pdo->commit();
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
            SUM(si.quantity) as total_quantity
            FROM sales s
            LEFT JOIN sales_items si ON s.id = si.sale_id
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
            SUM(si.quantity) as total_quantity
            FROM sales s
            LEFT JOIN sales_items si ON s.id = si.sale_id
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
?>
