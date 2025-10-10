<?php
/**
 * Customer Order Decline API
 * Allows customers to decline approved orders if they don't like the pricing
 */

session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$order_id = $input['order_id'] ?? null;
$decline_reason = $input['decline_reason'] ?? '';

if (!$order_id || empty($decline_reason)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID and reason are required']);
    exit();
}

try {
    // Start transaction to update both tables
    $conn->begin_transaction();
    
    // Update customer_requests status to cancelled
    $updateStmt = $conn->prepare("
        UPDATE customer_requests 
        SET status = 'cancelled', 
            admin_response = CONCAT(COALESCE(admin_response, ''), '\n\n🚫 Customer declined pricing: ', ?)
        WHERE id = ? AND user_id = ? AND status = 'approved'
    ");
    $updateStmt->bind_param("sii", $decline_reason, $order_id, $user_id);
    $updateStmt->execute();

    if ($updateStmt->affected_rows > 0) {
        // Set payment_status to 'cancelled' instead of deleting
        $cancelApprovedStmt = $conn->prepare("
            UPDATE approved_orders 
            SET payment_status = 'cancelled'
            WHERE request_id = ?
        ");
        $cancelApprovedStmt->bind_param("i", $order_id);
        $cancelApprovedStmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Log admin notification
        error_log("ORDER DECLINED - Order #{$order_id} declined by user {$user_id}: {$decline_reason}");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Order declined successfully. You can submit a new request anytime.'
        ]);
    } else {
        $conn->rollback();
        echo json_encode([
            'success' => false, 
            'message' => 'Order not found or cannot be declined'
        ]);
    }

} catch (Exception $e) {
    error_log("Order decline error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>
