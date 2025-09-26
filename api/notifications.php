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
        getAllNotifications();
        break;
    case 'PUT':
        if (isset($_GET['id'])) {
            $source = $_GET['source'] ?? 'notification';
            markAsRead($_GET['id'], $source);
        } elseif (isset($_GET['mark_all_read'])) {
            markAllAsRead();
        }
        break;
    case 'DELETE':
        if (isset($_GET['id'])) {
            $source = $_GET['source'] ?? 'notification';
            deleteNotification($_GET['id'], $source);
        }
        break;
}

function getAllNotifications() {
    global $pdo;
    try {
        // Create request notification tracking table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS request_notification_tracking (
                id INT AUTO_INCREMENT PRIMARY KEY,
                request_id INT NOT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                is_dismissed BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_request (request_id)
            )
        ");
        
        // Get all notifications (now includes request notifications stored in the table)
        $stmt = $pdo->query("SELECT id, title, message, type, is_read, created_at, 'notification' as source FROM notifications ORDER BY created_at DESC");
        $notifications = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $notifications]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function markAsRead($id, $source = 'notification') {
    global $pdo;
    try {
        // All notifications are now in the notifications table
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function markAllAsRead() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE is_read = FALSE");
        $stmt->execute();
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteNotification($id, $source = 'notification') {
    global $pdo;
    try {
        // All notifications are now in the notifications table
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
