<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

try {
    // Verify this is an admin session before logout
    if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin') {
        // Log the logout action (optional - for audit trail)
        error_log("Admin logout: " . ($_SESSION['admin_name'] ?? 'Unknown') . " at " . date('Y-m-d H:i:s'));
    }
    
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session cookie if it exists
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], 
            $params["domain"],
            $params["secure"], 
            $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
} catch (Exception $e) {
    // Log error but still redirect
    error_log("Logout error: " . $e->getMessage());
}

// Redirect to login page
header("Location: ../index.php");
exit();
?>
