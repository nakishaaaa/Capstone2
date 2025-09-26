<?php
session_start();
require_once '../config/database.php';
require_once '../includes/csrf.php';
require_once '../includes/session_helper.php';
require_once '../includes/email_notifications.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check admin authentication
$userData = getUserSessionData('admin');
$isAdmin = isset($_SESSION['admin_name']) && isset($_SESSION['admin_email']) && 
           isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin';

if (!$userData['is_logged_in'] && !$isAdmin) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? null);

try {
    $pdo = Database::getConnection();
    
    switch ($method) {
        case 'GET':
            if ($action === 'get_orders') {
                handleGetOrders($pdo);
            } elseif ($action === 'get_production_count') {
                handleGetProductionCount($pdo);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validate CSRF token
            if (!CSRFToken::validate($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
                exit;
            }
            
            if ($input['action'] === 'update_status') {
                handleUpdateStatus($pdo, $input);
            } elseif ($input['action'] === 'bulk_update') {
                handleBulkUpdate($pdo, $input);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Order Management API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

function handleGetOrders($pdo) {
    try {
        // Get approved orders with payment received, and orders in production stages
        $sql = "SELECT r.*, u.username as customer_name, u.email as customer_email 
                FROM user_requests r 
                LEFT JOIN users u ON r.user_id = u.id 
                WHERE (
                    (r.status = 'approved' AND r.payment_status IN ('partial_paid', 'fully_paid')) OR
                    r.status IN ('printing', 'ready_for_pickup', 'on_the_way', 'completed')
                )
                ORDER BY 
                    CASE r.status 
                        WHEN 'approved' THEN 1
                        WHEN 'printing' THEN 2
                        WHEN 'ready_for_pickup' THEN 3
                        WHEN 'on_the_way' THEN 4
                        WHEN 'completed' THEN 5
                        ELSE 6
                    END,
                    r.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format orders for frontend
        $formattedOrders = array_map(function($order) {
            return [
                'id' => $order['id'],
                'customer_name' => $order['customer_name'],
                'customer_email' => $order['customer_email'],
                'name' => $order['name'],
                'contact_number' => $order['contact_number'],
                'category' => $order['category'],
                'size' => $order['size'],
                'custom_size' => $order['custom_size'],
                'quantity' => $order['quantity'],
                'notes' => $order['notes'],
                'status' => $order['status'],
                'payment_status' => $order['payment_status'],
                'total_price' => $order['total_price'],
                'admin_response' => $order['admin_response'],
                'created_at' => $order['created_at'],
                'updated_at' => $order['updated_at'],
                'pricing_set_at' => $order['pricing_set_at'],
                'production_started_at' => $order['production_started_at'] ?? null,
                'ready_at' => $order['ready_at'] ?? null,
                'completed_at' => $order['completed_at'] ?? null,
                // Image fields
                'image_path' => $order['image_path'],
                'front_image_path' => $order['front_image_path'],
                'back_image_path' => $order['back_image_path'],
                'tag_image_path' => $order['tag_image_path'],
                'tag_location' => $order['tag_location'],
                'design_option' => $order['design_option']
            ];
        }, $orders);
        
        echo json_encode([
            'success' => true,
            'orders' => $formattedOrders
        ]);
        
    } catch (Exception $e) {
        error_log("Get orders error: " . $e->getMessage());
        throw $e;
    }
}

function handleUpdateStatus($pdo, $input) {
    try {
        $orderId = $input['order_id'] ?? null;
        $newStatus = $input['status'] ?? null;
        $note = $input['note'] ?? '';
        $sendEmail = $input['send_email'] ?? false;
        
        if (!$orderId || !$newStatus) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }
        
        // Get current order details
        $stmt = $pdo->prepare("SELECT r.*, u.email as customer_email, u.username as customer_name 
                              FROM user_requests r 
                              LEFT JOIN users u ON r.user_id = u.id 
                              WHERE r.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            return;
        }
        
        // Update order status with appropriate timestamp
        $updateFields = ['status = ?', 'admin_response = ?', 'updated_at = NOW()'];
        $updateValues = [$newStatus, $note];
        
        switch ($newStatus) {
            case 'printing':
                $updateFields[] = 'production_started_at = NOW()';
                break;
            case 'ready_for_pickup':
                $updateFields[] = 'ready_at = NOW()';
                break;
            case 'completed':
                $updateFields[] = 'completed_at = NOW()';
                break;
        }
        
        $updateValues[] = $orderId;
        
        $sql = "UPDATE user_requests SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateValues);
        
        // Send email notification if requested
        if ($sendEmail && $order['customer_email']) {
            $emailSent = EmailNotifications::sendOrderStatusUpdate($order, $newStatus, $note);
            if (!$emailSent) {
                error_log("Failed to send email notification for order {$orderId}");
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Order status updated successfully',
            'email_sent' => $sendEmail && $order['customer_email']
        ]);
        
    } catch (Exception $e) {
        error_log("Update status error: " . $e->getMessage());
        throw $e;
    }
}

function handleBulkUpdate($pdo, $input) {
    try {
        $orderIds = $input['order_ids'] ?? [];
        $newStatus = $input['status'] ?? null;
        $sendEmail = $input['send_email'] ?? false;
        
        if (empty($orderIds) || !$newStatus) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }
        
        $pdo->beginTransaction();
        
        // Get order details for email notifications
        $placeholders = str_repeat('?,', count($orderIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT r.*, u.email as customer_email, u.username as customer_name 
                              FROM user_requests r 
                              LEFT JOIN users u ON r.user_id = u.id 
                              WHERE r.id IN ($placeholders)");
        $stmt->execute($orderIds);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update all orders
        $updateFields = ['status = ?', 'admin_response = ?', 'updated_at = NOW()'];
        $note = "Bulk status update to " . ucfirst(str_replace('_', ' ', $newStatus));
        
        switch ($newStatus) {
            case 'printing':
                $updateFields[] = 'production_started_at = NOW()';
                break;
            case 'ready_for_pickup':
                $updateFields[] = 'ready_at = NOW()';
                break;
            case 'completed':
                $updateFields[] = 'completed_at = NOW()';
                break;
        }
        
        $sql = "UPDATE user_requests SET " . implode(', ', $updateFields) . " WHERE id IN ($placeholders)";
        $values = array_merge([$newStatus, $note], $orderIds);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        $pdo->commit();
        
        // Send email notifications
        $emailsSent = 0;
        if ($sendEmail) {
            foreach ($orders as $order) {
                if ($order['customer_email']) {
                    if (EmailNotifications::sendOrderStatusUpdate($order, $newStatus, $note)) {
                        $emailsSent++;
                    }
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => count($orderIds) . ' orders updated successfully',
            'emails_sent' => $emailsSent
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Bulk update error: " . $e->getMessage());
        throw $e;
    }
}

function handleGetProductionCount($pdo) {
    try {
        $stmt = $pdo->query("SELECT 
            SUM(CASE WHEN status = 'approved' AND (payment_status = 'partial_paid' OR payment_status = 'fully_paid') THEN 1 ELSE 0 END) as awaiting_production,
            SUM(CASE WHEN status IN ('printing', 'ready_for_pickup', 'on_the_way') THEN 1 ELSE 0 END) as in_production
            FROM user_requests");
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $productionCount = ($result['awaiting_production'] ?? 0) + ($result['in_production'] ?? 0);
        
        echo json_encode([
            'success' => true,
            'production_count' => $productionCount,
            'awaiting_production' => $result['awaiting_production'] ?? 0,
            'in_production' => $result['in_production'] ?? 0
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching production count: ' . $e->getMessage()
        ]);
    }
}

?>
