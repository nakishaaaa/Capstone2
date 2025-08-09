<?php
require_once '../config/database.php';

// Set secure CORS headers based on environment
$allowedOrigins = Environment::get('CORS_ALLOWED_ORIGINS', 'http://localhost,http://127.0.0.1');
$allowedOriginsArray = explode(',', $allowedOrigins);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOriginsArray)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Fallback for development
    if (!Environment::isProduction()) {
        header('Access-Control-Allow-Origin: http://localhost');
    }
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Headers: Cache-Control');
header('Access-Control-Allow-Credentials: true');

// Prevent script timeout but set reasonable limits
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '128M'); // Increased from 64M for better performance

// Add garbage collection optimization for long-running processes
if (function_exists('gc_enable')) {
    gc_enable();
}

// Connection management
$maxConnections = Environment::get('SSE_MAX_CONNECTIONS', 50);
$connectionTimeout = Environment::get('SSE_CONNECTION_TIMEOUT', 300); // 5 minutes
$startTime = time();

// Function to send SSE data with error handling
function sendSSEData($event, $data) {
    try {
        echo "event: $event\n";
        echo "data: " . json_encode($data) . "\n\n";
        
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
        
        // Check if client disconnected
        if (connection_aborted()) {
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("SSE Send Error: " . $e->getMessage());
        return false;
    }
}

// Function to check connection limits
function checkConnectionLimits($startTime, $connectionTimeout) {
    // Check timeout
    if (time() - $startTime > $connectionTimeout) {
        sendSSEData('timeout', ['message' => 'Connection timeout reached']);
        return false;
    }
    
    // Check memory usage
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = ini_get('memory_limit');
    $memoryLimitBytes = return_bytes($memoryLimit);
    
    if ($memoryUsage > ($memoryLimitBytes * 0.8)) {
        sendSSEData('memory_warning', ['message' => 'High memory usage detected']);
        return false;
    }
    
    return true;
}

// Helper function to convert memory limit to bytes
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

// Function to get current stats
function getCurrentStats() {
    global $pdo;
    
    try {
        // Get inventory stats
        $inventoryStmt = $pdo->query("SELECT 
            COUNT(*) as total_products,
            SUM(CASE WHEN stock <= min_stock THEN 1 ELSE 0 END) as low_stock_count,
            SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock_count
            FROM inventory WHERE status = 'active'");
        $inventoryStats = $inventoryStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get sales stats for today
        $salesStmt = $pdo->query("SELECT 
            COUNT(*) as total_sales,
            COALESCE(SUM(total_amount), 0) as total_revenue
            FROM sales WHERE DATE(created_at) = CURDATE()");
        $salesStats = $salesStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get pending requests count
        $requestsStmt = $pdo->query("SELECT COUNT(*) as pending_requests FROM user_requests WHERE status = 'pending'");
        $requestsStats = $requestsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get unread notifications count
        $notificationsStmt = $pdo->query("SELECT COUNT(*) as unread_notifications FROM notifications WHERE is_read = 0");
        $notificationsStats = $notificationsStmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'inventory' => $inventoryStats,
            'sales' => $salesStats,
            'requests' => $requestsStats,
            'notifications' => $notificationsStats,
            'timestamp' => time()
        ];
        
    } catch (PDOException $e) {
        error_log("SSE Stats Error: " . $e->getMessage());
        return null;
    }
}

// Function to get recent activity
function getRecentActivity() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            (SELECT 'sale' as type, id, total_amount as amount, created_at 
             FROM sales ORDER BY created_at DESC LIMIT 5)
            UNION ALL
            (SELECT 'request' as type, id, NULL as amount, created_at 
             FROM user_requests ORDER BY created_at DESC LIMIT 5)
            ORDER BY created_at DESC LIMIT 10
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("SSE Activity Error: " . $e->getMessage());
        return [];
    }
}

// Main SSE loop
$lastStatsHash = '';
$lastActivityHash = '';

// Send initial connection confirmation
sendSSEData('connected', ['message' => 'Real-time connection established', 'timestamp' => time()]);

while (true) {
    // Check connection limits and resource usage
    if (!checkConnectionLimits($startTime, $connectionTimeout)) {
        error_log("SSE connection terminated due to limits");
        break;
    }
    
    // Get current stats with error handling
    $currentStats = getCurrentStats();
    $currentActivity = getRecentActivity();
    
    if ($currentStats) {
        // Create hash without timestamp to prevent unnecessary updates
        $statsForHash = $currentStats;
        unset($statsForHash['timestamp']);
        $statsHash = md5(json_encode($statsForHash));
        
        // Send stats update if changed
        if ($statsHash !== $lastStatsHash) {
            if (!sendSSEData('stats_update', $currentStats)) {
                break; // Client disconnected
            }
            $lastStatsHash = $statsHash;
        }
    }
    
    // Check for activity changes
    $activityHash = md5(json_encode($currentActivity));
    if ($activityHash !== $lastActivityHash) {
        if (!sendSSEData('activity_update', $currentActivity)) {
            break; // Client disconnected
        }
        $lastActivityHash = $activityHash;
    }
    
    // Send heartbeat every 30 seconds
    static $lastHeartbeat = 0;
    if (time() - $lastHeartbeat >= 30) {
        if (!sendSSEData('heartbeat', ['timestamp' => time()])) {
            break; // Client disconnected
        }
        $lastHeartbeat = time();
    }
    
    // Check if client disconnected
    if (connection_aborted()) {
        error_log("SSE client disconnected");
        break;
    }
    
    // Sleep for 1 second before next check
    sleep(1);
}

// Cleanup on exit
if (isset($pdo)) {
    $pdo = null;
}
?>
