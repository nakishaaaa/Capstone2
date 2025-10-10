<?php
/**
 * Enhanced Automatic System Cleanup
 * Runs once per day automatically when dashboard is accessed
 * Handles audit logs and notification cleanup with professional logging
 * Zero configuration, error-proof implementation
 */

// Only run if we have a database connection and user is logged in
if (isset($conn) && isset($_SESSION['user_id']) && $_SESSION['role'] === 'developer') {
    
    // Check if cleanup should run today
    $lastRunFile = __DIR__ . '/../logs/last_cleanup_date.txt';
    $today = date('Y-m-d');
    $shouldRun = true;
    
    if (file_exists($lastRunFile)) {
        $lastRunDate = trim(file_get_contents($lastRunFile));
        $shouldRun = ($lastRunDate !== $today);
    }
    
    if ($shouldRun) {
        $cleanupResults = [];
        $totalCleaned = 0;
        
        try {
            // Create logs directory if it doesn't exist
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            // === AUDIT LOGS CLEANUP (60 days) ===
            $auditCutoffDate = date('Y-m-d H:i:s', strtotime('-60 days'));
            
            // Count audit records to be deleted
            $auditCountStmt = $conn->prepare("SELECT COUNT(*) as count FROM audit_logs WHERE created_at < ?");
            $auditCountStmt->bind_param('s', $auditCutoffDate);
            $auditCountStmt->execute();
            $auditResult = $auditCountStmt->get_result();
            $auditRecordsToDelete = $auditResult->fetch_assoc()['count'];
            
            if ($auditRecordsToDelete > 0) {
                // Delete old audit records
                $auditDeleteStmt = $conn->prepare("DELETE FROM audit_logs WHERE created_at < ?");
                $auditDeleteStmt->bind_param('s', $auditCutoffDate);
                $auditDeleteStmt->execute();
                
                $cleanupResults[] = "Audit Logs: Deleted {$auditRecordsToDelete} records older than 60 days";
                $totalCleaned += $auditRecordsToDelete;
            } else {
                $cleanupResults[] = "Audit Logs: No records older than 60 days found";
            }
            
            // === NOTIFICATIONS CLEANUP (Tiered Retention) ===
            $notificationsCleaned = 0;
            
            // Check if notifications table exists
            $tableCheckStmt = $conn->prepare("SHOW TABLES LIKE 'notifications'");
            $tableCheckStmt->execute();
            $notificationsTableExists = $tableCheckStmt->get_result()->num_rows > 0;
            
            if ($notificationsTableExists) {
                // Clean read notifications older than 30 days
                $readCutoffDate = date('Y-m-d H:i:s', strtotime('-30 days'));
                $readCountStmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE is_read = 1 AND created_at < ?");
                $readCountStmt->bind_param('s', $readCutoffDate);
                $readCountStmt->execute();
                $readResult = $readCountStmt->get_result();
                $readNotificationsToDelete = $readResult->fetch_assoc()['count'];
                
                if ($readNotificationsToDelete > 0) {
                    $readDeleteStmt = $conn->prepare("DELETE FROM notifications WHERE is_read = 1 AND created_at < ?");
                    $readDeleteStmt->bind_param('s', $readCutoffDate);
                    $readDeleteStmt->execute();
                    $notificationsCleaned += $readNotificationsToDelete;
                }
                
                // Clean unread notifications older than 60 days (longer retention)
                $unreadCutoffDate = date('Y-m-d H:i:s', strtotime('-60 days'));
                $unreadCountStmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE (is_read = 0 OR is_read IS NULL) AND created_at < ?");
                $unreadCountStmt->bind_param('s', $unreadCutoffDate);
                $unreadCountStmt->execute();
                $unreadResult = $unreadCountStmt->get_result();
                $unreadNotificationsToDelete = $unreadResult->fetch_assoc()['count'];
                
                if ($unreadNotificationsToDelete > 0) {
                    $unreadDeleteStmt = $conn->prepare("DELETE FROM notifications WHERE (is_read = 0 OR is_read IS NULL) AND created_at < ?");
                    $unreadDeleteStmt->bind_param('s', $unreadCutoffDate);
                    $unreadDeleteStmt->execute();
                    $notificationsCleaned += $unreadNotificationsToDelete;
                }
                
                if ($notificationsCleaned > 0) {
                    $cleanupResults[] = "Notifications: Deleted {$notificationsCleaned} notifications (Read: 30+ days, Unread: 60+ days)";
                    $totalCleaned += $notificationsCleaned;
                } else {
                    $cleanupResults[] = "Notifications: No expired notifications found";
                }
            } else {
                $cleanupResults[] = "Notifications: Table not found, skipping cleanup";
            }
            
            // === REALTIME NOTIFICATIONS CLEANUP (7 days) ===
            $realtimeTableCheckStmt = $conn->prepare("SHOW TABLES LIKE 'realtime_notifications'");
            $realtimeTableCheckStmt->execute();
            $realtimeTableExists = $realtimeTableCheckStmt->get_result()->num_rows > 0;
            
            if ($realtimeTableExists) {
                $realtimeCutoffDate = date('Y-m-d H:i:s', strtotime('-7 days'));
                $realtimeCountStmt = $conn->prepare("SELECT COUNT(*) as count FROM realtime_notifications WHERE created_at < ?");
                $realtimeCountStmt->bind_param('s', $realtimeCutoffDate);
                $realtimeCountStmt->execute();
                $realtimeResult = $realtimeCountStmt->get_result();
                $realtimeNotificationsToDelete = $realtimeResult->fetch_assoc()['count'];
                
                if ($realtimeNotificationsToDelete > 0) {
                    $realtimeDeleteStmt = $conn->prepare("DELETE FROM realtime_notifications WHERE created_at < ?");
                    $realtimeDeleteStmt->bind_param('s', $realtimeCutoffDate);
                    $realtimeDeleteStmt->execute();
                    
                    $cleanupResults[] = "Realtime Notifications: Deleted {$realtimeNotificationsToDelete} records older than 7 days";
                    $totalCleaned += $realtimeNotificationsToDelete;
                } else {
                    $cleanupResults[] = "Realtime Notifications: No records older than 7 days found";
                }
            } else {
                $cleanupResults[] = "Realtime Notifications: Table not found, skipping cleanup";
            }
            
            // === COMPREHENSIVE LOGGING ===
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "\n=== SYSTEM CLEANUP REPORT - {$timestamp} ===\n";
            $logMessage .= "Total Records Cleaned: {$totalCleaned}\n";
            $logMessage .= "Cleanup Details:\n";
            
            foreach ($cleanupResults as $result) {
                $logMessage .= "  • {$result}\n";
            }
            
            $logMessage .= "Cleanup Status: " . ($totalCleaned > 0 ? "SUCCESS" : "NO ACTION NEEDED") . "\n";
            $logMessage .= "Next Cleanup: " . date('Y-m-d', strtotime('+1 day')) . "\n";
            $logMessage .= "=== END CLEANUP REPORT ===\n\n";
            
            // Log the cleanup activity
            file_put_contents(__DIR__ . '/../logs/system_cleanup.log', $logMessage, FILE_APPEND | LOCK_EX);
            
            // Update last run date
            file_put_contents($lastRunFile, $today);
            
            // Optional: Log to error log for monitoring
            if ($totalCleaned > 0) {
                error_log("System Cleanup: Successfully cleaned {$totalCleaned} records");
            }
            
        } catch (Exception $e) {
            // Professional error handling with detailed logging
            $errorMessage = date('Y-m-d H:i:s') . " - CLEANUP ERROR: " . $e->getMessage() . "\n";
            $errorMessage .= "Stack Trace: " . $e->getTraceAsString() . "\n\n";
            
            // Log error to both files
            file_put_contents(__DIR__ . '/../logs/system_cleanup.log', $errorMessage, FILE_APPEND | LOCK_EX);
            error_log("System cleanup failed: " . $e->getMessage());
        }
    }
}
?>
