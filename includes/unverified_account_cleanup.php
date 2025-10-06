<?php

/**
 * Unverified Account Cleanup Service
 * Handles cleanup of expired unverified user accounts
 */

class UnverifiedAccountCleanup {
    private $pdo;
    private $cleanup_hours;
    
    public function __construct($pdo, $cleanup_hours = 24) {
        $this->pdo = $pdo;
        $this->cleanup_hours = $cleanup_hours; // Default: 24 hours
    }
    
    /**
     * Clean up expired unverified accounts
     * @return array Cleanup results
     */
    public function cleanupExpiredAccounts() {
        try {
            // Find unverified accounts older than cleanup_hours
            $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$this->cleanup_hours} hours"));
            
            // First, get the accounts that will be deleted for logging
            $selectStmt = $this->pdo->prepare("
                SELECT id, username, email, role, created_at 
                FROM users 
                WHERE is_email_verified = FALSE 
                AND email_verification_token IS NOT NULL 
                AND created_at < ?
                AND role != 'developer'
            ");
            $selectStmt->execute([$cutoff_time]);
            $expiredAccounts = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($expiredAccounts)) {
                return [
                    'success' => true,
                    'deleted_count' => 0,
                    'message' => 'No expired unverified accounts found',
                    'accounts' => []
                ];
            }
            
            // Delete the expired unverified accounts
            $deleteStmt = $this->pdo->prepare("
                DELETE FROM users 
                WHERE is_email_verified = FALSE 
                AND email_verification_token IS NOT NULL 
                AND created_at < ?
                AND role != 'developer'
            ");
            $deleteStmt->execute([$cutoff_time]);
            
            $deletedCount = $deleteStmt->rowCount();
            
            // Log the cleanup action
            $this->logCleanupAction($expiredAccounts, $deletedCount);
            
            return [
                'success' => true,
                'deleted_count' => $deletedCount,
                'message' => "Cleaned up {$deletedCount} expired unverified accounts",
                'accounts' => $expiredAccounts,
                'cutoff_time' => $cutoff_time
            ];
            
        } catch (Exception $e) {
            error_log("Unverified account cleanup error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'deleted_count' => 0
            ];
        }
    }
    
