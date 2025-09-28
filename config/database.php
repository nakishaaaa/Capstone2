<?php
// Error reporting: disable in production
error_reporting(0);
ini_set('display_errors', '0');

// Database configuration - PRODUCTION READY
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
    // Log error instead of displaying it in production
    error_log("Database Connection failed: " . $e->getMessage());
    die("Database connection error. Please contact support.");
}

// Legacy mysqli connection for backward compatibility
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        error_log("MySQLi Connection failed: " . $conn->connect_error);
        die("Database connection error. Please contact support.");
    }
    $conn->set_charset("utf8mb4");
} catch(Exception $e) {
    error_log("MySQLi Connection failed: " . $e->getMessage());
    die("Database connection error. Please contact support.");
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
