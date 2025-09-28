<?php
/**
 * Client-Side Error Logging API
 * Captures JavaScript errors and other client-side issues
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/audit_helper.php';
require_once __DIR__ . '/../config/database.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Extract error information
    $errorMessage = $input['error'] ?? 'Unknown error';
    $fileName = $input['file'] ?? 'unknown';
    $lineNumber = $input['line'] ?? 0;
    $columnNumber = $input['column'] ?? 0;
    $stackTrace = $input['stack'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $url = $input['url'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    $userId = $input['user_id'] ?? null;
    $errorType = $input['type'] ?? 'javascript_error';
    
    // Get client IP
    $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (strpos($ipAddress, ',') !== false) {
        $ipAddress = trim(explode(',', $ipAddress)[0]);
    }
    
    // Create detailed error description
    $description = "Client Error: $errorMessage";
    if ($fileName !== 'unknown') {
        $description .= " | File: $fileName";
    }
    if ($lineNumber > 0) {
        $description .= " | Line: $lineNumber";
    }
    if ($columnNumber > 0) {
        $description .= " | Column: $columnNumber";
    }
    if (!empty($url)) {
        $description .= " | URL: $url";
    }
    if (!empty($stackTrace)) {
        $description .= " | Stack: " . substr($stackTrace, 0, 500);
    }
    
    // Log to audit system
    logAuditEvent($userId, $errorType, $description, $ipAddress, $userAgent);
    
    // Also log to PHP error log for immediate visibility
    error_log("CLIENT ERROR: $errorMessage at $fileName:$lineNumber from IP $ipAddress");
    
    echo json_encode([
        'success' => true,
        'message' => 'Error logged successfully'
    ]);
    
} catch (Exception $e) {
    // Silent fail - don't break client functionality
    error_log("Error logging client error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Failed to log error'
    ]);
}
?>
