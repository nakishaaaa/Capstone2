<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/audit_helper.php';

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $_SESSION['verification_error'] = 'Invalid verification link.';
    header("Location: index.php");
    exit();
}

$token = $_GET['token'];

try {
    // Look up user by verification token
    $stmt = $conn->prepare("SELECT id, username, email, is_email_verified FROM users WHERE email_verification_token = ? AND is_email_verified = FALSE");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['verification_error'] = 'Invalid or expired verification link.';
        header("Location: index.php");
        exit();
    }
    
    $user = $result->fetch_assoc();
    
    // Update user as verified
    $updateStmt = $conn->prepare("UPDATE users SET is_email_verified = TRUE, email_verified_at = NOW(), email_verification_token = NULL WHERE id = ?");
    $updateStmt->bind_param("i", $user['id']);
    
    if ($updateStmt->execute()) {
        // Log email verification event
        logAuditEvent($user['id'], 'email_verified', "Email verified for user: " . $user['username']);
        
        $_SESSION['verification_success'] = 'Email verified successfully! You can now log in.';
        $_SESSION['active_form'] = 'login';
    } else {
        $_SESSION['verification_error'] = 'Verification failed. Please try again.';
    }
    
} catch (Exception $e) {
    error_log("Email verification error: " . $e->getMessage());
    $_SESSION['verification_error'] = 'An error occurred during verification. Please try again.';
}

header("Location: index.php");
exit();
?>
