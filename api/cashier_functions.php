<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON responses
ini_set('log_errors', 1);

require_once '../config/database.php';

// Check if user is cashier or admin
$is_authorized = false;
$user_role = $_SESSION['role'] ?? $_SESSION['admin_role'] ?? null;

if ($user_role === 'admin' || $user_role === 'cashier') {
    $is_authorized = true;
}

if (!$is_authorized) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Staff privileges required.']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    switch($action) {
        case 'get_pos_data':
            // Cashiers can access POS data
            getPOSData($pdo);
            break;
            
        case 'view_inventory':
            // Cashiers can view inventory but not modify
            viewInventory($pdo);
            break;
            
        case 'process_sale':
            // Cashiers can process sales
            processSale($pdo);
            break;
            
        case 'get_dashboard_stats':
            // Get limited dashboard stats for cashiers
            getCashierDashboardStats($pdo);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function getPOSData($pdo) {
    // Placeholder for POS data retrieval
    echo json_encode([
        'success' => true,
        'message' => 'POS data accessible to cashiers'
    ]);
}

function viewInventory($pdo) {
    // Placeholder for inventory viewing
    echo json_encode([
        'success' => true,
        'message' => 'Inventory view accessible to cashiers'
    ]);
}

function processSale($pdo) {
    // Placeholder for sale processing
    echo json_encode([
        'success' => true,
        'message' => 'Sale processing accessible to cashiers'
    ]);
}

function getCashierDashboardStats($pdo) {
    // Limited stats for cashiers
    $stats = [
        'today_sales' => 0,
        'pending_orders' => 0,
        'low_stock_items' => 0
    ];
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}
?>
