<?php
// PayMongo API Configuration - Load from environment variables
function loadEnvFile($filePath) {
    if (!file_exists($filePath)) {
        return;
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue; // Skip comments
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
        }
    }
}

// Load environment variables
loadEnvFile(__DIR__ . '/../.env');

define('PAYMONGO_SECRET_KEY', $_ENV['PAYMONGO_SECRET_KEY'] ?? '');
define('PAYMONGO_PUBLIC_KEY', $_ENV['PAYMONGO_PUBLIC_KEY'] ?? ''); 
define('PAYMONGO_API_URL', $_ENV['PAYMONGO_API_URL'] ?? 'https://api.paymongo.com/v1');

// Payment Links Configuration
define('PAYMENT_LINKS_ENABLED', true);
define('DEFAULT_CHECKOUT_URL', 'https://pm.link/053prints-paymongo-payment');

// Webhook Configuration
define('PAYMONGO_WEBHOOK_SECRET', $_ENV['PAYMONGO_WEBHOOK_SECRET'] ?? '');

// Success/Failure URLs
define('PAYMENT_SUCCESS_URL', 'http://localhost/Capstone2/my_orders.php?payment=success');
define('PAYMENT_FAILURE_URL', 'http://localhost/Capstone2/my_orders.php?payment=failed');

// Payment Methods Configuration
$PAYMONGO_PAYMENT_METHODS = [
    'gcash' => [
        'name' => 'GCash',
        'icon' => 'fa-mobile-alt',
        'type' => 'ewallet'
    ],
    'grab_pay' => [
        'name' => 'GrabPay',
        'icon' => 'fa-car',
        'type' => 'ewallet'
    ],
    'paymaya' => [
        'name' => 'PayMaya',
        'icon' => 'fa-credit-card',
        'type' => 'ewallet'
    ],
    'online_banking' => [
        'name' => 'Online Banking',
        'icon' => 'fa-university',
        'type' => 'bank'
    ]
];

// Helper function to get authorization header
function getPayMongoAuthHeader() {
    return 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':');
}

// Helper function to make PayMongo API requests
function makePayMongoRequest($endpoint, $data = null, $method = 'GET') {
    $url = PAYMONGO_API_URL . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: ' . getPayMongoAuthHeader()
    ]);
    
    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'data' => json_decode($response, true),
        'http_code' => $httpCode
    ];
}
?>
