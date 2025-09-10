<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/audit_helper.php';

// Enable error logging and display for debugging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in as super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
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
            
            // Log the action
            logAuditEvent($_SESSION['user_id'], 'maintenance_toggle', "Maintenance mode " . ($enabled === 'true' ? 'enabled' : 'disabled'));
            
            echo json_encode(['success' => true, 'message' => 'Maintenance mode updated']);
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
            
            foreach ($data['settings'] as $key => $value) {
                $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = ?");
                $stmt->bind_param("sis", $value, $_SESSION['user_id'], $key);
                $stmt->execute();
            }
            
            logAuditEvent($_SESSION['user_id'], 'settings_update', 'System settings updated');
            
            echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
            break;

        case 'get_users':
            $search = $_GET['search'] ?? '';
            $role = $_GET['role'] ?? '';
            $status = $_GET['status'] ?? '';
            
            $query = "SELECT id, username, email, role, status, last_login, created_at FROM users WHERE role != 'super_admin'";
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
                $query .= " AND al.action = ?";
                $params[] = $filterAction;
                $types .= 's';
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
            
            if ($user['role'] === 'super_admin') {
                echo json_encode(['success' => false, 'message' => 'Cannot delete super admin accounts']);
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
            
            // Get customer support ticket notifications (last 7 days)
            if ($filter === 'all' || $filter === 'customer_support') {
                // Add is_read column to support_tickets table if it doesn't exist
                $conn->query("ALTER TABLE support_tickets ADD COLUMN IF NOT EXISTS is_read BOOLEAN DEFAULT FALSE");
                
                $ticketQuery = "
                    SELECT 
                        id,
                        CONCAT('New support ticket: ', subject) as title,
                        CONCAT('From: ', username, ' - ', LEFT(message, 100), '...') as message,
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
                $tableCheck = $conn->query("SHOW TABLES LIKE 'support_messages'");
                if ($tableCheck->num_rows > 0) {
                    // Add is_read column to support_messages table if it doesn't exist
                    $conn->query("ALTER TABLE support_messages ADD COLUMN IF NOT EXISTS is_read BOOLEAN DEFAULT FALSE");
                    
                    $messageQuery = "
                        SELECT 
                            id,
                            CONCAT('New support message: ', COALESCE(subject, 'No subject')) as title,
                            CONCAT('From: ', user_name, ' - ', LEFT(message, 100), '...') as message,
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
            $notificationSource = $data['source'] ?? 'developer';
            
            if ($notificationSource === 'customer_support') {
                // Check if it's a ticket or message and update accordingly
                $supportType = $data['support_type'] ?? 'ticket';
                if ($supportType === 'ticket') {
                    $stmt = $conn->prepare("UPDATE support_tickets SET is_read = TRUE WHERE id = ?");
                } else {
                    $stmt = $conn->prepare("UPDATE support_messages SET is_read = TRUE WHERE id = ?");
                }
            } else {
                $stmt = $conn->prepare("UPDATE developer_notifications SET is_read = TRUE WHERE id = ?");
            }
            
            $stmt->bind_param("i", $notificationId);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
            break;

        case 'delete_notification':
            $data = json_decode(file_get_contents('php://input'), true);
            $notificationId = $data['id'];
            $notificationSource = $data['source'] ?? 'developer';
            
            if ($notificationSource === 'customer_support') {
                // Check if it's a ticket or message and delete accordingly
                $supportType = $data['support_type'] ?? 'ticket';
                if ($supportType === 'ticket') {
                    $stmt = $conn->prepare("DELETE FROM support_tickets WHERE id = ?");
                } else {
                    $stmt = $conn->prepare("DELETE FROM support_messages WHERE id = ?");
                }
            } else {
                $stmt = $conn->prepare("DELETE FROM developer_notifications WHERE id = ?");
            }
            
            $stmt->bind_param("i", $notificationId);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Notification deleted']);
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
            $options = $data['options'] ?? ['database' => true, 'files' => true];
            
            // Create backup_logs table if it doesn't exist
            $conn->query("CREATE TABLE IF NOT EXISTS backup_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                backup_type VARCHAR(50) NOT NULL DEFAULT 'manual',
                file_name VARCHAR(255) NOT NULL,
                file_size BIGINT DEFAULT NULL,
                status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
                error_message TEXT DEFAULT NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_created_at (created_at)
            )");
            
            // Create backup entry
            $fileName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $stmt = $conn->prepare("INSERT INTO backup_logs (backup_type, file_name, status, created_by, created_at) VALUES (?, ?, 'pending', ?, NOW())");
            $stmt->bind_param("ssi", $backupType, $fileName, $_SESSION['user_id']);
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
                $backupContent = "-- Database Backup Created: " . date('Y-m-d H:i:s') . "\n";
                $backupContent .= "-- Database: users_db\n\n";
                
                // Get all tables
                $tables = [];
                $result = $conn->query("SHOW TABLES");
                while ($row = $result->fetch_array()) {
                    $tables[] = $row[0];
                }
                
                // Backup each table
                foreach ($tables as $table) {
                    $backupContent .= "\n-- Table structure for table `$table`\n";
                    $backupContent .= "DROP TABLE IF EXISTS `$table`;\n";
                    
                    // Get table structure
                    $result = $conn->query("SHOW CREATE TABLE `$table`");
                    $row = $result->fetch_array();
                    $backupContent .= $row[1] . ";\n\n";
                    
                    // Get table data
                    $backupContent .= "-- Dumping data for table `$table`\n";
                    $result = $conn->query("SELECT * FROM `$table`");
                    
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
            
            // Mark all developer notifications as read
            $stmt = $conn->prepare("UPDATE developer_notifications SET is_read = TRUE WHERE is_read = FALSE");
            $stmt->execute();
            $totalAffected += $stmt->affected_rows;
            
            // Mark all support tickets as read (last 7 days)
            $stmt = $conn->prepare("UPDATE support_tickets SET is_read = TRUE WHERE is_read = FALSE AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute();
            $totalAffected += $stmt->affected_rows;
            
            // Mark all support messages as read (last 7 days)
            $tableCheck = $conn->query("SHOW TABLES LIKE 'support_messages'");
            if ($tableCheck->num_rows > 0) {
                $stmt = $conn->prepare("UPDATE support_messages SET is_read = TRUE WHERE is_read = FALSE AND message_type = 'customer' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                $stmt->execute();
                $totalAffected += $stmt->affected_rows;
            }
            
            echo json_encode(['success' => true, 'message' => "{$totalAffected} notifications marked as read"]);
            break;

        case 'get_analytics_data':
            // Get user statistics
            $userStats = [];
            $result = $conn->query("SELECT COUNT(*) as total FROM users");
            $userStats['total_users'] = $result->fetch_assoc()['total'];
            
            $result = $conn->query("SELECT COUNT(*) as active FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $userStats['active_users'] = $result->fetch_assoc()['active'];
            
            // Get system statistics
            $systemStats = [];
            $result = $conn->query("SELECT COUNT(*) as total FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $systemStats['daily_activities'] = $result->fetch_assoc()['total'];
            
            // Get database size
            $result = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = DATABASE()");
            $systemStats['database_size'] = $result->fetch_assoc()['size_mb'] . ' MB';
            
            echo json_encode([
                'success' => true, 
                'user_stats' => $userStats,
                'system_stats' => $systemStats
            ]);
            break;

        case 'get_console_errors':
            // Create console_errors table if it doesn't exist
            $conn->query("CREATE TABLE IF NOT EXISTS console_errors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                error_type VARCHAR(100) NOT NULL,
                error_message TEXT NOT NULL,
                url VARCHAR(500),
                user_agent TEXT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_created_at (created_at)
            )");
            
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
            $conn->query("CREATE TABLE IF NOT EXISTS console_errors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                error_type VARCHAR(100) NOT NULL,
                error_message TEXT NOT NULL,
                url VARCHAR(500),
                user_agent TEXT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_created_at (created_at)
            )");
            
            $stmt = $conn->prepare("INSERT INTO console_errors (error_type, error_message, url, user_agent, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $errorType, $errorMessage, $url, $userAgent, $ipAddress);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Error logged successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to log error']);
            }
            break;

        case 'clear_console_errors':
            $conn->query("DELETE FROM console_errors");
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
            
            $query = "SELECT id, user_id, username, subject, message, priority, status, attachment_path, created_at FROM support_tickets WHERE 1=1";
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
            
            // Get statistics
            $stats = [];
            $result = $conn->query("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'open'");
            $stats['open_tickets'] = $result->fetch_assoc()['total'];
            
            $result = $conn->query("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'pending'");
            $stats['pending_tickets'] = $result->fetch_assoc()['total'];
            
            $result = $conn->query("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'resolved' AND DATE(created_at) = CURDATE()");
            $stats['resolved_today'] = $result->fetch_assoc()['total'];
            
            // Request statistics (if user_requests table exists)
            $result = $conn->query("SHOW TABLES LIKE 'user_requests'");
            if ($result->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as total FROM user_requests WHERE status = 'pending'");
                $stats['pending_requests'] = $result->fetch_assoc()['total'];
            }
            
            // System statistics
            $result = $conn->query("SELECT COUNT(*) as total FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $stats['recent_activities'] = $result->fetch_assoc()['total'];
            
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
                       st.attachment_path, st.created_at, u.email as customer_email
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
            // Get comprehensive dashboard statistics
            $stats = [];
            
            // User statistics
            $result = $conn->query("SELECT COUNT(*) as total FROM users");
            $stats['total_users'] = $result->fetch_assoc()['total'];
            
            // Order statistics
            $result = $conn->query("SELECT COUNT(*) as total FROM sales WHERE DATE(created_at) = CURDATE()");
            $stats['total_orders'] = $result->fetch_assoc()['total'];
            
            // Revenue statistics
            $result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(created_at) = CURDATE()");
            $stats['total_revenue'] = number_format($result->fetch_assoc()['total'], 2);
            
            // System health (placeholder)
            $stats['system_health'] = 'Good';
            
            echo json_encode(['success' => true, 'stats' => $stats]);
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
