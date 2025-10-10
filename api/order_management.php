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
           isset($_SESSION['admin_role']) && ($_SESSION['admin_role'] === 'admin' || $_SESSION['admin_role'] === 'super_admin');

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
            } elseif ($action === 'get_awaiting_payment') {
                handleGetAwaitingPayment($pdo);
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
            } elseif ($input['action'] === 'cancel_order') {
                handleCancelOrder($pdo, $input);
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
        $sql = "SELECT 
                    cr.*,
                    u.username as customer_name, 
                    u.email as customer_email,
                    u.firstname as customer_first_name,
                    u.lastname as customer_last_name,
                    CONCAT(u.firstname, ' ', u.lastname) as customer_full_name,
                    rd.size,
                    rd.custom_size,
                    rd.design_option,
                    rd.tag_location,
                    ao.total_price,
                    ao.payment_status,
                    ao.paid_amount,
                    ao.downpayment_percentage,
                    ao.downpayment_amount,
                    ao.payment_method,
                    ao.production_status,
                    ao.pricing_set_at,
                    ao.production_started_at,
                    ao.ready_at,
                    ao.completed_at
                FROM customer_requests cr
                LEFT JOIN users u ON cr.user_id = u.id
                LEFT JOIN request_details rd ON cr.id = rd.request_id
                INNER JOIN approved_orders ao ON cr.id = ao.request_id
                WHERE (cr.deleted IS NULL OR cr.deleted = 0)
                AND ao.payment_status NOT IN ('awaiting_payment')
                ORDER BY 
                    CASE ao.payment_status
                        WHEN 'cancelled' THEN 7
                        ELSE CASE ao.production_status 
                            WHEN 'pending' THEN 1
                            WHEN 'printing' THEN 2
                            WHEN 'ready_for_pickup' THEN 3
                            WHEN 'on_the_way' THEN 4
                            WHEN 'completed' THEN 5
                            ELSE 6
                        END
                    END,
                    cr.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get attachments for each order
        foreach ($orders as &$order) {
            $attachStmt = $pdo->prepare("
                SELECT attachment_type, file_path
                FROM request_attachments
                WHERE request_id = ?
            ");
            $attachStmt->execute([$order['id']]);
            $attachments = $attachStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format for backward compatibility
            $order['image_path'] = null;
            $order['front_image_path'] = null;
            $order['back_image_path'] = null;
            $order['tag_image_path'] = null;
            
            $imagePaths = [];
            foreach ($attachments as $att) {
                if ($att['attachment_type'] === 'image') {
                    $imagePaths[] = $att['file_path'];
                } elseif ($att['attachment_type'] === 'front_image') {
                    $order['front_image_path'] = $att['file_path'];
                } elseif ($att['attachment_type'] === 'back_image') {
                    $order['back_image_path'] = $att['file_path'];
                } elseif ($att['attachment_type'] === 'tag_image') {
                    $order['tag_image_path'] = $att['file_path'];
                }
            }
            
            if (!empty($imagePaths)) {
                $order['image_path'] = json_encode($imagePaths);
            }
        }
        
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
                'status' => $order['production_status'],
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
            'orders' => $formattedOrders,
            'debug' => [
                'total_orders' => count($orders),
                'formatted_orders' => count($formattedOrders),
                'sample_statuses' => array_unique(array_column($formattedOrders, 'status')),
                'query_used' => $sql
            ]
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
        $stmt = $pdo->prepare("
            SELECT 
                cr.*,
                u.email as customer_email, 
                u.username as customer_name,
                ao.production_started_at,
                ao.ready_at,
                ao.completed_at
            FROM customer_requests cr
            LEFT JOIN users u ON cr.user_id = u.id
            LEFT JOIN approved_orders ao ON cr.id = ao.request_id
            WHERE cr.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            return;
        }
        
        // Update order status with appropriate timestamp
        $pdo->beginTransaction();
        try {
            // Update customer_requests status AND approved_orders production_status
            $sql = "UPDATE customer_requests SET status = ?, admin_response = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$newStatus, $note, $orderId]);
            
            // Update approved_orders production_status and timestamps
            switch ($newStatus) {
                case 'printing':
                    $sql = "UPDATE approved_orders SET production_status = 'printing', production_started_at = NOW() WHERE request_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$orderId]);
                    break;
                case 'ready_for_pickup':
                    $sql = "UPDATE approved_orders SET production_status = 'ready_for_pickup', ready_at = NOW() WHERE request_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$orderId]);
                    break;
                case 'on_the_way':
                    $sql = "UPDATE approved_orders SET production_status = 'on_the_way' WHERE request_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$orderId]);
                    break;
                case 'completed':
                    $sql = "UPDATE approved_orders SET production_status = 'completed', completed_at = NOW() WHERE request_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$orderId]);
                    break;
            }
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
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
        $stmt = $pdo->prepare("
            SELECT 
                cr.*,
                u.email as customer_email, 
                u.username as customer_name
            FROM customer_requests cr
            LEFT JOIN users u ON cr.user_id = u.id
            WHERE cr.id IN ($placeholders)
        ");
        $stmt->execute($orderIds);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $note = "Bulk status update to " . ucfirst(str_replace('_', ' ', $newStatus));
        
        // Update customer_requests status
        $sql = "UPDATE customer_requests SET status = ?, admin_response = ? WHERE id IN ($placeholders)";
        $values = array_merge([$newStatus, $note], $orderIds);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        // Update approved_orders production_status and timestamps
        switch ($newStatus) {
            case 'printing':
                $sql = "UPDATE approved_orders SET production_status = 'printing', production_started_at = NOW() WHERE request_id IN ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($orderIds);
                break;
            case 'ready_for_pickup':
                $sql = "UPDATE approved_orders SET production_status = 'ready_for_pickup', ready_at = NOW() WHERE request_id IN ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($orderIds);
                break;
            case 'on_the_way':
                $sql = "UPDATE approved_orders SET production_status = 'on_the_way' WHERE request_id IN ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($orderIds);
                break;
            case 'completed':
                $sql = "UPDATE approved_orders SET production_status = 'completed', completed_at = NOW() WHERE request_id IN ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($orderIds);
                break;
        }
        
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
        $stmt = $pdo->query("
            SELECT 
                SUM(CASE WHEN cr.status = 'approved' AND ao.payment_status IN ('partial_paid', 'fully_paid') AND ao.production_status = 'pending' THEN 1 ELSE 0 END) as awaiting_production,
                SUM(CASE WHEN ao.production_status IN ('printing', 'ready_for_pickup', 'on_the_way') THEN 1 ELSE 0 END) as in_production
            FROM customer_requests cr
            LEFT JOIN approved_orders ao ON cr.id = ao.request_id
            WHERE (cr.deleted IS NULL OR cr.deleted = 0)
        ");
        
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

function handleGetAwaitingPayment($pdo) {
    try {
        // Get approved orders that are awaiting payment
        $sql = "SELECT 
                    cr.*,
                    u.username as customer_name, 
                    u.email as customer_email,
                    u.firstname as customer_first_name,
                    u.lastname as customer_last_name,
                    CONCAT(u.firstname, ' ', u.lastname) as customer_full_name,
                    rd.size,
                    rd.custom_size,
                    rd.design_option,
                    rd.tag_location,
                    ao.total_price,
                    ao.payment_status,
                    ao.paid_amount,
                    ao.downpayment_percentage,
                    ao.downpayment_amount,
                    ao.payment_method,
                    ao.production_status,
                    ao.pricing_set_at,
                    ao.production_started_at,
                    ao.ready_at,
                    ao.completed_at
                FROM customer_requests cr
                LEFT JOIN users u ON cr.user_id = u.id
                LEFT JOIN request_details rd ON cr.id = rd.request_id
                INNER JOIN approved_orders ao ON cr.id = ao.request_id
                WHERE (cr.deleted IS NULL OR cr.deleted = 0)
                AND ao.payment_status = 'awaiting_payment'
                ORDER BY cr.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get attachments for each order
        foreach ($orders as &$order) {
            $attachStmt = $pdo->prepare("
                SELECT attachment_type, file_path
                FROM request_attachments
                WHERE request_id = ?
            ");
            $attachStmt->execute([$order['id']]);
            $attachments = $attachStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $imagePaths = [];
            foreach ($attachments as $att) {
                if ($att['attachment_type'] === 'image') {
                    $imagePaths[] = $att['file_path'];
                }
            }
            $order['image_path'] = !empty($imagePaths) ? json_encode($imagePaths) : null;
            
            // Format order data
            $order['orderNumber'] = 'ORD-' . str_pad($order['id'], 6, '0', STR_PAD_LEFT);
            $order['createdAtFormatted'] = date('M j, Y g:i A', strtotime($order['created_at']));
            $order['updatedAtFormatted'] = date('M j, Y g:i A', strtotime($order['updated_at']));
        }
        
        echo json_encode([
            'success' => true,
            'orders' => $orders
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching awaiting payment orders: ' . $e->getMessage()
        ]);
    }
}

function handleCancelOrder($pdo, $input) {
    try {
        $orderId = $input['order_id'] ?? null;
        $note = $input['note'] ?? '';
        $sendEmail = $input['send_email'] ?? false;
        
        if (!$orderId) {
            echo json_encode(['success' => false, 'message' => 'Order ID is required']);
            return;
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Update customer_requests status to cancelled
        $stmt = $pdo->prepare("
            UPDATE customer_requests 
            SET status = 'cancelled',
                admin_response = CONCAT(COALESCE(admin_response, ''), '\n\n🚫 Admin cancelled order: ', ?)
            WHERE id = ?
        ");
        $stmt->execute([$note ?: 'Order cancelled by admin', $orderId]);
        
        // Update approved_orders payment_status to cancelled
        $stmt = $pdo->prepare("
            UPDATE approved_orders 
            SET payment_status = 'cancelled'
            WHERE request_id = ?
        ");
        $stmt->execute([$orderId]);
        
        // Commit transaction
        $pdo->commit();
        
        // Log admin action
        error_log("ADMIN CANCELLED ORDER - Order #{$orderId} cancelled by admin. Note: " . ($note ?: 'No note provided'));
        
        // TODO: Send email notification if requested
        if ($sendEmail) {
            // Email notification logic can be added here
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Order cancelled successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Error cancelling order: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to cancel order: ' . $e->getMessage()
        ]);
    }
}

?>