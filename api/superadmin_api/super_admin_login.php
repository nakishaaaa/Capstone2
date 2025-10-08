<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../devlog.php');
    exit();
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Validate input
if (empty($username) || empty($password)) {
    $_SESSION['super_admin_error'] = 'Please enter both username and access key.';
    header('Location: ../../devlog.php');
    exit();
}

try {
    // Check for developer user
    $stmt = $conn->prepare("SELECT id, username, password, role, deleted_at FROM users WHERE username = ? AND role = 'developer'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Check if account is soft deleted (deactivated)
            if (isset($user['deleted_at']) && $user['deleted_at'] !== null) {
                $_SESSION['super_admin_error'] = 'Your account has been deactivated. Please contact the system administrator.';
                header('Location: ../../devlog.php');
                exit();
            }
            
            // Log successful login using audit helper
            logLoginEvent($user['id'], $user['username'], 'developer');
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Store session for SSE real-time updates
            require_once '../../includes/session_manager.php';
            storeUserSession($user['id'], $user['role'], 24); // Store for 24 hours
            error_log("Developer Login: Stored SSE session for user " . $user['id'] . " with role " . $user['role']);
            
            // Update last login
            $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $user['id']);
            $updateStmt->execute();
            
            header('Location: ../../super_admin_dashboard.php');
            exit();
        }
    }
    
    // Log failed login attempt using audit helper
    logFailedLoginEvent($username, 'Invalid developer credentials');
    
    $_SESSION['super_admin_error'] = 'Invalid credentials. Access denied.';
    header('Location: ../../devlog.php');
    exit();
    
} catch (Exception $e) {
    error_log("Super admin login error: " . $e->getMessage());
    $_SESSION['super_admin_error'] = 'System error occurred. Please try again.';
    header('Location: ../../devlog.php');
    exit();
}

?>
