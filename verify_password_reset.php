<?php
session_start();
require_once 'config/database.php';

// Create password reset tokens table if it doesn't exist
try {
    $pdo = Database::getConnection();
    $sql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        used_at DATETIME NULL,
        INDEX idx_token (token),
        INDEX idx_expires (expires_at),
        INDEX idx_user_id (user_id)
    )";
    $pdo->exec($sql);
} catch (Exception $e) {
    error_log("Error creating password_reset_tokens table: " . $e->getMessage());
}

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

if (empty($token)) {
    $error = 'Invalid verification link.';
} else {
    try {
        // Verify token
        $stmt = $pdo->prepare("
            SELECT user_id, expires_at, used_at 
            FROM password_reset_tokens 
            WHERE token = ?
        ");
        $stmt->execute([$token]);
        $resetToken = $stmt->fetch();
        
        if (!$resetToken) {
            $error = 'Invalid or expired verification link.';
        } elseif ($resetToken['used_at']) {
            $error = 'This verification link has already been used.';
        } elseif (strtotime($resetToken['expires_at']) < time()) {
            $error = 'This verification link has expired. Please request a new password reset.';
        } else {
            // Mark token as used
            $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE token = ?");
            $stmt->execute([$token]);
            
            // Set session flag for password reset verification
            $_SESSION['password_reset_verified'] = [
                'user_id' => $resetToken['user_id'],
                'timestamp' => time(),
                'expires' => time() + 1800 // 30 minutes
            ];
            
            $success = true;
        }
        
    } catch (Exception $e) {
        error_log("Error verifying password reset token: " . $e->getMessage());
        $error = 'An error occurred while verifying your request. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Verification - 053 PRINTS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            overflow: hidden;
            background: linear-gradient(rgba(0,0,0,0.45), rgba(0,0,0,0.45)), url('images/053 bg1.jpg') center/cover no-repeat;
            position: relative;
        }
        
        /* Custom styles for verification page */
        .button-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        @media (max-width: 600px) {
            .button-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="logo-center">
            <img src="images/053logo.png" alt="053Prints Logo">
        </div>
        
        <div class="login-card active">
            <?php if ($success): ?>
                <div class="form-title">Email Verified Successfully!</div>
                <div style="text-align: center; margin: 30px 0;">
                    <p style="color: #bbb; margin-bottom: 30px; line-height: 1.6;">Your email has been verified. You can now proceed to change your password.</p>
                </div>
                <div class="button-group">
                    <button type="button" class="btn btn-primary" onclick="window.location.href='user_page.php?password_reset_verified=true'">
                        Change Password
                    </button>
                </div>
            <?php else: ?>
                <div class="form-title">Verification Failed</div>
                <div style="text-align: center; margin: 30px 0;">
                    <p style="color: #bbb; margin-bottom: 30px; line-height: 1.6;"><?php echo htmlspecialchars($error); ?></p>
                </div>
                <div class="button-group">
                    <button type="button" class="btn btn-primary" onclick="window.location.href='index.php'">
                        Go Home
                    </button>
                </div>
                <div class="login-link" style="margin: 0.25rem 0 0.75rem; text-align: center; color: #bbb; font-size: 13px;">
                    Need help? <a href="mailto:support@053prints.com" style="color:#4facfe; font-weight:600;">Contact Support</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
