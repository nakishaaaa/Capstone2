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

// Check if user is admin or super admin - only admins can manage users
$is_authorized = false;
$user_role = $_SESSION['role'] ?? $_SESSION['admin_role'] ?? null;

if ($user_role === 'admin' || $user_role === 'super_admin') {
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
        softDeleteUser($pdo);
        break;
    case 'restore_user':
        restoreUser($pdo);
        break;
    case 'permanent_delete':
        permanentDeleteUser($pdo);
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
        $include_deleted = $_GET['include_deleted'] ?? 'false';
        $include_customers = $_GET['include_customers'] ?? 'false';
        
        $sql = "SELECT u.id, u.username, u.email, u.role, u.status, u.created_at, u.last_login, 
                       u.deleted_at, u.deletion_reason, du.username as deleted_by_username
                FROM users u 
                LEFT JOIN users du ON u.deleted_by = du.id 
                WHERE u.role != 'super_admin'";
        
        // Exclude customer accounts for regular admin access
        if ($include_customers !== 'true') {
            $sql .= " AND u.role != 'user'";
        }
        $params = [];
        
        // Handle soft delete filter
        if ($include_deleted === 'only') {
            $sql .= " AND u.deleted_at IS NOT NULL";
        } elseif ($include_deleted === 'false') {
            $sql .= " AND u.deleted_at IS NULL";
        }
        // If include_deleted === 'true', show all users (active and deleted)
        
        if (!empty($search)) {
            $sql .= " AND (u.username LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($role_filter !== 'all') {
            $sql .= " AND u.role = ?";
            $params[] = $role_filter;
        }
        
        if ($status_filter !== 'all') {
            $sql .= " AND u.status = ?";
            $params[] = $status_filter;
        }
        
        $sql .= " ORDER BY u.deleted_at ASC, u.created_at DESC";
        
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

function softDeleteUser($pdo) {
    try {
        $user_id = $_POST['user_id'] ?? '';
        $reason = $_POST['reason'] ?? 'Deleted by administrator';
        
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
        
        // Check if user exists and is not already deleted
        $stmt = $pdo->prepare("SELECT username, deleted_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        if ($user['deleted_at']) {
            http_response_code(400);
            echo json_encode(['error' => 'User is already deleted']);
            return;
        }
        
        // Soft delete user
        $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW(), deleted_by = ?, deletion_reason = ? WHERE id = ?");
        $stmt->execute([$current_user_id, $reason, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'User account deactivated successfully',
                'username' => $user['username']
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to deactivate user account']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to deactivate user: ' . $e->getMessage()]);
    }
}

function restoreUser($pdo) {
    try {
        $user_id = $_POST['user_id'] ?? '';
        
        if (empty($user_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
            return;
        }
        
        // Check if user exists and is deleted
        $stmt = $pdo->prepare("SELECT username, deleted_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        if (!$user['deleted_at']) {
            http_response_code(400);
            echo json_encode(['error' => 'User is not deleted']);
            return;
        }
        
        // Restore user
        $stmt = $pdo->prepare("UPDATE users SET deleted_at = NULL, deleted_by = NULL, deletion_reason = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'User account restored successfully',
                'username' => $user['username']
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to restore user account']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to restore user: ' . $e->getMessage()]);
    }
}

function permanentDeleteUser($pdo) {
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
            echo json_encode(['error' => 'Cannot permanently delete your own account']);
            return;
        }
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        // Permanently delete user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'User permanently deleted',
                'username' => $user['username']
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to permanently delete user']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to permanently delete user: ' . $e->getMessage()]);
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
        
        // Total users (excluding customers and super admin)
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role != 'user' AND role != 'super_admin'");
        $stats['total'] = $stmt->fetch()['count'];
        
        // Users by role (excluding customers and super admin)
        $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE role != 'user' AND role != 'super_admin' GROUP BY role");
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