    /**
     * Get statistics about unverified accounts
     * @return array Statistics
     */
    public function getUnverifiedAccountStats() {
        try {
            $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$this->cleanup_hours} hours"));
            
            // Total unverified accounts
            $totalStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total 
                FROM users 
                WHERE is_email_verified = FALSE 
                AND email_verification_token IS NOT NULL
                AND role != 'developer'
            ");
            $totalStmt->execute();
            $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Expired unverified accounts
            $expiredStmt = $this->pdo->prepare("
                SELECT COUNT(*) as expired 
                FROM users 
                WHERE is_email_verified = FALSE 
                AND email_verification_token IS NOT NULL 
                AND created_at < ?
                AND role != 'developer'
            ");
            $expiredStmt->execute([$cutoff_time]);
            $expired = $expiredStmt->fetch(PDO::FETCH_ASSOC)['expired'];
            
            // Recent unverified accounts (within cleanup window)
            $recent = $total - $expired;
            
            return [
                'success' => true,
                'total_unverified' => $total,
                'expired_ready_for_cleanup' => $expired,
                'recent_unverified' => $recent,
                'cleanup_hours' => $this->cleanup_hours,
                'cutoff_time' => $cutoff_time
            ];
            
        } catch (Exception $e) {
            error_log("Unverified account stats error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get list of unverified accounts with details
     * @param bool $expired_only Show only expired accounts
     * @return array List of accounts
     */
    public function getUnverifiedAccountsList($expired_only = false) {
        try {
            $sql = "
                SELECT id, username, email, role, created_at,
                       TIMESTAMPDIFF(HOUR, created_at, NOW()) as hours_since_creation
                FROM users 
                WHERE is_email_verified = FALSE 
                AND email_verification_token IS NOT NULL
                AND role != 'developer'
            ";
            
            $params = [];
            if ($expired_only) {
                $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$this->cleanup_hours} hours"));
                $sql .= " AND created_at < ?";
                $params[] = $cutoff_time;
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add status for each account
            foreach ($accounts as &$account) {
                $account['status'] = $account['hours_since_creation'] >= $this->cleanup_hours ? 'expired' : 'pending';
                $account['expires_in_hours'] = max(0, $this->cleanup_hours - $account['hours_since_creation']);
            }
            
            return [
                'success' => true,
                'accounts' => $accounts,
                'cleanup_hours' => $this->cleanup_hours
            ];
            
        } catch (Exception $e) {
            error_log("Unverified accounts list error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Manually delete a specific unverified account
     * @param int $user_id User ID to delete
     * @return array Result
     */
    public function deleteUnverifiedAccount($user_id) {
        try {
            // First check if account is unverified
            $checkStmt = $this->pdo->prepare("
                SELECT id, username, email, role, is_email_verified 
                FROM users 
                WHERE id = ? AND role != 'developer'
            ");
            $checkStmt->execute([$user_id]);
            $account = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$account) {
                return [
                    'success' => false,
                    'error' => 'Account not found or cannot be deleted'
                ];
            }
            
            if ($account['is_email_verified']) {
                return [
                    'success' => false,
                    'error' => 'Cannot delete verified accounts through this method'
                ];
            }
            
            // Delete the account
            $deleteStmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
            $deleteStmt->execute([$user_id]);
            
            if ($deleteStmt->rowCount() > 0) {
                $this->logCleanupAction([$account], 1, 'manual');
                
                return [
                    'success' => true,
                    'message' => "Deleted unverified account: {$account['username']} ({$account['email']})"
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to delete account'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Manual account deletion error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Log cleanup actions for audit trail
     * @param array $accounts Deleted accounts
     * @param int $count Number of accounts deleted
     * @param string $type Type of cleanup (auto/manual)
     */
    private function logCleanupAction($accounts, $count, $type = 'auto') {
        try {
            $logMessage = "Unverified account cleanup ({$type}): Deleted {$count} accounts";
            if (!empty($accounts)) {
                $usernames = array_column($accounts, 'username');
                $logMessage .= " - Users: " . implode(', ', $usernames);
            }
            
            error_log($logMessage);
            
            // If audit_helper is available, use it
            if (function_exists('logAuditEvent')) {
                logAuditEvent(null, 'unverified_account_cleanup', $logMessage);
            }
            
        } catch (Exception $e) {
            error_log("Cleanup logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Send reminder email to unverified accounts before deletion
     * @param int $reminder_hours Hours before deletion to send reminder
     * @return array Result
     */
    public function sendReminderEmails($reminder_hours = 2) {
        try {
            // Find accounts that will expire in reminder_hours
            $reminder_cutoff = date('Y-m-d H:i:s', strtotime("-" . ($this->cleanup_hours - $reminder_hours) . " hours"));
            $deletion_cutoff = date('Y-m-d H:i:s', strtotime("-{$this->cleanup_hours} hours"));
            
            $stmt = $this->pdo->prepare("
                SELECT id, username, email, email_verification_token, created_at
                FROM users 
                WHERE is_email_verified = FALSE 
                AND email_verification_token IS NOT NULL 
                AND created_at < ? 
                AND created_at >= ?
                AND role != 'developer'
            ");
            $stmt->execute([$reminder_cutoff, $deletion_cutoff]);
            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $sent_count = 0;
            foreach ($accounts as $account) {
                if ($this->sendReminderEmail($account, $reminder_hours)) {
                    $sent_count++;
                }
            }
            
            return [
                'success' => true,
                'reminder_count' => count($accounts),
                'sent_count' => $sent_count,
                'message' => "Sent {$sent_count} reminder emails"
            ];
            
        } catch (Exception $e) {
            error_log("Reminder email error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send individual reminder email
     * @param array $account Account details
     * @param int $hours_remaining Hours until deletion
     * @return bool Success
     */
    private function sendReminderEmail($account, $hours_remaining) {
        try {
            if (!function_exists('sendVerificationReminderEmail')) {
                return false; // Function not available
            }
            
            return sendVerificationReminderEmail(
                $account['email'], 
                $account['username'], 
                $account['email_verification_token'],
                $hours_remaining
            );
            
        } catch (Exception $e) {
            error_log("Individual reminder email error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Quick cleanup function for cron jobs
 * @param int $cleanup_hours Hours after which to delete unverified accounts
 * @return array Result
 */
function cleanupUnverifiedAccounts($cleanup_hours = 24) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = Database::getConnection();
        
        $cleanup = new UnverifiedAccountCleanup($pdo, $cleanup_hours);
        return $cleanup->cleanupExpiredAccounts();
        
    } catch (Exception $e) {
        error_log("Quick cleanup error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

?>
