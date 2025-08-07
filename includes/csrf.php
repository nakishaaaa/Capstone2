<?php
class CSRFToken {
    public static function generate() {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    public static function validate($token) {
        if (!isset($_SESSION)) {
            session_start();
        }
        // Check if token exists in session
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        // Check if token has expired (1 hour)
        if (time() - $_SESSION['csrf_token_time'] > 3600) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
            return false;
        }
        // Validate token
        $valid = hash_equals($_SESSION['csrf_token'], $token);
        return $valid;
    }
    
    public static function getToken() {
        if (!isset($_SESSION)) {
            session_start();
        }
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return self::generate();
        }
        
        // Check if token has expired
        if (time() - $_SESSION['csrf_token_time'] > 3600) {
            return self::generate();
        }
        return $_SESSION['csrf_token'];
    }
}
?>
