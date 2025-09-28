<?php
session_start();
require_once '../config/database.php';
require_once '../includes/csrf.php';
require_once '../includes/session_helper.php';

// Set JSON header
header('Content-Type: application/json');

// CORS headers
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check for user-specific session variables first (user_user_id, user_name, etc.)
if (isset($_SESSION['user_user_id']) && isset($_SESSION['user_name'])) {
    $userData = [
        'user_id' => $_SESSION['user_user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'],
        'is_logged_in' => true
    ];
} else {
    // Fallback to general session if it's a user role
    $userData = getUserSessionData();
    if (!$userData['is_logged_in'] || $userData['role'] !== 'user') {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized - user role required']);
        exit();
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $userData['user_id'];

// Get database connection
$pdo = Database::getConnection();

try {
    switch ($method) {
        case 'GET':
            // Get user account information
            getUserInfo($pdo, $user_id);
            break;
            
        case 'POST':
            // Handle password reset requests
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validate CSRF token
            if (!CSRFToken::validate($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                exit();
            }
            
            $action = $input['action'] ?? '';
            if ($action === 'request_password_reset') {
                requestPasswordReset($pdo, $user_id);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
            }
            break;
            
        case 'PUT':
            // Handle both account updates and password updates
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validate CSRF token for state-changing operations
            if (!CSRFToken::validate($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                exit();
            }
            
            // Check if this is a password update or account update
            if (isset($input['current_password']) && isset($input['new_password'])) {
                updatePassword($pdo, $user_id, $input);
            } else {
                updateAccountInfo($pdo, $user_id, $input);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function getUserInfo($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, username, firstname, lastname, email, contact_number, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        // Format the created_at date
        if ($user['created_at']) {
            $user['created_at'] = date('F j, Y g:i A', strtotime($user['created_at']));
        }
        
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function requestPasswordReset($pdo, $user_id) {
    try {
        error_log("requestPasswordReset called for user_id: " . $user_id);
        
        // Create table if it doesn't exist (without foreign key constraint for now)
        $createTableSql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
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
        $pdo->exec($createTableSql);
        error_log("Password reset tokens table created/verified");
        
        // Get user email and name
        $stmt = $pdo->prepare("SELECT email, firstname, lastname FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        error_log("User data retrieved: " . json_encode($user));
        
        if (!$user) {
            error_log("User not found for ID: " . $user_id);
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        if (empty($user['email'])) {
            error_log("No email for user ID: " . $user_id);
            http_response_code(400);
            echo json_encode(['error' => 'No email address associated with this account']);
            return;
        }
        
        // Generate verification token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        error_log("Generated token for user " . $user_id . ": " . substr($token, 0, 10) . "...");
        
        // Store token in database
        $stmt = $pdo->prepare("
            INSERT INTO password_reset_tokens (user_id, token, expires_at, created_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            token = VALUES(token), 
            expires_at = VALUES(expires_at), 
            created_at = NOW()
        ");
        $stmt->execute([$user_id, $token, $expires]);
        
        error_log("Token stored in database successfully");
        
        // Create verification link
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $verificationLink = $baseUrl . dirname($_SERVER['REQUEST_URI']) . '/../verify_password_reset.php?token=' . $token;
        
        // Send email
        $userName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
        if (empty($userName)) {
            $userName = 'User';
        }
        
        $subject = "Password Reset Verification - 053 PRINTS";
        $message = buildPasswordResetEmailTemplate($userName, $verificationLink);
        
        error_log("Attempting to send email to: " . $user['email']);
        error_log("Verification link: " . $verificationLink);
        
        require_once '../includes/email_notifications.php';
        $emailSent = EmailNotifications::send($user['email'], $subject, $message);
        
        error_log("Email sent result: " . ($emailSent ? 'SUCCESS' : 'FAILED'));
        
        if ($emailSent) {
            echo json_encode([
                'success' => true,
                'message' => 'Verification email sent to your email address. Please check your inbox and click the link to proceed.'
            ]);
        } else {
            error_log("Failed to send email to: " . $user['email']);
            http_response_code(500);
            echo json_encode(['error' => 'Failed to send verification email. Please try again.']);
        }
        
    } catch (Exception $e) {
        error_log("Error in requestPasswordReset: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Server error occurred. Please try again.']);
    }
}

function buildPasswordResetEmailTemplate($userName, $verificationLink) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Password Reset - 053 PRINTS</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f4f4f4; 
            }
            .container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: white; 
                border-radius: 12px; 
                overflow: hidden; 
                box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            }
            .header { 
                background: #1a1a1a; 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 24px; 
                font-weight: 600; 
            }
            .content { 
                padding: 30px 20px; 
                text-align: left;
            }
            .button-container {
                text-align: center;
                margin: 20px 0;
            }
            .reset-button { 
                display: inline-block; 
                background: #4facfe !important; 
                color: white !important; 
                padding: 15px 30px; 
                text-decoration: none; 
                border-radius: 8px; 
                font-weight: bold; 
                margin: 20px 0; 
                font-size: 16px;
                border: none;
            }
            .reset-button:hover { 
                background: #3d8bfe !important; 
                color: white !important;
            }
            .warning-box { 
                background: #fff3cd; 
                border: 1px solid #ffeaa7; 
                border-radius: 8px; 
                padding: 15px; 
                margin: 20px 0; 
                color: #856404;
            }
            .footer { 
                background: #f8f9fa; 
                text-align: center; 
                padding: 20px; 
                color: #666; 
                font-size: 14px; 
                border-top: 1px solid #eee;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>053 PRINTS</h1>
                <p style='margin: 10px 0 0 0; opacity: 0.9;'>Password Reset Request</p>
            </div>
            
            <div class='content'>
                <p style='font-size: 16px; margin-bottom: 20px;'>Dear <strong>" . htmlspecialchars($userName) . "</strong>,</p>
                
                <p style='font-size: 16px;'>You have requested to reset your password. Click the button below to verify your email and proceed with changing your password:</p>
                
                <div class='button-container'>
                    <a href='" . htmlspecialchars($verificationLink) . "' class='reset-button'>Verify Email & Reset Password</a>
                </div>
                
                <div class='warning-box'>
                    <p style='margin: 0; font-weight: 500;'>⏰ <strong>This link expires in 1 hour</strong></p>
                    <p style='margin: 5px 0 0 0;'>If you didn't request this password reset, please ignore this email.</p>
                </div>
            </div>
            
            <div class='footer'>
                <p><strong>053 PRINTS Security Team</strong></p>
                <p>This is an automated security email. Please do not reply.</p>
                <p style='margin-top: 15px; font-size: 12px; color: #999;'>
                    Sent: " . date('Y-m-d H:i:s') . "
                </p>
            </div>
        </div>
    </body>
    </html>";
}

function updateAccountInfo($pdo, $user_id, $input) {
    try {
        // Debug logging
        error_log("updateAccountInfo called for user_id: " . $user_id);
        error_log("Input data: " . json_encode($input));
        
        // Validate required fields (excluding email)
        if (!isset($input['username']) || !isset($input['firstname']) || !isset($input['lastname'])) {
            error_log("Missing required fields");
            http_response_code(400);
            echo json_encode(['error' => 'Username, first name, and last name are required']);
            return;
        }
        
        // Check if username already exists for other users
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$input['username'], $user_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            http_response_code(400);
            echo json_encode(['error' => 'Username already exists']);
            return;
        }
        
        // Update user information (excluding email)
        // Check if contact_number field exists, fallback to contact
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = ?, firstname = ?, lastname = ?, contact_number = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $input['username'],
                $input['firstname'],
                $input['lastname'],
                $input['contact'] ?? null,
                $user_id
            ]);
        } catch (PDOException $e) {
            // If contact_number doesn't exist, try with contact field
            if (strpos($e->getMessage(), 'contact_number') !== false) {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET username = ?, firstname = ?, lastname = ?, contact = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $input['username'],
                    $input['firstname'],
                    $input['lastname'],
                    $input['contact'] ?? null,
                    $user_id
                ]);
            } else {
                throw $e;
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function updatePassword($pdo, $user_id, $input) {
    try {
        // Check if email was verified for password reset
        if (!isset($_SESSION['password_reset_verified']) || 
            $_SESSION['password_reset_verified']['user_id'] !== $user_id ||
            $_SESSION['password_reset_verified']['expires'] < time()) {
            
            http_response_code(403);
            echo json_encode(['error' => 'Email verification required. Please verify your email first.']);
            return;
        }
        
        // Validate input
        if (!isset($input['current_password']) || !isset($input['new_password']) || !isset($input['confirm_password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'All password fields are required']);
            return;
        }
        
        if ($input['new_password'] !== $input['confirm_password']) {
            http_response_code(400);
            echo json_encode(['error' => 'New passwords do not match']);
            return;
        }
        
        if (strlen($input['new_password']) < 6) {
            http_response_code(400);
            echo json_encode(['error' => 'New password must be at least 6 characters long']);
            return;
        }
        
        // Get current user password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        // Verify current password
        if (!password_verify($input['current_password'], $user['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Current password is incorrect']);
            return;
        }
        
        // Hash new password
        $new_password_hash = password_hash($input['new_password'], PASSWORD_DEFAULT);
        
        // Update password in database
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_password_hash, $user_id]);
        
        // Clear password reset verification session
        unset($_SESSION['password_reset_verified']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Password updated successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
