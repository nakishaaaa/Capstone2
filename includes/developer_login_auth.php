<?php
/**
 * Developer Login Email Authentication
 * Simple email verification for developer accounts before login
 */

require_once __DIR__ . '/email_notifications.php';

class DeveloperLoginAuth {
    
    /**
     * Generate and send login verification code for developer
     * @param array $user User data
     * @return string|false Verification code or false on failure
     */
    public static function sendLoginCode($user) {
        try {
            // Generate 6-digit verification code
            $verificationCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            
            // Store verification code in session with expiration (10 minutes)
            $_SESSION['dev_login_verification'] = [
                'code' => $verificationCode,
                'user_data' => $user,
                'expires' => time() + (10 * 60), // 10 minutes
                'attempts' => 0
            ];
            
            // Send email with verification code
            $subject = "Developer Login Verification Code - 053 PRINTS";
            $message = self::buildVerificationEmail($user, $verificationCode);
            
            if (EmailNotifications::send($user['email'], $subject, $message)) {
                error_log("Developer login verification code sent to: " . $user['email']);
                return $verificationCode;
            } else {
                error_log("Failed to send developer login verification code to: " . $user['email']);
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Developer login auth error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify the submitted code and complete login
     * @param string $submittedCode Code entered by user
     * @return array Result with success status and message
     */
    public static function verifyCodeAndLogin($submittedCode) {
        if (!isset($_SESSION['dev_login_verification'])) {
            return ['success' => false, 'message' => 'No verification session found. Please try logging in again.'];
        }
        
        $verification = $_SESSION['dev_login_verification'];
        
        // Check if code has expired
        if (time() > $verification['expires']) {
            unset($_SESSION['dev_login_verification']);
            return ['success' => false, 'message' => 'Verification code has expired. Please try logging in again.'];
        }
        
        // Increment attempt counter
        $_SESSION['dev_login_verification']['attempts']++;
        
        // Check attempt limit (max 3 attempts)
        if ($_SESSION['dev_login_verification']['attempts'] > 3) {
            unset($_SESSION['dev_login_verification']);
            return ['success' => false, 'message' => 'Too many failed attempts. Please try logging in again.'];
        }
        
        // Verify the code
        if ($submittedCode === $verification['code']) {
            // Code is correct - complete the login process
            $user = $verification['user_data'];
            $role = trim($user['role']);
            
            // Clear verification session
            unset($_SESSION['dev_login_verification']);
            
            // Set all session variables for successful login
            $_SESSION[$role . '_user_id'] = $user['id'];
            $_SESSION[$role . '_name'] = $user['username'];
            $_SESSION[$role . '_email'] = $user['email'];
            $_SESSION[$role . '_role'] = $user['role'];
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $role;
            
            error_log("Developer login verification successful for: " . $user['username']);
            return ['success' => true, 'message' => 'Verification successful', 'user' => $user];
        } else {
            $attemptsLeft = 3 - $_SESSION['dev_login_verification']['attempts'];
            return ['success' => false, 'message' => "Invalid verification code. $attemptsLeft attempts remaining."];
        }
    }
    
    /**
     * Check if developer verification is pending
     * @return bool
     */
    public static function isVerificationPending() {
        return isset($_SESSION['dev_login_verification']) && 
               time() <= $_SESSION['dev_login_verification']['expires'];
    }
    
    /**
     * Clear verification session
     */
    public static function clearVerification() {
        unset($_SESSION['dev_login_verification']);
    }
    
    /**
     * Build verification email template
     * @param array $user User data
     * @param string $code Verification code
     * @return string HTML email content
     */
    private static function buildVerificationEmail($user, $code) {
        $userName = htmlspecialchars($user['username']);
        $userEmail = htmlspecialchars($user['email']);
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Developer Login Verification - 053 PRINTS</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
                .header { background: #dc2626; color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; text-align: center; }
                .verification-code { display: inline-block; padding: 15px 25px; background: #f8f9fa; border: 2px solid #dc2626; border-radius: 8px; font-size: 28px; font-weight: bold; letter-spacing: 5px; color: #dc2626; margin: 20px 0; font-family: monospace; }
                .security-notice { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 15px; margin: 20px 0; color: #991b1b; }
                .footer { background: #f8f9fa; text-align: center; padding: 20px; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔒 053 PRINTS</h1>
                    <p style='margin: 10px 0 0 0;'>Developer Login Verification</p>
                </div>
                
                <div class='content'>
                    <p>Hello <strong>$userName</strong>,</p>
                    <p>A developer login attempt was made for your account.</p>
                    
                    <div class='security-notice'>
                        <strong>🛡️ Security Alert:</strong> Developer accounts require email verification for enhanced security.
                    </div>
                    
                    <p>Your verification code is:</p>
                    <div class='verification-code'>$code</div>
                    
                    <p style='color: #666; font-size: 14px;'>
                        This code will expire in <strong>10 minutes</strong>.<br>
                        If you did not attempt to log in, please ignore this email.
                    </p>
                    
                    <p style='color: #999; font-size: 12px; margin-top: 20px;'>
                        Sent: " . date('Y-m-d H:i:s') . "<br>
                        Account: $userEmail
                    </p>
                </div>
                
                <div class='footer'>
                    <p><strong>053 PRINTS Security System</strong></p>
                    <p>This is an automated security email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
}
?>
