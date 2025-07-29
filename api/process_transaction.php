<?php
header('Content-Type: application/json');
include '../config.php';

try {
    // Get today's sales
    $today = date('Y-m-d');
    
    // Total sales today
    $salesQuery = "SELECT COALESCE(SUM(total_amount), 0) as total_sales 
                   FROM transactions 
                   WHERE DATE(created_at) = ?";
    $stmt = $pdo->prepare($salesQuery);
    $stmt->execute([$today]);
    $totalSales = $stmt->fetchColumn();
    
    // Total orders today
    $ordersQuery = "SELECT COUNT(*) as total_orders 
                    FROM transactions 
                    WHERE DATE(created_at) = ?";
    $stmt = $pdo->prepare($ordersQuery);
    $stmt->execute([$today]);
    $totalOrders = $stmt->fetchColumn();
    
    // Total products
    $productsQuery = "SELECT COUNT(*) as total_products FROM products WHERE status = 'active'";
    $stmt = $pdo->prepare($productsQuery);
    $stmt->execute();
    $totalProducts = $stmt->fetchColumn();
    
    // Low stock items
    $lowStockQuery = "SELECT COUNT(*) as low_stock 
                      FROM products 
                      WHERE stock_quantity <= minimum_stock AND status = 'active'";
    $stmt = $pdo->prepare($lowStockQuery);
    $stmt->execute();
    $lowStock = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'totalSales' => floatval($totalSales),
        'totalOrders' => intval($totalOrders),
        'totalProducts' => intval($totalProducts),
        'lowStock' => intval($lowStock)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching dashboard stats: ' . $e->getMessage()
    ]);
}
?>