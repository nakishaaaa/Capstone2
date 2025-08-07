<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

session_start();

// Include database connection
require_once '../config/database.php';
require_once '../includes/csrf.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            // CSRF protection for POST requests
            if (!CSRFToken::validate($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
                exit();
            }
            
            createRequest();
            break;
            
        case 'GET':
            if (isset($_GET['user_id'])) {
                getUserRequests($_GET['user_id']);
            } else {
                getAllRequests();
            }
            break;
            
        case 'PUT':
            // CSRF protection for PUT requests
            $input = json_decode(file_get_contents('php://input'), true);
            if (!CSRFToken::validate($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
                exit();
            }
            
            updateRequestStatus();
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function createRequest() {
    global $pdo;
    
    try {
        // Validate required fields
        $required_fields = ['category', 'size', 'quantity', 'name', 'contact_number'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }
        
        // Handle file upload if present
        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/requests/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $file_extension;
            $image_path = $upload_dir . $filename;
            
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                throw new Exception('Failed to upload image');
            }
            
            // Store relative path
            $image_path = 'uploads/requests/' . $filename;
        }
        
        // Insert request into database
        $sql = "INSERT INTO user_requests (user_id, category, size, quantity, name, contact_number, notes, image_path, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $_POST['category'],
            $_POST['size'],
            $_POST['quantity'],
            $_POST['name'],
            $_POST['contact_number'],
            $_POST['notes'] ?? '',
            $image_path
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Request submitted successfully! Your request is now pending admin approval.',
            'request_id' => $pdo->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getUserRequests($user_id) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM user_requests WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'requests' => $requests]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getAllRequests() {
    global $pdo;
    
    try {
        // Get all requests
        $sql = "SELECT * FROM user_requests ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get statistics
        $stats_sql = "SELECT 
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
                        COUNT(*) as total
                      FROM user_requests";
        $stats_stmt = $pdo->prepare($stats_sql);
        $stats_stmt->execute();
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'requests' => $requests, 'stats' => $stats]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateRequestStatus() {
    global $pdo;
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['request_id']) || empty($input['status'])) {
            throw new Exception('Request ID and status are required');
        }
        
        $allowed_statuses = ['pending', 'approved', 'rejected'];
        if (!in_array($input['status'], $allowed_statuses)) {
            throw new Exception('Invalid status');
        }
        
        $sql = "UPDATE user_requests SET status = ?, admin_response = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $input['status'],
            $input['admin_response'] ?? '',
            $input['request_id']
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Request status updated successfully']);
        } else {
            throw new Exception('Request not found or no changes made');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
