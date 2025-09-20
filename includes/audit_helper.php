<?php
/**
 * Audit Helper Functions
 * Provides centralized audit logging functionality
 */

require_once 'config.php';

/**
 * Log an audit event
 * @param int|null $user_id User ID (null for system events)
 * @param string $action Action performed (login, logout, etc.)
 * @param string $description Detailed description of the action
 * @param string|null $ip_address IP address of the user
 * @param string|null $user_agent User agent string
 * @return bool Success status
 */
function logAuditEvent($user_id, $action, $description, $ip_address = null, $user_agent = null) {
    global $conn;
    
    // Get IP address if not provided
    if ($ip_address === null) {
        $ip_address = getUserIP();
    }
    
    // Get user agent if not provided
    if ($user_agent === null) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    }
    
    // Prepare the SQL statement
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    
    if ($stmt) {
        $stmt->bind_param("issss", $user_id, $action, $description, $ip_address, $user_agent);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    return false;
}

/**
 * Get user's IP address
 * @return string IP address
 */
function getUserIP() {
    // Check for shared internet/proxy
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    // Check for IP passed from proxy
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    // Check for IP from remote address
    elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    }
    
    return 'unknown';
}

/**
 * Log user login event
 * @param int $user_id User ID
 * @param string $username Username
 * @param string $role User role
 * @return bool Success status
 */
function logLoginEvent($user_id, $username, $role) {
    $description = "User '{$username}' (Role: {$role}) logged in successfully";
    return logAuditEvent($user_id, 'login', $description);
}

/**
 * Log user logout event
 * @param int $user_id User ID
 * @param string $username Username
 * @param string $role User role
 * @return bool Success status
 */
function logLogoutEvent($user_id, $username, $role) {
    $description = "User '{$username}' (Role: {$role}) logged out";
    return logAuditEvent($user_id, 'logout', $description);
}

/**
 * Log failed login attempt
 * @param string $username Attempted username
 * @param string $reason Failure reason
 * @return bool Success status
 */
function logFailedLoginEvent($username, $reason = 'Invalid credentials') {
    $description = "Failed login attempt for username '{$username}': {$reason}";
    
    // Log to audit_logs table only (no more login_attempts table)
    return logAuditEvent(null, 'login_failed', $description);
}


/**
 * Log user registration event
 * @param int $user_id User ID
 * @param string $username Username
 * @return bool Success status
 */
function logRegistrationEvent($user_id, $username) {
    $description = "New user '{$username}' registered successfully";
    return logAuditEvent($user_id, 'user_registered', $description);
}

/**
 * Log password reset event
 * @param int $user_id User ID
 * @param string $username Username
 * @return bool Success status
 */
function logPasswordResetEvent($user_id, $username) {
    $description = "Password reset completed for user '{$username}'";
    return logAuditEvent($user_id, 'password_reset', $description);
}
?>
