<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/enhanced_audit.php';

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errorCode = $_FILES['image']['error'] ?? 'unknown';
    logFileError('upload', 'image', "Upload error code: $errorCode");
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['image'];

$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
$fileType = $file['type'];

if (!in_array($fileType, $allowedTypes)) {
    logFileError('upload', $file['name'], "Invalid file type: $fileType");
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, and WebP are allowed']);
    exit;
}

$maxSize = 5 * 1024 * 1024; // 5MB in bytes
if ($file['size'] > $maxSize) {
    $sizeMB = round($file['size'] / 1024 / 1024, 2);
    logFileError('upload', $file['name'], "File too large: {$sizeMB}MB (max: 5MB)");
    echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 5MB']);
    exit;
}

// Create uploads directory if it doesn't exist
$uploadDir = '../uploads/products/';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        logFileError('mkdir', $uploadDir, "Failed to create directory with permissions 0755");
        echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
        exit;
    }
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'product_' . uniqid() . '_' . time() . '.' . $extension;
$filepath = $uploadDir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Return relative path for database storage
    $relativePath = 'uploads/products/' . $filename;
    
    // Log successful upload
    EnhancedAudit::safeLog([EnhancedAudit::class, 'logFileOperation'], 'upload', $filename, true, null);
    
    echo json_encode([
        'success' => true,
        'image_url' => $relativePath,
        'filename' => $filename,
        'message' => 'Image uploaded successfully'
    ]);
} else {
    logFileError('move_uploaded_file', $filename, "Failed to move from {$file['tmp_name']} to $filepath");
    echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
}
?>
