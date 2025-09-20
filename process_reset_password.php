<?php
session_start(); // Start session for CSRF token

// Check CSRF token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    // Invalid CSRF token
    header("Location: reset_password.php?token=" . urlencode($_POST['token'] ?? '') . "&status=error&message=" . urlencode("Invalid request. Please try again."));
    exit;
}

// Get token from POST
$token = $_POST["token"] ?? null;
if (!$token) {
    header("Location: index.php?status=error&message=" . urlencode("Invalid or expired token."));
    exit;
}
$token_hash = hash("sha256", $token);

// Connect to database
$conn = require __DIR__ . "/includes/config.php";

// Look up user by reset token hash
$sql = "SELECT * FROM users WHERE reset_token_hash = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Check if user exists and token is not expired
if ($user === null || strtotime($user["reset_token_expires_at"]) <= time()) {
    header("Location: index.php?status=error&message=" . urlencode("Invalid or expired token."));
    exit;
}

// Validate password length
if (strlen($_POST["password"]) < 8) {
    header("Location: reset_password.php?token=" . urlencode($token) . "&status=error&message=" . urlencode("Password must be at least 8 characters."));
    exit;
}
// Validate password contains at least one letter
if (!preg_match("/[a-z]/i", $_POST["password"])) {
    header("Location: reset_password.php?token=" . urlencode($token) . "&status=error&message=" . urlencode("Password must contain at least one letter."));
    exit;
}
// Validate password contains at least one number
if (!preg_match("/[0-9]/", $_POST["password"])) {
    header("Location: reset_password.php?token=" . urlencode($token) . "&status=error&message=" . urlencode("Password must contain at least one number."));
    exit;
}
// Check password confirmation
if ($_POST["password"] !== $_POST["password_confirmation"]) {
    header("Location: reset_password.php?token=" . urlencode($token) . "&status=error&message=" . urlencode("Passwords must match."));
    exit;
}

// Hash the new password
$password_hash = password_hash($_POST["password"], PASSWORD_DEFAULT);

// Update user's password and clear reset token fields
$sql = "UPDATE users
        SET password = ?,
            reset_token_hash = NULL,
            reset_token_expires_at = NULL
        WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $password_hash, $user["id"]);
$stmt->execute();

// Unset CSRF token after use
unset($_SESSION['csrf_token']);

// Redirect to login page with success message
header("Location: index.php?status=success&message=" . urlencode("Your password has been reset successfully! You may now log in with your new password."));
exit;