<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/developer_login_auth.php';
require_once '../../includes/audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../dev_otp_verify.php');
    exit();
}

$otpCode = $_POST['otp_code'] ?? '';

// Validate input
if (empty($otpCode) || strlen($otpCode) !== 6 || !ctype_digit($otpCode)) {
    $_SESSION['otp_error'] = 'Please enter a valid 6-digit verification code.';
    header('Location: ../../dev_otp_verify.php');
    exit();
}

try {
    // Verify the OTP code and complete login
    $result = DeveloperLoginAuth::verifyCodeAndLogin($otpCode);
    
    if ($result['success']) {
        $user = $result['user'];
        
        // Log successful login using audit helper
        logLoginEvent($user['id'], $user['username'], 'developer');
        
        // Store session for SSE real-time updates
        require_once '../../includes/session_manager.php';
        storeUserSession($user['id'], $user['role'], 24); // Store for 24 hours
        error_log("Developer Login: Stored SSE session for user " . $user['id'] . " with role " . $user['role']);
        
        // Update last login
        $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->bind_param("i", $user['id']);
        $updateStmt->execute();
        
        $_SESSION['otp_success'] = 'Login successful! Redirecting...';
        
        // Redirect to dashboard after a brief delay
        header('Location: ../../super_admin_dashboard.php');
        exit();
        
    } else {
        // Log failed verification attempt
        $username = $_SESSION['dev_login_verification']['user_data']['username'] ?? 'Unknown';
        logFailedLoginEvent($username, 'Invalid OTP code: ' . $result['message']);
        
        $_SESSION['otp_error'] = $result['message'];
        header('Location: ../../dev_otp_verify.php');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Developer OTP verification error: " . $e->getMessage());
    $_SESSION['otp_error'] = 'System error occurred. Please try again.';
    header('Location: ../../dev_otp_verify.php');
    exit();
}
?>
