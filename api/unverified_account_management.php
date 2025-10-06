<?php
// Set content type to JSON and disable HTML error output
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

session_start();
require_once '../config/database.php';
require_once '../includes/csrf.php';
require_once '../includes/unverified_account_cleanup.php';

// CSRF validation helper
function isValidCsrf($token) {
    if (function_exists('verifyCsrfToken')) {
        return verifyCsrfToken($token);
    }
    if (function_exists('validateCSRFToken')) {
        return validateCSRFToken($token);
    }
    if (class_exists('CSRFToken')) {
        return CSRFToken::validate($token);
    }
    return false;
}

// Check if user is admin or super admin
$is_authorized = false;
$user_role = $_SESSION['role'] ?? $_SESSION['admin_role'] ?? null;

if ($user_role === 'admin' || $user_role === 'super_admin' || $user_role === 'developer') {
    $is_authorized = true;
}

if (!$is_authorized) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin privileges required.']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Verify CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isValidCsrf($_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
}

// Get database connection
try {
    $pdo = Database::getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Initialize cleanup service
$cleanup_hours = $_GET['cleanup_hours'] ?? 24; // Default 24 hours
$cleanup = new UnverifiedAccountCleanup($pdo, $cleanup_hours);

switch ($action) {
    case 'get_stats':
        getUnverifiedStats($cleanup);
        break;
    case 'get_accounts':
        getUnverifiedAccounts($cleanup);
        break;
    case 'cleanup_expired':
        cleanupExpiredAccounts($cleanup);
        break;
    case 'delete_account':
        deleteSpecificAccount($cleanup);
        break;
    case 'send_reminders':
        sendReminderEmails($cleanup);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

/**
 * Get statistics about unverified accounts
 */
function getUnverifiedStats($cleanup) {
    try {
        $stats = $cleanup->getUnverifiedAccountStats();
        echo json_encode($stats);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get stats: ' . $e->getMessage()]);
    }
}

/**
 * Get list of unverified accounts
 */
function getUnverifiedAccounts($cleanup) {
    try {
        $expired_only = ($_GET['expired_only'] ?? 'false') === 'true';
        $result = $cleanup->getUnverifiedAccountsList($expired_only);
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get accounts: ' . $e->getMessage()]);
    }
}

/**
 * Clean up expired unverified accounts
 */
function cleanupExpiredAccounts($cleanup) {
    try {
        $result = $cleanup->cleanupExpiredAccounts();
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to cleanup accounts: ' . $e->getMessage()]);
    }
}

/**
 * Delete a specific unverified account
 */
function deleteSpecificAccount($cleanup) {
    try {
        $user_id = $_POST['user_id'] ?? '';
        
        if (empty($user_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
            return;
        }
        
        $result = $cleanup->deleteUnverifiedAccount($user_id);
        
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode($result);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete account: ' . $e->getMessage()]);
    }
}

/**
 * Send reminder emails to accounts about to expire
 */
function sendReminderEmails($cleanup) {
    try {
        $reminder_hours = $_POST['reminder_hours'] ?? 2;
        $result = $cleanup->sendReminderEmails($reminder_hours);
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to send reminders: ' . $e->getMessage()]);
    }
}

?>
