<?php
session_start();
require_once '../config/database.php';
require_once '../includes/csrf.php';
require_once '../includes/session_helper.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetRequests();
            break;
        case 'POST':
            handleCreateRequest();
            break;
        case 'PUT':
            handleUpdateRequest();
            break;
        case 'DELETE':
            handleDeleteRequest();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Requests API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handleGetRequests() {
    global $pdo;
    
    // Check authentication - admin can see all, users see only their own
    $userData = getUserSessionData();
    if (!$userData['is_logged_in']) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    try {
        if ($userData['role'] === 'admin') {
            // Admin sees all requests
            $stmt = $pdo->prepare("
                SELECT r.*, u.name as user_name, u.email as user_email 
                FROM user_requests r 
                LEFT JOIN users u ON r.user_id = u.id 
                ORDER BY r.created_at DESC
            ");
            $stmt->execute();
        } else {
            // Users see only their own requests
            $stmt = $pdo->prepare("
                SELECT r.*, u.name as user_name, u.email as user_email 
                FROM user_requests r 
                LEFT JOIN users u ON r.user_id = u.id 
                WHERE r.user_id = ? 
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$userData['user_id']]);
        }
        
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'requests' => $requests]);
    } catch (Exception $e) {
        error_log("Get requests error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch requests']);
    }
}

function handleCreateRequest() {
    global $pdo;
    
    // Check user authentication
    $userData = getUserSessionData('user');
    if (!$userData['is_logged_in']) {
        // Fallback check
        $userData = getUserSessionData();
        if (!$userData['is_logged_in'] || $userData['role'] === 'admin') {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized - Users only']);
            return;
        }
    }
    
    // Validate CSRF token
    if (!CSRFToken::validate($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        return;
    }
    
    // Validate required fields
    $required_fields = ['category', 'size', 'quantity'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }
    
    try {
        // Handle file upload if present
        $file_path = null;
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/requests/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
                throw new Exception('Failed to upload file');
            }
            
            $file_path = 'uploads/requests/' . $file_name; // Store relative path
        }
        
        // Insert request
        $sql = "INSERT INTO user_requests (user_id, category, size, quantity, notes, file_path, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $userData['user_id'],
            $_POST['category'],
            $_POST['size'],
            $_POST['quantity'],
            $_POST['notes'] ?? '',
            $file_path
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Request submitted successfully']);
    } catch (Exception $e) {
        error_log("Create request error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create request']);
    }
}

function handleUpdateRequest() {
    global $pdo;
    
    // Only admins can update requests
    $userData = getUserSessionData('admin');
    if (!$userData['is_logged_in']) {
        // Fallback check
        $userData = getUserSessionData();
        if (!$userData['is_logged_in'] || $userData['role'] !== 'admin') {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized - Admin only']);
            return;
        }
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate CSRF token
    if (!CSRFToken::validate($input['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        return;
    }
    
    if (!isset($input['id']) || !isset($input['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    try {
        $sql = "UPDATE user_requests SET status = ?, admin_response = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $input['status'],
            $input['admin_response'] ?? '',
            $input['id']
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Request updated successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Request not found']);
        }
    } catch (Exception $e) {
        error_log("Update request error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update request']);
    }
}

function handleDeleteRequest() {
    global $pdo;
    
    // Only admins can delete requests
    $userData = getUserSessionData('admin');
    if (!$userData['is_logged_in']) {
        // Fallback check
        $userData = getUserSessionData();
        if (!$userData['is_logged_in'] || $userData['role'] !== 'admin') {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized - Admin only']);
            return;
        }
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate CSRF token
    if (!CSRFToken::validate($input['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        return;
    }
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing request ID']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM user_requests WHERE id = ?");
        $stmt->execute([$input['id']]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Request deleted successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Request not found']);
        }
    } catch (Exception $e) {
        error_log("Delete request error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete request']);
    }
}
?>
