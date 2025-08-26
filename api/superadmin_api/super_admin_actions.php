<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/audit_helper.php';

// Disable error display to prevent HTML in JSON responses
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Check if user is logged in as super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

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
            
            $query = "SELECT id, username, email, role, status, last_login, created_at FROM users WHERE 1=1";
            $params = [];
            $types = '';
            
            if ($search) {
                $query .= " AND (username LIKE ? OR email LIKE ?)";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $types .= 'ss';
            }
            
            if ($role) {
                $query .= " AND role = ?";
                $params[] = $role;
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
                SELECT al.*, u.username 
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
            
            $query = "SELECT * FROM developer_notifications WHERE 1=1";
            $params = [];
            $types = '';
            
            if ($filter !== 'all') {
                $query .= " AND type = ?";
                $params[] = $filter;
                $types .= 's';
            }
            
            $query .= " ORDER BY created_at DESC LIMIT 50";
            
            $stmt = $conn->prepare($query);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'notifications' => $notifications]);
            break;

        case 'mark_notification_read':
            $data = json_decode(file_get_contents('php://input'), true);
            $notificationId = $data['id'];
            
            $stmt = $conn->prepare("UPDATE developer_notifications SET is_read = TRUE WHERE id = ?");
            $stmt->bind_param("i", $notificationId);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
            break;

        case 'delete_notification':
            $data = json_decode(file_get_contents('php://input'), true);
            $notificationId = $data['id'];
            
            $stmt = $conn->prepare("DELETE FROM developer_notifications WHERE id = ?");
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
            
            // Create backup entry
            $fileName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $stmt = $conn->prepare("INSERT INTO backup_logs (backup_type, file_name, status, created_by, created_at) VALUES (?, ?, 'pending', ?, NOW())");
            $stmt->bind_param("ssi", $backupType, $fileName, $_SESSION['user_id']);
            $stmt->execute();
            $backupId = $conn->insert_id;
            
            // Start backup process (this would typically be done in background)
            $backupPath = '../backups/' . $fileName;
            
            // Create backups directory if it doesn't exist
            if (!is_dir('../backups/')) {
                mkdir('../backups/', 0755, true);
            }
            
            // Simple database backup
            $command = "mysqldump --user={$db_username} --password={$db_password} --host={$db_host} {$db_name} > {$backupPath}";
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0 && file_exists($backupPath)) {
                $fileSize = filesize($backupPath);
                $stmt = $conn->prepare("UPDATE backup_logs SET status = 'completed', file_size = ?, completed_at = NOW() WHERE id = ?");
                $stmt->bind_param("ii", $fileSize, $backupId);
                $stmt->execute();
                
                logAuditEvent($_SESSION['user_id'], 'backup_created', "Manual backup created: $fileName");
                
                echo json_encode(['success' => true, 'message' => 'Backup created successfully', 'file' => $fileName]);
            } else {
                $stmt = $conn->prepare("UPDATE backup_logs SET status = 'failed', error_message = ? WHERE id = ?");
                $errorMsg = 'Backup command failed';
                $stmt->bind_param("si", $errorMsg, $backupId);
                $stmt->execute();
                
                echo json_encode(['success' => false, 'message' => 'Backup failed']);
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
            $stmt = $conn->prepare("UPDATE developer_notifications SET is_read = TRUE WHERE is_read = FALSE");
            $stmt->execute();
            $affectedRows = $stmt->affected_rows;
            
            echo json_encode(['success' => true, 'message' => "{$affectedRows} notifications marked as read"]);
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

        case 'get_dashboard_stats':
            // Get comprehensive dashboard statistics
            $stats = [];
            
            // User statistics
            $result = $conn->query("SELECT COUNT(*) as total FROM users");
            $stats['total_users'] = $result->fetch_assoc()['total'];
            
            $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role IN ('admin', 'super_admin')");
            $stats['total_admins'] = $result->fetch_assoc()['total'];
            
            $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $stats['active_users_24h'] = $result->fetch_assoc()['total'];
            
            // Sales statistics (if sales table exists)
            $result = $conn->query("SHOW TABLES LIKE 'sales'");
            if ($result->num_rows > 0) {
                $result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(created_at) = CURDATE()");
                $stats['sales_today'] = $result->fetch_assoc()['total'];
                
                $result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE WEEK(created_at) = WEEK(NOW())");
                $stats['sales_week'] = $result->fetch_assoc()['total'];
            }
            
            // Request statistics (if user_requests table exists)
            $result = $conn->query("SHOW TABLES LIKE 'user_requests'");
            if ($result->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as total FROM user_requests WHERE status = 'pending'");
                $stats['pending_requests'] = $result->fetch_assoc()['total'];
            }
            
            // System statistics
            $result = $conn->query("SELECT COUNT(*) as total FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $stats['recent_activities'] = $result->fetch_assoc()['total'];
            
            $result = $conn->query("SELECT COUNT(*) as total FROM developer_notifications WHERE is_read = FALSE");
            $stats['unread_notifications'] = $result->fetch_assoc()['total'];
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Super admin action error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

?>
