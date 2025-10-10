<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/audit_helper.php';
require_once '../../includes/developer_login_auth.php';

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
    $stmt = $conn->prepare("SELECT id, username, password, role, email, deleted_at FROM users WHERE username = ? AND role = 'developer'");
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
            
            // Send OTP verification email instead of direct login
            $otpCode = DeveloperLoginAuth::sendLoginCode($user);
            
            if ($otpCode) {
                // Log OTP sent event
                logLoginEvent($user['id'], $user['username'], 'developer', 'OTP_SENT');
                
                // Redirect to OTP verification page
                header('Location: ../../dev_otp_verify.php');
                exit();
            } else {
                $_SESSION['super_admin_error'] = 'Failed to send verification email. Please try again.';
                header('Location: ../../devlog.php');
                exit();
            }
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
