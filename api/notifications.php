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
            markAsRead($_GET['id']);
        } elseif (isset($_GET['mark_all_read'])) {
            markAllAsRead();
        }
        break;
    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteNotification($_GET['id']);
        }
        break;
}

function getAllNotifications() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC");
        $notifications = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $notifications]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function markAsRead($id) {
    global $pdo;
    try {
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

function deleteNotification($id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
