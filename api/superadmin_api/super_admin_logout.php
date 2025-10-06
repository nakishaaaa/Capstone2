<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/audit_helper.php';

// Check if user is logged in as developer
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'developer') {
    // Log the logout using audit helper
    logLogoutEvent($_SESSION['user_id'], $_SESSION['username'], 'developer');
}

// Destroy session
session_destroy();

// Redirect to super admin login page
header('Location: ../../devlog.php');
exit();
?>
