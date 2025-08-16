<?php
/**
 * Admin Messaging API
 * Handles admin side of real-time messaging with users
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../includes/config.php';
require_once '../includes/csrf.php';

// Check admin authentication
session_start();
if (!isset($_SESSION['admin_name']) || !isset($_SESSION['admin_email']) || !isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin privileges required.']);
    exit();
}

try {
    // Connect to database
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetConversations($pdo);
            break;
            
        case 'POST':
            handleSendAdminMessage($pdo);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
    
} catch (PDOException $e) {
    error_log("Database error in admin_messaging.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("Error in admin_messaging.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGetConversations($pdo) {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'messages') {
        // Get messages for a specific conversation
        $conversationId = $_GET['conversation_id'] ?? '';
        $lastMessageId = intval($_GET['last_message_id'] ?? 0);
        
        if (empty($conversationId)) {
            throw new Exception('Conversation ID is required');
        }
        
        $query = "
            SELECT id, user_name, admin_name, message, is_admin, created_at, is_read
            FROM support_messages 
            WHERE conversation_id = ? AND id > ?
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
        
        // Mark user messages as read by admin
        if (!empty($messages)) {
            $stmt = $pdo->prepare("
                UPDATE support_messages 
                SET is_read = TRUE 
                WHERE conversation_id = ? AND is_admin = FALSE AND is_read = FALSE
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
        return;
    }
    
    // Default: Get conversations list
    $query = "
        SELECT 
            conversation_id,
            user_name,
            user_email,
            MAX(created_at) as last_message_time,
            COUNT(*) as message_count,
            SUM(CASE WHEN is_admin = FALSE AND is_read = FALSE THEN 1 ELSE 0 END) as unread_count,
            (SELECT message FROM support_messages sm2 
             WHERE sm2.conversation_id = sm.conversation_id 
             ORDER BY created_at DESC LIMIT 1) as last_message
        FROM support_messages sm
        GROUP BY conversation_id, user_name, user_email
        ORDER BY last_message_time DESC
        LIMIT 50
    ";
    
    $stmt = $pdo->query($query);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format conversations
    foreach ($conversations as &$conversation) {
        $conversation['last_message_time_formatted'] = date('M j, Y g:i A', strtotime($conversation['last_message_time']));
        $conversation['time_ago'] = timeAgo($conversation['last_message_time']);
        $conversation['has_unread'] = $conversation['unread_count'] > 0;
        
        // Truncate last message for preview
        if (strlen($conversation['last_message']) > 60) {
            $conversation['last_message_preview'] = substr($conversation['last_message'], 0, 60) . '...';
        } else {
            $conversation['last_message_preview'] = $conversation['last_message'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'conversations' => $conversations,
            'total_unread' => array_sum(array_column($conversations, 'unread_count'))
        ]
    ]);
}

function handleSendAdminMessage($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate CSRF token
    if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
        throw new Exception('Invalid CSRF token');
    }
    
    $conversationId = $input['conversation_id'] ?? '';
    $message = trim($input['message'] ?? '');
    
    if (empty($conversationId)) {
        throw new Exception('Conversation ID is required');
    }
    
    if (empty($message)) {
        throw new Exception('Message is required');
    }
    
    if (strlen($message) > 1000) {
        throw new Exception('Message must be less than 1000 characters');
    }
    
    // Get admin information
    $adminName = $_SESSION['admin_name'];
    
    // Insert admin message
    $stmt = $pdo->prepare("
        INSERT INTO support_messages 
        (conversation_id, user_id, user_name, user_email, admin_name, message, is_admin, created_at) 
        VALUES (?, NULL, '', '', ?, ?, TRUE, NOW())
    ");
    
    $stmt->execute([
        $conversationId,
        $adminName,
        $message
    ]);
    
    $messageId = $pdo->lastInsertId();
    
    // Log the admin message
    error_log("Admin message sent by $adminName in conversation $conversationId: $message");
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
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
