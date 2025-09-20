<?php

session_start();
require_once 'includes/csrf.php'; // Include CSRF protection

// Validate CSRF token first
if (!CSRFToken::validate($_POST['csrf_token'] ?? '')) {
    $_SESSION['forgot_error'] = 'Invalid security token. Please try again.';
    header("Location: index.php?form=forgot");
    exit();
}

$email = $_POST["email"];
$token = bin2hex(random_bytes(16));
$token_hash = hash("sha256", $token);
$expiry = date("Y-m-d H:i:s", time() + 60 * 30);

require_once __DIR__ . "/includes/config.php";

$sql = "UPDATE users
        SET reset_token_hash = ?,
            reset_token_expires_at = ?
        WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $token_hash, $expiry, $email);
$stmt->execute();

if ($conn->affected_rows) {

    $mail = require __DIR__ . "/includes/mailer.php";

    $mail->setFrom("noreply@example.com");
    $mail->addAddress($email);
    $mail->Subject  = "Password Reset";
    $mail->Body = <<<END

    Click <a href="localhost/Capstone2/reset_password.php?token=$token">here</a>
    to reset your password.

    END;
    
    
    try {
        $mail->send();
        // Store success in session and redirect
        $_SESSION['forgot_success'] = 'Password reset link has been sent to your email.';
        header("Location: index.php?form=forgot");
        exit();
    } 
    catch (Exception $e) {
        // Store error in session and redirect
        session_start();
        $_SESSION['forgot_error'] = 'Failed to send email. Please try again.';
        header("Location: index.php?form=forgot");
        exit();
    }
} else {
    // Store error in session and redirect
    $_SESSION['forgot_error'] = 'Email not found. Please check your email address.';
    header("Location: index.php?form=forgot");
    exit();
}