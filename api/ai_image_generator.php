<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

// Check if user is logged in
if (!isset($_SESSION['name'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Include CSRF protection
require_once '../includes/csrf.php';

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFToken::validate($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'generate_image':
            generateImage();
            break;
        case 'download_image':
            downloadImage();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function generateImage() {
    $prompt = trim($_POST['prompt'] ?? '');
    
    if (empty($prompt)) {
        http_response_code(400);
        echo json_encode(['error' => 'Prompt is required']);
        return;
    }
    
    // DeepAI API configuration
    $api_key = 'ad500340-688e-4fc0-85cd-3cb4fdf423e9'; // Replace with your actual DeepAI API key
    $api_url = 'https://api.deepai.org/api/text2img';
    
    // Prepare the data for DeepAI API
    $data = array(
        'text' => $prompt
    );
    
    // Initialize cURL
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Api-Key: ' . $api_key
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 seconds timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    
    curl_close($ch);
    
    if ($curl_error) {
        http_response_code(500);
        echo json_encode(['error' => 'Network error: ' . $curl_error]);
        return;
    }
    
    if ($http_code !== 200) {
        http_response_code(500);
        echo json_encode(['error' => 'DeepAI API error: HTTP ' . $http_code]);
        return;
    }
    
    $result = json_decode($response, true);
    
    if (!$result || !isset($result['output_url'])) {
        http_response_code(500);
        echo json_encode(['error' => 'Invalid response from DeepAI API']);
        return;
    }
    
    // Store the generated image info in session for potential download
    $_SESSION['last_generated_image'] = [
        'url' => $result['output_url'],
        'prompt' => $prompt,
        'generated_at' => time()
    ];
    
    echo json_encode([
        'success' => true,
        'image_url' => $result['output_url'],
        'prompt' => $prompt,
        'message' => 'Image generated successfully!'
    ]);
}

function downloadImage() {
    if (!isset($_SESSION['last_generated_image'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No image available for download']);
        return;
    }
    
    $imageInfo = $_SESSION['last_generated_image'];
    $imageUrl = $imageInfo['url'];
    $prompt = $imageInfo['prompt'];
    
    // Create a safe filename from the prompt
    $filename = preg_replace('/[^a-zA-Z0-9\-_]/', '_', substr($prompt, 0, 50));
    $filename = 'ai_generated_' . $filename . '_' . time() . '.jpg';
    
    // Get the image content
    $imageContent = file_get_contents($imageUrl);
    
    if ($imageContent === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to download image']);
        return;
    }
    
    // Set headers for file download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($imageContent));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    echo $imageContent;
}
?>
