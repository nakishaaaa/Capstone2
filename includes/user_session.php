<?php
/**
 * User Session Management
 * Handles user authentication and session validation for user dashboard
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize user session variables
$isUserLoggedIn = false;
$userName = '';
$userEmail = '';

// Validate user session
if (isset($_SESSION['user_name']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'user') {
    $isUserLoggedIn = true;
    $userName = $_SESSION['user_name'];
    $userEmail = $_SESSION['user_email'] ?? '';
}

// Redirect if not logged in
if (!$isUserLoggedIn) {
    header("Location: index.php");
    exit();
}

/**
 * Get sanitized user data for display
 * @param string $data The data to sanitize
 * @return string Sanitized data
 */
function getSafeUserData($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}
?>
