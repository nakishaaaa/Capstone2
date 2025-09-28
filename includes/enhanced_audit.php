<?php
/**
 * Enhanced Audit Logging System
 * Provides comprehensive error tracking and monitoring capabilities
 */

require_once __DIR__ . '/audit_helper.php';

class EnhancedAudit {
    
    /**
     * Log file operation errors
     */
    public static function logFileOperation($operation, $fileName, $success, $errorDetails = null, $userId = null) {
        $action = $success ? "file_{$operation}_success" : "file_{$operation}_error";
        $description = $success 
            ? "File $operation successful: $fileName"
            : "File $operation failed: $fileName" . ($errorDetails ? " - $errorDetails" : "");
            
        logAuditEvent($userId, $action, $description);
    }
    
    /**
     * Log database operation issues
     */
    public static function logDatabaseOperation($operation, $table, $affectedRows, $expectedRows = null, $userId = null) {
        if ($expectedRows !== null && $affectedRows !== $expectedRows) {
            $action = "db_{$operation}_warning";
            $description = "Database $operation on $table: expected $expectedRows rows, got $affectedRows";
            logAuditEvent($userId, $action, $description);
        } elseif ($affectedRows === 0 && $operation !== 'select') {
            $action = "db_{$operation}_no_effect";
            $description = "Database $operation on $table affected 0 rows";
            logAuditEvent($userId, $action, $description);
        }
    }
    
    /**
     * Log API call failures
     */
    public static function logApiFailure($endpoint, $method, $responseCode, $errorMessage, $userId = null) {
        $action = "api_failure";
        $description = "API call failed: $method $endpoint (HTTP $responseCode) - $errorMessage";
        logAuditEvent($userId, $action, $description);
    }
    
    /**
     * Log payment processing issues
     */
    public static function logPaymentIssue($paymentId, $amount, $status, $errorDetails, $userId = null) {
        $action = "payment_error";
        $description = "Payment processing issue: ID $paymentId, Amount $amount, Status $status - $errorDetails";
        logAuditEvent($userId, $action, $description);
    }
    
    /**
     * Log email sending failures
     */
    public static function logEmailFailure($recipient, $subject, $errorMessage, $userId = null) {
        $action = "email_failure";
        $description = "Email sending failed: To $recipient, Subject '$subject' - $errorMessage";
        logAuditEvent($userId, $action, $description);
    }
    
    /**
     * Log session issues
     */
    public static function logSessionIssue($issueType, $details, $userId = null) {
        $action = "session_$issueType";
        $description = "Session issue: $issueType - $details";
        logAuditEvent($userId, $action, $description);
    }
    
    /**
     * Log form validation failures
     */
    public static function logValidationFailure($formName, $field, $value, $rule, $userId = null) {
        $action = "validation_failure";
        $description = "Form validation failed: $formName.$field = '$value' (rule: $rule)";
        logAuditEvent($userId, $action, $description);
    }
    
    /**
     * Log business logic errors
     */
    public static function logBusinessLogicError($process, $errorType, $details, $userId = null) {
        $action = "business_logic_error";
        $description = "Business logic error in $process: $errorType - $details";
        logAuditEvent($userId, $action, $description);
    }
    
    /**
     * Log performance issues
     */
    public static function logPerformanceIssue($operation, $duration, $threshold, $details = null, $userId = null) {
        if ($duration > $threshold) {
            $action = "performance_warning";
            $description = "Slow operation: $operation took {$duration}ms (threshold: {$threshold}ms)";
            if ($details) $description .= " - $details";
            logAuditEvent($userId, $action, $description);
        }
    }
    
    /**
     * Log security concerns
     */
    public static function logSecurityConcern($type, $details, $severity = 'medium', $userId = null) {
        $action = "security_$type";
        $description = "Security concern ($severity): $details";
        logAuditEvent($userId, $action, $description);
    }
    
    /**
     * Safe wrapper for audit logging that won't break main functionality
     */
    public static function safeLog($callback, ...$args) {
        try {
            call_user_func($callback, ...$args);
        } catch (Exception $e) {
            // Silent fail - log to PHP error log but don't break main functionality
            error_log("Enhanced audit logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Monitor function execution and log errors
     */
    public static function monitorExecution($functionName, $callback, $userId = null) {
        $startTime = microtime(true);
        
        try {
            $result = $callback();
            
            $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            
            // Log if execution took longer than 5 seconds
            if ($duration > 5000) {
                self::logPerformanceIssue($functionName, $duration, 5000, null, $userId);
            }
            
            return $result;
            
        } catch (Exception $e) {
            $duration = (microtime(true) - $startTime) * 1000;
            
            self::safeLog([self::class, 'logBusinessLogicError'], 
                $functionName, 
                'exception', 
                $e->getMessage() . " (took {$duration}ms)", 
                $userId
            );
            
            throw $e; // Re-throw to maintain original behavior
        }
    }
}

/**
 * Global helper functions for easy access
 */

function logFileError($operation, $fileName, $errorDetails, $userId = null) {
    EnhancedAudit::safeLog([EnhancedAudit::class, 'logFileOperation'], $operation, $fileName, false, $errorDetails, $userId);
}

function logDbWarning($operation, $table, $affectedRows, $expectedRows = null, $userId = null) {
    EnhancedAudit::safeLog([EnhancedAudit::class, 'logDatabaseOperation'], $operation, $table, $affectedRows, $expectedRows, $userId);
}

function logApiError($endpoint, $method, $responseCode, $errorMessage, $userId = null) {
    EnhancedAudit::safeLog([EnhancedAudit::class, 'logApiFailure'], $endpoint, $method, $responseCode, $errorMessage, $userId);
}

function logPaymentError($paymentId, $amount, $status, $errorDetails, $userId = null) {
    EnhancedAudit::safeLog([EnhancedAudit::class, 'logPaymentIssue'], $paymentId, $amount, $status, $errorDetails, $userId);
}

function logEmailError($recipient, $subject, $errorMessage, $userId = null) {
    EnhancedAudit::safeLog([EnhancedAudit::class, 'logEmailFailure'], $recipient, $subject, $errorMessage, $userId);
}

function monitorFunction($functionName, $callback, $userId = null) {
    return EnhancedAudit::monitorExecution($functionName, $callback, $userId);
}
?>
