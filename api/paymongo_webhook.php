<?php
require_once '../includes/paymongo_config.php';
require_once '../config/database.php';

// Disable error display for clean webhook responses
ini_set('display_errors', 0);
error_reporting(0);

// Get the raw POST data
$input = file_get_contents("php://input");
$event = json_decode($input, true);

// Log webhook events for debugging
$logFile = '../logs/paymongo_webhook.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

file_put_contents($logFile, date('Y-m-d H:i:s') . " - Webhook received: " . $input . "\n", FILE_APPEND | LOCK_EX);

try {
    if (!$event || !isset($event['data'])) {
        throw new Exception('Invalid webhook payload');
    }
    
    $eventType = $event['data']['attributes']['type'] ?? '';
    $eventData = $event['data']['attributes']['data'] ?? [];
    
    $pdo = Database::getConnection();
    
    switch ($eventType) {
        case 'link.payment.paid':
            handleLinkPaymentPaid($pdo, $eventData);
            break;
            
        case 'link.payment.failed':
            handleLinkPaymentFailed($pdo, $eventData);
            break;
            
        default:
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Unhandled event type: " . $eventType . "\n", FILE_APPEND | LOCK_EX);
            break;
    }
    
    // Respond with 200 OK
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

function handleLinkPaymentPaid($pdo, $eventData) {
    $linkId = $eventData['id'] ?? '';
    
    if (!$linkId) {
        throw new Exception('Missing link ID in webhook data');
    }
    
    // Find the request associated with this payment link
    $stmt = $pdo->prepare("SELECT * FROM user_requests WHERE paymongo_link_id = ?");
    $stmt->execute([$linkId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        throw new Exception('Request not found for link ID: ' . $linkId);
    }
    
    // Update request status to partial_paid
    $stmt = $pdo->prepare("
        UPDATE user_requests 
        SET status = 'partial_paid',
            payment_status = 'partial_paid',
            paid_amount = downpayment_amount,
            payment_date = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$request['id']]);
    
    // Log successful payment
    $logFile = '../logs/paymongo_webhook.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Payment successful for Request ID: " . $request['id'] . ", Link ID: " . $linkId . "\n", FILE_APPEND | LOCK_EX);
}

function handleLinkPaymentFailed($pdo, $eventData) {
    $linkId = $eventData['id'] ?? '';
    
    if (!$linkId) {
        throw new Exception('Missing link ID in webhook data');
    }
    
    // Find the request associated with this payment link
    $stmt = $pdo->prepare("SELECT * FROM user_requests WHERE paymongo_link_id = ?");
    $stmt->execute([$linkId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        throw new Exception('Request not found for link ID: ' . $linkId);
    }
    
    // Update payment status to failed
    $stmt = $pdo->prepare("
        UPDATE user_requests 
        SET payment_status = 'failed'
        WHERE id = ?
    ");
    $stmt->execute([$request['id']]);
    
    // Log failed payment
    $logFile = '../logs/paymongo_webhook.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Payment failed for Request ID: " . $request['id'] . ", Link ID: " . $linkId . "\n", FILE_APPEND | LOCK_EX);
}
?>
