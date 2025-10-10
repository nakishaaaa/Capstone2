<?php
/**
 * Pusher Configuration
 * Real-time messaging for Hostinger deployment
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Pusher\Pusher;

// Pusher credentials
define('PUSHER_APP_ID', '2060857');
define('PUSHER_KEY', '5f2e092f1e11a34b880f');
define('PUSHER_SECRET', 'f2403e9bcc17b2e517b4');
define('PUSHER_CLUSTER', 'ap1');

/**
 * Get Pusher instance
 * @return Pusher
 */
function getPusherInstance() {
    static $pusher = null;
    
    if ($pusher === null) {
        $pusher = new Pusher(
            PUSHER_KEY,
            PUSHER_SECRET,
            PUSHER_APP_ID,
            [
                'cluster' => PUSHER_CLUSTER,
                'useTLS' => true
            ]
        );
    }
    
    return $pusher;
}

/**
 * Trigger a Pusher event
 * @param string $channel Channel name
 * @param string $event Event name
 * @param array $data Event data
 * @return bool Success status
 */
function triggerPusherEvent($channel, $event, $data) {
    try {
        $pusher = getPusherInstance();
        $pusher->trigger($channel, $event, $data);
        return true;
    } catch (Exception $e) {
        error_log("Pusher Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger stats update for all admins/cashiers
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function triggerAdminStatsUpdate($conn) {
    try {
        // Get all admin/cashier user IDs
        $stmt = $conn->prepare("SELECT id FROM users WHERE role IN ('admin', 'cashier') AND status = 'active'");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $channels = [];
        while ($row = $result->fetch_assoc()) {
            $channels[] = "user-{$row['id']}";
        }
        
        if (empty($channels)) {
            return true;
        }
        
        // Get current stats
        $stats = [];
        
        // Today's sales
        $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total_sales FROM sales WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stats['sales'] = [
            'total_revenue' => floatval($result['total_sales']),
            'total_sales' => 0 // Can add count if needed
        ];
        
        // Pending requests
        $stmt = $conn->prepare("SELECT COUNT(*) as pending_requests FROM customer_requests WHERE status = 'pending'");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stats['requests'] = [
            'pending_requests' => intval($result['pending_requests'])
        ];
        
        // Inventory stats
        $stmt = $conn->prepare("SELECT COUNT(*) as low_stock FROM inventory WHERE stock <= 5 AND stock > 0");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $lowStock = intval($result['low_stock']);
        
        $stmt = $conn->prepare("SELECT COUNT(*) as total_products FROM inventory");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $totalProducts = intval($result['total_products']);
        
        $stats['inventory'] = [
            'low_stock_count' => $lowStock,
            'total_products' => $totalProducts
        ];
        
        // Notifications
        $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE is_read = 0");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stats['notifications'] = [
            'unread_notifications' => intval($result['unread'])
        ];
        
        $stats['timestamp'] = time();
        
        // Trigger event for all admin channels
        $pusher = getPusherInstance();
        $pusher->trigger($channels, 'stats-update', $stats);
        
        return true;
    } catch (Exception $e) {
        error_log("Pusher Admin Stats Update Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger developer dashboard notification
 * @param string $message Notification message
 * @param string $type Notification type (info, success, warning, error)
 * @param string $activityType Activity type for feed
 * @return bool Success status
 */
function triggerDeveloperNotification($message, $type = 'info', $activityType = 'default') {
    try {
        $data = [
            'message' => $message,
            'type' => $type,
            'timestamp' => time()
        ];
        
        // Send system alert
        $pusher = getPusherInstance();
        $pusher->trigger('developer-channel', 'system-alert', $data);
        
        // Send activity update
        $activityData = [
            'message' => $message,
            'type' => $activityType,
            'timestamp' => time()
        ];
        $pusher->trigger('developer-channel', 'user-activity', $activityData);
        
        return true;
    } catch (Exception $e) {
        error_log("Developer Notification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger support ticket notification for developer
 * @param string $message Optional custom message
 * @return bool Success status
 */
function triggerSupportTicketNotification($message = null) {
    try {
        $data = [
            'message' => $message ?: 'New support ticket received',
            'timestamp' => time()
        ];
        
        $pusher = getPusherInstance();
        $pusher->trigger('developer-channel', 'support-ticket', $data);
        
        return true;
    } catch (Exception $e) {
        error_log("Support Ticket Notification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger system maintenance notification
 * @param string $action Maintenance action (start, end, backup, etc.)
 * @param string $details Additional details
 * @return bool Success status
 */
function triggerMaintenanceNotification($action, $details = '') {
    try {
        $messages = [
            'start' => 'System maintenance started',
            'end' => 'System maintenance completed',
            'backup' => 'System backup completed',
            'update' => 'System update completed'
        ];
        
        $message = $messages[$action] ?? "System maintenance: {$action}";
        if ($details) {
            $message .= " - {$details}";
        }
        
        return triggerDeveloperNotification($message, 'info', 'maintenance');
    } catch (Exception $e) {
        error_log("Maintenance Notification Error: " . $e->getMessage());
        return false;
    }
}

?>
