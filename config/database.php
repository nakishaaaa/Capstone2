<?php
// Load environment configuration
require_once __DIR__ . '/env.php';

// Set error reporting based on environment
if (Environment::isDebug()) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Database configuration from environment
$servername = Environment::get('DB_HOST', 'localhost');
$username = Environment::get('DB_USERNAME', 'root');
$password = Environment::get('DB_PASSWORD', '');
$dbname = Environment::get('DB_NAME', 'users_db');

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set connection timeout and other optimizations
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 30);
    $pdo->setAttribute(PDO::ATTR_PERSISTENT, false); // Disable persistent connections for better resource management
    
} catch(PDOException $e) {
    // Log the error securely without exposing sensitive info
    error_log("Database Connection failed: " . $e->getMessage());
    
    if (Environment::isDebug()) {
        die("Database Connection failed: " . $e->getMessage());
    } else {
        die("Database connection error. Please try again later.");
    }
}

// Create MySQLi connection for legacy compatibility
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("MySQLi Connection failed: " . $conn->connect_error);
    }
    
    // Set charset for MySQLi
    $conn->set_charset("utf8mb4");
    
} catch(Exception $e) {
    error_log("MySQLi Connection failed: " . $e->getMessage());
    
    if (Environment::isDebug()) {
        die("MySQLi Connection failed: " . $e->getMessage());
    } else {
        die("Database connection error. Please try again later.");
    }
}

// Register shutdown function to ensure connections are closed
register_shutdown_function(function() {
    global $pdo, $conn;
    
    if (isset($pdo)) {
        $pdo = null;
    }
    
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
});
?>
