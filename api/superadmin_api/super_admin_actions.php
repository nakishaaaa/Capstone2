<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/audit_helper.php';

// Enable error logging and display for debugging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in as developer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'developer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Debug logging
error_log("Super admin API called with action: " . ($action ?? 'none'));

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// If no action in GET/POST, check JSON body
if (empty($action)) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

try {
    switch ($action) {
        case 'toggle_maintenance':
            $data = json_decode(file_get_contents('php://input'), true);
            $enabled = $data['enabled'] ? 'true' : 'false';
            
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = 'maintenance_mode'");
            $stmt->bind_param("si", $enabled, $_SESSION['user_id']);
            $stmt->execute();
            
            // If maintenance is being enabled, force logout all customer sessions
            if ($enabled === 'true') {
                // Create a maintenance flag file to signal all customer sessions to logout
                $maintenance_flag = '../maintenance_active.flag';
                file_put_contents($maintenance_flag, time());
                
                // Also set a database flag for immediate logout
                $logout_stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'force_customer_logout'");
                $logout_time = time();
                $logout_stmt->bind_param("s", $logout_time);
                $logout_stmt->execute();
            } else {
                // Remove maintenance flag when disabled
                $maintenance_flag = '../maintenance_active.flag';
                if (file_exists($maintenance_flag)) {
                    unlink($maintenance_flag);
                }
                
                // Clear the force logout flag
                $logout_stmt = $conn->prepare("UPDATE system_settings SET setting_value = '0' WHERE setting_key = 'force_customer_logout'");
                $logout_stmt->execute();
            }
            
            // Log the action with detailed description
            $status = ($enabled === 'true') ? 'enabled' : 'disabled';
            logAuditEvent($_SESSION['user_id'], 'maintenance_toggle', "Maintenance mode {$status} - System " . ($enabled === 'true' ? 'locked for customers' : 'unlocked for all users'));
            
            echo json_encode(['success' => true, 'message' => 'Maintenance mode updated']);
            break;

        case 'get_support_conversations':
            // Get archive filter from request (default to active only)
            $archiveFilter = $_GET['archive_filter'] ?? 'active'; // 'active', 'archived', 'all'
            
            // Build archive condition
            $archiveCondition = '';
            if ($archiveFilter === 'active') {
                $archiveCondition = ' AND sm.archived = 0';
            } elseif ($archiveFilter === 'archived') {
                $archiveCondition = ' AND sm.archived = 1';
            }
            // 'all' means no additional condition
            
            // Get conversations from support_tickets_messages table with status from support_tickets
            $query = "
                SELECT 
                    sm.conversation_id as ticket_id,
                    sm.user_name as username,
                    sm.user_email as customer_email,
                    MAX(sm.created_at) AS last_updated,
                    (SELECT COALESCE(NULLIF(TRIM(s2.subject), ''), 'General') FROM support_tickets_messages s2 
                     WHERE s2.conversation_id = sm.conversation_id AND s2.is_admin = 0
                     ORDER BY s2.created_at ASC LIMIT 1) AS subject,
                    (SELECT s3.message FROM support_tickets_messages s3 
                     WHERE s3.conversation_id = sm.conversation_id
                     ORDER BY s3.created_at DESC LIMIT 1) AS last_message,
                    (SELECT s4.is_admin FROM support_tickets_messages s4 
                     WHERE s4.conversation_id = sm.conversation_id
                     ORDER BY s4.created_at DESC LIMIT 1) AS last_message_is_admin,
                    (SELECT s5.archived FROM support_tickets_messages s5 
                     WHERE s5.conversation_id = sm.conversation_id
                     ORDER BY s5.created_at DESC LIMIT 1) AS archived,
                    COUNT(*) AS message_count,
                    SUM(CASE WHEN sm.is_admin = 0 AND sm.is_read = 0 THEN 1 ELSE 0 END) AS unread_user_messages,
                    SUM(CASE WHEN sm.is_admin = 1 THEN 1 ELSE 0 END) AS admin_replies,
                    COALESCE(st.status, 'open') AS status
                FROM support_tickets_messages sm
                LEFT JOIN support_tickets st ON st.id = CAST(SUBSTRING(sm.conversation_id, 8) AS UNSIGNED)
                WHERE 1=1" . $archiveCondition . "
                GROUP BY sm.conversation_id, sm.user_name, sm.user_email, st.status
                ORDER BY last_updated DESC
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();
            $conversations = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $conversations[] = [
                        'ticket_id' => $row['ticket_id'],
                        'username' => $row['username'],
                        'subject' => $row['subject'],
                        'status' => 'open',
                        'priority' => 'medium',
                        'created_at' => $row['last_updated'],
                        'last_message_at' => $row['last_updated'],
                        'last_message_by' => $row['last_message_is_admin'] ? 'admin' : 'customer',
                        'unread_count' => (int)$row['unread_user_messages'],
                        'customer_email' => $row['customer_email'],
                        'last_message' => $row['last_message'],
                        'message_count' => $row['message_count'],
                        'admin_replies' => $row['admin_replies'],
                        'archived' => (int)($row['archived'] ?? 0)
                    ];
                }
            }
            
            // Stats - same as admin support
            $totalStmt = $conn->prepare("SELECT COUNT(DISTINCT conversation_id) as total FROM support_tickets_messages");
            $totalStmt->execute();
            $totalResult = $totalStmt->get_result();
            $total = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;
            
            $unreadStmt = $conn->prepare("SELECT COUNT(DISTINCT conversation_id) as unread FROM support_tickets_messages WHERE is_admin = 0 AND is_read = 0");
            $unreadStmt->execute();
            $unreadResult = $unreadStmt->get_result();
            $unread = $unreadResult ? $unreadResult->fetch_assoc()['unread'] : 0;
            
            $repliedStmt = $conn->prepare("SELECT COUNT(DISTINCT conversation_id) as replied FROM support_tickets_messages WHERE is_admin = 1");
            $repliedStmt->execute();
            $repliedResult = $repliedStmt->get_result();
            $replied = $repliedResult ? $repliedResult->fetch_assoc()['replied'] : 0;
            
            $stats = [
                'total' => $total,
                'unread' => $unread,
                'replied' => $replied,
                'active' => $total
            ];
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'conversations' => $conversations,
                    'stats' => $stats
                ]
            ]);
            break;
            
        case 'get_conversation_messages':
            $conversationId = $_GET['ticket_id'] ?? '';
            
            if (empty($conversationId)) {
                echo json_encode(['success' => false, 'message' => 'Conversation ID is required']);
                break;
            }
            
            // Get messages from support_tickets_messages table with ticket status
            $query = "
                SELECT 
                    sm.id, sm.conversation_id, sm.user_name, sm.user_email, sm.admin_name, 
                    sm.subject, sm.message, sm.attachment_paths, sm.is_admin, sm.created_at, sm.is_read,
                    COALESCE(st.status, 'open') AS status
                FROM support_tickets_messages sm
                LEFT JOIN support_tickets st ON st.id = CAST(SUBSTRING(sm.conversation_id, 8) AS UNSIGNED)
                WHERE sm.conversation_id = ?
                ORDER BY sm.created_at ASC
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $conversationId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $messages = [];
            $ticket = null;
            
            while ($row = $result->fetch_assoc()) {
                // Set ticket info from first message
                if (!$ticket) {
                    $ticket = [
                        'conversation_id' => $row['conversation_id'],
                        'username' => $row['user_name'],
                        'customer_email' => $row['user_email'],
                        'subject' => $row['subject'],
                        'status' => $row['status']
                    ];
                }
                
                $messages[] = [
                    'id' => $row['id'],
                    'sender_type' => $row['is_admin'] ? 'admin' : 'customer',
                    'sender_name' => $row['is_admin'] ? ($row['admin_name'] ?: 'Super Admin') : $row['user_name'],
                    'message' => $row['message'],
                    'attachment_path' => $row['attachment_paths'],
                    'created_at' => $row['created_at'],
                    'is_read' => $row['is_read']
                ];
            }
            
            if (empty($messages)) {
                echo json_encode(['success' => false, 'message' => 'Conversation not found']);
                break;
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'ticket' => $ticket,
                    'messages' => $messages
                ]
            ]);
            break;
            
        case 'send_conversation_reply':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
                break;
            }
            
            $conversationId = $input['ticket_id'] ?? '';
            $message = trim($input['message'] ?? '');
            
            if (empty($conversationId) || empty($message)) {
                echo json_encode(['success' => false, 'message' => 'Conversation ID and message are required']);
                break;
            }
            
            if (strlen($message) > 2000) {
                echo json_encode(['success' => false, 'message' => 'Message must be less than 2000 characters']);
                break;
            }
            
            $adminName = $_SESSION['username'] ?? 'Super Admin';
            
            // Get conversation details to get user info
            $stmt = $conn->prepare("SELECT user_name, user_email, subject FROM support_tickets_messages WHERE conversation_id = ? LIMIT 1");
            $stmt->bind_param("s", $conversationId);
            $stmt->execute();
            $conversation = $stmt->get_result()->fetch_assoc();
            
            if (!$conversation) {
                echo json_encode(['success' => false, 'message' => 'Conversation not found']);
                break;
            }
            
            // Insert admin reply as a new message (same as admin support)
            $stmt = $conn->prepare("
                INSERT INTO support_tickets_messages 
                (conversation_id, user_name, user_email, admin_name, subject, message, is_admin, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, TRUE, NOW())
            ");
            $stmt->bind_param("ssssss", 
                $conversationId,
                $conversation['user_name'],
                $conversation['user_email'],
                $adminName,
                $conversation['subject'],
                $message
            );
            
            if ($stmt->execute()) {
                // Mark all unread messages in this conversation as read
                $markReadStmt = $conn->prepare("UPDATE support_tickets_messages SET is_read = TRUE WHERE conversation_id = ? AND is_admin = 0");
                $markReadStmt->bind_param("s", $conversationId);
                $markReadStmt->execute();
                
                // Get customer user_id for real-time notification
                $userStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $userStmt->bind_param("s", $conversation['user_name']);
                $userStmt->execute();
                $userResult = $userStmt->get_result()->fetch_assoc();
                
                if ($userResult) {
                    // Send real-time notification via Pusher
                    require_once '../../includes/pusher_config.php';
                    
                    triggerPusherEvent('support-channel', 'new-developer-reply', [
                        'conversation_id' => $conversationId,
                        'customer_id' => $userResult['id'],
                        'admin_name' => $adminName,
                        'message' => substr($message, 0, 100),
                        'timestamp' => time()
                    ]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Reply sent successfully'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to send reply']);
            }
            break;
            
        case 'mark_conversation_read':
            $input = json_decode(file_get_contents('php://input'), true);
            $conversationId = $input['ticket_id'] ?? '';
            
            if (empty($conversationId)) {
                echo json_encode(['success' => false, 'message' => 'Conversation ID is required']);
                break;
            }
            
            // Mark all unread customer messages in this conversation as read (same as admin support)
            $stmt = $conn->prepare("UPDATE support_tickets_messages SET is_read = TRUE WHERE conversation_id = ? AND is_admin = 0");
            $stmt->bind_param("s", $conversationId);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Conversation marked as read'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to mark conversation as read']);
            }
            break;

        case 'recent_activity':
            $stmt = $conn->prepare("
                SELECT al.*, u.username 
                FROM audit_logs al 
                LEFT JOIN users u ON al.user_id = u.id 
                ORDER BY al.created_at DESC 
                LIMIT 10
            ");
            
            if ($stmt) {
                $stmt->execute();
                $result = $stmt->get_result();
                $activities = $result->fetch_all(MYSQLI_ASSOC);
                
                // If no activities found, create some sample data for testing
                if (empty($activities)) {
                    $activities = [
                        [
                            'id' => 1,
                            'action' => 'login',
                            'description' => 'System initialized - no recent activities yet',
                            'username' => 'System',
                            'created_at' => date('Y-m-d H:i:s')
                        ]
                    ];
                }
                
                echo json_encode(['success' => true, 'activities' => $activities]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to prepare statement']);
            }
            break;

        case 'get_settings':
            $stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $settings = [];
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;

        case 'save_settings':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $maintenanceChanged = false;
            $maintenanceStatus = '';
            
            foreach ($data['settings'] as $key => $value) {
                $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = ?");
                $stmt->bind_param("sis", $value, $_SESSION['user_id'], $key);
                $stmt->execute();
                
                // Track maintenance mode changes
                if ($key === 'maintenance_mode') {
                    $maintenanceChanged = true;
                    $maintenanceStatus = $value;
                }
            }
            
            // Log specific maintenance toggle or generic settings update
            if ($maintenanceChanged) {
                $status = ($maintenanceStatus === 'true') ? 'enabled' : 'disabled';
                logAuditEvent($_SESSION['user_id'], 'maintenance_toggle', "Maintenance mode {$status} - System " . ($maintenanceStatus === 'true' ? 'locked for customers' : 'unlocked for all users'));
            } else {
                logAuditEvent($_SESSION['user_id'], 'settings_update', 'System settings updated');
            }
            
            echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
            break;

        case 'get_users':
            $search = $_GET['search'] ?? '';
            $role = $_GET['role'] ?? '';
            $status = $_GET['status'] ?? '';
            
            $query = "SELECT id, username, email, role, status, last_login, created_at FROM users WHERE role != 'developer'";
            $params = [];
            $types = '';
            
            if ($search) {
                $query .= " AND (username LIKE ? OR email LIKE ?)";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $types .= 'ss';
            }
            
            if ($role && $role !== 'all') {
                $query .= " AND role = ?";
                $params[] = $role;
                $types .= 's';
            }
            
            if ($status && $status !== 'all') {
                $query .= " AND status = ?";
                $params[] = $status;
                $types .= 's';
            }
            
            $query .= " ORDER BY created_at DESC";
            
            $stmt = $conn->prepare($query);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $users = $result->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'users' => $users]);
            break;

        case 'get_audit_logs':
            $data = json_decode(file_get_contents('php://input'), true);
            $dateRange = $data['date_range'] ?? 'week';
            $filterAction = $data['filter_action'] ?? 'all';
            $filterUser = $data['filter_user'] ?? '';
            
            // Build date condition
            $dateCondition = '';
            switch ($dateRange) {
                case 'today':
                    $dateCondition = "DATE(al.created_at) = CURDATE()";
                    break;
                case 'yesterday':
                    $dateCondition = "DATE(al.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                    break;
                case 'week':
                    $dateCondition = "al.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                    break;
                case 'month':
                    $dateCondition = "al.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                    break;
                case 'all':
                default:
                    $dateCondition = "1=1";
                    break;
            }
            
            // Build query
            $query = "
                SELECT al.id, al.user_id, al.action, al.description, al.ip_address, al.user_agent, 
                       al.created_at as timestamp, u.username, u.email as user_email
                FROM audit_logs al 
                LEFT JOIN users u ON al.user_id = u.id 
                WHERE $dateCondition
            ";
            
            $params = [];
            $types = '';
            
            // Add action filter
            if ($filterAction !== 'all') {
                // Handle maintenance toggle filter - match both possible action names
                if ($filterAction === 'maintenance_toggle') {
                    $query .= " AND (al.action = ? OR al.action = ? OR al.action = ?)";
                    $params[] = 'maintenance_toggle';
                    $params[] = 'SETTINGS_UPDATE';
                    $params[] = 'settings_update';
                    $types .= 'sss';
                } else {
                    $query .= " AND al.action = ?";
                    $params[] = $filterAction;
                    $types .= 's';
                }
            }
            
            // Add user filter
            if ($filterUser) {
                $query .= " AND (u.username LIKE ? OR al.user_id = ?)";
                $userParam = "%$filterUser%";
                $params[] = $userParam;
                $params[] = intval($filterUser);
                $types .= 'si';
            }
            
            $query .= " ORDER BY al.created_at DESC LIMIT 100";
            
            $stmt = $conn->prepare($query);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $logs = $result->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;

        case 'get_user':
            $userId = $_GET['user_id'] ?? 0;
            
            if (!$userId) {
                echo json_encode(['success' => false, 'message' => 'User ID is required']);
                break;
            }
            
            $stmt = $conn->prepare("SELECT id, username, email, role, status, last_login, created_at FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if ($user) {
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
            break;

        case 'create_admin':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Debug logging
            error_log("Create admin data received: " . print_r($data, true));
            
            $username = $data['username'] ?? '';
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            $role = $data['role'] ?? 'admin';
            $status = $data['status'] ?? 'active';
            
            // Debug logging
            error_log("Parsed values - Username: '$username', Email: '$email', Password length: " . strlen($password) . ", Role: '$role', Status: '$status'");
            
            // Validate input
            if (empty($username) || empty($email) || empty($password)) {
                error_log("Validation failed: empty fields");
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                break;
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                error_log("Validation failed: invalid email format");
                echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                break;
            }
            
            if (strlen($password) < 6) {
                error_log("Validation failed: password too short");
                echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
                break;
            }
            
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                error_log("Validation failed: username or email already exists");
                echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
                break;
            }
            
            // Hash password and create user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssss", $username, $email, $hashedPassword, $role, $status);
            
            if ($stmt->execute()) {
                $newUserId = $conn->insert_id;
                
                // Log the action
                logAuditEvent($_SESSION['user_id'], 'user_created', "New {$role} account created: {$username} ({$email})");
                
                error_log("Account created successfully for user: $username");
                echo json_encode(['success' => true, 'message' => 'Account created successfully', 'user_id' => $newUserId]);
            } else {
                error_log("Database error: " . $conn->error);
                echo json_encode(['success' => false, 'message' => 'Failed to create account: ' . $conn->error]);
            }
            break;

        case 'update_user':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $userId = $data['user_id'] ?? 0;
            $username = $data['username'] ?? '';
            $email = $data['email'] ?? '';
            $role = $data['role'] ?? '';
            $status = $data['status'] ?? '';
            
            if (!$userId || empty($username) || empty($email) || empty($role) || empty($status)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                break;
            }
            
            // Check if username or email already exists for other users
            $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->bind_param("ssi", $username, $email, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
                break;
            }
            
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $username, $email, $role, $status, $userId);
            
            if ($stmt->execute()) {
                logAuditEvent($_SESSION['user_id'], 'user_updated', "User account updated: {$username} ({$email})");
                echo json_encode(['success' => true, 'message' => 'Account updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update account']);
            }
            break;

        case 'delete_user':
            $data = json_decode(file_get_contents('php://input'), true);
            $userId = $data['user_id'] ?? 0;
            
            if (!$userId) {
                echo json_encode(['success' => false, 'message' => 'User ID is required']);
                break;
            }
            
            // Prevent deleting super admin accounts
            $stmt = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                break;
            }
            
            if ($user['role'] === 'developer') {
                echo json_encode(['success' => false, 'message' => 'Cannot delete developer accounts']);
                break;
            }
            
            if ($userId == $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
                break;
            }
            
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            
            if ($stmt->execute()) {
                logAuditEvent($_SESSION['user_id'], 'user_deleted', "User account deleted: {$user['username']}");
                echo json_encode(['success' => true, 'message' => 'Account deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete account']);
            }
            break;


        case 'get_notifications':
            $filter = $_GET['filter'] ?? 'all';
            $notifications = [];
            
            // Get security notifications (failed logins, suspicious activity) from audit_logs
            if ($filter === 'all' || $filter === 'security') {
                // Security events from audit logs (last 24 hours)
                $securityQuery = "
                    SELECT 
                        al.id,
                        CASE 
                            WHEN al.action LIKE '%login_fail%' THEN 'Failed Login Attempt'
                            WHEN al.action LIKE '%suspicious%' THEN 'Suspicious Activity'
                            WHEN al.action LIKE '%blocked%' THEN 'Security Block'
                            ELSE 'Security Alert'
                        END as title,
                        CONCAT('Action: ', al.action, ' - ', al.description, ' (IP: ', COALESCE(al.ip_address, 'unknown'), ')') as message,
                        CASE 
                            WHEN al.action LIKE '%fail%' OR al.action LIKE '%blocked%' THEN 'error'
                            WHEN al.action LIKE '%suspicious%' THEN 'warning'
                            ELSE 'info'
                        END as type,
                        COALESCE(al.is_read, FALSE) as is_read,
                        al.created_at,
                        'security' as notification_source,
                        'security_event' as security_type
                    FROM audit_logs al
                    WHERE (al.action LIKE '%login_failed%' OR al.action LIKE '%suspicious%' OR al.action LIKE '%blocked%' OR al.action LIKE '%security%')
                    AND al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    AND COALESCE(al.deleted, FALSE) = FALSE
                    ORDER BY al.created_at DESC 
                    LIMIT 10
                ";
                
                $stmt = $conn->prepare($securityQuery);
                $stmt->execute();
                $securityNotifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $notifications = array_merge($notifications, $securityNotifications);
                
                // Debug logging for security notifications
                error_log("Security notifications query executed. Found " . count($securityNotifications) . " security events");
                if (count($securityNotifications) > 0) {
                    error_log("First security notification: " . json_encode($securityNotifications[0]));
                }
                
            }
            
            // Get system health notifications (technical issues only, no business/sales data)
            if ($filter === 'all' || $filter === 'system') {
                // Add is_read and deleted columns to audit_logs if they don't exist
                // Use prepared statements for ALTER TABLE (safe since no user input)
                $stmt1 = $conn->prepare("ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS is_read BOOLEAN DEFAULT FALSE");
                $stmt1->execute();
                $stmt2 = $conn->prepare("ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS deleted BOOLEAN DEFAULT FALSE");
                $stmt2->execute();
                
                // Enhanced system errors from audit logs (last 24 hours) - comprehensive error tracking
                $errorQuery = "
                    SELECT 
                        al.id,
                        CASE 
                            WHEN al.action LIKE '%javascript_error%' THEN 'JavaScript Error'
                            WHEN al.action LIKE '%api_failure%' THEN 'API Failure'
                            WHEN al.action LIKE '%file_%_error%' THEN 'File Operation Error'
                            WHEN al.action LIKE '%db_%_warning%' THEN 'Database Warning'
                            WHEN al.action LIKE '%payment_error%' THEN 'Payment Processing Error'
                            WHEN al.action LIKE '%email_failure%' THEN 'Email Delivery Failure'
                            WHEN al.action LIKE '%performance_warning%' THEN 'Performance Issue'
                            WHEN al.action LIKE '%business_logic_error%' THEN 'Business Logic Error'
                            WHEN al.action LIKE '%validation_failure%' THEN 'Validation Error'
                            WHEN al.action LIKE '%resource_error%' THEN 'Resource Loading Error'
                            WHEN al.action LIKE '%network_error%' THEN 'Network Connectivity Issue'
                            WHEN al.action LIKE '%promise_rejection%' THEN 'Unhandled Promise Error'
                            WHEN al.action LIKE '%session_%' THEN 'Session Management Issue'
                            ELSE 'System Error Detected'
                        END as title,
                        CONCAT('Action: ', al.action, ' - ', al.description) as message,
                        CASE 
                            WHEN al.action LIKE '%javascript_error%' OR al.action LIKE '%promise_rejection%' THEN 'warning'
                            WHEN al.action LIKE '%performance_warning%' OR al.action LIKE '%db_%_warning%' THEN 'info'
                            WHEN al.action LIKE '%payment_error%' OR al.action LIKE '%api_failure%' THEN 'error'
                            ELSE 'error'
                        END as type,
                        COALESCE(al.is_read, FALSE) as is_read,
                        al.created_at,
                        'system' as notification_source,
                        CASE 
                            WHEN al.action LIKE '%javascript_error%' OR al.action LIKE '%promise_rejection%' OR al.action LIKE '%resource_error%' THEN 'client_error'
                            WHEN al.action LIKE '%api_failure%' OR al.action LIKE '%network_error%' THEN 'api_error'
                            WHEN al.action LIKE '%file_%_error%' THEN 'file_error'
                            WHEN al.action LIKE '%db_%' THEN 'database_error'
                            WHEN al.action LIKE '%payment_error%' THEN 'payment_error'
                            WHEN al.action LIKE '%email_failure%' THEN 'email_error'
                            WHEN al.action LIKE '%performance_warning%' THEN 'performance_issue'
                            WHEN al.action LIKE '%business_logic_error%' THEN 'business_error'
                            WHEN al.action LIKE '%validation_failure%' THEN 'validation_error'
                            WHEN al.action LIKE '%session_%' THEN 'session_error'
                            ELSE 'system_error'
                        END as system_type
                    FROM audit_logs al
                    WHERE (
                        al.action LIKE '%error%' OR 
                        al.action LIKE '%failure%' OR 
                        al.action LIKE '%warning%' OR
                        (al.action LIKE '%fail%' AND al.action NOT LIKE '%login_failed%') OR
                        al.action LIKE '%javascript_error%' OR
                        al.action LIKE '%promise_rejection%' OR
                        al.action LIKE '%resource_error%' OR
                        al.action LIKE '%network_error%' OR
                        al.action LIKE '%api_failure%' OR
                        al.action LIKE '%file_%_error%' OR
                        al.action LIKE '%db_%_warning%' OR
                        al.action LIKE '%payment_error%' OR
                        al.action LIKE '%email_failure%' OR
                        al.action LIKE '%performance_warning%' OR
                        al.action LIKE '%business_logic_error%' OR
                        al.action LIKE '%validation_failure%' OR
                        al.action LIKE '%session_%'
                    )
                    AND al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    AND COALESCE(al.deleted, FALSE) = FALSE
                    ORDER BY al.created_at DESC 
                    LIMIT 25
                ";
                
                $stmt = $conn->prepare($errorQuery);
                $stmt->execute();
                $errorNotifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $notifications = array_merge($notifications, $errorNotifications);
            }
            
            
            // Get customer support ticket notifications (last 7 days)
            if ($filter === 'all' || $filter === 'customer_support') {
                // Add is_read column to support_tickets table if it doesn't exist
                $stmt = $conn->prepare("ALTER TABLE support_tickets ADD COLUMN IF NOT EXISTS is_read BOOLEAN DEFAULT FALSE");
                $stmt->execute();
                
                $ticketQuery = "
                    SELECT 
                        id,
                        CONCAT('New support ticket: ', subject) as title,
                        CONCAT('From: ', username, ' - ', 
                            IF(CHAR_LENGTH(message) > 100, 
                                CONCAT(LEFT(message, 100), '...'), 
                                message
                            )
                        ) as message,
                        'info' as type,
                        COALESCE(is_read, FALSE) as is_read,
                        created_at,
                        'customer_support' as notification_source,
                        'ticket' as support_type,
                        id as ticket_id
                    FROM support_tickets 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ORDER BY created_at DESC 
                    LIMIT 25
                ";
                
                $stmt = $conn->prepare($ticketQuery);
                $stmt->execute();
                $ticketNotifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $notifications = array_merge($notifications, $ticketNotifications);
            }
            
            // Get customer support message notifications (last 7 days)
            if ($filter === 'all' || $filter === 'customer_support') {
                // Check if support_messages table exists
                $tableCheckStmt = $conn->prepare("SHOW TABLES LIKE 'support_messages'");
                $tableCheckStmt->execute();
                $tableCheck = $tableCheckStmt->get_result();
                if ($tableCheck->num_rows > 0) {
                    // Add is_read column to support_messages table if it doesn't exist
                    $stmt = $conn->prepare("ALTER TABLE support_messages ADD COLUMN IF NOT EXISTS is_read BOOLEAN DEFAULT FALSE");
                    $stmt->execute();
                    
                    $messageQuery = "
                        SELECT 
                            id,
                            CONCAT('New support message: ', COALESCE(subject, 'No subject')) as title,
                            CONCAT('From: ', user_name, ' - ', 
                                IF(CHAR_LENGTH(message) > 100, 
                                    CONCAT(LEFT(message, 100), '...'), 
                                    message
                                )
                            ) as message,
                            'info' as type,
                            COALESCE(is_read, FALSE) as is_read,
                            created_at,
                            'customer_support' as notification_source,
                            'message' as support_type,
                            conversation_id
                        FROM support_messages 
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        AND message_type = 'customer_support'
                        AND is_admin = FALSE
                        ORDER BY created_at DESC 
                        LIMIT 25
                    ";
                    
                    $stmt = $conn->prepare($messageQuery);
                    $stmt->execute();
                    $messageNotifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $notifications = array_merge($notifications, $messageNotifications);
                }
            }
            
            // Sort all notifications by created_at DESC
            usort($notifications, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
            // Limit to 50 total notifications
            $notifications = array_slice($notifications, 0, 50);
            
            // Debug logging
            error_log("Total notifications found: " . count($notifications));
            
            echo json_encode(['success' => true, 'notifications' => $notifications, 'count' => count($notifications)]);
            break;

        case 'mark_notification_read':
            $data = json_decode(file_get_contents('php://input'), true);
            $notificationId = $data['id'];
            $notificationSource = $data['source'] ?? 'system';
            
            $success = false;
            
            if ($notificationSource === 'customer_support') {
                // Check if it's a ticket or message and update accordingly
                $supportType = $data['support_type'] ?? 'ticket';
                if ($supportType === 'ticket') {
                    $stmt = $conn->prepare("UPDATE support_tickets SET is_read = TRUE WHERE id = ?");
                    $stmt->bind_param("i", $notificationId);
                    $success = $stmt->execute();
                } else {
                    $stmt = $conn->prepare("UPDATE support_messages SET is_read = TRUE WHERE id = ?");
                    $stmt->bind_param("i", $notificationId);
                    $success = $stmt->execute();
                }
            } elseif ($notificationSource === 'security') {
                // For security notifications from audit_logs
                $stmt = $conn->prepare("UPDATE audit_logs SET is_read = TRUE WHERE id = ?");
                $stmt->bind_param("i", $notificationId);
                $success = $stmt->execute();
            } elseif ($notificationSource === 'system') {
                // Determine which table based on system_type
                $systemType = $data['system_type'] ?? 'high_value_order';
                if ($systemType === 'high_value_order') {
                    $stmt = $conn->prepare("UPDATE customer_requests SET is_read = TRUE WHERE id = ?");
                } elseif ($systemType === 'low_inventory') {
                    $stmt = $conn->prepare("UPDATE inventory SET is_read = TRUE WHERE id = ?");
                } else {
                    // system_error or other audit log types
                    $stmt = $conn->prepare("UPDATE audit_logs SET is_read = TRUE WHERE id = ?");
                }
                $stmt->bind_param("i", $notificationId);
                $success = $stmt->execute();
            }
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
            }
            break;

        case 'delete_notification':
            $data = json_decode(file_get_contents('php://input'), true);
            $notificationId = $data['id'];
            $notificationSource = $data['source'] ?? 'system';
            
            $success = false;
            
            if ($notificationSource === 'customer_support') {
                // For customer support, we can actually delete the records
                $supportType = $data['support_type'] ?? 'ticket';
                if ($supportType === 'ticket') {
                    $stmt = $conn->prepare("DELETE FROM support_tickets WHERE id = ?");
                    $stmt->bind_param("i", $notificationId);
                    $success = $stmt->execute();
                } else {
                    $stmt = $conn->prepare("DELETE FROM support_messages WHERE id = ?");
                    $stmt->bind_param("i", $notificationId);
                    $success = $stmt->execute();
                }
            } elseif ($notificationSource === 'security') {
                // For security notifications from audit_logs, mark as deleted
                $stmt = $conn->prepare("UPDATE audit_logs SET deleted = TRUE WHERE id = ?");
                $stmt->bind_param("i", $notificationId);
                $success = $stmt->execute();
            } elseif ($notificationSource === 'system') {
                // For system notifications, mark as deleted instead of actually deleting the records
                $systemType = $data['system_type'] ?? 'high_value_order';
                if ($systemType === 'high_value_order') {
                    $stmt = $conn->prepare("UPDATE customer_requests SET deleted = TRUE WHERE id = ?");
                } elseif ($systemType === 'low_inventory') {
                    $stmt = $conn->prepare("UPDATE inventory SET deleted = TRUE WHERE id = ?");
                } else {
                    // system_error or other audit log types
                    $stmt = $conn->prepare("UPDATE audit_logs SET deleted = TRUE WHERE id = ?");
                }
                $stmt->bind_param("i", $notificationId);
                $success = $stmt->execute();
            }
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Notification deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete notification']);
            }
            break;

        case 'get_backup_history':
            $stmt = $conn->prepare("SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 50");
            $stmt->execute();
            $backups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'backups' => $backups]);
            break;

        case 'create_backup':
            $data = json_decode(file_get_contents('php://input'), true);
            $backupType = $data['type'] ?? 'manual';
            $description = $data['description'] ?? 'Manual backup';
            $options = $data['options'] ?? ['database' => true, 'files' => true];
            
            // Create backup_logs table if it doesn't exist
            $createTableStmt = $conn->prepare("CREATE TABLE IF NOT EXISTS backup_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                backup_type VARCHAR(50) NOT NULL DEFAULT 'manual',
                file_name VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                file_size BIGINT DEFAULT NULL,
                status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
                error_message TEXT DEFAULT NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_created_at (created_at)
            )");
            $createTableStmt->execute();
            
            // Add description column if it doesn't exist (for existing tables)
            $alterStmt = $conn->prepare("ALTER TABLE backup_logs ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL AFTER file_name");
            $alterStmt->execute();
            
            // Create backup entry
            $fileName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $stmt = $conn->prepare("INSERT INTO backup_logs (backup_type, file_name, description, status, created_by, created_at) VALUES (?, ?, ?, 'pending', ?, NOW())");
            $stmt->bind_param("sssi", $backupType, $fileName, $description, $_SESSION['user_id']);
            $stmt->execute();
            $backupId = $conn->insert_id;
            
            // Start backup process
            $backupPath = '../../api/backups/' . $fileName;
            
            // Create backups directory if it doesn't exist
            if (!is_dir('../../api/backups/')) {
                mkdir('../../api/backups/', 0755, true);
            }
            
            // PHP-based database backup (more reliable than mysqldump)
            try {
                $backupContent = "-- Database backup created on " . date('Y-m-d H:i:s') . "\n";
                $backupContent .= "-- Generated by Super Admin Dashboard\n\n";
                
                // Get all tables with validation
                $tables = [];
                $stmt = $conn->prepare("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_array()) {
                    // Validate table name to prevent injection
                    if (preg_match('/^[a-zA-Z0-9_]+$/', $row[0])) {
                        $tables[] = $row[0];
                    }
                }
                $stmt->close();
                
                // Backup each table
                foreach ($tables as $table) {
                    $backupContent .= "\n-- Table structure for table `$table`\n";
                    $backupContent .= "DROP TABLE IF EXISTS `$table`;\n";
                    
                    // Get table structure with prepared statement (table name already validated)
                    // Note: SHOW CREATE TABLE cannot use ? placeholders, but table name is validated above
                    if (preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                        $createTableQuery = "SHOW CREATE TABLE `" . $conn->real_escape_string($table) . "`";
                        $stmt = $conn->prepare($createTableQuery);
                        if ($stmt && $stmt->execute()) {
                            $result = $stmt->get_result();
                            $row = $result->fetch_array();
                            $backupContent .= $row[1] . ";\n\n";
                            $stmt->close();
                        }
                    }
                    
                    // Get table data with prepared statement (table name already validated)
                    $backupContent .= "-- Dumping data for table `$table`\n";
                    if (preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                        $selectQuery = "SELECT * FROM `" . $conn->real_escape_string($table) . "`";
                        $stmt = $conn->prepare($selectQuery);
                        if ($stmt && $stmt->execute()) {
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $backupContent .= "INSERT INTO `$table` VALUES (";
                                $values = [];
                                foreach ($row as $value) {
                                    if ($value === null) {
                                        $values[] = 'NULL';
                                    } else {
                                    $values[] = "'" . $conn->real_escape_string($value) . "'";
                                    }
                                }
                                $backupContent .= implode(', ', $values) . ");\n";
                            }
                        }
                            $stmt->close();
                        }
                    }
                    $backupContent .= "\n";
                }
                
                // Write backup to file
                if (file_put_contents($backupPath, $backupContent)) {
                    $fileSize = filesize($backupPath);
                    $stmt = $conn->prepare("UPDATE backup_logs SET status = 'completed', file_size = ?, completed_at = NOW() WHERE id = ?");
                    $stmt->bind_param("ii", $fileSize, $backupId);
                    $stmt->execute();
                    
                    logAuditEvent($_SESSION['user_id'], 'backup_created', "Manual backup created: $fileName");
                    
                    echo json_encode(['success' => true, 'message' => 'Backup created successfully', 'file' => $fileName]);
                } else {
                    throw new Exception('Failed to write backup file');
                }
                
            } catch (Exception $e) {
                $errorMsg = 'Backup failed: ' . $e->getMessage();
                $stmt = $conn->prepare("UPDATE backup_logs SET status = 'failed', error_message = ? WHERE id = ?");
                $stmt->bind_param("si", $errorMsg, $backupId);
                $stmt->execute();
                
                echo json_encode(['success' => false, 'message' => $errorMsg]);
            }
            break;

        case 'download_backup':
            $backupId = $_GET['backup_id'] ?? '';
            
            if (empty($backupId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Backup ID is required']);
                break;
            }
            
            // Get backup file info
            $stmt = $conn->prepare("SELECT file_name, file_size, status FROM backup_logs WHERE id = ?");
            $stmt->bind_param("i", $backupId);
            $stmt->execute();
            $result = $stmt->get_result();
            $backup = $result->fetch_assoc();
            
            if (!$backup) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Backup not found']);
                break;
            }
            
            if ($backup['status'] !== 'completed') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Backup is not completed']);
                break;
            }
            
            $filePath = '../../api/backups/' . $backup['file_name'];
            
            if (!file_exists($filePath)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Backup file not found']);
                break;
            }
            
            // Set headers for file download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $backup['file_name'] . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            // Output file content
            readfile($filePath);
            
            // Log the download
            logAuditEvent($_SESSION['user_id'], 'backup_downloaded', "Backup downloaded: " . $backup['file_name']);
            exit();

        case 'delete_backup':
            $data = json_decode(file_get_contents('php://input'), true);
            $backupId = $data['backup_id'] ?? '';
            
            if (empty($backupId)) {
                echo json_encode(['success' => false, 'message' => 'Backup ID is required']);
                break;
            }
            
            // Get backup file info
            $stmt = $conn->prepare("SELECT file_name FROM backup_logs WHERE id = ?");
            $stmt->bind_param("i", $backupId);
            $stmt->execute();
            $result = $stmt->get_result();
            $backup = $result->fetch_assoc();
            
            if (!$backup) {
                echo json_encode(['success' => false, 'message' => 'Backup not found']);
                break;
            }
            
            // Delete file if exists
            $filePath = '../../api/backups/' . $backup['file_name'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Delete database record
            $stmt = $conn->prepare("DELETE FROM backup_logs WHERE id = ?");
            $stmt->bind_param("i", $backupId);
            
            if ($stmt->execute()) {
                logAuditEvent($_SESSION['user_id'], 'backup_deleted', "Backup deleted: " . $backup['file_name']);
                echo json_encode(['success' => true, 'message' => 'Backup deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete backup']);
            }
            break;

        case 'restore_backup':
            if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'No backup file uploaded or upload error']);
                break;
            }

            $uploadedFile = $_FILES['backup_file'];
            $fileName = $uploadedFile['name'];
            $tmpName = $uploadedFile['tmp_name'];
            
            // Validate file extension
            $allowedExtensions = ['sql', 'zip'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Only .sql and .zip files are allowed']);
                break;
            }

            try {
                // Read the SQL file content
                $sqlContent = file_get_contents($tmpName);
                
                if ($sqlContent === false) {
                    echo json_encode(['success' => false, 'message' => 'Failed to read backup file']);
                    break;
                }

                // Disable foreign key checks temporarily
                $conn->query('SET FOREIGN_KEY_CHECKS = 0');
                $conn->query('SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO"');
                
                // Use multi_query to execute the entire SQL file at once
                if ($conn->multi_query($sqlContent)) {
                    $successCount = 0;
                    $errorCount = 0;
                    
                    // Process all results
                    do {
                        $successCount++;
                        // Store first result set if available
                        if ($result = $conn->store_result()) {
                            $result->free();
                        }
                        // Check if there are more results
                        if (!$conn->more_results()) {
                            break;
                        }
                    } while ($conn->next_result());
                    
                    // Check for errors
                    if ($conn->errno) {
                        $errorCount++;
                        error_log("SQL Error in restore: " . $conn->error);
                    }
                } else {
                    $errorCount = 1;
                    error_log("SQL Error in restore: " . $conn->error);
                    $successCount = 0;
                }
                
                // Re-enable foreign key checks
                $conn->query('SET FOREIGN_KEY_CHECKS = 1');
                
                // Log the restore action
                logAuditEvent($_SESSION['user_id'], 'backup_restored', "Database restored from backup: {$fileName}. Executed {$successCount} statements, {$errorCount} errors");
                
                if ($errorCount > 0) {
                    echo json_encode(['success' => false, 'message' => "Backup restored with {$errorCount} errors. Check logs for details."]);
                } else {
                    echo json_encode(['success' => true, 'message' => "Backup restored successfully. {$successCount} statements executed."]);
                }
                
            } catch (Exception $e) {
                // Re-enable foreign key checks in case of error
                $conn->query('SET FOREIGN_KEY_CHECKS = 1');
                
                error_log("Backup restore error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error restoring backup: ' . $e->getMessage()]);
            }
            break;

        case 'clear_cache':
            // Clear PHP opcache if available
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            // Clear session files (optional - be careful in production)
            $sessionPath = session_save_path() ?: sys_get_temp_dir();
            $files = glob($sessionPath . '/sess_*');
            $cleared = 0;
            
            foreach ($files as $file) {
                if (is_file($file) && (time() - filemtime($file)) > 3600) { // Only clear old sessions
                    unlink($file);
                    $cleared++;
                }
            }
            
            // Log the action
            logAuditEvent($_SESSION['user_id'], 'cache_cleared', "System cache cleared, {$cleared} old session files removed");
            
            echo json_encode(['success' => true, 'message' => "Cache cleared successfully. {$cleared} old session files removed."]);
            break;

        case 'system_check':
            $checks = [];
            
            // Database connection check
            $checks['database'] = ['status' => 'ok', 'message' => 'Database connection successful'];
            
            // Check disk space
            $freeSpace = disk_free_space('.');
            $totalSpace = disk_total_space('.');
            $usagePercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
            
            $checks['disk_space'] = [
                'status' => $usagePercent > 90 ? 'error' : ($usagePercent > 80 ? 'warning' : 'ok'),
                'message' => sprintf('Disk usage: %.1f%% (%.2f GB free)', $usagePercent, $freeSpace / 1024 / 1024 / 1024)
            ];
            
            // Check PHP version
            $phpVersion = PHP_VERSION;
            $checks['php_version'] = [
                'status' => version_compare($phpVersion, '7.4.0', '>=') ? 'ok' : 'warning',
                'message' => "PHP version: {$phpVersion}"
            ];
            
            // Check required extensions
            $requiredExtensions = ['mysqli', 'json', 'session'];
            $missingExtensions = [];
            
            foreach ($requiredExtensions as $ext) {
                if (!extension_loaded($ext)) {
                    $missingExtensions[] = $ext;
                }
            }
            
            $checks['extensions'] = [
                'status' => empty($missingExtensions) ? 'ok' : 'error',
                'message' => empty($missingExtensions) ? 'All required extensions loaded' : 'Missing extensions: ' . implode(', ', $missingExtensions)
            ];
            
            // Check file permissions
            $writableDirs = ['exports/', 'images/'];
            $permissionIssues = [];
            
            foreach ($writableDirs as $dir) {
                if (!is_writable($dir)) {
                    $permissionIssues[] = $dir;
                }
            }
            
            $checks['permissions'] = [
                'status' => empty($permissionIssues) ? 'ok' : 'warning',
                'message' => empty($permissionIssues) ? 'File permissions OK' : 'Write permission issues: ' . implode(', ', $permissionIssues)
            ];
            
            // Overall status
            $hasErrors = false;
            $hasWarnings = false;
            
            foreach ($checks as $check) {
                if ($check['status'] === 'error') $hasErrors = true;
                if ($check['status'] === 'warning') $hasWarnings = true;
            }
            
            $overallStatus = $hasErrors ? 'error' : ($hasWarnings ? 'warning' : 'ok');
            
            // Log the system check
            logAuditEvent($_SESSION['user_id'], 'system_check', "System check performed - Status: {$overallStatus}");
            
            echo json_encode(['success' => true, 'status' => $overallStatus, 'checks' => $checks]);
            break;

        case 'mark_all_notifications_read':
            $totalAffected = 0;
            
            // Mark all security notifications as read (from audit_logs)
            $stmt = $conn->prepare("UPDATE audit_logs SET is_read = TRUE WHERE COALESCE(is_read, FALSE) = FALSE AND (action LIKE '%login_failed%' OR action LIKE '%suspicious%' OR action LIKE '%blocked%' OR action LIKE '%security%') AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            if ($stmt) {
                $stmt->execute();
                $totalAffected += $stmt->affected_rows;
                $stmt->close();
            }
            
            
            // Mark all audit log notifications as read (system errors and admin actions)
            $stmt = $conn->prepare("UPDATE audit_logs SET is_read = TRUE WHERE COALESCE(is_read, FALSE) = FALSE AND ((action LIKE '%error%' OR (action LIKE '%fail%' AND action NOT LIKE '%login_failed%')) OR action IN ('user_role_change', 'user_delete', 'maintenance_toggle', 'system_setting_change', 'admin_login', 'cashier_login')) AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            if ($stmt) {
                $stmt->execute();
                $totalAffected += $stmt->affected_rows;
                $stmt->close();
            }
            
            // Mark all support tickets as read (last 7 days)
            $tableCheckStmt = $conn->prepare("SHOW TABLES LIKE 'support_tickets'");
            $tableCheckStmt->execute();
            $tableCheck = $tableCheckStmt->get_result();
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $stmt = $conn->prepare("UPDATE support_tickets SET is_read = TRUE WHERE COALESCE(is_read, FALSE) = FALSE AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                if ($stmt) {
                    $stmt->execute();
                    $totalAffected += $stmt->affected_rows;
                    $stmt->close();
                }
            }
            
            // Mark all support messages as read (last 7 days)
            $tableCheckStmt = $conn->prepare("SHOW TABLES LIKE 'support_messages'");
            $tableCheckStmt->execute();
            $tableCheck = $tableCheckStmt->get_result();
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $stmt = $conn->prepare("UPDATE support_messages SET is_read = TRUE WHERE COALESCE(is_read, FALSE) = FALSE AND message_type = 'customer_support' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                if ($stmt) {
                    $stmt->execute();
                    $totalAffected += $stmt->affected_rows;
                    $stmt->close();
                }
            }
            
            echo json_encode(['success' => true, 'message' => "All notifications marked as read"]);
            break;

        case 'get_analytics_data':
            try {
                // Get user statistics with prepared statements
                $userStats = [];
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
                if ($stmt && $stmt->execute()) {
                    $result = $stmt->get_result();
                    $userStats['total_users'] = $result->fetch_assoc()['total'];
                    $stmt->close();
                } else {
                    $userStats['total_users'] = 0;
                }
                
                $stmt = $conn->prepare("SELECT COUNT(*) as active FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                if ($stmt && $stmt->execute()) {
                    $result = $stmt->get_result();
                    $userStats['active_users'] = $result->fetch_assoc()['active'];
                    $stmt->close();
                } else {
                    $userStats['active_users'] = 0;
                }
                
                // Get system statistics with prepared statements
                $systemStats = [];
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                if ($stmt && $stmt->execute()) {
                    $result = $stmt->get_result();
                    $systemStats['daily_activities'] = $result->fetch_assoc()['total'];
                    $stmt->close();
                } else {
                    $systemStats['daily_activities'] = 0;
                }
                
                // Get database size with prepared statement
                $stmt = $conn->prepare("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = DATABASE()");
                if ($stmt && $stmt->execute()) {
                    $result = $stmt->get_result();
                    $systemStats['database_size'] = $result->fetch_assoc()['size_mb'] . ' MB';
                    $stmt->close();
                } else {
                    $systemStats['database_size'] = '0 MB';
                }
            
                echo json_encode([
                    'success' => true, 
                    'user_stats' => $userStats,
                    'system_stats' => $systemStats
                ]);
            } catch (Exception $e) {
                error_log("Analytics data error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error loading analytics data']);
            }
            break;

        case 'get_console_errors':
            // Create console_errors table if it doesn't exist
            $createTableStmt = $conn->prepare("CREATE TABLE IF NOT EXISTS console_errors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                error_type VARCHAR(100) NOT NULL,
                error_message TEXT NOT NULL,
                url VARCHAR(500),
                user_agent TEXT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_created_at (created_at)
            )");
            $createTableStmt->execute();
            
            $limit = $_GET['limit'] ?? 50;
            $stmt = $conn->prepare("SELECT * FROM console_errors ORDER BY created_at DESC LIMIT ?");
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $errors = $result->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'errors' => $errors]);
            break;

        case 'log_console_error':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $errorType = $data['type'] ?? 'Unknown';
            $errorMessage = $data['message'] ?? '';
            $url = $data['url'] ?? '';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            
            // Create table if it doesn't exist
            $createTableStmt = $conn->prepare("CREATE TABLE IF NOT EXISTS console_errors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                error_type VARCHAR(100) NOT NULL,
                error_message TEXT NOT NULL,
                url VARCHAR(500),
                user_agent TEXT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_created_at (created_at)
            )");
            $createTableStmt->execute();
            
            $stmt = $conn->prepare("INSERT INTO console_errors (error_type, error_message, url, user_agent, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $errorType, $errorMessage, $url, $userAgent, $ipAddress);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Error logged successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to log error']);
            }
            break;

        case 'clear_console_errors':
            $deleteStmt = $conn->prepare("DELETE FROM console_errors");
            $deleteStmt->execute();
            echo json_encode(['success' => true, 'message' => 'Console errors cleared']);
            break;

        case 'reply_to_ticket':
            $data = json_decode(file_get_contents('php://input'), true);
            $ticket_id = $data['ticket_id'] ?? null;
            $reply_message = $data['reply_message'] ?? '';
            $new_status = $data['status'] ?? null;
            
            if (!$ticket_id || empty($reply_message)) {
                echo json_encode(['success' => false, 'message' => 'Ticket ID and reply message are required']);
                break;
            }
            
            // Check if ticket exists
            $stmt = $conn->prepare("SELECT id, user_id, subject FROM support_tickets WHERE id = ?");
            $stmt->bind_param('i', $ticket_id);
            $stmt->execute();
            $ticket = $stmt->get_result()->fetch_assoc();
            
            if (!$ticket) {
                echo json_encode(['success' => false, 'message' => 'Ticket not found']);
                break;
            }
            
            // Update ticket with admin response
            $admin_id = $_SESSION['user_id'] ?? 1;
            $admin_username = $_SESSION['username'] ?? 'Admin';
            
            $updateQuery = "UPDATE support_tickets SET admin_response = ?, admin_id = ?, admin_username = ?, updated_at = CURRENT_TIMESTAMP";
            $params = [$reply_message, $admin_id, $admin_username];
            $types = 'sis';
            
            if ($new_status) {
                $updateQuery .= ", status = ?";
                $params[] = $new_status;
                $types .= 's';
            }
            
            $updateQuery .= " WHERE id = ?";
            $params[] = $ticket_id;
            $types .= 'i';
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                // Log the action
                $description = "Replied to support ticket #$ticket_id";
                $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, 'ticket_reply', ?, ?, ?)");
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                $stmt->bind_param('isss', $admin_id, $description, $ip_address, $user_agent);
                $stmt->execute();
                
                echo json_encode(['success' => true, 'message' => 'Reply sent successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to send reply']);
            }
            break;

        case 'get_support_tickets':
            // Get filters from both GET and POST (JSON body)
            $input = json_decode(file_get_contents('php://input'), true);
            $search = $input['search'] ?? $_GET['search'] ?? '';
            $status = $input['status'] ?? $_GET['status'] ?? '';
            $priority = $input['priority'] ?? $_GET['priority'] ?? '';
            
            $query = "SELECT id, user_id, username, subject, message, priority, status, attachment_path, original_filename, created_at FROM support_tickets WHERE 1=1";
            $params = [];
            $types = '';
            
            if ($search && trim($search) !== '') {
                $query .= " AND (username LIKE ? OR subject LIKE ? OR message LIKE ?)";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
                $types .= 'sss';
            }
            
            if ($status && $status !== '' && $status !== 'all') {
                $query .= " AND status = ?";
                $params[] = $status;
                $types .= 's';
            }
            
            if ($priority && $priority !== '' && $priority !== 'all') {
                $query .= " AND priority = ?";
                $params[] = $priority;
                $types .= 's';
            }
            
            $query .= " ORDER BY created_at DESC LIMIT 100";
            
            $stmt = $conn->prepare($query);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $tickets = $result->fetch_all(MYSQLI_ASSOC);
            
            // Get statistics with prepared statements
            $stats = [];
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'open'");
            if ($stmt && $stmt->execute()) {
                $result = $stmt->get_result();
                $stats['open_tickets'] = $result->fetch_assoc()['total'];
                $stmt->close();
            } else {
                $stats['open_tickets'] = 0;
            }
            
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'pending'");
            if ($stmt && $stmt->execute()) {
                $result = $stmt->get_result();
                $stats['pending_tickets'] = $result->fetch_assoc()['total'];
                $stmt->close();
            } else {
                $stats['pending_tickets'] = 0;
            }
            
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'resolved' AND DATE(created_at) = CURDATE()");
            if ($stmt && $stmt->execute()) {
                $result = $stmt->get_result();
                $stats['resolved_today'] = $result->fetch_assoc()['total'];
                $stmt->close();
            } else {
                $stats['resolved_today'] = 0;
            }
            
            // Request statistics (if customer_requests table exists)
            $stmt = $conn->prepare("SHOW TABLES LIKE 'customer_requests'");
            if ($stmt && $stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $stmt->close();
                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM customer_requests WHERE status = 'pending' AND deleted = 0");
                    if ($stmt && $stmt->execute()) {
                        $result = $stmt->get_result();
                        $stats['pending_requests'] = $result->fetch_assoc()['total'];
                        $stmt->close();
                    } else {
                        $stats['pending_requests'] = 0;
                    }
                } else {
                    $stmt->close();
                    $stats['pending_requests'] = 0;
                }
            } else {
                $stats['pending_requests'] = 0;
            }
            
            // System statistics
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            if ($stmt && $stmt->execute()) {
                $result = $stmt->get_result();
                $stats['recent_activities'] = $result->fetch_assoc()['total'];
                $stmt->close();
            } else {
                $stats['recent_activities'] = 0;
            }
            
            // Skip developer notifications since table was deleted
            $stats['unread_notifications'] = 0;
            
            echo json_encode(['success' => true, 'tickets' => $tickets, 'stats' => $stats]);
            break;

        case 'get_ticket':
            $ticketId = $_GET['ticket_id'] ?? 0;
            
            if (!$ticketId) {
                echo json_encode(['success' => false, 'message' => 'Ticket ID is required']);
                break;
            }
            
            $stmt = $conn->prepare("
                SELECT st.id, st.user_id, st.username, st.subject, st.message, st.priority, st.status, 
                       st.attachment_path, st.original_filename, st.created_at, u.email as customer_email
                FROM support_tickets st 
                LEFT JOIN users u ON st.user_id = u.id 
                WHERE st.id = ?
            ");
            $stmt->bind_param("i", $ticketId);
            $stmt->execute();
            $result = $stmt->get_result();
            $ticket = $result->fetch_assoc();
            
            if ($ticket) {
                // Format the ticket data for display
                $ticket['customer_name'] = $ticket['username'];
                echo json_encode(['success' => true, 'ticket' => $ticket]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Ticket not found']);
            }
            break;

        case 'test_connection':
            echo json_encode(['success' => true, 'message' => 'API connection working', 'timestamp' => date('Y-m-d H:i:s')]);
            break;
            
        case 'save_user':
            error_log("Starting save_user action");
            
            // Check database connection first
            if (!$conn) {
                error_log("Database connection failed");
                echo json_encode(['success' => false, 'message' => 'Database connection failed']);
                break;
            }
            
            try {
                $rawInput = file_get_contents('php://input');
                error_log("Raw input: " . $rawInput);
                
                if (empty($rawInput)) {
                    error_log("Empty input received");
                    echo json_encode(['success' => false, 'message' => 'No data received']);
                    break;
                }
                
                $data = json_decode($rawInput, true);
                error_log("Decoded data: " . print_r($data, true));
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("JSON decode error: " . json_last_error_msg());
                    echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
                    break;
                }
                
                if (!$data) {
                    error_log("Invalid JSON data received");
                    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
                    break;
                }
                
                $userId = $data['user_id'] ?? null;
                $username = $data['username'] ?? '';
                $firstname = $data['firstname'] ?? '';
                $lastname = $data['lastname'] ?? '';
                $email = $data['email'] ?? '';
                $role = $data['role'] ?? 'user';
                $status = $data['status'] ?? 'active';
                $password = $data['password'] ?? '';
                
                // If firstname/lastname not provided, split username
                if (empty($firstname) && empty($lastname) && !empty($username)) {
                    $nameParts = explode(' ', $username, 2);
                    $firstname = $nameParts[0];
                    $lastname = $nameParts[1] ?? '';
                }
                
                error_log("Parsed values - userId: $userId, username: $username, firstname: $firstname, lastname: $lastname, email: $email, role: $role, status: $status");
                
                // Validate required fields
                if (empty($username) || empty($email) || empty($firstname)) {
                    error_log("Validation failed - missing required fields");
                    echo json_encode(['success' => false, 'message' => 'Username, firstname, and email are required']);
                    break;
                }
                
                error_log("Validation passed, proceeding to database operations");
                
            } catch (Exception $e) {
                error_log("Exception in save_user initial processing: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error processing request: ' . $e->getMessage()]);
                break;
            }
            
            try {
                // Check if email already exists (for new users or different user)
                $checkUserId = $userId ?? 0;
                $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $checkStmt->bind_param("si", $email, $checkUserId);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Email already exists']);
                    break;
                }
                
                if ($userId) {
                    // Update existing user
                    if (!empty($password)) {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $updateUserId = (int)$userId;
                        $stmt = $conn->prepare("UPDATE users SET username = ?, firstname = ?, lastname = ?, email = ?, role = ?, status = ?, password = ? WHERE id = ?");
                        $stmt->bind_param("sssssssi", $username, $firstname, $lastname, $email, $role, $status, $hashedPassword, $updateUserId);
                    } else {
                        $updateUserId = (int)$userId;
                        $stmt = $conn->prepare("UPDATE users SET username = ?, firstname = ?, lastname = ?, email = ?, role = ?, status = ? WHERE id = ?");
                        $stmt->bind_param("ssssssi", $username, $firstname, $lastname, $email, $role, $status, $updateUserId);
                    }
                    
                    if ($stmt->execute()) {
                        if (function_exists('logAuditEvent')) {
                            logAuditEvent($_SESSION['user_id'], 'user_update', "Updated user: $username");
                        }
                        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
                    } else {
                        error_log("Update failed: " . $conn->error);
                        echo json_encode(['success' => false, 'message' => 'Failed to update user: ' . $conn->error]);
                    }
                } else {
                    // Create new user
                    if (empty($password)) {
                        echo json_encode(['success' => false, 'message' => 'Password is required for new users']);
                        break;
                    }
                    
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, firstname, lastname, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("sssssss", $username, $firstname, $lastname, $email, $hashedPassword, $role, $status);
                    
                    if ($stmt->execute()) {
                        if (function_exists('logAuditEvent')) {
                            logAuditEvent($_SESSION['user_id'], 'user_create', "Created new user: $username");
                        }
                        echo json_encode(['success' => true, 'message' => 'User created successfully']);
                    } else {
                        error_log("Insert failed: " . $conn->error);
                        echo json_encode(['success' => false, 'message' => 'Failed to create user: ' . $conn->error]);
                    }
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'get_dashboard_stats':
            try {
                // Get comprehensive dashboard statistics
                $stats = [];
                
                // User statistics with prepared statement
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
                if ($stmt && $stmt->execute()) {
                    $result = $stmt->get_result();
                    $stats['total_users'] = $result->fetch_assoc()['total'];
                    $stmt->close();
                } else {
                    $stats['total_users'] = 0;
                }
                
                // Admin accounts count
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role IN ('admin', 'developer')");
                if ($stmt && $stmt->execute()) {
                    $result = $stmt->get_result();
                    $stats['total_admins'] = $result->fetch_assoc()['total'];
                    $stmt->close();
                } else {
                    $stats['total_admins'] = 0;
                }
                
                // Order statistics with prepared statement
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM sales WHERE DATE(created_at) = CURDATE()");
                if ($stmt && $stmt->execute()) {
                    $result = $stmt->get_result();
                    $stats['total_orders'] = $result->fetch_assoc()['total'];
                    $stmt->close();
                } else {
                    $stats['total_orders'] = 0;
                }
                
                // Revenue statistics with prepared statement
                $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(created_at) = CURDATE()");
                if ($stmt && $stmt->execute()) {
                    $result = $stmt->get_result();
                    $stats['total_revenue'] = number_format($result->fetch_assoc()['total'], 2);
                    $stmt->close();
                } else {
                    $stats['total_revenue'] = '0.00';
                }
                
                // Open customer support tickets count
                $stmt = $conn->prepare("SELECT COUNT(DISTINCT conversation_id) as total FROM support_tickets_messages WHERE archived = 0 OR archived IS NULL");
                if ($stmt && $stmt->execute()) {
                    $result = $stmt->get_result();
                    $stats['open_support'] = $result->fetch_assoc()['total'];
                    $stmt->close();
                } else {
                    $stats['open_support'] = 0;
                }
                
                // System health (placeholder)
                $stats['system_health'] = 'Good';
                
                echo json_encode(['success' => true, 'stats' => $stats]);
            } catch (Exception $e) {
                error_log("Dashboard stats error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error loading dashboard stats']);
            }
            break;

        case 'update_ticket_status':
            $data = json_decode(file_get_contents('php://input'), true);
            $conversationId = $data['conversation_id'] ?? null;
            $status = $data['status'] ?? null;
            
            if (!$conversationId || !$status) {
                echo json_encode(['success' => false, 'message' => 'Missing conversation ID or status']);
                break;
            }
            
            // Validate status values
            $validStatuses = ['open', 'pending', 'resolved', 'closed'];
            if (!in_array($status, $validStatuses)) {
                echo json_encode(['success' => false, 'message' => 'Invalid status value']);
                break;
            }
            
            try {
                // Extract ticket ID from conversation_id (format: ticket_X where X is the ticket ID)
                $ticketId = null;
                if (preg_match('/^ticket_(\d+)$/', $conversationId, $matches)) {
                    $ticketId = (int)$matches[1];
                }
                
                if (!$ticketId) {
                    echo json_encode(['success' => false, 'message' => 'Invalid conversation ID format']);
                    break;
                }
                
                // Debug: Log the extracted ticket ID
                error_log("Updating ticket status: conversation_id={$conversationId}, extracted_ticket_id={$ticketId}, new_status={$status}");
                
                // Update the ticket status in support_tickets table
                $stmt = $conn->prepare("UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $status, $ticketId);
                
                if ($stmt->execute()) {
                    $affectedRows = $stmt->affected_rows;
                    
                    if ($affectedRows > 0) {
                        // Log the status change
                        logAuditEvent($_SESSION['user_id'], 'ticket_status_update', "Ticket {$conversationId} (ID: {$ticketId}) status changed to {$status}");
                        
                        echo json_encode(['success' => true, 'message' => 'Ticket status updated successfully']);
                    } else {
                        // No rows affected - ticket doesn't exist, create it with proper fields
                        // First get conversation details
                        $detailStmt = $conn->prepare("SELECT user_name, user_email, subject, MIN(created_at) as created_at FROM support_tickets_messages WHERE conversation_id = ? LIMIT 1");
                        $detailStmt->bind_param("s", $conversationId);
                        $detailStmt->execute();
                        $details = $detailStmt->get_result()->fetch_assoc();
                        $detailStmt->close();
                        
                        if ($details) {
                            $createStmt = $conn->prepare("INSERT INTO support_tickets (id, username, email, subject, message, status, priority, created_at, updated_at) VALUES (?, ?, ?, ?, 'Auto-created from conversation', ?, 'medium', ?, NOW()) ON DUPLICATE KEY UPDATE status = ?, updated_at = NOW()");
                            $createStmt->bind_param("isssss", $ticketId, $details['user_name'], $details['user_email'], $details['subject'], $status, $details['created_at'], $status);
                        } else {
                            $createStmt = $conn->prepare("INSERT INTO support_tickets (id, status, created_at, updated_at) VALUES (?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE status = ?, updated_at = NOW()");
                            $createStmt->bind_param("iss", $ticketId, $status, $status);
                        }
                        
                        if ($createStmt->execute()) {
                            logAuditEvent($_SESSION['user_id'], 'ticket_status_update', "Ticket {$conversationId} (ID: {$ticketId}) status set to {$status} (created new record)");
                            echo json_encode(['success' => true, 'message' => 'Ticket status updated successfully']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to create ticket record']);
                        }
                        $createStmt->close();
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update ticket status']);
                }
                $stmt->close();
            } catch (Exception $e) {
                error_log("Error updating ticket status: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error occurred']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
            break;
    }
    
} catch (Exception $e) {
    error_log("Super admin action error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Server error occurred: ' . $e->getMessage()]);
} catch (Error $e) {
    error_log("Fatal error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $e->getMessage()]);
}

?>
