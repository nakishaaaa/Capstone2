<?php
header('Content-Type: application/json');
include '../config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['items']) || empty($input['items'])) {
        throw new Exception('Invalid transaction data');
    }
    
    $pdo->beginTransaction();
    
    // Generate transaction ID
    $transactionId = 'TXN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Insert transaction
    $transactionQuery = "INSERT INTO transactions (transaction_id, total_amount, payment_method, amount_received, change_amount, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, 'completed', NOW())";
    $stmt = $pdo->prepare($transactionQuery);
    $stmt->execute([
        $transactionId,
        $input['total'],
        $input['paymentMethod'],
        $input['amountReceived'],
        $input['change']
    ]);
    
    $transactionDbId = $pdo->lastInsertId();
    
    // Insert transaction items and update stock
    foreach ($input['items'] as $item) {
        // Insert transaction item
        $itemQuery = "INSERT INTO transaction_items (transaction_id, product_id, quantity, unit_price, total_price) 
                     VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($itemQuery);
        $stmt->execute([
            $transactionDbId,
            $item['id'],
            $item['quantity'],
            $item['price'],
            $item['price'] * $item['quantity']
        ]);
        
        // Update product stock
        $updateStockQuery = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?";
        $stmt = $pdo->prepare($updateStockQuery);
        $stmt->execute([$item['quantity'], $item['id']]);
        
        // Check if stock is now low and create notification
        $checkStockQuery = "SELECT name, stock_quantity, minimum_stock FROM products WHERE id = ?";
        $stmt = $pdo->prepare($checkStockQuery);
        $stmt->execute([$item['id']]);
        $product = $stmt->fetch();
        
        if ($product && $product['stock_quantity'] <= $product['minimum_stock']) {
            $notificationQuery = "INSERT INTO notifications (title, message, type, created_at) 
                                 VALUES (?, ?, 'warning', NOW())";
            $stmt = $pdo->prepare($notificationQuery);
            $stmt->execute([
                'Low Stock Alert',
                $product['name'] . ' stock is running low (' . $product['stock_quantity'] . ' remaining)'
            ]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'transactionId' => $transactionId,
        'message' => 'Transaction processed successfully'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Transaction failed: ' . $e->getMessage()
    ]);
}
?>