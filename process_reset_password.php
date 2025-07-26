<?php
session_start(); // Start session for CSRF token

// Check CSRF token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    // Invalid CSRF token
    echo "<p>Invalid request. Please try again.</p>";
    exit;
}

// Get token from POST
$token = $_POST["token"] ?? null;
if (!$token) {
    echo "<p>Invalid or expired token.</p>";
    exit;
}
$token_hash = hash("sha256", $token);

// Connect to database
$conn = require __DIR__ . "/config.php";

// Look up user by reset token hash
$sql = "SELECT * FROM users WHERE reset_token_hash = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Check if user exists and token is not expired
if ($user === null || strtotime($user["reset_token_expires_at"]) <= time()) {
    echo "<p>Invalid or expired token.</p>";
    exit;
}

// Validate password length
if (strlen($_POST["password"]) < 8) {
    echo "<p>Password must be at least 8 characters.</p>";
    exit;
}
// Validate password contains at least one letter
if (!preg_match("/[a-z]/i", $_POST["password"])) {
    echo "<p>Password must contain at least one letter.</p>";
    exit;
}
// Validate password contains at least one number
if (!preg_match("/[0-9]/", $_POST["password"])) {
    echo "<p>Password must contain at least one number.</p>";
    exit;
}
// Check password confirmation
if ($_POST["password"] !== $_POST["password_confirmation"]) {
    echo "<p>Passwords must match.</p>";
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

// Show user-friendly success message
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Reset Successful</title>
</head>
<body>
    <h1>Password Reset</h1>
    <p>Your password has been reset successfully! You may now <a href="index.php">log in</a> with your new password.</p>
</body>
</html>