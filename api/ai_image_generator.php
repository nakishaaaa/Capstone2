<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
require_once '../includes/env.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    session_start();
    require_once '../includes/session_helper.php';

    // Check if user is logged in (any role)
    $userData = getUserSessionData();
    if (!$userData['is_logged_in']) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized access']);
        exit();
    }

    // Include CSRF protection
    if (file_exists('../includes/csrf.php')) {
        require_once '../includes/csrf.php';
        
        // Validate CSRF token for POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRFToken::validate($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                exit();
            }
        }
    } else {
        // If CSRF file doesn't exist, skip CSRF validation for now
        error_log('CSRF file not found, skipping CSRF validation');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server initialization error: ' . $e->getMessage()]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'generate_image':
            generateImage();
            break;
        case 'edit_photo':
            editPhoto();
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
    try {
        error_log('GenerateImage function called');
        $prompt = trim($_POST['prompt'] ?? '');
        error_log('Prompt received: ' . $prompt);
        
        if (empty($prompt)) {
            error_log('Empty prompt error');
            http_response_code(400);
            echo json_encode(['error' => 'Prompt is required']);
            return;
        }
        
        // Validate prompt length
        if (strlen($prompt) > 500) {
            http_response_code(400);
            echo json_encode(['error' => 'Prompt is too long (max 500 characters)']);
            return;
        }
        
        // DeepAI API configuration
        $api_key = env('DEEPAI_API_KEY');
        $api_url = 'https://api.deepai.org/api/text2img';
        
        if (empty($api_key)) {
            error_log('DeepAI API key not configured');
            http_response_code(500);
            echo json_encode(['error' => 'API configuration error. Please contact administrator.']);
            return;
        }
        
        // Check if cURL is available
        if (!function_exists('curl_init')) {
            http_response_code(500);
            echo json_encode(['error' => 'cURL is not available on this server']);
            return;
        }
        
        // Prepare the data for DeepAI API (multipart/form-data as per official PHP docs)
        $data = array(
            'text' => $prompt
        );
        
        error_log('Preparing DeepAI API call with data: ' . json_encode($data));
        
        // Initialize cURL
        $ch = curl_init();
        
        if (!$ch) {
            error_log('Failed to initialize cURL');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to initialize cURL']);
            return;
        }
        
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'api-key: ' . $api_key
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 seconds timeout
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        error_log('Making cURL request to DeepAI API: ' . $api_url);
        error_log('Using API key: ' . substr($api_key, 0, 8) . '...');
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        error_log('DeepAI API HTTP response code: ' . $http_code);
        error_log('DeepAI API response body: ' . $response);
        if ($curl_error) {
            error_log('cURL error: ' . $curl_error);
        }
        
        curl_close($ch);
        
        if ($curl_error) {
            error_log('DeepAI cURL Error: ' . $curl_error);
            http_response_code(500);
            echo json_encode(['error' => 'Network error: ' . $curl_error]);
            return;
        }
        
        if ($http_code !== 200) {
            error_log('DeepAI API HTTP Error: ' . $http_code . ' Response: ' . $response);
            http_response_code(500);
            echo json_encode(['error' => 'DeepAI API error: HTTP ' . $http_code . '. Please try again.']);
            return;
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg() . ' Response: ' . $response);
            http_response_code(500);
            echo json_encode(['error' => 'Invalid JSON response from DeepAI API']);
            return;
        }
        
        if (!$result || !isset($result['output_url'])) {
            error_log('Invalid DeepAI response: ' . print_r($result, true));
            http_response_code(500);
            echo json_encode(['error' => 'Invalid response from DeepAI API. Please try again.']);
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
        
    } catch (Exception $e) {
        error_log('GenerateImage Exception: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
}

function editPhoto() {
    try {
        error_log('EditPhoto function called');
        
        // Check if image file was uploaded
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            error_log('No image uploaded or upload error: ' . ($_FILES['image']['error'] ?? 'No file'));
            http_response_code(400);
            echo json_encode(['error' => 'Please upload an image to edit']);
            return;
        }
        
        $prompt = trim($_POST['text'] ?? '');
        error_log('Edit prompt received: ' . $prompt);
        
        if (empty($prompt)) {
            error_log('Empty edit prompt error');
            http_response_code(400);
            echo json_encode(['error' => 'Edit instructions are required']);
            return;
        }
        
        // Validate prompt length
        if (strlen($prompt) > 500) {
            http_response_code(400);
            echo json_encode(['error' => 'Edit instructions are too long (max 500 characters)']);
            return;
        }
        
        // Validate uploaded file
        $uploadedFile = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($uploadedFile['type'], $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid file type. Please upload JPG, PNG, or GIF images only.']);
            return;
        }
        
        if ($uploadedFile['size'] > $maxFileSize) {
            http_response_code(400);
            echo json_encode(['error' => 'File too large. Maximum size is 10MB.']);
            return;
        }
        
        // DeepAI API configuration
        $api_key = env('DEEPAI_API_KEY');
        $api_url = 'https://api.deepai.org/api/image-editor';
        
        if (empty($api_key)) {
            error_log('DeepAI API key not configured');
            http_response_code(500);
            echo json_encode(['error' => 'API configuration error. Please contact administrator.']);
            return;
        }
        
        // Check if cURL is available
        if (!function_exists('curl_init')) {
            http_response_code(500);
            echo json_encode(['error' => 'cURL is not available on this server']);
            return;
        }
        
        error_log('Preparing DeepAI Photo Editor API call');
        
        // Initialize cURL
        $ch = curl_init();
        
        if (!$ch) {
            error_log('Failed to initialize cURL');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to initialize cURL']);
            return;
        }
        
        // Prepare multipart form data
        $postFields = array(
            'image' => new CURLFile($uploadedFile['tmp_name'], $uploadedFile['type'], $uploadedFile['name']),
            'text' => $prompt
        );
        
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'api-key: ' . $api_key
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minutes timeout for photo editing
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        error_log('Making cURL request to DeepAI Photo Editor API: ' . $api_url);
        error_log('Using API key: ' . substr($api_key, 0, 8) . '...');
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        error_log('DeepAI Photo Editor API HTTP response code: ' . $http_code);
        error_log('DeepAI Photo Editor API response body: ' . $response);
        if ($curl_error) {
            error_log('cURL error: ' . $curl_error);
        }
        
        curl_close($ch);
        
        if ($curl_error) {
            error_log('DeepAI Photo Editor cURL Error: ' . $curl_error);
            http_response_code(500);
            echo json_encode(['error' => 'Network error: ' . $curl_error]);
            return;
        }
        
        if ($http_code !== 200) {
            error_log('DeepAI Photo Editor API HTTP Error: ' . $http_code . ' Response: ' . $response);
            http_response_code(500);
            echo json_encode(['error' => 'DeepAI API error: HTTP ' . $http_code . '. Please try again.']);
            return;
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg() . ' Response: ' . $response);
            http_response_code(500);
            echo json_encode(['error' => 'Invalid JSON response from DeepAI API']);
            return;
        }
        
        if (!$result || !isset($result['output_url'])) {
            error_log('Invalid DeepAI Photo Editor response: ' . print_r($result, true));
            http_response_code(500);
            echo json_encode(['error' => 'Invalid response from DeepAI API. Please try again.']);
            return;
        }
        
        // Store the edited image info in session for potential download
        $_SESSION['last_generated_image'] = [
            'url' => $result['output_url'],
            'prompt' => $prompt,
            'generated_at' => time(),
            'type' => 'edited'
        ];
        
        echo json_encode([
            'success' => true,
            'image_url' => $result['output_url'],
            'prompt' => $prompt,
            'message' => 'Photo edited successfully!'
        ]);
        
    } catch (Exception $e) {
        error_log('EditPhoto Exception: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
}

function downloadImage() {
    try {
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
        $type = $imageInfo['type'] ?? 'generated';
        $prefix = ($type === 'edited') ? 'ai_edited_' : 'ai_generated_';
        $filename = $prefix . $filename . '_' . time() . '.jpg';
        
        // Get the image content using cURL for better error handling
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $imageUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $imageContent = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log('Download cURL Error: ' . $curl_error);
            http_response_code(500);
            echo json_encode(['error' => 'Failed to download image: ' . $curl_error]);
            return;
        }
        
        if ($http_code !== 200 || $imageContent === false) {
            error_log('Download HTTP Error: ' . $http_code);
            http_response_code(500);
            echo json_encode(['error' => 'Failed to download image from URL']);
            return;
        }
        
        if (empty($imageContent)) {
            http_response_code(500);
            echo json_encode(['error' => 'Downloaded image is empty']);
            return;
        }
        
        // Clear any previous output
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($imageContent));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        echo $imageContent;
        
    } catch (Exception $e) {
        error_log('DownloadImage Exception: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Server error during download: ' . $e->getMessage()]);
    }
}
?>
