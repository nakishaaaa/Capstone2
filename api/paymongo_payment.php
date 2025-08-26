<?php
session_start();
require_once '../includes/paymongo_config.php';
require_once '../config/database.php';
require_once '../includes/csrf.php';

header('Content-Type: application/json');

// Disable error display for clean JSON responses
ini_set('display_errors', 0);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Missing CSRF token']);
    exit;
}

// For manual confirmation, use a simpler CSRF check or skip it for testing
$action = $_POST['action'] ?? '';
if ($action === 'manual_confirm_payment') {
    // Skip CSRF for manual testing - remove this in production
    // Continue with the action
} else {
    // Regular CSRF validation for other actions
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

try {
    $pdo = Database::getConnection();
    
    if ($action === 'create_payment_link') {
        handleCreatePaymentLink($pdo);
    } else if ($action === 'confirm_link_payment') {
        handleConfirmLinkPayment($pdo);
    } else if ($action === 'manual_confirm_payment') {
        handleManualConfirmPayment($pdo);
    } else if ($action === 'confirm_payment_by_request') {
        handleConfirmPaymentByRequest($pdo);
    } else {
        throw new Exception('Invalid action');
    }        
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleCreatePaymentLink($pdo) {
    $requestId = $_POST['request_id'] ?? '';
    $userId = $_SESSION['user_id'] ?? '';
    
    if (!$requestId || !$userId) {
        throw new Exception('Missing required parameters');
    }
    
    // Get request details
    $stmt = $pdo->prepare("SELECT * FROM user_requests WHERE id = ? AND user_id = ?");
    $stmt->execute([$requestId, $userId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        throw new Exception('Request not found');
    }
    
    if ($request['status'] !== 'approved') {
        throw new Exception('Request is not approved for payment');
    }
    
    // Calculate payment amount with PayMongo minimum requirement
    $totalAmount = floatval($request['total_price']);
    $paymentType = $_POST['payment_type'] ?? 'downpayment';
    
    if ($paymentType === 'full_payment') {
        $paymentAmount = $totalAmount;
        $percentage = 100;
    } else {
        // Downpayment with minimum ₱100 requirement
        $downpaymentAmount = $totalAmount * 0.70;
        $paymentAmount = max($downpaymentAmount, 100); // Ensure minimum ₱100
        $paymentAmount = min($paymentAmount, $totalAmount); // Don't exceed total
        $percentage = round(($paymentAmount / $totalAmount) * 100);
    }
    
    $amountInCentavos = intval($paymentAmount * 100);
    
    // Create payment link data
    $data = [
        'data' => [
            'attributes' => [
                'amount' => $amountInCentavos,
                'currency' => 'PHP',
                'description' => 'Downpayment for Request #' . $requestId,
                'remarks' => 'Custom order downpayment (70%)',
                'redirect' => [
                    'success' => PAYMENT_SUCCESS_URL . '&request_id=' . $requestId,
                    'failed' => PAYMENT_FAILURE_URL . '&request_id=' . $requestId
                ]
            ]
        ]
    ];
    
    // Make API request to PayMongo
    $response = makePayMongoRequest('/links', $data, 'POST');
    
    if (!$response['success']) {
        throw new Exception('Failed to create payment link: ' . json_encode($response['data']));
    }
    
    $paymentLink = $response['data']['data'];
    $linkId = $paymentLink['id'];
    $checkoutUrl = $paymentLink['attributes']['checkout_url'];
    
    // Update the payment link with correct redirect URLs that include link_id
    $updateData = [
        'data' => [
            'attributes' => [
                'redirect' => [
                    'success' => PAYMENT_SUCCESS_URL . '&request_id=' . $requestId . '&link_id=' . $linkId,
                    'failed' => PAYMENT_FAILURE_URL . '&request_id=' . $requestId . '&link_id=' . $linkId
                ]
            ]
        ]
    ];
    
    $updateResponse = makePayMongoRequest('/links/' . $linkId, $updateData, 'PUT');
    
    // Store payment link details in database
    $stmt = $pdo->prepare("
        UPDATE user_requests 
        SET payment_method = 'paymongo_link', 
            paymongo_link_id = ?,
            downpayment_percentage = ?,
            downpayment_amount = ?
        WHERE id = ?
    ");
    $stmt->execute([$linkId, $percentage, $paymentAmount, $requestId]);
    
    echo json_encode([
        'success' => true,
        'checkout_url' => $checkoutUrl,
        'link_id' => $linkId,
        'amount' => $paymentAmount
    ]);
}

function handleConfirmLinkPayment($pdo) {
    $linkId = $_POST['link_id'] ?? '';
    $requestId = $_POST['request_id'] ?? '';
    
    if (!$linkId || !$requestId) {
        throw new Exception('Missing required parameters');
    }
    
    // Get payment link details from PayMongo
    $response = makePayMongoRequest('/links/' . $linkId);
    
    if (!$response['success']) {
        throw new Exception('Failed to retrieve payment link details');
    }
    
    $linkData = $response['data']['data'];
    $paymentStatus = $linkData['attributes']['status'];
    
    if ($paymentStatus === 'paid') {
        // Update request status to partial_paid
        $stmt = $pdo->prepare("
            UPDATE user_requests 
            SET payment_status = 'partial_paid',
                paid_amount = downpayment_amount,
                payment_date = NOW()
            WHERE id = ? AND paymongo_link_id = ?
        ");
        $stmt->execute([$requestId, $linkId]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Payment confirmed successfully',
                'status' => 'partial_paid'
            ]);
        } else {
            throw new Exception('Failed to update payment status');
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Payment not completed yet',
            'status' => $paymentStatus
        ]);
    }
}

function handleManualConfirmPayment($pdo) {
    $requestId = $_POST['request_id'] ?? '';
    
    if (!$requestId) {
        throw new Exception('Missing request ID');
    }
    
    // Get request details
    $stmt = $pdo->prepare("SELECT * FROM user_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request || !$request['paymongo_link_id']) {
        throw new Exception('Request not found or no PayMongo link associated');
    }
    
    // Check payment status with PayMongo
    $response = makePayMongoRequest('/links/' . $request['paymongo_link_id']);
    
    if (!$response['success']) {
        throw new Exception('Failed to retrieve payment link details');
    }
    
    $linkData = $response['data']['data'];
    $paymentStatus = $linkData['attributes']['status'];
    
    if ($paymentStatus === 'paid') {
        // Update request payment status
        $stmt = $pdo->prepare("
            UPDATE user_requests 
            SET payment_status = 'partial_paid',
                paid_amount = downpayment_amount,
                payment_date = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$requestId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment confirmed successfully',
            'status' => 'partial_paid'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Payment not completed yet',
            'paymongo_status' => $paymentStatus
        ]);
    }
}

function handleConfirmPaymentByRequest($pdo) {
    $requestId = $_POST['request_id'] ?? '';
    
    if (!$requestId) {
        throw new Exception('Missing request ID');
    }
    
    // Get request details including PayMongo link ID
    $stmt = $pdo->prepare("SELECT * FROM user_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        throw new Exception('Request not found');
    }
    
    if (!$request['paymongo_link_id']) {
        throw new Exception('No PayMongo link ID found for this request');
    }
    
    // Get payment link details from PayMongo
    $response = makePayMongoRequest('/links/' . $request['paymongo_link_id']);
    
    if (!$response['success']) {
        throw new Exception('Failed to retrieve payment link details');
    }
    
    $linkData = $response['data']['data'];
    $paymentStatus = $linkData['attributes']['status'];
    
    if ($paymentStatus === 'paid') {
        // Update request status to partial_paid
        $stmt = $pdo->prepare("
            UPDATE user_requests 
            SET payment_status = 'partial_paid',
                paid_amount = downpayment_amount,
                payment_date = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$requestId]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Payment confirmed successfully',
                'status' => 'partial_paid'
            ]);
        } else {
            throw new Exception('Failed to update payment status');
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Payment not completed on PayMongo side',
            'paymongo_status' => $paymentStatus
        ]);
    }
}
?>
