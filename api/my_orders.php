<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');
ini_set('display_errors', '0');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'user') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    $pdo = Database::getConnection();
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_orders':
            getOrders($pdo, $userId);
            break;
        case 'get_order_detail':
            getOrderDetail($pdo, $userId);
            break;
        case 'get_order_counts':
            getOrderCounts($pdo, $userId);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("My Orders API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function getOrders($pdo, $userId) {
    $status = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'newest';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $whereConditions = ['user_id = :user_id'];
    $params = ['user_id' => $userId];
    
    if ($status !== 'all') {
        $whereConditions[] = 'status = :status';
        $params['status'] = $status;
    }
    
    if (!empty($search)) {
        $whereConditions[] = '(category LIKE :search OR notes LIKE :search OR name LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    // Build ORDER BY clause
    $orderBy = match($sort) {
        'oldest' => 'ORDER BY created_at ASC',
        'status' => 'ORDER BY status ASC, created_at DESC',
        default => 'ORDER BY created_at DESC'
    };
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM user_requests $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalOrders = $countStmt->fetchColumn();
    
    // Get orders
    $sql = "SELECT 
                id,
                category,
                size,
                quantity,
                image_path,
                name,
                contact_number,
                notes,
                status,
                admin_response,
                total_price,
                downpayment_percentage,
                payment_status,
                created_at,
                updated_at
            FROM user_requests 
            $whereClause 
            $orderBy 
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format orders for frontend
    $formattedOrders = array_map(function($order) {
        $formatted = [
            'id' => $order['id'],
            'orderNumber' => 'ORD-' . str_pad($order['id'], 6, '0', STR_PAD_LEFT),
            'category' => $order['category'],
            'categoryDisplay' => formatCategoryName($order['category']),
            'size' => $order['size'],
            'quantity' => $order['quantity'],
            'image_url' => $order['image_path'],
            'customerName' => $order['name'],
            'contactNumber' => $order['contact_number'],
            'notes' => $order['notes'],
            'status' => $order['status'],
            'statusDisplay' => ucfirst($order['status']),
            'adminResponse' => $order['admin_response'],
            'totalPrice' => $order['total_price'],
            'downpaymentPercentage' => $order['downpayment_percentage'],
            'paymentStatus' => $order['payment_status'],
            'createdAt' => $order['created_at'],
            'updatedAt' => $order['updated_at'],
            'createdAtFormatted' => date('M j, Y g:i A', strtotime($order['created_at'])),
            'updatedAtFormatted' => date('M j, Y g:i A', strtotime($order['updated_at'])),
            'daysSinceCreated' => floor((time() - strtotime($order['created_at'])) / 86400)
        ];
        
        // Add payment information if order is approved with pricing
        if ($order['status'] === 'approved' && $order['total_price']) {
            $formatted['hasPayment'] = true;
            $formatted['needsPayment'] = in_array($order['payment_status'], ['awaiting_payment']);
            $formatted['isPaid'] = in_array($order['payment_status'], ['partial_paid', 'fully_paid']);
            $formatted['paymentStatus'] = $order['payment_status'];
        } else {
            $formatted['hasPayment'] = false;
            $formatted['needsPayment'] = false;
            $formatted['isPaid'] = false;
            $formatted['paymentStatus'] = null;
        }
        
        return $formatted;
    }, $orders);
    
    echo json_encode([
        'success' => true,
        'orders' => $formattedOrders,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => ceil($totalOrders / $limit),
            'totalOrders' => $totalOrders,
            'ordersPerPage' => $limit,
            'hasNext' => $page < ceil($totalOrders / $limit),
            'hasPrev' => $page > 1
        ]
    ]);
}

