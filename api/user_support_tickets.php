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

// Utility function for time formatting
function timeAgo($datetime) {
    if (!$datetime) return 'Never';
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';
        
        if ($action === 'list') {
            // Get all conversations for this user from support_tickets_messages
            $stmt = $conn->prepare("
                SELECT 
                    sm.conversation_id as id,
                    sm.subject,
                    MIN(sm.created_at) as created_at,
                    MAX(sm.created_at) as updated_at,
                    'open' as status,
                    'medium' as priority,
                    COUNT(*) as message_count,
                    SUM(CASE WHEN sm.is_admin = 1 THEN 1 ELSE 0 END) as reply_count,
                    (SELECT s2.message FROM support_tickets_messages s2 
                     WHERE s2.conversation_id = sm.conversation_id AND s2.is_admin = 0
                     ORDER BY s2.created_at ASC LIMIT 1) as message,
                    (SELECT s3.attachment_paths FROM support_tickets_messages s3 
                     WHERE s3.conversation_id = sm.conversation_id AND s3.attachment_paths IS NOT NULL
                     ORDER BY s3.created_at ASC LIMIT 1) as attachment_path
                FROM support_tickets_messages sm
                WHERE sm.user_id = ? OR sm.user_name = ?
                GROUP BY sm.conversation_id, sm.subject
                ORDER BY MAX(sm.created_at) DESC
            ");
            $stmt->bind_param("is", $userId, $userName);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $tickets = [];
            while ($row = $result->fetch_assoc()) {
                $tickets[] = [
                    'id' => $row['id'],
                    'subject' => $row['subject'],
                    'message' => $row['message'],
                    'priority' => $row['priority'],
                    'status' => $row['status'],
                    'attachment_path' => $row['attachment_path'],
                    'original_filename' => null, // Will be handled in conversation view
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                    'reply_count' => $row['reply_count'],
                    'message_count' => $row['message_count']
                ];
            }
            
            echo json_encode(['success' => true, 'tickets' => $tickets]);
            
        } elseif ($action === 'details') {
            $conversationId = $_GET['ticket_id'] ?? '';
            
            if (!$conversationId) {
                echo json_encode(['success' => false, 'message' => 'Conversation ID is required']);
                exit();
            }
            
            // Get conversation messages from support_tickets_messages
            $stmt = $conn->prepare("
                SELECT id, conversation_id, user_name, user_email, admin_name, 
                       subject, message, attachment_paths, is_admin, created_at, is_read
                FROM support_tickets_messages 
                WHERE conversation_id = ? AND (user_id = ? OR user_name = ?)
                ORDER BY created_at ASC
            ");
            $stmt->bind_param("sis", $conversationId, $userId, $userName);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $messages = [];
            $ticket = null;
            
            while ($row = $result->fetch_assoc()) {
                // Set ticket info from first message
                if (!$ticket) {
                    $ticket = [
                        'id' => $row['conversation_id'],
                        'subject' => $row['subject'],
                        'status' => 'open',
                        'priority' => 'medium',
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['created_at'] // Will be updated with last message
                    ];
                }
                
                // Update ticket's updated_at with latest message time
                $ticket['updated_at'] = $row['created_at'];
                
                $messages[] = [
                    'id' => $row['id'],
                    'sender_type' => $row['is_admin'] ? 'admin' : 'customer',
                    'sender_name' => $row['is_admin'] ? ($row['admin_name'] ?: 'Support Team') : $row['user_name'],
                    'message' => $row['message'],
                    'attachment_path' => $row['attachment_paths'],
                    'created_at' => $row['created_at'],
                    'is_read' => $row['is_read'],
                    'formatted_date' => date('M j, Y g:i A', strtotime($row['created_at'])),
                    'time_ago' => timeAgo($row['created_at'])
                ];
            }
            
            if (!$ticket) {
                echo json_encode(['success' => false, 'message' => 'Conversation not found']);
                exit();
            }
            
            echo json_encode([
                'success' => true, 
                'ticket' => $ticket,
                'messages' => $messages,
                'has_conversation' => count($messages) > 0
            ]);
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
