<?php
/**
 * Cron Job: Cleanup Unverified Accounts
 * 
 * This script should be run periodically (e.g., every hour) to clean up
 * unverified accounts that have expired.
 * 
 * Usage:
 * - Via cron: php /path/to/cleanup_unverified_accounts.php
 * - Via web: http://yoursite.com/cron/cleanup_unverified_accounts.php?key=your_secret_key
 */

// Security key for web-based execution (optional)
$CRON_SECRET_KEY = 'unverified_cleanup_2024_secure_key';

// Check if running via web with security key
if (isset($_GET['key'])) {
    if ($_GET['key'] !== $CRON_SECRET_KEY) {
        http_response_code(403);
        die('Access denied');
    }
    header('Content-Type: application/json');
}

// Set up error reporting for cron
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/unverified_account_cleanup.php';

// Configuration
$CLEANUP_HOURS = 24; // Delete accounts unverified for 24+ hours
$REMINDER_HOURS = 2;  // Send reminder 2 hours before deletion
$LOG_FILE = __DIR__ . '/../logs/unverified_cleanup.log';

// Ensure logs directory exists
$log_dir = dirname($LOG_FILE);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

/**
 * Log message with timestamp
 */
function logMessage($message, $level = 'INFO') {
    global $LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    file_put_contents($LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Also log to error_log for system logs
    error_log("Unverified Account Cleanup: {$message}");
}

/**
 * Main cleanup execution
 */
function executeCleanup() {
    global $CLEANUP_HOURS, $REMINDER_HOURS;
    
    try {
        logMessage("Starting unverified account cleanup process");
        
        // Get database connection
        $pdo = Database::getConnection();
        $cleanup = new UnverifiedAccountCleanup($pdo, $CLEANUP_HOURS);
        
        // Get initial statistics
        $stats = $cleanup->getUnverifiedAccountStats();
        if ($stats['success']) {
            logMessage("Initial stats - Total unverified: {$stats['total_unverified']}, Expired: {$stats['expired_ready_for_cleanup']}, Recent: {$stats['recent_unverified']}");
        }
        
        // Send reminder emails first (optional)
        if ($REMINDER_HOURS > 0) {
            logMessage("Sending reminder emails to accounts expiring in {$REMINDER_HOURS} hours");
            $reminder_result = $cleanup->sendReminderEmails($REMINDER_HOURS);
            if ($reminder_result['success']) {
                logMessage("Reminder emails: {$reminder_result['sent_count']} sent out of {$reminder_result['reminder_count']} candidates");
            } else {
                logMessage("Reminder email error: " . $reminder_result['error'], 'ERROR');
            }
        }
        
        // Perform cleanup
        logMessage("Cleaning up accounts unverified for {$CLEANUP_HOURS}+ hours");
        $cleanup_result = $cleanup->cleanupExpiredAccounts();
        
        if ($cleanup_result['success']) {
            if ($cleanup_result['deleted_count'] > 0) {
                logMessage("Successfully deleted {$cleanup_result['deleted_count']} expired unverified accounts");
                
                // Log details of deleted accounts
                foreach ($cleanup_result['accounts'] as $account) {
                    logMessage("Deleted: {$account['username']} ({$account['email']}) - Role: {$account['role']} - Created: {$account['created_at']}");
                }
            } else {
                logMessage("No expired unverified accounts found for cleanup");
            }
        } else {
            logMessage("Cleanup failed: " . $cleanup_result['error'], 'ERROR');
            return $cleanup_result;
        }
        
        // Get final statistics
        $final_stats = $cleanup->getUnverifiedAccountStats();
        if ($final_stats['success']) {
            logMessage("Final stats - Total unverified: {$final_stats['total_unverified']}, Expired: {$final_stats['expired_ready_for_cleanup']}, Recent: {$final_stats['recent_unverified']}");
        }
        
        logMessage("Cleanup process completed successfully");
        
        return [
            'success' => true,
            'deleted_count' => $cleanup_result['deleted_count'],
            'reminder_sent' => $reminder_result['sent_count'] ?? 0,
            'message' => 'Cleanup completed successfully',
            'stats' => $final_stats
        ];
        
    } catch (Exception $e) {
        $error_message = "Cleanup process failed: " . $e->getMessage();
        logMessage($error_message, 'ERROR');
        
        return [
            'success' => false,
            'error' => $error_message
        ];
    }
}

// Execute cleanup
$result = executeCleanup();

// Output result
if (isset($_GET['key'])) {
    // Web-based execution - return JSON
    echo json_encode($result);
} else {
    // CLI execution - print result
    if ($result['success']) {
        echo "Cleanup completed successfully.\n";
        echo "Deleted accounts: " . $result['deleted_count'] . "\n";
        if (isset($result['reminder_sent'])) {
            echo "Reminder emails sent: " . $result['reminder_sent'] . "\n";
        }
    } else {
        echo "Cleanup failed: " . $result['error'] . "\n";
        exit(1);
    }
}

// Example crontab entry:
// # Run every hour to cleanup unverified accounts
// 0 * * * * /usr/bin/php /path/to/your/project/cron/cleanup_unverified_accounts.php

?>