function getOrderDetail($pdo, $userId) {
    $orderId = $_GET['order_id'] ?? '';
    
    if (empty($orderId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Order ID is required']);
        return;
    }
    
    $sql = "SELECT 
                id,
                category,
                size,
                quantity,
                image_path,
                name,
                contact_number,
                notes,
                status,
                admin_response,
                total_price,
                downpayment_percentage,
                payment_status,
                created_at,
                updated_at
            FROM user_requests 
            WHERE id = :order_id AND user_id = :user_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'order_id' => $orderId,
        'user_id' => $userId
    ]);
    
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        return;
    }
    
    // Format order for frontend
    $formattedOrder = [
        'id' => $order['id'],
        'orderNumber' => 'ORD-' . str_pad($order['id'], 6, '0', STR_PAD_LEFT),
        'category' => $order['category'],
        'categoryDisplay' => formatCategoryName($order['category']),
        'size' => $order['size'],
        'quantity' => $order['quantity'],
        'image_url' => $order['image_path'],
        'customerName' => $order['name'],
        'contactNumber' => $order['contact_number'],
        'notes' => $order['notes'],
        'status' => $order['status'],
        'statusDisplay' => ucfirst($order['status']),
        'adminResponse' => $order['admin_response'],
        'totalPrice' => $order['total_price'],
        'downpaymentPercentage' => $order['downpayment_percentage'],
        'paymentStatus' => $order['payment_status'],
        'createdAt' => $order['created_at'],
        'updatedAt' => $order['updated_at'],
        'createdAtFormatted' => date('M j, Y g:i A', strtotime($order['created_at'])),
        'updatedAtFormatted' => date('M j, Y g:i A', strtotime($order['updated_at'])),
        'timeline' => generateOrderTimeline($order)
    ];
    
    // Add payment information if order is approved with pricing
    if ($order['status'] === 'approved' && $order['total_price']) {
        $formattedOrder['hasPayment'] = true;
        $formattedOrder['needsPayment'] = in_array($order['payment_status'], ['awaiting_payment', 'partial_paid']);
    } else {
        $formattedOrder['hasPayment'] = false;
        $formattedOrder['needsPayment'] = false;
    }
    
    echo json_encode([
        'success' => true,
        'order' => $formattedOrder
    ]);
}

function getOrderCounts($pdo, $userId) {
    $sql = "SELECT 
                status,
                COUNT(*) as count
            FROM user_requests 
            WHERE user_id = :user_id 
            GROUP BY status";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $counts = [
        'all' => 0,
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0
    ];
    
    foreach ($results as $result) {
        $counts[$result['status']] = $result['count'];
        $counts['all'] += $result['count'];
    }
    
    echo json_encode([
        'success' => true,
        'counts' => $counts
    ]);
}

function formatCategoryName($category) {
    $categoryMap = [
        't-shirt-print' => 'T-Shirt Print',
        'tag-print' => 'Tag Print',
        'sticker-print' => 'Sticker Print',
        'card-print' => 'Card Print',
        'document-print' => 'Document Print',
        'photo-print' => 'Photo Print',
        'photo-copy' => 'Photo Copy',
        'lamination' => 'Lamination',
        'typing-job' => 'Typing Job'
    ];
    
    return $categoryMap[$category] ?? ucfirst(str_replace('-', ' ', $category));
}

function generateOrderTimeline($order) {
    $timeline = [];
    
    // Order placed
    $timeline[] = [
        'status' => 'placed',
        'title' => 'Order Placed',
        'description' => 'Your order has been submitted successfully',
        'timestamp' => $order['created_at'],
        'timestampFormatted' => date('M j, Y g:i A', strtotime($order['created_at'])),
        'icon' => 'fas fa-plus-circle',
        'completed' => true
    ];
    
    // Order processing
    if ($order['status'] !== 'pending') {
        $timeline[] = [
            'status' => 'processing',
            'title' => 'Order Reviewed',
            'description' => 'Your order is being reviewed by our team',
            'timestamp' => $order['updated_at'],
            'timestampFormatted' => date('M j, Y g:i A', strtotime($order['updated_at'])),
            'icon' => 'fas fa-search',
            'completed' => true
        ];
    }
    
    // Final status
    if ($order['status'] === 'approved') {
        $timeline[] = [
            'status' => 'approved',
            'title' => 'Order Approved',
            'description' => $order['admin_response'] ?: 'Your order has been approved and is ready for processing',
            'timestamp' => $order['updated_at'],
            'timestampFormatted' => date('M j, Y g:i A', strtotime($order['updated_at'])),
            'icon' => 'fas fa-check-circle',
            'completed' => true
        ];
    } elseif ($order['status'] === 'rejected') {
        $timeline[] = [
            'status' => 'rejected',
            'title' => 'Order Rejected',
            'description' => $order['admin_response'] ?: 'Your order could not be processed',
            'timestamp' => $order['updated_at'],
            'timestampFormatted' => date('M j, Y g:i A', strtotime($order['updated_at'])),
            'icon' => 'fas fa-times-circle',
            'completed' => true
        ];
    } else {
        // Pending status
        $timeline[] = [
            'status' => 'pending',
            'title' => 'Under Review',
            'description' => 'Your order is currently being reviewed',
            'timestamp' => null,
            'timestampFormatted' => 'Pending',
            'icon' => 'fas fa-clock',
            'completed' => false
        ];
    }
    
    return $timeline;
}
?>
