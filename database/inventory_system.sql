<?php
header('Content-Type: application/json');
include '../config.php';

try {
    $query = "SELECT id, title, message, type, is_read as read, created_at as timestamp
              FROM notifications 
              ORDER BY created_at DESC 
              LIMIT 50";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert read status to boolean
    foreach ($notifications as &$notification) {
        $notification['read'] = (bool)$notification['read'];
    }
    
    echo json_encode($notifications);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching notifications: ' . $e->getMessage()
    ]);
}
?>