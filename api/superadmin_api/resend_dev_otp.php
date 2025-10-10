<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/developer_login_auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    // Check if there's a pending verification session
    if (!DeveloperLoginAuth::isVerificationPending()) {
        echo json_encode(['success' => false, 'message' => 'No verification session found. Please try logging in again.']);
        exit();
    }
    
    // Get user data from existing verification session
    $verification = $_SESSION['dev_login_verification'];
    $user = $verification['user_data'];
    
    // Send new verification code
    $newCode = DeveloperLoginAuth::sendLoginCode($user);
    
    if ($newCode) {
        echo json_encode([
            'success' => true, 
            'message' => 'New verification code sent to your email address.'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send verification code. Please try again.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Developer OTP resend error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'System error occurred. Please try again.'
    ]);
}
?>
