<?php
/**
 * Anonymous Support API
 * Handles support messages from non-logged-in users on index.php
 * Messages appear in admin's Customer Support section
 */

// Disable HTML error display to prevent breaking JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start output buffering to catch any unexpected output
ob_start();

session_start();
require_once '../includes/config.php';
require_once '../includes/csrf.php';

// Clean any output buffer and set JSON header
ob_clean();
header('Content-Type: application/json');

try {
    // Test database connection
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }

    // Skip CSRF verification for anonymous users (they don't have sessions)
    // TODO: Implement alternative security measures like rate limiting

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'anonymous_support') {
    
    // Get form data
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? 'Anonymous Support Request');
    $message = trim($_POST['message'] ?? '');
    
    // Validate message (required field)
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Message is required']);
        exit;
    }
    
    // Validate message length
    if (strlen($message) < 10) {
        echo json_encode(['success' => false, 'message' => 'Message must be at least 10 characters long']);
        exit;
    }
    
    if (strlen($message) > 5000) {
        echo json_encode(['success' => false, 'message' => 'Message is too long (maximum 5000 characters)']);
        exit;
    }
    
    // Validate email if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }
    
    // Anonymous user details
    $userName = 'Anonymous';
    $userEmail = !empty($email) ? $email : 'anonymous@guest.local';
    
    // Generate user-friendly ticket ID from 000000 to 999999
    $ticketId = 'ANON-' . date('Y') . '-' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Create a unique conversation ID for anonymous messages
    $conversationId = 'anon_' . time() . '_' . bin2hex(random_bytes(4));
    
    // Message metadata
    $messageType = 'customer_support';
    $conversationStatus = 'open';
    $isAdmin = 0;
    $isRead = 0;
    
    try {
        // Test if table exists first
        $testQuery = "SHOW TABLES LIKE 'support_messages'";
        $testResult = $conn->query($testQuery);
        if (!$testResult || $testResult->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'Support messages table not found']);
            exit;
        }
        
        // Insert into support_messages table with ticket ID
        $stmt = $conn->prepare("
            INSERT INTO support_messages 
            (conversation_id, user_name, user_email, subject, message, message_type, is_admin) 
            VALUES (?, ?, ?, ?, ?, 'customer_support', 0)
        ");
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
            exit;
        }
        
        // Add ticket ID to subject for admin reference
        $subjectWithTicket = "[$ticketId] $subject";
        
        if (!$stmt->bind_param("sssss", $conversationId, $userName, $userEmail, $subjectWithTicket, $message)) {
            echo json_encode(['success' => false, 'message' => 'Bind failed: ' . $stmt->error]);
            exit;
        }
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // Send real-time notification via Pusher to admins
            require_once '../includes/pusher_config.php';
            
            triggerPusherEvent('support-channel', 'new-anonymous-ticket', [
                'conversation_id' => $conversationId,
                'ticket_id' => $ticketId,
                'user_name' => $userName,
                'user_email' => $userEmail,
                'subject' => $subjectWithTicket,
                'message' => substr($message, 0, 100),
                'timestamp' => time()
            ]);
            
            $responseMessage = "Thank you for contacting us! Your ticket ID is: $ticketId (SAVE THIS TICKET ID)";
            if (!empty($email)) {
                $responseMessage .= "\n\nWe'll send reply notifications to your email.";
            } else {
                $responseMessage .= "\n\nSave this ticket ID to check for replies later.";
            }
            
            echo json_encode([
                'success' => true,
                'message' => $responseMessage,
                'ticket_id' => $ticketId,
                'has_email' => !empty($email)
            ]);
        } else {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Execute failed: ' . $conn->error]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
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
