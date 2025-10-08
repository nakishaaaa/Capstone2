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
    
    // Also check for admin-specific session variables
    $isAdmin = isset($_SESSION['admin_name']) && isset($_SESSION['admin_email']) && 
               isset($_SESSION['admin_role']) && ($_SESSION['admin_role'] === 'admin' || $_SESSION['admin_role'] === 'super_admin');
    
    if (!$userData['is_logged_in'] && !$isAdmin) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    if ($isAdmin) {
        $userData['role'] = 'admin';
        $userData['is_logged_in'] = true;
    }
    
    try {
        if ($userData['role'] === 'admin' || $userData['role'] === 'super_admin') {
            // Admin sees all requests with details
            $stmt = $pdo->prepare("
                SELECT 
                    cr.*,
                    u.username as user_name, 
                    u.email as user_email,
                    rd.size,
                    rd.custom_size,
                    rd.size_breakdown,
                    rd.design_option,
                    rd.tag_location,
                    ao.total_price,
                    ao.downpayment_percentage,
                    ao.downpayment_amount,
                    ao.paid_amount,
                    ao.payment_status,
                    ao.payment_method,
                    ao.payment_date,
                    ao.paymongo_link_id,
                    ao.pricing_set_at,
                    ao.production_started_at,
                    ao.ready_at,
                    ao.completed_at
                FROM customer_requests cr
                LEFT JOIN users u ON cr.user_id = u.id
                LEFT JOIN request_details rd ON cr.id = rd.request_id
                LEFT JOIN approved_orders ao ON cr.id = ao.request_id
                WHERE cr.deleted = 0
                ORDER BY cr.created_at DESC
            ");
            $stmt->execute();
        } else {
            // Users see only their own requests
            $stmt = $pdo->prepare("
                SELECT 
                    cr.*,
                    u.username as user_name, 
                    u.email as user_email,
                    rd.size,
                    rd.custom_size,
                    rd.size_breakdown,
                    rd.design_option,
                    rd.tag_location,
                    ao.total_price,
                    ao.downpayment_percentage,
                    ao.downpayment_amount,
                    ao.paid_amount,
                    ao.payment_status,
                    ao.payment_method,
                    ao.payment_date,
                    ao.paymongo_link_id,
                    ao.production_status,
                    ao.pricing_set_at,
                    ao.production_started_at,
                    ao.ready_at,
                    ao.completed_at
                FROM customer_requests cr
                LEFT JOIN users u ON cr.user_id = u.id
                LEFT JOIN request_details rd ON cr.id = rd.request_id
                LEFT JOIN approved_orders ao ON cr.id = ao.request_id
                WHERE cr.user_id = ? AND cr.deleted = 0
                ORDER BY cr.created_at DESC
            ");
            $stmt->execute([$userData['user_id']]);
        }
        
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get attachments for each request
        foreach ($requests as &$request) {
            $attachStmt = $pdo->prepare("
                SELECT attachment_type, file_path
                FROM request_attachments
                WHERE request_id = ?
                ORDER BY attachment_type
            ");
            $attachStmt->execute([$request['id']]);
            $attachments = $attachStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format attachments for backward compatibility
            $request['image_path'] = null;
            $request['front_image_path'] = null;
            $request['back_image_path'] = null;
            $request['tag_image_path'] = null;
            
            $imagePaths = [];
            foreach ($attachments as $att) {
                if ($att['attachment_type'] === 'image') {
                    $imagePaths[] = $att['file_path'];
                } elseif ($att['attachment_type'] === 'front_image') {
                    $request['front_image_path'] = $att['file_path'];
                } elseif ($att['attachment_type'] === 'back_image') {
                    $request['back_image_path'] = $att['file_path'];
                } elseif ($att['attachment_type'] === 'tag_image') {
                    $request['tag_image_path'] = $att['file_path'];
                }
            }
            
            if (!empty($imagePaths)) {
                $request['image_path'] = json_encode($imagePaths);
            }
        }
        
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
    $required_fields = ['category', 'size', 'quantity', 'name', 'contact_number'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }
    
    // Validate that file upload is required for all print services and lamination
    $category = $_POST['category'];
    $services_requiring_files = [
        't-shirt-print', 'tag-print', 'sticker-print', 'card-print', 
        'document-print', 'photo-print', 'photo-copy', 'lamination'
    ];
    
    if (in_array($category, $services_requiring_files)) {
        $has_files = false;
        
        // Check for T-shirt specific files or card-specific files
        if ($category === 't-shirt-print') {
            // Debug: Log what files are received
            error_log("T-shirt files received:");
            error_log("front_image: " . (isset($_FILES['front_image']) ? $_FILES['front_image']['error'] : 'not set'));
            error_log("back_image: " . (isset($_FILES['back_image']) ? $_FILES['back_image']['error'] : 'not set'));
            error_log("tag_image: " . (isset($_FILES['tag_image']) ? $_FILES['tag_image']['error'] : 'not set'));
            error_log("image: " . (isset($_FILES['image']) ? (is_array($_FILES['image']['error']) ? 'array' : $_FILES['image']['error']) : 'not set'));
            
            // For T-shirt: require at least one design file (front, back, or regular image)
            $has_design_files = (isset($_FILES['front_image']) && $_FILES['front_image']['error'] === UPLOAD_ERR_OK) ||
                               (isset($_FILES['back_image']) && $_FILES['back_image']['error'] === UPLOAD_ERR_OK) ||
                               (isset($_FILES['image']) && (
                                   (is_array($_FILES['image']['error']) && in_array(UPLOAD_ERR_OK, $_FILES['image']['error'])) ||
                                   (!is_array($_FILES['image']['error']) && $_FILES['image']['error'] === UPLOAD_ERR_OK)
                               ));
            
            $design_option = $_POST['design_option'] ?? '';
            
            if ($design_option === 'customize') {
                // For customize option: require at least one of front, back, or tag
                $has_tag_file = isset($_FILES['tag_image']) && $_FILES['tag_image']['error'] === UPLOAD_ERR_OK;
                $has_files = $has_design_files || $has_tag_file;
                
                if (!$has_files) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Please upload at least one design file (front design, back design, or tag).']);
                    return;
                }
            } else {
                // For ready design option: only require design files, tag is optional
                $has_files = $has_design_files;
                
                if (!$has_design_files) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Please upload your design file.']);
                    return;
                }
            }
        } elseif ($category === 'card-print' && (isset($_POST['size']) && ($_POST['size'] === 'calling' || $_POST['size'] === 'business'))) {
            // For calling/business cards, check for card-specific front/back designs
            $has_files = (isset($_FILES['card_front_image']) && $_FILES['card_front_image']['error'] === UPLOAD_ERR_OK) ||
                        (isset($_FILES['card_back_image']) && $_FILES['card_back_image']['error'] === UPLOAD_ERR_OK) ||
                        (isset($_FILES['image']) && (
                            (is_array($_FILES['image']['error']) && in_array(UPLOAD_ERR_OK, $_FILES['image']['error'])) ||
                            (!is_array($_FILES['image']['error']) && $_FILES['image']['error'] === UPLOAD_ERR_OK)
                        ));
        } else {
            // Check for regular image uploads
            $has_files = isset($_FILES['image']) && (
                (is_array($_FILES['image']['error']) && in_array(UPLOAD_ERR_OK, $_FILES['image']['error'])) ||
                (!is_array($_FILES['image']['error']) && $_FILES['image']['error'] === UPLOAD_ERR_OK)
            );
        }
        
        if (!$has_files) {
            http_response_code(400);
            echo json_encode(['error' => 'File upload is required for this service. Please select at least one image or document file.']);
            return;
        }
    }
    
    try {
        // Handle file uploads
        $image_path = null;
        $front_image_path = null;
        $back_image_path = null;
        $tag_image_path = null;
        
        $upload_dir = '../uploads/requests/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Handle multiple image uploads (for non-tshirt categories)
        $image_paths = [];
        if (isset($_FILES['image']) && is_array($_FILES['image']['name'])) {
            // Multiple files uploaded
            for ($i = 0; $i < count($_FILES['image']['name']); $i++) {
                if ($_FILES['image']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_extension = pathinfo($_FILES['image']['name'][$i], PATHINFO_EXTENSION);
                    $file_name = uniqid('req_' . $i . '_', true) . '.' . $file_extension;
                    $destination = $upload_dir . $file_name;
                    
                    if (!move_uploaded_file($_FILES['image']['tmp_name'][$i], $destination)) {
                        throw new Exception('Failed to upload image file: ' . $_FILES['image']['name'][$i]);
                    }
                    
                    $image_paths[] = 'uploads/requests/' . $file_name;
                }
            }
        } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Single file uploaded (backward compatibility)
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('req_', true) . '.' . $file_extension;
            $destination = $upload_dir . $file_name;
            
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                throw new Exception('Failed to upload image file');
            }
            
            $image_paths[] = 'uploads/requests/' . $file_name;
        }
        
        // Convert array to JSON string for database storage
        $image_path = !empty($image_paths) ? json_encode($image_paths) : null;
        
        // Handle T-shirt specific uploads
        if ($_POST['category'] === 't-shirt-print') {
            // Front image
            if (isset($_FILES['front_image']) && $_FILES['front_image']['error'] === UPLOAD_ERR_OK) {
                $file_extension = pathinfo($_FILES['front_image']['name'], PATHINFO_EXTENSION);
                $file_name = uniqid('req_tshirt_front_', true) . '.' . $file_extension;
                $destination = $upload_dir . $file_name;
                
                if (!move_uploaded_file($_FILES['front_image']['tmp_name'], $destination)) {
                    throw new Exception('Failed to upload front image');
                }
                
                $front_image_path = 'uploads/requests/' . $file_name;
            }
            
            // Back image
            if (isset($_FILES['back_image']) && $_FILES['back_image']['error'] === UPLOAD_ERR_OK) {
                $file_extension = pathinfo($_FILES['back_image']['name'], PATHINFO_EXTENSION);
                $file_name = uniqid('req_tshirt_back_', true) . '.' . $file_extension;
                $destination = $upload_dir . $file_name;
                
                if (!move_uploaded_file($_FILES['back_image']['tmp_name'], $destination)) {
                    throw new Exception('Failed to upload back image');
                }
                
                $back_image_path = 'uploads/requests/' . $file_name;
            }
        }
        
        // Handle card specific uploads (use same database fields as T-shirts)
        if ($_POST['category'] === 'card-print' && isset($_POST['size']) && ($_POST['size'] === 'calling' || $_POST['size'] === 'business')) {
            // Front image (from card_front_image field)
            if (isset($_FILES['card_front_image']) && $_FILES['card_front_image']['error'] === UPLOAD_ERR_OK) {
                $file_extension = pathinfo($_FILES['card_front_image']['name'], PATHINFO_EXTENSION);
                $file_name = uniqid('req_card_front_', true) . '.' . $file_extension;
                $destination = $upload_dir . $file_name;
                
                if (!move_uploaded_file($_FILES['card_front_image']['tmp_name'], $destination)) {
                    throw new Exception('Failed to upload card front image');
                }
                
                $front_image_path = 'uploads/requests/' . $file_name;
            }
            
            // Back image (from card_back_image field)
            if (isset($_FILES['card_back_image']) && $_FILES['card_back_image']['error'] === UPLOAD_ERR_OK) {
                $file_extension = pathinfo($_FILES['card_back_image']['name'], PATHINFO_EXTENSION);
                $file_name = uniqid('req_card_back_', true) . '.' . $file_extension;
                $destination = $upload_dir . $file_name;
                
                if (!move_uploaded_file($_FILES['card_back_image']['tmp_name'], $destination)) {
                    throw new Exception('Failed to upload card back image');
                }
                
                $back_image_path = 'uploads/requests/' . $file_name;
            }
        }
        
        // Handle T-shirt tag image
        if ($_POST['category'] === 't-shirt-print') {
            
            // Tag image
            if (isset($_FILES['tag_image']) && $_FILES['tag_image']['error'] === UPLOAD_ERR_OK) {
                $file_extension = pathinfo($_FILES['tag_image']['name'], PATHINFO_EXTENSION);
                $file_name = uniqid('req_tag_', true) . '.' . $file_extension;
                $destination = $upload_dir . $file_name;
                
                if (!move_uploaded_file($_FILES['tag_image']['tmp_name'], $destination)) {
                    throw new Exception('Failed to upload tag image');
                }
                
                $tag_image_path = 'uploads/requests/' . $file_name;
            }
        }
        
        // No need to alter table structure - using normalized schema
        
        // Handle size breakdown data for T-shirt orders
        $size_breakdown = null;
        if ($_POST['category'] === 't-shirt-print' && isset($_POST['size_breakdown'])) {
            $size_breakdown = $_POST['size_breakdown'];
            // Validate JSON format
            $decoded = json_decode($size_breakdown, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid size breakdown format']);
                return;
            }
        }
        
        // Begin transaction for normalized schema
        $pdo->beginTransaction();
        
        try {
            // 1. Insert into customer_requests
            $sql = "INSERT INTO customer_requests (
                        user_id, category, name, contact_number, quantity, notes, status
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, 'pending'
                    )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $userData['user_id'],
                $_POST['category'],
                $_POST['name'],
                $_POST['contact_number'],
                $_POST['quantity'],
                $_POST['notes'] ?? ''
            ]);
            
            $requestId = $pdo->lastInsertId();
            
            // 2. Insert into request_details
            $sql = "INSERT INTO request_details (
                        request_id, size, custom_size, size_breakdown, design_option, tag_location
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?
                    )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $requestId,
                $_POST['size'],
                $_POST['custom_size'] ?? null,
                $size_breakdown,
                $_POST['design_option'] ?? null,
                $_POST['tag_location'] ?? null
            ]);
            
            // 3. Insert attachments into request_attachments
            $attachSql = "INSERT INTO request_attachments (request_id, attachment_type, file_path) VALUES (?, ?, ?)";
            $attachStmt = $pdo->prepare($attachSql);
            
            // Insert regular images
            if (!empty($image_paths)) {
                foreach ($image_paths as $path) {
                    $attachStmt->execute([$requestId, 'image', $path]);
                }
            }
            
            // Insert front image
            if ($front_image_path) {
                $attachStmt->execute([$requestId, 'front_image', $front_image_path]);
            }
            
            // Insert back image
            if ($back_image_path) {
                $attachStmt->execute([$requestId, 'back_image', $back_image_path]);
            }
            
            // Insert tag image
            if ($tag_image_path) {
                $attachStmt->execute([$requestId, 'tag_image', $tag_image_path]);
            }
            
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
        // Create a notification for the admin
        $notificationTitle = "New Order Request: " . $_POST['category'];
        $notificationMessage = "Customer: " . $_POST['name'] . " - " . $_POST['category'] . " (" . $_POST['size'] . ") - Qty: " . $_POST['quantity'];
        
        $notificationSql = "INSERT INTO notifications (title, message, type, is_read, created_at) VALUES (?, ?, 'info', 0, NOW())";
        $notificationStmt = $pdo->prepare($notificationSql);
        $notificationStmt->execute([$notificationTitle, $notificationMessage]);
        
        // Send email notification to all admins (non-blocking)
        try {
            require_once '../includes/email_notifications.php';
            error_log("Attempting to send admin notification: " . $notificationTitle);
            $emailResult = EmailNotifications::sendAdminNotification($notificationTitle, $notificationMessage, 'info');
            error_log("Email notification result: " . ($emailResult ? 'SUCCESS' : 'FAILED'));
        } catch (Exception $emailError) {
            // Log email error but don't fail the request submission
            error_log("Email notification error: " . $emailError->getMessage());
        }
        
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
        // If approving, require pricing information
        if ($input['status'] === 'approved') {
            if (!isset($input['total_price']) || $input['total_price'] <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Total price is required when approving orders']);
                return;
            }
            
            $pdo->beginTransaction();
            try {
                // Update customer_requests status
                $sql = "UPDATE customer_requests SET 
                            status = ?, 
                            admin_response = ?
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $input['status'],
                    $input['admin_response'] ?? '',
                    $input['id']
                ]);
                
                // Fixed 70% downpayment
                $downpayment_percentage = 70;
                $downpayment_amount = ($input['total_price'] * $downpayment_percentage) / 100;
                
                // Create approved_orders record
                $sql = "INSERT INTO approved_orders (
                            request_id,
                            total_price,
                            downpayment_percentage,
                            downpayment_amount,
                            payment_status,
                            pricing_set_at
                        ) VALUES (?, ?, ?, ?, 'awaiting_payment', NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $input['id'],
                    $input['total_price'],
                    $downpayment_percentage,
                    $downpayment_amount
                ]);
                
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        } else {
            // For rejection or other status updates
            $sql = "UPDATE customer_requests SET status = ?, admin_response = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $input['status'],
                $input['admin_response'] ?? '',
                $input['id']
            ]);
        }
        
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
        // Soft delete
        $stmt = $pdo->prepare("UPDATE customer_requests SET deleted = 1 WHERE id = ?");
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
