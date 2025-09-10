<?php
/**
 * User Support Tickets API
 * Allows customers to view their support tickets and admin replies
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
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';
        
        if ($action === 'list') {
            // Get all tickets for this user
            $stmt = $conn->prepare("
                SELECT st.id, st.subject, st.message, st.priority, st.status, 
                       st.attachment_path, st.created_at, st.updated_at,
                       CASE WHEN st.admin_response IS NOT NULL THEN 1 ELSE 0 END as reply_count
                FROM support_tickets st
                WHERE st.user_id = ? OR st.username = ?
                ORDER BY st.created_at DESC
            ");
            $stmt->bind_param("is", $userId, $userName);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $tickets = [];
            while ($row = $result->fetch_assoc()) {
                $tickets[] = $row;
            }
            
            echo json_encode(['success' => true, 'tickets' => $tickets]);
            
        } elseif ($action === 'details') {
            $ticketId = $_GET['ticket_id'] ?? 0;
            
            if (!$ticketId) {
                echo json_encode(['success' => false, 'message' => 'Ticket ID is required']);
                exit();
            }
            
            // Get ticket details with admin response
            $stmt = $conn->prepare("
                SELECT st.id, st.subject, st.message, st.priority, st.status, 
                       st.attachment_path, st.created_at, st.updated_at,
                       st.admin_response, st.admin_username
                FROM support_tickets st
                WHERE st.id = ? AND (st.user_id = ? OR st.username = ?)
            ");
            $stmt->bind_param("iis", $ticketId, $userId, $userName);
            $stmt->execute();
            $result = $stmt->get_result();
            $ticket = $result->fetch_assoc();
            
            if (!$ticket) {
                echo json_encode(['success' => false, 'message' => 'Ticket not found']);
                exit();
            }
            
            echo json_encode(['success' => true, 'ticket' => $ticket]);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    error_log("Error in user_support_tickets.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
