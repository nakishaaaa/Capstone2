<?php
/**
 * Customer Support Messaging API
 * Handles real-time messaging between users and admins
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../includes/csrf.php';

session_start();

try {
    // Database connection is already established in database.php as $pdo
    
    // Check if table exists before creating (more efficient)
    $stmt = $pdo->query("SHOW TABLES LIKE 'support_messages'");
    if ($stmt->rowCount() == 0) {
        // Create messages table only if it doesn't exist
        $createTableSQL = "
            CREATE TABLE support_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                conversation_id VARCHAR(50) NOT NULL,
                user_id INT NULL,
                user_name VARCHAR(255) NOT NULL,
                user_email VARCHAR(255) NOT NULL,
                admin_name VARCHAR(255) NULL,
                subject VARCHAR(255) NULL,
                message TEXT NOT NULL,
                message_type ENUM('customer_support', 'dev_support') DEFAULT 'customer_support',
                is_admin BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_read BOOLEAN DEFAULT FALSE,
                INDEX idx_conversation_id (conversation_id),
                INDEX idx_created_at (created_at),
                INDEX idx_is_read (is_read),
                INDEX idx_message_type (message_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $pdo->exec($createTableSQL);
    } else {
        // Check if subject column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM support_messages LIKE 'subject'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE support_messages ADD COLUMN subject VARCHAR(255) NULL AFTER admin_name");
        }
        
        // Check if message_type column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM support_messages LIKE 'message_type'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE support_messages ADD COLUMN message_type ENUM('customer_support', 'dev_support') DEFAULT 'customer_support' AFTER message");
        }
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetMessages($pdo);
            break;
            
        case 'POST':
            handleSendMessage($pdo);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
    
} catch (PDOException $e) {
    error_log("Database error in customer_support.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    
} catch (Exception $e) {
    error_log("Error in customer_support.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGetMessages($pdo) {
    $conversationId = $_GET['conversation_id'] ?? '';
    $lastMessageId = intval($_GET['last_message_id'] ?? 0);
    
    if (empty($conversationId)) {
        throw new Exception('Conversation ID is required');
    }
    
    // Get messages after the last message ID for real-time updates
    $query = "
        SELECT id, user_name, admin_name, message, is_admin, created_at, is_read
        FROM support_messages 
        WHERE conversation_id = ? AND id > ? AND message_type = 'customer_support'
        ORDER BY created_at ASC
        LIMIT 50
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$conversationId, $lastMessageId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format messages
    foreach ($messages as &$message) {
        $message['created_at_formatted'] = date('M j, Y g:i A', strtotime($message['created_at']));
        $message['time_ago'] = timeAgo($message['created_at']);
        $message['sender_name'] = $message['is_admin'] ? $message['admin_name'] : $message['user_name'];
    }
    
    // Mark messages as read by user (non-admin messages)
    if (!empty($messages)) {
        $stmt = $pdo->prepare("
            UPDATE support_messages 
            SET is_read = TRUE 
            WHERE conversation_id = ? AND is_admin = TRUE AND is_read = FALSE AND message_type = 'customer_support'
        ");
        $stmt->execute([$conversationId]);
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'messages' => $messages,
            'conversation_id' => $conversationId
        ]
    ]);
}

function handleSendMessage($pdo) {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate CSRF token
    if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
        throw new Exception('Invalid CSRF token');
    }
    
    $message = trim($input['message'] ?? '');
    $subject = trim($input['subject'] ?? '');
    $conversationId = $input['conversation_id'] ?? '';
    
    // Get user information from session
    $user_id = $_SESSION['user_user_id'] ?? $_SESSION['user_id'] ?? null;
    $user_email = $_SESSION['user_email'] ?? $_SESSION['email'] ?? 'anonymous@example.com';
    $user_name = $_SESSION['user_name'] ?? $_SESSION['name'] ?? 'Anonymous';
    
    if (empty($message)) {
        throw new Exception('Message is required');
    }
    
    if (strlen($message) > 1000) {
        throw new Exception('Message must be less than 1000 characters');
    }
    
    if (strlen($subject) > 255) {
        throw new Exception('Subject must be less than 255 characters');
    }
    
    // Generate a NEW conversation ID if not provided (top-level message creates a new conversation)
    if (empty($conversationId)) {
        $conversationId = 'CHAT-' . date('Y') . '-' . ($user_id ?? 'guest') . '-' . time();
    }
    
    // If this is a reply to existing conversation and no subject provided, get the original subject
    if (!empty($conversationId) && empty($subject)) {
        $stmt = $pdo->prepare("SELECT subject FROM support_messages WHERE conversation_id = ? AND subject IS NOT NULL AND TRIM(subject) != '' ORDER BY created_at ASC LIMIT 1");
        $stmt->execute([$conversationId]);
        $originalSubject = $stmt->fetchColumn();
        if ($originalSubject) {
            $subject = $originalSubject;
        }
    }
    
    // Insert message with customer_support type
    $stmt = $pdo->prepare("
        INSERT INTO support_messages 
        (conversation_id, user_id, user_name, user_email, subject, message, message_type, is_admin, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'customer_support', FALSE, NOW())
    ");
    
    $stmt->execute([
        $conversationId,
        $user_id,
        $user_name,
        $user_email,
        $subject,
        $message
    ]);
    
    $messageId = $pdo->lastInsertId();
    
    // Log the message for admin notification
    error_log("New support message from $user_email in conversation $conversationId: $message");
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully!',
        'data' => [
            'message_id' => $messageId,
            'conversation_id' => $conversationId,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}
?>
