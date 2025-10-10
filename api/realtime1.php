<?php
// Disable error output to prevent breaking SSE stream
ini_set('display_errors', 0);
error_reporting(0);

// Set SSE headers BEFORE including any files that might output content
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Cache-Control');
header('Access-Control-Allow-Credentials: true');

// Flush headers immediately
if (ob_get_level()) ob_flush();
flush();

require_once '../config/database.php';

// Headers already set above before including database config

// Prevent script timeout but set reasonable limits
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '64M');

// Add garbage collection optimization for long-running processes
if (function_exists('gc_enable')) {
    gc_enable();
}

// Connection management
$maxConnections = 10;
$connectionTimeout = 60; // 5 minutes
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

// Test database connection before starting SSE
try {
    if (!isset($pdo)) {
        throw new Exception("Database connection not available");
    }
    // Test query
    $pdo->query("SELECT 1");
} catch (Exception $e) {
    // Send error and exit
    echo "event: error\n";
    echo "data: " . json_encode(['message' => 'Database connection failed']) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
    exit;
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
        
        // Get comprehensive requests stats
        $requestsStmt = $pdo->query("SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN cr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
            SUM(CASE WHEN cr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
            SUM(CASE WHEN cr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
            SUM(CASE WHEN cr.status = 'approved' AND ao.payment_status IN ('partial_paid', 'fully_paid') THEN 1 ELSE 0 END) as awaiting_production,
            SUM(CASE WHEN cr.status IN ('printing', 'ready_for_pickup', 'on_the_way') THEN 1 ELSE 0 END) as in_production
            FROM customer_requests cr
            LEFT JOIN approved_orders ao ON cr.id = ao.request_id
            WHERE cr.deleted = 0");
        $requestsStats = $requestsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate total production orders (awaiting + in production)
        $requestsStats['production_orders'] = ($requestsStats['awaiting_production'] ?? 0) + ($requestsStats['in_production'] ?? 0);
        
        // Get unread notifications count
        $notificationsStmt = $pdo->query("SELECT COUNT(*) as unread_notifications FROM notifications WHERE is_read = 0");
        $notificationsStats = $notificationsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get support messages stats
        $supportStmt = $pdo->query("SELECT 
            COUNT(DISTINCT conversation_id) as total_conversations,
            COUNT(DISTINCT CASE WHEN is_admin = 0 AND is_read = 0 THEN conversation_id END) as unread_conversations
            FROM support_tickets_messages");
        $supportStats = $supportStmt->fetch(PDO::FETCH_ASSOC);

        return [
            'inventory' => $inventoryStats,
            'sales' => $salesStats,
            'requests' => $requestsStats,
            'notifications' => $notificationsStats,
            'support' => $supportStats,
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
            (SELECT 'sale' as type, id, total_amount as amount, created_at, NULL as conversation_id
             FROM sales ORDER BY created_at DESC LIMIT 5)
            UNION ALL
            (SELECT 'request' as type, id, NULL as amount, created_at, NULL as conversation_id
             FROM customer_requests WHERE deleted = 0 ORDER BY created_at DESC LIMIT 5)
            UNION ALL
            (SELECT 'support_message' as type, id, NULL as amount, created_at, conversation_id
             FROM support_messages 
             WHERE is_admin = 0 
             ORDER BY created_at DESC LIMIT 10)
            ORDER BY created_at DESC LIMIT 15
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("SSE Activity Error: " . $e->getMessage());
        return [];
    }
}

// Function to get and deliver real-time notifications
function getRealtimeNotifications() {
    global $pdo;
    
    try {
        // Get undelivered notifications
        $stmt = $pdo->query("
            SELECT id, user_id, type, data, created_at 
            FROM realtime_notifications 
            WHERE delivered = FALSE 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY created_at ASC
            LIMIT 50
        ");
        
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($notifications)) {
            // Mark as delivered
            $ids = array_column($notifications, 'id');
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $updateStmt = $pdo->prepare("UPDATE realtime_notifications SET delivered = TRUE WHERE id IN ($placeholders)");
            $updateStmt->execute($ids);
            
            // Parse JSON data for each notification
            foreach ($notifications as &$notification) {
                $notification['data'] = json_decode($notification['data'], true);
            }
        }
        
        return $notifications;
        
    } catch (PDOException $e) {
        error_log("SSE Realtime Notifications Error: " . $e->getMessage());
        return [];
    }
}

// Function to check for new support messages (for admins)
function getNewSupportMessages($lastCheck = null) {
    global $pdo;
    
    try {
        $query = "SELECT sm.*, 
                  (SELECT COUNT(*) FROM support_tickets_messages sm2 
                   WHERE sm2.conversation_id = sm.conversation_id AND sm2.is_admin = 0 AND sm2.is_read = 0) as unread_count
                  FROM support_tickets_messages sm 
                  WHERE sm.is_admin = 0 AND sm.is_read = 0";
        
        if ($lastCheck) {
            $query .= " AND sm.created_at > ?";
            $stmt = $pdo->prepare($query . " ORDER BY sm.created_at DESC LIMIT 10");
            $stmt->execute([$lastCheck]);
        } else {
            $stmt = $pdo->prepare($query . " ORDER BY sm.created_at DESC LIMIT 10");
            $stmt->execute();
        }
        
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format messages for frontend
        $formattedMessages = [];
        foreach ($messages as $msg) {
            $formattedMessages[] = [
                'conversation_id' => $msg['conversation_id'],
                'user_name' => $msg['user_name'],
                'user_email' => $msg['user_email'],
                'subject' => $msg['subject'],
                'message' => substr($msg['message'], 0, 100) . (strlen($msg['message']) > 100 ? '...' : ''),
                'unread_count' => (int)$msg['unread_count'],
                'created_at' => $msg['created_at'],
                'timestamp' => strtotime($msg['created_at'])
            ];
        }
        
        return $formattedMessages;
        
    } catch (PDOException $e) {
        error_log("SSE Support Messages Error: " . $e->getMessage());
        return [];
    }
}

// Function to check for new admin replies (for customers)
function getNewAdminReplies($userId, $lastCheck = null) {
    global $pdo;
    
    try {
        // Query support_messages table (regular admin-to-customer support)
        // NOT support_tickets_messages (developer tickets)
        $query = "SELECT sm.* 
                  FROM support_messages sm 
                  WHERE sm.is_admin = 1 
                  AND sm.message_type = 'customer_support'
                  AND sm.conversation_id IN (
                      SELECT DISTINCT conversation_id 
                      FROM support_messages 
                      WHERE user_name = (SELECT username FROM users WHERE id = ?) 
                      AND is_admin = 0
                      AND message_type = 'customer_support'
                  )";
        
        if ($lastCheck) {
            $query .= " AND sm.created_at > ?";
            $stmt = $pdo->prepare($query . " ORDER BY sm.created_at DESC LIMIT 10");
            $stmt->execute([$userId, $lastCheck]);
        } else {
            $stmt = $pdo->prepare($query . " ORDER BY sm.created_at DESC LIMIT 10");
            $stmt->execute([$userId]);
        }
        
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format messages for frontend
        $formattedMessages = [];
        foreach ($messages as $msg) {
            $formattedMessages[] = [
                'id' => $msg['id'],
                'conversation_id' => $msg['conversation_id'],
                'admin_name' => $msg['admin_name'] ?: 'Support Team',
                'subject' => $msg['subject'],
                'message' => $msg['message'],
                'created_at' => $msg['created_at'],
                'timestamp' => strtotime($msg['created_at'])
            ];
        }
        
        return $formattedMessages;
        
    } catch (PDOException $e) {
        error_log("SSE Admin Replies Error: " . $e->getMessage());
        return [];
    }
}

// Function to check for new messages in a specific conversation
function getNewConversationMessages($conversationId, $lastCheck = null) {
    global $pdo;
    
    try {
        $query = "SELECT * FROM support_tickets_messages 
                  WHERE conversation_id = ?";
        
        if ($lastCheck) {
            $query .= " AND created_at > ?";
            $stmt = $pdo->prepare($query . " ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$conversationId, $lastCheck]);
        } else {
            $stmt = $pdo->prepare($query . " ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$conversationId]);
        }
        
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format messages for frontend
        $formattedMessages = [];
        foreach ($messages as $msg) {
            $formattedMessages[] = [
                'id' => $msg['id'],
                'conversation_id' => $msg['conversation_id'],
                'user_name' => $msg['user_name'],
                'admin_name' => $msg['admin_name'],
                'subject' => $msg['subject'],
                'message' => $msg['message'],
                'is_admin' => (bool)$msg['is_admin'],
                'created_at' => $msg['created_at'],
                'timestamp' => strtotime($msg['created_at'])
            ];
        }
        
        return $formattedMessages;
        
    } catch (PDOException $e) {
        error_log("SSE Conversation Messages Error: " . $e->getMessage());
        return [];
    }
}

// Detect user type and ID from session
error_log("SSE DEBUG: All cookies: " . print_r($_COOKIE, true));

// Ensure session cookie is used if available
if (isset($_COOKIE['PHPSESSID'])) {
    error_log("SSE DEBUG: Setting session ID to: " . $_COOKIE['PHPSESSID']);
    session_id($_COOKIE['PHPSESSID']);
} else {
    error_log("SSE DEBUG: No PHPSESSID cookie found!");
}

session_start();
error_log("SSE DEBUG: Session after start: " . print_r($_SESSION, true));
error_log("SSE DEBUG: Session save path: " . session_save_path());
$sessionFile = session_save_path() . '/sess_' . session_id();
error_log("SSE DEBUG: Session file: " . $sessionFile);
error_log("SSE DEBUG: Session file exists: " . (file_exists(session_save_path() . '/sess_' . session_id()) ? 'YES' : 'NO'));

$userRole = $_SESSION['role'] ?? 'guest';
$userId = $_SESSION['user_id'] ?? null;

error_log("SSE DEBUG: Final values - Role: $userRole, User ID: " . ($userId ?? 'null'));

// If still guest, try alternative session variables
if ($userRole === 'guest' && isset($_SESSION['user_role'])) {
    $userRole = $_SESSION['user_role'];
    $userId = $_SESSION['user_user_id'] ?? $_SESSION['user_id'];
    error_log("SSE DEBUG: Using alternative session vars - Role: $userRole, User ID: " . ($userId ?? 'null'));
}

// PERMANENT FIX: Use session tracking table
if ($userRole === 'guest' && isset($_COOKIE['PHPSESSID'])) {
    try {
        $sessionId = $_COOKIE['PHPSESSID'];
        error_log("SSE DEBUG: Attempting to recover session for: " . $sessionId);
        
        // Check session tracking table
        $stmt = $pdo->prepare("SELECT user_id, user_role FROM sse_sessions WHERE session_id = ? AND expires_at > NOW()");
        $stmt->execute([$sessionId]);
        $sessionInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sessionInfo) {
            $userRole = $sessionInfo['user_role'];
            $userId = $sessionInfo['user_id'];
            error_log("SSE DEBUG: Found user in session tracking - Role: $userRole, User ID: $userId");
        } else {
            error_log("SSE DEBUG: No active session found in tracking table for: " . $sessionId);
        }
        
    } catch (Exception $e) {
        error_log("SSE DEBUG: Error checking session tracking: " . $e->getMessage());
    }
}

// Main SSE loop
$lastStatsHash = '';
$lastActivityHash = '';
$lastSupportHash = '';
$lastAdminReplyHash = '';
$lastSupportCheck = date('Y-m-d H:i:s', time() - 60); // Check last minute initially

// Send initial connection confirmation
sendSSEData('connected', [
    'message' => 'Real-time connection established', 
    'user_role' => $userRole,
    'user_id' => $userId,
    'timestamp' => time()
]);

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
    
    // Check for real-time notifications
    $realtimeNotifications = getRealtimeNotifications();
    if (!empty($realtimeNotifications)) {
        if (!sendSSEData('realtime_notifications', $realtimeNotifications)) {
            break; // Client disconnected
        }
    }
    
    // Check for events based on user role
    if (in_array($userRole, ['admin', 'developer'])) {
        // Check for new support messages (for admins)
        $currentSupportMessages = getNewSupportMessages($lastSupportCheck);
        $supportHash = md5(json_encode($currentSupportMessages));
        if ($supportHash !== $lastSupportHash && !empty($currentSupportMessages)) {
            if (!sendSSEData('support_messages_update', [
                'messages' => $currentSupportMessages,
                'unread_count' => count($currentSupportMessages)
            ])) {
                break; // Client disconnected
            }
            $lastSupportHash = $supportHash;
        }
    } elseif (($userRole === 'customer' || $userRole === 'user') && $userId) {
        // Check for new admin replies (for customers/users)
        $currentAdminReplies = getNewAdminReplies($userId, $lastSupportCheck);
        $adminReplyHash = md5(json_encode($currentAdminReplies));
        if ($adminReplyHash !== $lastAdminReplyHash && !empty($currentAdminReplies)) {
            error_log("SSE: Sending admin_replies_update to user $userId (role: $userRole)");
            if (!sendSSEData('admin_replies_update', [
                'messages' => $currentAdminReplies,
                'count' => count($currentAdminReplies)
            ])) {
                break; // Client disconnected
            }
            $lastAdminReplyHash = $adminReplyHash;
        }
    }
    
    $lastSupportCheck = date('Y-m-d H:i:s');
    
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
