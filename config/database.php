<?php
// Error reporting: only enable if not explicitly disabled by caller (e.g., JSON API endpoints)
if (ini_get('display_errors') !== '0') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Database configuration
$servername = "localhost";
$username = "aenv";
$password = "springthief044";
$dbname = "users_db";

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}

// Legacy mysqli connection for backward compatibility
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("MySQLi Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch(Exception $e) {
    die("MySQLi Connection failed: " . $e->getMessage());
}

// Lightweight helper to provide a unified access pattern
if (!class_exists('Database')) {
    class Database {
        public static function getConnection() {
            // Return the PDO instance created above
            global $pdo;
            return $pdo;
        }

        public static function getMysqliConnection() {
            global $conn;
            return $conn;
        }
    }
}
?>
