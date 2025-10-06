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
require_once '../includes/email_notifications.php';

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
                attachment_paths TEXT NULL,
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
        
        // Check if message_type column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM support_messages LIKE 'message_type'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE support_messages ADD COLUMN message_type ENUM('customer_support', 'dev_support') DEFAULT 'customer_support' AFTER attachment_path");
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
        SELECT id, user_name, admin_name, message, attachment_paths, is_admin, created_at, is_read
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
    $attachmentPaths = [];
    
    // Check if this is a form data request (with file upload) or JSON request
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $isFormData = strpos($contentType, 'multipart/form-data') !== false;
    
    if ($isFormData) {
        // Handle form data with file upload
        $message = trim($_POST['message'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $conversationId = $_POST['conversation_id'] ?? '';
        $csrfToken = $_POST['csrf_token'] ?? '';
        
        // Handle multiple image uploads
        if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
            for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['attachments']['name'][$i],
                        'type' => $_FILES['attachments']['type'][$i],
                        'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                        'error' => $_FILES['attachments']['error'][$i],
                        'size' => $_FILES['attachments']['size'][$i]
                    ];
                    $attachmentPaths[] = handleImageUpload($file);
                }
            }
        }
    } else {
        // Handle JSON input (existing functionality)
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        $message = trim($input['message'] ?? '');
        $subject = trim($input['subject'] ?? '');
        $conversationId = $input['conversation_id'] ?? '';
        $csrfToken = $input['csrf_token'] ?? '';
    }
    
    // Validate CSRF token
    if (!isset($csrfToken) || !validateCSRFToken($csrfToken)) {
        throw new Exception('Invalid CSRF token');
    }
    
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
    
    // If this is a reply to existing conversation, check status and get the original subject
    if (!empty($conversationId)) {
        // Check if conversation exists and get its status
        $stmt = $pdo->prepare("SELECT conversation_status, subject FROM support_messages WHERE conversation_id = ? AND message_type = 'customer_support' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$conversationId]);
        $existingConversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingConversation) {
            // Check if conversation is closed/solved
            if (in_array($existingConversation['conversation_status'], ['solved', 'closed'])) {
                throw new Exception('This conversation has been ' . $existingConversation['conversation_status'] . ' and no longer accepts new messages.');
            }
            
            // Use original subject if no subject provided
            if (empty($subject) && !empty($existingConversation['subject'])) {
                $subject = $existingConversation['subject'];
            }
        }
    }
    
    // Convert attachment paths to JSON for storage
    $attachmentPathsJson = !empty($attachmentPaths) ? json_encode($attachmentPaths) : null;
    
    // Insert message with customer_support type and attachments
    $stmt = $pdo->prepare("
        INSERT INTO support_messages 
        (conversation_id, user_id, user_name, user_email, subject, message, attachment_paths, message_type, is_admin, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'customer_support', FALSE, NOW())
    ");
    
    $stmt->execute([
        $conversationId,
        $user_id,
        $user_name,
        $user_email,
        $subject,
        $message,
        $attachmentPathsJson
    ]);
    
    $messageId = $pdo->lastInsertId();
    
    // Create notification for admins and send email immediately
    createAdminNotification($pdo, $user_name, $user_email, $subject, $message, $conversationId);
    sendAdminEmailNotification($user_name, $user_email, $subject, $message, $conversationId);
    
    // Log the message for admin notification
    $attachmentCount = count($attachmentPaths);
    $attachmentInfo = $attachmentCount > 0 ? " (with $attachmentCount image" . ($attachmentCount > 1 ? 's' : '') . ")" : "";
    error_log("New support message from $user_email in conversation $conversationId: $message$attachmentInfo");
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully!',
        'data' => [
            'message_id' => $messageId,
            'conversation_id' => $conversationId,
            'attachment_paths' => $attachmentPaths,
            'attachment_count' => $attachmentCount,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
}

function handleImageUpload($file) {
    // Define upload directory
    $uploadDir = '../uploads/support_attachments/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }
    
    // Validate image file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB per image
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid image type. Please upload JPEG, PNG, GIF, or WebP images only.');
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('Image size too large. Maximum size is 5MB per image.');
    }
    
    // Additional image validation
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new Exception('Invalid image file.');
    }
    
    // Generate unique filename
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'support_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to upload image');
    }
    
    // Return relative path for database storage
    return 'uploads/support_attachments/' . $fileName;
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

/**
 * Create notification in admin notifications table
 */
function createAdminNotification($pdo, $userName, $userEmail, $subject, $message, $conversationId) {
    try {
        // Create notifications table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                type ENUM('info', 'warning', 'error', 'success') DEFAULT 'info',
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                conversation_id VARCHAR(50) NULL,
                user_email VARCHAR(255) NULL,
                INDEX idx_created_at (created_at),
                INDEX idx_is_read (is_read),
                INDEX idx_conversation_id (conversation_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Check if conversation_id column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'conversation_id'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN conversation_id VARCHAR(50) NULL AFTER created_at");
        }
        
        // Check if user_email column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'user_email'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN user_email VARCHAR(255) NULL AFTER conversation_id");
        }
        
        $notificationTitle = "New Customer Support Message";
        $notificationMessage = "New message from $userName ($userEmail)" . 
                              ($subject ? " - Subject: $subject" : "") . 
                              " - " . substr($message, 0, 100) . 
                              (strlen($message) > 100 ? "..." : "");
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (title, message, type, conversation_id, user_email) 
            VALUES (?, ?, 'info', ?, ?)
        ");
        $stmt->execute([$notificationTitle, $notificationMessage, $conversationId, $userEmail]);
        
        error_log("Created admin notification for customer support message from $userEmail");
        
    } catch (Exception $e) {
        error_log("Error creating admin notification: " . $e->getMessage());
    }
}

/**
 * Send email notification to all admin users
 */
function sendAdminEmailNotification($userName, $userEmail, $subject, $message, $conversationId) {
    try {
        $notificationTitle = "New Customer Support Message Received";
        $notificationMessage = "Customer: $userName ($userEmail)\n" .
                              "Conversation ID: $conversationId\n" .
                              ($subject ? "Subject: $subject\n" : "") .
                              "Message: " . substr($message, 0, 200) . 
                              (strlen($message) > 200 ? "..." : "") . "\n\n" .
                              "Please check your admin dashboard to respond to this message.";
        
        $success = EmailNotifications::sendAdminNotification(
            $notificationTitle,
            $notificationMessage,
            'info'
        );
        
        if ($success) {
            error_log("Successfully sent email notifications to admins for customer support message from $userEmail");
        } else {
            error_log("Failed to send email notifications to admins for customer support message from $userEmail");
        }
        
    } catch (Exception $e) {
        error_log("Error sending admin email notification: " . $e->getMessage());
    }
}
?>
