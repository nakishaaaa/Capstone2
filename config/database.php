<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "users_db";

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Test the connection
    $stmt = $pdo->query("SELECT 1");
    
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

// Function to test database connection
function testDatabaseConnection() {
    global $pdo;
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return [
            'success' => true,
            'tables' => $tables,
            'message' => 'Database connected successfully'
        ];
    } catch(PDOException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'message' => 'Database connection failed'
        ];
    }
}
?>
