<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../config/database.php';

header('Content-Type: application/json');

try {
    // Get request history from the request_history table
    $sql = "SELECT * FROM request_history ORDER BY cleared_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats_sql = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM request_history";
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'history' => $history,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error loading request history: ' . $e->getMessage()
    ]);
}
?>
