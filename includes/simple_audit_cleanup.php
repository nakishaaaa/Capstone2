<?php
/**
 * Simple Automatic Audit Cleanup
 * Runs once per day automatically when dashboard is accessed
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
        try {
            // Simple cleanup query - delete audit logs older than 60 days
            $cutoffDate = date('Y-m-d H:i:s', strtotime('-60 days'));
            
            // Count records to be deleted
            $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM audit_logs WHERE created_at < ?");
            $countStmt->bind_param('s', $cutoffDate);
            $countStmt->execute();
            $result = $countStmt->get_result();
            $recordsToDelete = $result->fetch_assoc()['count'];
            
            if ($recordsToDelete > 0) {
                // Delete old records
                $deleteStmt = $conn->prepare("DELETE FROM audit_logs WHERE created_at < ?");
                $deleteStmt->bind_param('s', $cutoffDate);
                $deleteStmt->execute();
                
                // Log the cleanup
                $logMessage = date('Y-m-d H:i:s') . " - Deleted {$recordsToDelete} audit records older than 60 days\n";
            } else {
                // Log that no cleanup was needed
                $logMessage = date('Y-m-d H:i:s') . " - No audit records older than 60 days found\n";
            }
            
            // Create logs directory if it doesn't exist
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            // Log the cleanup activity
            file_put_contents(__DIR__ . '/../logs/audit_cleanup.log', $logMessage, FILE_APPEND | LOCK_EX);
            
            // Update last run date
            file_put_contents($lastRunFile, $today);
            
        } catch (Exception $e) {
            // Silent fail - don't break anything
            error_log("Simple audit cleanup failed: " . $e->getMessage());
        }
    }
}
?>
