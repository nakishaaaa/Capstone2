<?php
/**
 * Anonymous Reply API
 * Handles replies from anonymous users to existing support conversations
 */

// Disable HTML error display to prevent breaking JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start output buffering to catch any unexpected output
ob_start();

session_start();
require_once '../includes/config.php';

// Clean any output buffer and set JSON header
ob_clean();
header('Content-Type: application/json');

try {
    // Test database connection
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'anonymous_reply') {
        
        // Get form data
        $ticket_id = trim($_POST['ticket_id'] ?? '');
        $conversation_id = trim($_POST['conversation_id'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        // Validate required fields
        if (empty($ticket_id)) {
            echo json_encode(['success' => false, 'message' => 'Ticket ID is required']);
            exit;
        }
        
        if (empty($conversation_id)) {
            echo json_encode(['success' => false, 'message' => 'Conversation ID is required']);
            exit;
        }
        
        if (empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Message is required']);
            exit;
        }
        
        // Validate message length
        if (strlen($message) < 5) {
            echo json_encode(['success' => false, 'message' => 'Message must be at least 5 characters long']);
            exit;
        }
        
        if (strlen($message) > 5000) {
            echo json_encode(['success' => false, 'message' => 'Message is too long (maximum 5000 characters)']);
            exit;
        }
        
        try {
            // Verify that the conversation exists and get user details (from any message in conversation)
            $stmt = $conn->prepare("
                SELECT user_name, user_email, subject 
                FROM support_messages 
                WHERE conversation_id = ? AND subject LIKE ? AND message_type = 'customer_support'
                ORDER BY created_at ASC 
                LIMIT 1
            ");
            
            $searchPattern = "%[" . $ticket_id . "]%";
            $stmt->bind_param("ss", $conversation_id, $searchPattern);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid ticket ID or conversation not found']);
                exit;
            }
            
            $originalMessage = $result->fetch_assoc();
            $stmt->close();
            
            // Insert the reply into the same conversation
            $stmt = $conn->prepare("
                INSERT INTO support_messages 
                (conversation_id, user_name, user_email, subject, message, message_type, is_admin, is_read, created_at) 
                VALUES (?, ?, ?, ?, ?, 'customer_support', 0, 0, NOW())
            ");
            
            if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Database prepare failed: ' . $conn->error]);
                exit;
            }
            
            // Use original user details and subject for consistency
            $replySubject = $originalMessage['subject']; // Keep same subject format
            
            // For anonymous users, use consistent details
            $userName = $originalMessage['user_name'] ?: 'Anonymous';
            $userEmail = $originalMessage['user_email'] ?: 'anonymous@guest.local';
            
            if (!$stmt->bind_param("sssss", 
                $conversation_id, 
                $userName, 
                $userEmail, 
                $replySubject, 
                $message
            )) {
                echo json_encode(['success' => false, 'message' => 'Database bind failed: ' . $stmt->error]);
                exit;
            }
            
            if ($stmt->execute()) {
                $stmt->close();
                
                // Send real-time notification via Pusher to admins
                require_once '../includes/pusher_config.php';
                
                triggerPusherEvent('support-channel', 'new-customer-support-message', [
                    'conversation_id' => $conversation_id,
                    'user_name' => $userName,
                    'user_email' => $userEmail,
                    'subject' => $replySubject,
                    'message' => substr($message, 0, 100),
                    'timestamp' => time()
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Your reply has been sent successfully! Our team will respond soon.'
                ]);
                
            } else {
                $stmt->close();
                echo json_encode(['success' => false, 'message' => 'Failed to send reply: ' . $conn->error]);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
    }

} catch (Exception $e) {
    // Catch any unexpected errors and return as JSON
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
} catch (Error $e) {
    // Catch fatal errors and return as JSON
    echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $e->getMessage()]);
}
?>
