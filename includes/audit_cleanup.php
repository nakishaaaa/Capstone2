<?php
/**
 * Audit Trail Cleanup Service
 * Automatically removes audit logs older than 60 days
 * Maintains system performance while preserving necessary audit history
 */

// Only include database if not already included and not called from auto cleanup
if (!isset($conn) && !defined('AUDIT_CLEANUP_INCLUDED')) {
    require_once '../config/database.php';
}

class AuditCleanup {
    private $conn;
    private $retentionDays = 60;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Clean up audit logs older than 60 days
     * @return array Results of cleanup operation
     */
    public function cleanupAuditLogs() {
        $results = [
            'success' => false,
            'deleted_count' => 0,
            'retention_days' => $this->retentionDays,
            'cutoff_date' => '',
            'message' => ''
        ];
        
        try {
            // Calculate cutoff date (60 days ago)
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$this->retentionDays} days"));
            $results['cutoff_date'] = $cutoffDate;
            
            // Count records to be deleted (for logging)
            $countStmt = $this->conn->prepare("
                SELECT COUNT(*) as count 
                FROM audit_logs 
                WHERE created_at < ?
            ");
            $countStmt->bind_param('s', $cutoffDate);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $recordsToDelete = $countResult->fetch_assoc()['count'];
            
            if ($recordsToDelete > 0) {
                // Delete old audit logs
                $deleteStmt = $this->conn->prepare("
                    DELETE FROM audit_logs 
                    WHERE created_at < ?
                ");
                $deleteStmt->bind_param('s', $cutoffDate);
                
                if ($deleteStmt->execute()) {
                    $results['success'] = true;
                    $results['deleted_count'] = $recordsToDelete;
                    $results['message'] = "Successfully deleted {$recordsToDelete} audit logs older than {$this->retentionDays} days";
                    
                    // Log the cleanup action
                    $this->logCleanupAction($recordsToDelete, $cutoffDate);
                } else {
                    $results['message'] = "Failed to delete audit logs: " . $this->conn->error;
                }
                
                $deleteStmt->close();
            } else {
                $results['success'] = true;
                $results['message'] = "No audit logs older than {$this->retentionDays} days found";
            }
            
            $countStmt->close();
            
        } catch (Exception $e) {
            $results['message'] = "Error during cleanup: " . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Log the cleanup action for audit purposes
     */
    private function logCleanupAction($deletedCount, $cutoffDate) {
        try {
            $logStmt = $this->conn->prepare("
                INSERT INTO audit_logs (user_id, action, description, ip_address, created_at) 
                VALUES (NULL, 'audit_cleanup', ?, 'system', NOW())
            ");
            
            $description = "Automated audit cleanup: Deleted {$deletedCount} records older than {$cutoffDate}";
            $logStmt->bind_param('s', $description);
            $logStmt->execute();
            $logStmt->close();
        } catch (Exception $e) {
            // Silent fail for logging - don't interrupt cleanup process
            error_log("Failed to log audit cleanup: " . $e->getMessage());
        }
    }
    
    /**
     * Get audit log statistics
     */
    public function getAuditStats() {
        $stats = [
            'total_records' => 0,
            'records_last_30_days' => 0,
            'records_last_60_days' => 0,
            'oldest_record' => null,
            'newest_record' => null
        ];
        
        try {
            // Total records
            $totalStmt = $this->conn->query("SELECT COUNT(*) as count FROM audit_logs");
            if ($totalStmt) {
                $stats['total_records'] = $totalStmt->fetch_assoc()['count'];
            }
            
            // Records in last 30 days
            $recent30Stmt = $this->conn->query("
                SELECT COUNT(*) as count 
                FROM audit_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            if ($recent30Stmt) {
                $stats['records_last_30_days'] = $recent30Stmt->fetch_assoc()['count'];
            }
            
            // Records in last 60 days
            $recent60Stmt = $this->conn->query("
                SELECT COUNT(*) as count 
                FROM audit_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            ");
            if ($recent60Stmt) {
                $stats['records_last_60_days'] = $recent60Stmt->fetch_assoc()['count'];
            }
            
            // Oldest and newest records
            $rangeStmt = $this->conn->query("
                SELECT 
                    MIN(created_at) as oldest,
                    MAX(created_at) as newest
                FROM audit_logs
            ");
            if ($rangeStmt) {
                $range = $rangeStmt->fetch_assoc();
                $stats['oldest_record'] = $range['oldest'];
                $stats['newest_record'] = $range['newest'];
            }
            
        } catch (Exception $e) {
            error_log("Error getting audit stats: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Manual cleanup trigger (for admin interface)
     */
    public function manualCleanup() {
        $results = $this->cleanupAuditLogs();
        
        // Add manual trigger info
        $results['triggered_by'] = 'manual';
        $results['timestamp'] = date('Y-m-d H:i:s');
        
        return $results;
    }
}

// If called directly (for cron jobs) - only run if not included by another script
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME']) && !defined('AUDIT_CLEANUP_INCLUDED')) {
    $cleanup = new AuditCleanup($conn);
    $results = $cleanup->cleanupAuditLogs();
    
    echo json_encode($results, JSON_PRETTY_PRINT);
    
    // Log to file for cron monitoring
    $logFile = '../logs/audit_cleanup.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = date('Y-m-d H:i:s') . " - " . $results['message'] . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>
