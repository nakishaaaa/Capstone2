<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

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
}

function getSalesStats() {
    global $pdo;
    try {
        // Today's stats
        $today = date('Y-m-d');
        
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
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_sales' => floatval($todayStats['total_sales']),
                'total_orders' => intval($todayStats['total_orders']),
                'total_products' => intval($productStats['total_products']),
                'low_stock' => intval($stockStats['low_stock'])
            ]
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function processSale($data) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Insert sale
        $stmt = $pdo->prepare("INSERT INTO sales (transaction_id, customer_name, total_amount, tax_amount, payment_method, amount_received, change_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['transaction_id'],
            $data['customer_name'] ?? 'Walk-in Customer',
            $data['total_amount'],
            $data['tax_amount'],
            $data['payment_method'],
            $data['amount_received'],
            $data['change_amount']
        ]);
        
        $saleId = $pdo->lastInsertId();
        
        // Insert sale items and update inventory
        foreach ($data['items'] as $item) {
            // Insert sale item
            $stmt = $pdo->prepare("INSERT INTO sales_items (sale_id, product_id, product_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $saleId,
                $item['id'],
                $item['name'],
                $item['quantity'],
                $item['price'],
                $item['price'] * $item['quantity']
            ]);
            
            // Update inventory stock
            $stmt = $pdo->prepare("UPDATE inventory SET stock = stock - ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['id']]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'sale_id' => $saleId]);
    } catch(PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getAllSales() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT s.*, 
            GROUP_CONCAT(CONCAT(si.product_name, ' x', si.quantity) SEPARATOR ', ') as products
            FROM sales s
            LEFT JOIN sales_items si ON s.id = si.sale_id
            GROUP BY s.id
            ORDER BY s.created_at DESC
            LIMIT 100");
        $sales = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $sales]);
    } catch(PDOException $e) {
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
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
