<?php
/**
 * Admin Support Messages API
 * Handles admin operations for customer support messages
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display to prevent JSON corruption
ini_set('log_errors', 1);

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

// Check admin authentication
session_start();

// Check admin authentication - allow admin, cashier, and super_admin roles
$allowedRoles = ['admin', 'cashier', 'super_admin'];
$userRole = $_SESSION['role'] ?? null;

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['email']) || !in_array($userRole, $allowedRoles)) {
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'message' => 'Access denied. Admin, super admin, or cashier privileges required.'
    ]);
    exit();
}

// Set admin-specific session variables for compatibility with existing code
$_SESSION['admin_name'] = $_SESSION['username'];
$_SESSION['admin_email'] = $_SESSION['email'];
$_SESSION['admin_role'] = $_SESSION['role'];

try {
    // Database connection is already established in database.php as $pdo
    
    // Ensure support_messages table exists with message_type column
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS support_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id VARCHAR(50) NOT NULL,
            user_id INT NULL,
            user_name VARCHAR(255) NOT NULL,
            user_email VARCHAR(255) NOT NULL,
            admin_name VARCHAR(255) NULL,
            subject VARCHAR(255) NULL,
            message TEXT NOT NULL,
            attachment_paths TEXT NULL,
            message_type ENUM('customer_support', 'dev_support') DEFAULT 'customer_support',
            conversation_status ENUM('open', 'solved', 'closed') DEFAULT 'open',
            is_admin BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_read BOOLEAN DEFAULT FALSE,
            INDEX idx_conversation_id (conversation_id),
            INDEX idx_created_at (created_at),
            INDEX idx_is_read (is_read),
            INDEX idx_message_type (message_type),
            INDEX idx_conversation_status (conversation_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($createTableSQL);
    
    // Check if attachment_paths column exists (updated for multiple attachments)
    $stmt = $pdo->query("SHOW COLUMNS FROM support_messages LIKE 'attachment_paths'");
    if ($stmt->rowCount() == 0) {
        // Check if old attachment_path column exists and migrate data
        $oldStmt = $pdo->query("SHOW COLUMNS FROM support_messages LIKE 'attachment_path'");
        if ($oldStmt->rowCount() > 0) {
            // Rename old column to new column
            $pdo->exec("ALTER TABLE support_messages CHANGE attachment_path attachment_paths TEXT NULL");
        } else {
            // Add new column
            $pdo->exec("ALTER TABLE support_messages ADD COLUMN attachment_paths TEXT NULL AFTER message");
        }
    }
    
    // Check if message_type column exists in existing table
    $stmt = $pdo->query("SHOW COLUMNS FROM support_messages LIKE 'message_type'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE support_messages ADD COLUMN message_type ENUM('customer_support', 'dev_support') DEFAULT 'customer_support' AFTER attachment_path");
        
        // Update existing messages to have customer_support type
        $pdo->exec("UPDATE support_messages SET message_type = 'customer_support' WHERE message_type IS NULL");
        error_log("Updated existing support messages with customer_support type");
    } else {
        // Ensure existing NULL values are set to customer_support
        $pdo->exec("UPDATE support_messages SET message_type = 'customer_support' WHERE message_type IS NULL");
    }
    
    // Check if conversation_status column exists in existing table
    $stmt = $pdo->query("SHOW COLUMNS FROM support_messages LIKE 'conversation_status'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE support_messages ADD COLUMN conversation_status ENUM('open', 'solved', 'closed') DEFAULT 'open' AFTER message_type");
        
        // Update existing messages to have open status
        $pdo->exec("UPDATE support_messages SET conversation_status = 'open' WHERE conversation_status IS NULL");
        error_log("Added conversation_status column and set existing messages to 'open'");
    } else {
        // Ensure existing NULL values are set to open
        $pdo->exec("UPDATE support_messages SET conversation_status = 'open' WHERE conversation_status IS NULL");
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetSupportMessages($pdo);
            break;
            
        case 'POST':
            handlePostActions($pdo);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
    
} catch (PDOException $e) {
    error_log("Database error in admin_support_messages.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage(),
        'debug' => true
    ]);
} catch (Exception $e) {
    error_log("Error in admin_support_messages.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => true
    ]);
}

function handleGetSupportMessages($pdo) {
    try {
        $action = $_GET['action'] ?? 'conversations';
        
        if ($action === 'conversation_messages') {
            return getConversationMessages($pdo, $_GET['conversation_id'] ?? '');
        }
        
        // Get conversations grouped by conversation_id - only customer support messages
        $query = "
            SELECT 
                sm.conversation_id,
                sm.user_name,
                sm.user_email,
                MAX(sm.created_at) AS last_updated,
                (SELECT COALESCE(NULLIF(TRIM(s2.subject), ''), 'General') FROM support_messages s2 
                 WHERE s2.conversation_id = sm.conversation_id AND s2.is_admin = 0 AND s2.message_type = 'customer_support'
                 ORDER BY s2.created_at ASC LIMIT 1) AS subject,
                (SELECT s3.message FROM support_messages s3 
                 WHERE s3.conversation_id = sm.conversation_id AND s3.message_type = 'customer_support'
                 ORDER BY s3.created_at DESC LIMIT 1) AS last_message,
                (SELECT s4.is_admin FROM support_messages s4 
                 WHERE s4.conversation_id = sm.conversation_id AND s4.message_type = 'customer_support'
                 ORDER BY s4.created_at DESC LIMIT 1) AS last_message_is_admin,
                (SELECT s5.conversation_status FROM support_messages s5 
                 WHERE s5.conversation_id = sm.conversation_id AND s5.message_type = 'customer_support'
                 ORDER BY s5.created_at DESC LIMIT 1) AS conversation_status,
                COUNT(*) AS message_count,
                SUM(CASE WHEN sm.is_admin = 0 AND sm.is_read = 0 THEN 1 ELSE 0 END) AS unread_user_messages,
                SUM(CASE WHEN sm.is_admin = 1 THEN 1 ELSE 0 END) AS admin_replies
            FROM support_messages sm
            WHERE sm.message_type = 'customer_support'
            GROUP BY sm.conversation_id, sm.user_name, sm.user_email
            ORDER BY last_updated DESC
            LIMIT 50
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format conversations
        $formattedConversations = array_map(function($conv) {
            return [
                'conversation_id' => $conv['conversation_id'],
                'user_name' => $conv['user_name'],
                'user_email' => $conv['user_email'],
                'subject' => $conv['subject'],
                'last_message' => $conv['last_message'],
                'last_message_is_admin' => (bool)$conv['last_message_is_admin'],
                'conversation_status' => $conv['conversation_status'] ?? 'open',
                'last_updated' => $conv['last_updated'],
                'last_updated_human' => timeAgo($conv['last_updated']),
                'message_count' => (int)$conv['message_count'],
                'unread_count' => (int)$conv['unread_user_messages'],
                'has_admin_reply' => (int)$conv['admin_replies'] > 0
            ];
        }, $conversations);
        
        // Stats - only customer support messages
        $totalStmt = $pdo->prepare("SELECT COUNT(DISTINCT conversation_id) as total FROM support_messages WHERE message_type = 'customer_support'");
        $totalStmt->execute();
        $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $unreadStmt = $pdo->prepare("SELECT COUNT(DISTINCT conversation_id) as unread FROM support_messages WHERE is_admin = 0 AND is_read = 0 AND message_type = 'customer_support'");
        $unreadStmt->execute();
        $unread = $unreadStmt->fetch(PDO::FETCH_ASSOC)['unread'];
        
        echo json_encode([
            'success' => true,
            'data' => [
                'conversations' => $formattedConversations,
                'stats' => [
                    'total' => $total,
                    'unread' => $unread,
                    'replied' => 0,
                    'active_conversations' => $total
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to fetch support messages: " . $e->getMessage());
    }
}

function getConversationMessages($pdo, $conversationId) {
    if (empty($conversationId)) {
        throw new Exception('Conversation ID is required');
    }
    
    // Debug: Log the conversation ID being requested
    error_log("getConversationMessages called with conversation_id: " . $conversationId);
    
    // First check if conversation exists at all
    $checkQuery = "SELECT COUNT(*) as count FROM support_messages WHERE conversation_id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$conversationId]);
    $totalCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
    error_log("Total messages in conversation: " . $totalCount);
    
    // Check with message_type filter
    $checkTypeQuery = "SELECT COUNT(*) as count FROM support_messages WHERE conversation_id = ? AND message_type = 'customer_support'";
    $checkTypeStmt = $pdo->prepare($checkTypeQuery);
    $checkTypeStmt->execute([$conversationId]);
    $typeCount = $checkTypeStmt->fetch(PDO::FETCH_ASSOC)['count'];
    error_log("Customer support messages in conversation: " . $typeCount);
    
    $query = "
        SELECT 
            id, conversation_id, user_name, user_email, admin_name, 
            subject, message, attachment_paths, is_admin, created_at, is_read
        FROM support_messages 
        WHERE conversation_id = ? AND message_type = 'customer_support'
        ORDER BY created_at ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$conversationId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Retrieved " . count($messages) . " messages for conversation " . $conversationId);
    
    // Format messages
    $formattedMessages = array_map(function($msg) {
        return [
            'id' => $msg['id'],
            'conversation_id' => $msg['conversation_id'],
            'sender_name' => $msg['is_admin'] ? ($msg['admin_name'] ?: 'Admin') : $msg['user_name'],
            'sender_email' => $msg['user_email'],
            'message' => $msg['message'],
            'subject' => $msg['subject'],
            'attachment_paths' => $msg['attachment_paths'],
            'is_admin' => (bool)$msg['is_admin'],
            'created_at' => $msg['created_at'],
            'time_ago' => timeAgo($msg['created_at']),
            'is_read' => (bool)$msg['is_read']
        ];
    }, $messages);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'messages' => $formattedMessages,
            'conversation_id' => $conversationId
        ]
    ]);
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    if ($time < 31536000) return floor($time/2592000) . 'mo ago';
    return floor($time/31536000) . 'y ago';
}

function handlePostActions($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate CSRF token
    if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
        throw new Exception('Invalid CSRF token');
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'reply':
            handleReplyToMessage($pdo, $input);
            break;
            
        case 'reply_to_conversation':
            handleReplyToConversation($pdo, $input);
            break;
            
        case 'mark_read':
            handleMarkAsRead($pdo, $input);
            break;
            
        case 'mark_conversation_read':
            handleMarkConversationAsRead($pdo, $input);
            break;
            
        case 'update_conversation_status':
            handleUpdateConversationStatus($pdo, $input);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
}

function handleReplyToMessage($pdo, $input) {
    $messageId = intval($input['message_id'] ?? 0);
    $response = trim($input['response'] ?? '');
    
    if (!$messageId) {
        throw new Exception('Message ID is required');
    }
    
    if (empty($response)) {
        throw new Exception('Response message is required');
    }
    
    if (strlen($response) > 2000) {
        throw new Exception('Response must be less than 2000 characters');
    }
    
    $adminName = $_SESSION['admin_name'];
    
    // Get the original message to create a reply
    $stmt = $pdo->prepare("SELECT conversation_id, user_name, user_email FROM support_messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $originalMessage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$originalMessage) {
        throw new Exception('Original message not found');
    }
    
    // Insert admin reply as a new message with customer_support type
    // Get the original subject from the conversation
    $subjectStmt = $pdo->prepare("SELECT subject FROM support_messages WHERE conversation_id = ? AND subject IS NOT NULL AND TRIM(subject) != '' AND message_type = 'customer_support' ORDER BY created_at ASC LIMIT 1");
    $subjectStmt->execute([$originalMessage['conversation_id']]);
    $originalSubject = $subjectStmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        INSERT INTO support_messages 
        (conversation_id, user_name, user_email, admin_name, subject, message, message_type, is_admin, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'customer_support', TRUE, NOW())
    ");
    
    $stmt->execute([
        $originalMessage['conversation_id'],
        $originalMessage['user_name'],
        $originalMessage['user_email'],
        $adminName,
        $originalSubject,
        $response
    ]);
    
    // Mark original message as read
    $stmt = $pdo->prepare("UPDATE support_messages SET is_read = TRUE WHERE id = ?");
    $stmt->execute([$messageId]);
    
    // Log the admin response
    error_log("Admin response sent by $adminName for message ID $messageId");
    
    echo json_encode([
        'success' => true,
        'message' => 'Response sent successfully'
    ]);
}

function handleReplyToConversation($pdo, $input) {
    $conversationId = trim($input['conversation_id'] ?? '');
    $response = trim($input['response'] ?? '');
    
    if (empty($conversationId)) {
        throw new Exception('Conversation ID is required');
    }
    
    if (empty($response)) {
        throw new Exception('Response message is required');
    }
    
    if (strlen($response) > 2000) {
        throw new Exception('Response must be less than 2000 characters');
    }
    
    // Get admin name from session (temporarily use a default)
    $adminName = $_SESSION['admin_name'] ?? 'Admin';
    
    // Get conversation details and check status
    $stmt = $pdo->prepare("SELECT user_id, user_name, user_email, conversation_status FROM support_messages WHERE conversation_id = ? AND message_type = 'customer_support' AND is_admin = 0 ORDER BY created_at ASC LIMIT 1");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        throw new Exception('Conversation not found');
    }
    
    // Check if conversation is closed/solved
    if (in_array($conversation['conversation_status'], ['solved', 'closed'])) {
        throw new Exception('Cannot reply to a ' . $conversation['conversation_status'] . ' conversation. Please reopen it first.');
    }
    
    // Get the original subject from the conversation
    $subjectStmt = $pdo->prepare("SELECT subject FROM support_messages WHERE conversation_id = ? AND subject IS NOT NULL AND TRIM(subject) != '' AND message_type = 'customer_support' ORDER BY created_at ASC LIMIT 1");
    $subjectStmt->execute([$conversationId]);
    $originalSubject = $subjectStmt->fetchColumn();
    
    // Insert admin reply with customer_support type
    $stmt = $pdo->prepare("
        INSERT INTO support_messages 
        (conversation_id, user_name, user_email, admin_name, subject, message, message_type, is_admin, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'customer_support', TRUE, NOW())
    ");
    
    $stmt->execute([
        $conversationId,
        $conversation['user_name'],
        $conversation['user_email'],
        $adminName,
        $originalSubject,
        $response
    ]);
    
    // Trigger real-time notification for new admin reply
    triggerNewReplyNotification($pdo, $conversationId, $conversation, $adminName, $response);
    
    echo json_encode([
        'success' => true,
        'message' => 'Reply sent successfully'
    ]);
}

function handleMarkAsRead($pdo, $input) {
    $messageId = intval($input['message_id'] ?? 0);
    
    if (!$messageId) {
        throw new Exception('Message ID is required');
    }
    
    $stmt = $pdo->prepare("
        UPDATE support_messages 
        SET is_read = TRUE 
        WHERE id = ?
    ");
    
    $stmt->execute([$messageId]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Message not found or already read');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Message marked as read'
    ]);
}

function handleMarkConversationAsRead($pdo, $input) {
    $conversationId = trim($input['conversation_id'] ?? '');
    
    if (empty($conversationId)) {
        throw new Exception('Conversation ID is required');
    }
    
    // Mark all user messages in this conversation as read
    $stmt = $pdo->prepare("
        UPDATE support_messages 
        SET is_read = TRUE 
        WHERE conversation_id = ? AND is_admin = FALSE AND is_read = FALSE AND message_type = 'customer_support'
    ");
    
    $stmt->execute([$conversationId]);
    
    $affectedRows = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => 'Conversation marked as read',
        'messages_marked' => $affectedRows
    ]);
}

function handleUpdateConversationStatus($pdo, $input) {
    $conversationId = trim($input['conversation_id'] ?? '');
    $status = trim($input['status'] ?? '');
    
    if (empty($conversationId)) {
        throw new Exception('Conversation ID is required');
    }
    
    if (!in_array($status, ['open', 'solved', 'closed'])) {
        throw new Exception('Invalid status. Must be: open, solved, or closed');
    }
    
    // Check if conversation exists and get customer info
    $checkStmt = $pdo->prepare("SELECT user_id, user_name, user_email FROM support_messages WHERE conversation_id = ? AND message_type = 'customer_support' AND is_admin = 0 ORDER BY created_at ASC LIMIT 1");
    $checkStmt->execute([$conversationId]);
    $customerInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customerInfo) {
        throw new Exception('Conversation not found');
    }
    
    // Update all messages in this conversation with the new status
    $stmt = $pdo->prepare("
        UPDATE support_messages 
        SET conversation_status = ? 
        WHERE conversation_id = ? AND message_type = 'customer_support'
    ");
    
    $stmt->execute([$status, $conversationId]);
    
    $affectedRows = $stmt->rowCount();
    
    // Log the status change
    $adminName = $_SESSION['admin_name'] ?? 'Admin';
    error_log("Conversation $conversationId status changed to '$status' by $adminName");
    
    // Trigger real-time notification for customer
    triggerStatusChangeNotification($pdo, $conversationId, $status, $customerInfo, $adminName);
    
    echo json_encode([
        'success' => true,
        'message' => "Conversation status updated to '$status'",
        'status' => $status,
        'messages_updated' => $affectedRows
    ]);
}

function getSupportStats($pdo) {
    // Get various statistics
    $stats = [];
    
    // Total user messages (not admin replies)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM support_messages WHERE is_admin = 0");
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Unread user messages
    $stmt = $pdo->query("SELECT COUNT(*) as unread FROM support_messages WHERE is_read = 0 AND is_admin = 0");
    $stats['unread'] = $stmt->fetch(PDO::FETCH_ASSOC)['unread'];
    
    // Conversations with admin replies
    $stmt = $pdo->query("SELECT COUNT(DISTINCT conversation_id) as replied FROM support_messages WHERE is_admin = 1");
    $stats['replied'] = $stmt->fetch(PDO::FETCH_ASSOC)['replied'];
    
    // Active conversations (unique users from last 30 days)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_email) as active FROM support_messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND is_admin = 0");
    $stats['active_conversations'] = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
    
    return $stats;
}

function triggerStatusChangeNotification($pdo, $conversationId, $newStatus, $customerInfo, $adminName) {
    try {
        // Create a notification record for real-time delivery
        $notificationData = [
            'type' => 'support_status_change',
            'conversation_id' => $conversationId,
            'new_status' => $newStatus,
            'customer_id' => $customerInfo['user_id'],
            'customer_name' => $customerInfo['user_name'],
            'customer_email' => $customerInfo['user_email'],
            'admin_name' => $adminName,
            'timestamp' => time(),
            'message' => "Your support ticket status has been updated to: " . ucfirst($newStatus)
        ];
        
        // Store in a temporary notifications table for SSE pickup
        $stmt = $pdo->prepare("
            INSERT INTO realtime_notifications 
            (user_id, type, data, created_at) 
            VALUES (?, 'support_status_change', ?, NOW())
            ON DUPLICATE KEY UPDATE 
            data = VALUES(data), created_at = VALUES(created_at)
        ");
        
        // Create table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS realtime_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                type VARCHAR(50) NOT NULL,
                data JSON NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                delivered BOOLEAN DEFAULT FALSE,
                INDEX idx_user_type (user_id, type),
                INDEX idx_created (created_at),
                INDEX idx_delivered (delivered)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $stmt->execute([$customerInfo['user_id'], json_encode($notificationData)]);
        
        error_log("Real-time status notification queued for user {$customerInfo['user_id']} - conversation $conversationId status: $newStatus");
        
    } catch (Exception $e) {
        error_log("Failed to trigger status change notification: " . $e->getMessage());
    }
}

function triggerNewReplyNotification($pdo, $conversationId, $customerInfo, $adminName, $replyMessage) {
    try {
        // Create a notification record for real-time delivery
        $notificationData = [
            'type' => 'support_new_reply',
            'conversation_id' => $conversationId,
            'customer_id' => $customerInfo['user_id'],
            'customer_name' => $customerInfo['user_name'],
            'customer_email' => $customerInfo['user_email'],
            'admin_name' => $adminName,
            'reply_preview' => substr($replyMessage, 0, 100) . (strlen($replyMessage) > 100 ? '...' : ''),
            'timestamp' => time(),
            'message' => "You have a new reply from {$adminName} on your support ticket"
        ];
        
        // Store in a temporary notifications table for SSE pickup
        $stmt = $pdo->prepare("
            INSERT INTO realtime_notifications 
            (user_id, type, data, created_at) 
            VALUES (?, 'support_new_reply', ?, NOW())
        ");
        
        // Ensure table exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS realtime_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                type VARCHAR(50) NOT NULL,
                data JSON NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                delivered BOOLEAN DEFAULT FALSE,
                INDEX idx_user_type (user_id, type),
                INDEX idx_created (created_at),
                INDEX idx_delivered (delivered)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $stmt->execute([$customerInfo['user_id'], json_encode($notificationData)]);
        
        error_log("Real-time reply notification queued for user {$customerInfo['user_id']} - conversation $conversationId from: $adminName");
        
    } catch (Exception $e) {
        error_log("Failed to trigger new reply notification: " . $e->getMessage());
    }
}
?>
