<?php
session_start();
require_once '../config/database.php';
require_once '../includes/csrf.php';
require_once '../includes/session_helper.php';

// Set JSON header
header('Content-Type: application/json');

// CORS headers
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check for user-specific session variables first (user_user_id, user_name, etc.)
if (isset($_SESSION['user_user_id']) && isset($_SESSION['user_name'])) {
    $userData = [
        'user_id' => $_SESSION['user_user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'],
        'is_logged_in' => true
    ];
} else {
    // Fallback to general session if it's a user role
    $userData = getUserSessionData();
    if (!$userData['is_logged_in'] || $userData['role'] !== 'user') {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized - user role required']);
        exit();
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $userData['user_id'];

try {
    switch ($method) {
        case 'GET':
            // Get user account information
            getUserInfo($pdo, $user_id);
            break;
            
        case 'PUT':
            // Update password
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validate CSRF token for state-changing operations
            if (!CSRFToken::validate($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                exit();
            }
            
            updatePassword($pdo, $user_id, $input);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function getUserInfo($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, username, firstname, lastname, email, contact_number, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        // Format the created_at date
        if ($user['created_at']) {
            $user['created_at'] = date('F j, Y g:i A', strtotime($user['created_at']));
        }
        
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function updatePassword($pdo, $user_id, $input) {
    try {
        // Validate input
        if (!isset($input['current_password']) || !isset($input['new_password']) || !isset($input['confirm_password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'All password fields are required']);
            return;
        }
        
        if ($input['new_password'] !== $input['confirm_password']) {
            http_response_code(400);
            echo json_encode(['error' => 'New passwords do not match']);
            return;
        }
        
        if (strlen($input['new_password']) < 6) {
            http_response_code(400);
            echo json_encode(['error' => 'New password must be at least 6 characters long']);
            return;
        }
        
        // Get current user password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        // Verify current password
        if (!password_verify($input['current_password'], $user['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Current password is incorrect']);
            return;
        }
        
        // Hash new password
        $new_password_hash = password_hash($input['new_password'], PASSWORD_DEFAULT);
        
        // Update password in database
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_password_hash, $user_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Password updated successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
