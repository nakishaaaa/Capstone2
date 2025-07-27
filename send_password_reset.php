<?php

$email = $_POST["email"];
$token = bin2hex(random_bytes(16));
$token_hash = hash("sha256", $token);
$expiry = date("Y-m-d H:i:s", time() + 60 * 30);

require_once __DIR__ . "/config.php";

$sql = "UPDATE users
        SET reset_token_hash = ?,
            reset_token_expires_at = ?
        WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $token_hash, $expiry, $email);
$stmt->execute();

if ($conn->affected_rows) {

    $mail = require __DIR__ . "/mailer.php";

    $mail->setFrom("noreply@example.com");
    $mail->addAddress($email);
    $mail->Subject  = "Password Reset";
    $mail->Body = <<<END

    Click <a href="localhost/Capstone2/reset_password.php?token=$token">here</a>
    to reset your password.

    END;
    
    
    try {
        $mail->send();
        // Redirect with success message
        header("Location: forgot_password.php?status=success&message=Password reset link has been sent to your email.");
        exit();
    } 
    catch (Exception $e) {
        // Redirect with error message
        header("Location: forgot_password.php?status=error&message=Failed to send email. Please try again.");
        exit();
    }
} else {
    // Redirect with invalid email message
    header("Location: forgot_password.php?status=error&message=Email not found. Please check your email address.");
    exit();
}