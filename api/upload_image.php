<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['image'];

$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$fileType = $file['type'];

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed']);
    exit;
}

$maxSize = 5 * 1024 * 1024; // 5MB in bytes
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 5MB']);
    exit;
}

// Create uploads directory if it doesn't exist
$uploadDir = '../uploads/products/';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
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
    
    echo json_encode([
        'success' => true,
        'image_url' => $relativePath,
        'filename' => $filename,
        'message' => 'Image uploaded successfully'
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
}
?>
