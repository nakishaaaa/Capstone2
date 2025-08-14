<?php
// Test the new DeepAI API key
error_reporting(E_ALL);
ini_set('display_errors', 1);

$api_key = '67e9121a-b54e-4f1e-88f1-9ecf61e69b50';
$api_url = 'https://api.deepai.org/api/text2img';
$prompt = 'a simple red circle';

echo "<h2>Testing New DeepAI API Key</h2>";
echo "<p><strong>API Key:</strong> " . substr($api_key, 0, 10) . "...</p>";
echo "<p><strong>Prompt:</strong> " . $prompt . "</p>";

// Check if cURL is available
if (!function_exists('curl_init')) {
    echo "<p style='color: red;'>ERROR: cURL is not available</p>";
    exit();
}

// Test the API
$data = array('text' => $prompt);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Api-Key: ' . $api_key));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> " . $http_code . "</p>";
echo "<p><strong>cURL Error:</strong> " . ($curl_error ?: 'None') . "</p>";
echo "<p><strong>Response:</strong> <pre>" . htmlspecialchars($response) . "</pre></p>";

if ($curl_error) {
    echo "<p style='color: red;'><strong>FAILED:</strong> cURL Error - " . $curl_error . "</p>";
} elseif ($http_code !== 200) {
    echo "<p style='color: red;'><strong>FAILED:</strong> HTTP Error " . $http_code . "</p>";
    $result = json_decode($response, true);
    if ($result && isset($result['status'])) {
        echo "<p style='color: red;'><strong>Error Details:</strong> " . $result['status'] . "</p>";
    }
} else {
    $result = json_decode($response, true);
    if ($result && isset($result['output_url'])) {
        echo "<p style='color: green;'><strong>SUCCESS:</strong> Image generated!</p>";
        echo "<p><strong>Image URL:</strong> <a href='" . $result['output_url'] . "' target='_blank'>" . $result['output_url'] . "</a></p>";
        echo "<p><img src='" . $result['output_url'] . "' style='max-width: 300px; border: 1px solid #ccc;' /></p>";
    } else {
        echo "<p style='color: red;'><strong>FAILED:</strong> Invalid response format</p>";
    }
}
?>
