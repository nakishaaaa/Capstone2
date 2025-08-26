<?php
// Set content type to JSON and disable HTML error output
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

session_start();
require_once '../config/database.php';
require_once '../includes/csrf.php';

// CSRF validation helper compatible with different implementations
function isValidCsrf($token) {
    if (function_exists('verifyCsrfToken')) { // legacy name
        return verifyCsrfToken($token);
    }
    if (function_exists('validateCSRFToken')) { // helper defined in includes/csrf.php
        return validateCSRFToken($token);
    }
    if (class_exists('CSRFToken')) { // class-based API
        return CSRFToken::validate($token);
    }
    return false;
}

// Check if user is admin - only admins can manage users
$is_authorized = false;
$user_role = $_SESSION['role'] ?? $_SESSION['admin_role'] ?? null;

if ($user_role === 'admin') {
    $is_authorized = true;
}

if (!$is_authorized) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin privileges required.']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Verify CSRF token for POST requests only
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

switch ($action) {
    case 'get_users':
        getUsersList($pdo);
        break;
    case 'add_user':
        addUser($pdo);
        break;
    case 'edit_user':
        editUser($pdo);
        break;
    case 'delete_user':
        deleteUser($pdo);
        break;
    case 'toggle_status':
        toggleUserStatus($pdo);
        break;
    case 'get_user_stats':
        getUserStats($pdo);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function getUsersList($pdo) {
    try {
        $search = $_GET['search'] ?? '';
        $role_filter = $_GET['role'] ?? 'all';
        $status_filter = $_GET['status'] ?? 'all';
        
        // Exclude customer accounts (role='user') from user management
        $sql = "SELECT id, username, email, role, status, created_at, last_login FROM users WHERE role != 'user'";
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (username LIKE ? OR email LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($role_filter !== 'all') {
            $sql .= " AND role = ?";
            $params[] = $role_filter;
        }
        
        if ($status_filter !== 'all') {
            $sql .= " AND status = ?";
            $params[] = $status_filter;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'users' => $users]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch users: ' . $e->getMessage()]);
    }
}

function addUser($pdo) {
    try {
        $username = trim($_POST['username'] ?? $_POST['name'] ?? ''); // Accept both username and name
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        // Validate required fields
        if (empty($username) || empty($email) || empty($password) || empty($role)) {
            http_response_code(400);
            echo json_encode(['error' => 'All fields are required']);
            return;
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email format']);
            return;
        }
        
        // Validate role
        $valid_roles = ['admin', 'cashier', 'user'];
        if (!in_array($role, $valid_roles)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid role specified']);
            return;
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Email already exists']);
            return;
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user - use username field instead of name
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$username, $email, $hashed_password, $role]);
        
        echo json_encode(['success' => true, 'message' => 'User added successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add user: ' . $e->getMessage()]);
    }
}

function editUser($pdo) {
    try {
        $user_id = $_POST['user_id'] ?? '';
        $username = trim($_POST['username'] ?? $_POST['name'] ?? ''); // Accept both username and name
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $status = $_POST['status'] ?? 'active'; // Default status if not provided
        $reset_password = isset($_POST['reset_password']);
        $new_password = $_POST['new_password'] ?? '';
        
        // Validate required fields
        if (empty($user_id) || empty($username) || empty($email) || empty($role)) {
            http_response_code(400);
            echo json_encode(['error' => 'All fields are required']);
            return;
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email format']);
            return;
        }
        
        // Validate role
        $valid_roles = ['admin', 'cashier', 'user'];
        if (!in_array($role, $valid_roles)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid role specified']);
            return;
        }
        
        // Check if email exists for other users
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Email already exists for another user']);
            return;
        }
        
        // Prevent self-demotion from admin
        $current_user_id = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? null;
        if ($user_id == $current_user_id && $role !== 'admin') {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot change your own admin role']);
            return;
        }
        
        // Update user - use username field instead of name
        if ($reset_password && !empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ? WHERE id = ?");
            $stmt->execute([$username, $email, $role, $hashed_password, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
            $stmt->execute([$username, $email, $role, $user_id]);
        }
        
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update user: ' . $e->getMessage()]);
    }
}

function deleteUser($pdo) {
    try {
        $user_id = $_POST['user_id'] ?? '';
        
        if (empty($user_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
            return;
        }
        
        // Prevent self-deletion
        $current_user_id = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? null;
        if ($user_id == $current_user_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete your own account']);
            return;
        }
        
        // Delete user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete user: ' . $e->getMessage()]);
    }
}

function toggleUserStatus($pdo) {
    try {
        $user_id = $_POST['user_id'] ?? '';
        
        if (empty($user_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
            return;
        }
        
        // Prevent self-status change
        $current_user_id = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? null;
        if ($user_id == $current_user_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot change your own status']);
            return;
        }
        
        // Get current status
        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        // Toggle status
        $new_status = ($user['status'] === 'active') ? 'inactive' : 'active';
        
        // Update status
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => "User status changed to $new_status",
                'new_status' => $new_status
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update user status']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to toggle user status: ' . $e->getMessage()]);
    }
}

function getUserStats($pdo) {
    try {
        $stats = [];
        
        // Total users (excluding customers)
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role != 'user'");
        $stats['total'] = $stmt->fetch()['count'];
        
        // Users by role (excluding customers)
        $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE role != 'user' GROUP BY role");
        $role_counts = $stmt->fetchAll();
        
        $stats['admin'] = 0;
        $stats['cashier'] = 0;
        
        foreach ($role_counts as $role_count) {
            $stats[$role_count['role']] = $role_count['count'];
        }
        
        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get user stats: ' . $e->getMessage()]);
    }
}
?>
