<?php
/**
 * Archive/Unarchive Conversation API
 * Allows admins to hide/show conversations in customer support
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/csrf.php';

header('Content-Type: application/json');

try {
    // Check if user has admin privileges (admin, super_admin, or developer)
    $allowedRoles = ['admin', 'super_admin', 'developer'];
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    // Validate CSRF token
    $input = json_decode(file_get_contents('php://input'), true);
    if (!validateCSRFToken($input['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $conversationId = $input['conversation_id'] ?? '';
    $action = $input['action'] ?? ''; // 'archive' or 'unarchive'

    if (empty($conversationId) || empty($action)) {
        echo json_encode(['success' => false, 'message' => 'Conversation ID and action are required']);
        exit;
    }

    if (!in_array($action, ['archive', 'unarchive'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid action. Use "archive" or "unarchive"']);
        exit;
    }

    // First, check if conversation exists in either table
    // Check support_messages table (admin dashboard)
    $checkStmt1 = $conn->prepare("
        SELECT COUNT(*) as count, MAX(archived) as current_archived 
        FROM support_messages 
        WHERE conversation_id = ? AND message_type = 'customer_support'
    ");
    $checkStmt1->bind_param("s", $conversationId);
    $checkStmt1->execute();
    $result1 = $checkStmt1->get_result()->fetch_assoc();
    $checkStmt1->close();
    
    // Check support_tickets_messages table (developer portal)
    $checkStmt2 = $conn->prepare("
        SELECT COUNT(*) as count, MAX(archived) as current_archived 
        FROM support_tickets_messages 
        WHERE conversation_id = ?
    ");
    $checkStmt2->bind_param("s", $conversationId);
    $checkStmt2->execute();
    $result2 = $checkStmt2->get_result()->fetch_assoc();
    $checkStmt2->close();
    
    $totalCount = $result1['count'] + $result2['count'];
    $currentArchived = max($result1['current_archived'] ?? 0, $result2['current_archived'] ?? 0);
    
    if ($totalCount == 0) {
        echo json_encode(['success' => false, 'message' => 'Conversation not found in either system']);
        exit;
    }
    
    // Set archived status based on action
    $archived = ($action === 'archive') ? 1 : 0;
    
    // Check if already in desired state
    if ($currentArchived === $archived) {
        $actionText = ($action === 'archive') ? 'archived' : 'unarchived';
        echo json_encode([
            'success' => true,
            'message' => 'Conversation is already ' . $actionText,
            'action' => $action,
            'conversation_id' => $conversationId
        ]);
        exit;
    }
    
    // Update all messages in the conversation (both tables)
    // First update support_messages table
    $stmt1 = $conn->prepare("
        UPDATE support_messages 
        SET archived = ? 
        WHERE conversation_id = ? AND message_type = 'customer_support'
    ");
    
    // Also update support_tickets_messages table (for developer portal)
    $stmt2 = $conn->prepare("
        UPDATE support_tickets_messages 
        SET archived = ? 
        WHERE conversation_id = ?
    ");
    
    // Execute both statements
    $stmt1->bind_param("is", $archived, $conversationId);
    $stmt2->bind_param("is", $archived, $conversationId);
    
    $success1 = $stmt1->execute();
    $affectedRows1 = $stmt1->affected_rows;
    $stmt1->close();
    
    $success2 = $stmt2->execute();
    $affectedRows2 = $stmt2->affected_rows;
    $stmt2->close();
    
    $totalAffectedRows = $affectedRows1 + $affectedRows2;
    
    if ($success1 || $success2) {
        // Log the action
        $adminName = $_SESSION['username'] ?? 'Unknown Admin';
        $actionText = ($action === 'archive') ? 'archived' : 'unarchived';
        error_log("Admin $adminName $actionText conversation: $conversationId (affected rows: $totalAffectedRows)");
        
        echo json_encode([
            'success' => true,
            'message' => ucfirst($actionText) . ' conversation successfully',
            'action' => $action,
            'conversation_id' => $conversationId,
            'affected_rows' => $totalAffectedRows
        ]);
    } else {
        error_log("Archive conversation SQL error: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

} catch (Exception $e) {
    error_log("Archive conversation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
