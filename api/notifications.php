<?php
header('Content-Type: application/json');
include '../config.php';

try {
    $query = "SELECT 
                p.id,
                p.name,
                c.name as category,
                p.stock_quantity as stock,
                p.minimum_stock as minStock,
                p.price,
                CASE 
                    WHEN p.stock_quantity = 0 THEN 'Out of Stock'
                    WHEN p.stock_quantity <= p.minimum_stock THEN 'Low Stock'
                    ELSE 'In Stock'
                END as status
              FROM products p
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE p.status = 'active'
              ORDER BY p.name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert price to float
    foreach ($inventory as &$item) {
        $item['price'] = floatval($item['price']);
        $item['stock'] = intval($item['stock']);
        $item['minStock'] = intval($item['minStock']);
    }
    
    echo json_encode($inventory);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching inventory: ' . $e->getMessage()
    ]);
}
?>