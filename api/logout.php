<?php
session_start();
require_once '../includes/session_helper.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$role = $input['role'] ?? null;

try {
    if ($role && in_array($role, ['admin', 'user'])) {
        // Clear role-specific session variables
        unset($_SESSION[$role . '_user_id']);
        unset($_SESSION[$role . '_name']);
        unset($_SESSION[$role . '_email']);
        unset($_SESSION[$role . '_role']);
        
        // Check if other role is still logged in
        $otherRole = ($role === 'admin') ? 'user' : 'admin';
        $otherUserData = getUserSessionData($otherRole);
        
        if ($otherUserData['is_logged_in']) {
            // Keep legacy session variables for the other role
            $_SESSION['user_id'] = $_SESSION[$otherRole . '_user_id'];
            $_SESSION['name'] = $_SESSION[$otherRole . '_name'];
            $_SESSION['email'] = $_SESSION[$otherRole . '_email'];
            $_SESSION['role'] = $_SESSION[$otherRole . '_role'];
        } else {
            // Clear all legacy session variables if no other role is logged in
            unset($_SESSION['user_id']);
            unset($_SESSION['name']);
            unset($_SESSION['email']);
            unset($_SESSION['role']);
        }
        
        echo json_encode(['success' => true, 'message' => ucfirst($role) . ' logged out successfully']);
    } else {
        // Clear all session data (full logout)
        session_destroy();
        header("Location: ../index.php");
        echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
    }
} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Logout failed']);
}
?>
