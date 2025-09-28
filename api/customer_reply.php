<?php
/**
 * Customer Reply API
 * Allows customers to reply to support tickets
 */

session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_name']) || $_SESSION['user_role'] !== 'user') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$userId = $_SESSION['user_user_id'] ?? $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? '';

if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID not found in session']);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit();
    }
    
    $conversationId = $input['conversation_id'] ?? '';
    $message = trim($input['message'] ?? '');
    
    if (empty($conversationId) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Conversation ID and message are required']);
        exit();
    }
    
    if (strlen($message) > 2000) {
        echo json_encode(['success' => false, 'message' => 'Message must be less than 2000 characters']);
        exit();
    }
    
    // Verify the conversation belongs to this user
    $verifyStmt = $conn->prepare("
        SELECT subject, user_email 
        FROM support_tickets_messages 
        WHERE conversation_id = ? AND (user_id = ? OR user_name = ?) 
        LIMIT 1
    ");
    $verifyStmt->bind_param("sis", $conversationId, $userId, $userName);
    $verifyStmt->execute();
    $conversation = $verifyStmt->get_result()->fetch_assoc();
    
    if (!$conversation) {
        echo json_encode(['success' => false, 'message' => 'Conversation not found or access denied']);
        exit();
    }
    
    // Insert customer reply
    $insertStmt = $conn->prepare("
        INSERT INTO support_tickets_messages 
        (conversation_id, user_id, user_name, user_email, admin_name, subject, message, is_admin, created_at, is_read) 
        VALUES (?, ?, ?, ?, NULL, ?, ?, FALSE, NOW(), TRUE)
    ");
    
    $userEmail = $_SESSION['user_email'] ?? $conversation['user_email'] ?? 'no-email@example.com';
    
    $insertStmt->bind_param("sissss", 
        $conversationId,
        $userId,
        $userName,
        $userEmail,
        $conversation['subject'],
        $message
    );
    
    if ($insertStmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Reply sent successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send reply']);
    }
    
} catch (Exception $e) {
    error_log("Error in customer_reply.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
