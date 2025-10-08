<?php
session_start(); // Start the session to use session variables
require_once 'includes/config.php'; // Include the database connection
require_once 'includes/csrf.php'; // Include CSRF protection
require_once 'includes/audit_helper.php'; // Include audit logging functions
require_once 'includes/email_verification.php'; // Include email verification functions
require_once 'includes/unverified_account_cleanup.php'; // Include cleanup functions

// Create simple maintenance check function
function isMaintenanceModeEnabled() {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode' LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['setting_value'] === 'true';
        }
        return false;
    } catch (Exception $e) {
        return false; // Default to no maintenance if error
    }
}

// Handle registration form submission
if (isset($_POST['register'])) {
    // Check maintenance mode - block registration during maintenance
    if (isMaintenanceModeEnabled()) {
        $_SESSION['register_error'] = 'Registration is temporarily disabled due to system maintenance. Please try again later.';
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }
    
    // Validate CSRF token first
    if (!CSRFToken::validate($_POST['csrf_token'] ?? '')) {
        $_SESSION['register_error'] = 'Invalid security token. Please try again.';
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // Get form data safely
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $contact_number = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : '';
    $raw_password = isset($_POST['password']) ? $_POST['password'] : '';
    $terms_agreement = isset($_POST['terms_agreement']) ? $_POST['terms_agreement'] : '';
    
    // Validate password requirements before hashing
    if ($raw_password) {
        // Check minimum length (8 characters)
        if (strlen($raw_password) < 8) {
            $_SESSION['register_error'] = 'Password must be at least 8 characters long!';
            $_SESSION['active_form'] = 'register';
            header("Location: index.php");
            exit();
        }
        
        // Check maximum length (64 characters)
        if (strlen($raw_password) > 64) {
            $_SESSION['register_error'] = 'Password must be no more than 64 characters long!';
            $_SESSION['active_form'] = 'register';
            header("Location: index.php");
            exit();
        }
        
        // Check for at least one lowercase letter
        if (!preg_match('/[a-z]/', $raw_password)) {
            $_SESSION['register_error'] = 'Password must contain at least one lowercase letter!';
            $_SESSION['active_form'] = 'register';
            header("Location: index.php");
            exit();
        }
        
        // Check for at least one uppercase letter
        if (!preg_match('/[A-Z]/', $raw_password)) {
            $_SESSION['register_error'] = 'Password must contain at least one uppercase letter!';
            $_SESSION['active_form'] = 'register';
            header("Location: index.php");
            exit();
        }
        
        // Check for at least one number
        if (!preg_match('/[0-9]/', $raw_password)) {
            $_SESSION['register_error'] = 'Password must contain at least one number!';
            $_SESSION['active_form'] = 'register';
            header("Location: index.php");
            exit();
        }
    }
    
    // Hash the password for security (only after validation)
    $password = $raw_password ? password_hash($raw_password, PASSWORD_DEFAULT) : '';

    // Validate first name (only letters and spaces allowed)
    if ($first_name && !preg_match('/^[a-zA-Z\s]+$/', $first_name)) {
        $_SESSION['register_error'] = 'First name can only contain letters and spaces!';
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // Validate last name (only letters and spaces allowed)
    if ($last_name && !preg_match('/^[a-zA-Z\s]+$/', $last_name)) {
        $_SESSION['register_error'] = 'Last name can only contain letters and spaces!';
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // Validate username length (3-20 characters)
    if ($username && (strlen($username) < 3 || strlen($username) > 20)) {
        if (strlen($username) < 3) {
            $_SESSION['register_error'] = 'Username must be at least 3 characters long!';
        } else {
            $_SESSION['register_error'] = 'Username must be no more than 20 characters long!';
        }
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // Validate username characters (only letters and numbers allowed)
    if ($username && !preg_match('/^[a-zA-Z0-9]+$/', $username)) {
        $_SESSION['register_error'] = 'Username can only contain letters and numbers!';
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // Validate email format and allowed domains
    if ($email) {
        // Check basic email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['register_error'] = 'Please enter a valid email address!';
            $_SESSION['active_form'] = 'register';
            header("Location: index.php");
            exit();
        }
        
        // List of allowed legitimate email domains
        $allowed_domains = [
            // Gmail
            'gmail.com',
            // Outlook / Hotmail / Live / MSN
            'outlook.com', 'hotmail.com', 'live.com', 'msn.com',
            // Yahoo Mail
            'yahoo.com', 'ymail.com', 'rocketmail.com',
            // iCloud Mail
            'icloud.com', 'me.com', 'mac.com',
            // AOL Mail
            'aol.com',
            // Zoho Mail
            'zoho.com',
            // Proton Mail
            'protonmail.com', 'proton.me',
            // GMX Mail
            'gmx.com', 'gmx.net',
            // Mail.com
            'mail.com'
        ];
        
        // List of disposable/temporary email domains to block
        $disposable_domains = [
            '10minutemail.com', 'tempmail.org', 'guerrillamail.com', 'mailinator.com',
            'yopmail.com', 'temp-mail.org', 'throwaway.email', 'getnada.com',
            'maildrop.cc', 'sharklasers.com', 'guerrillamailblock.com', 'tempail.com',
            'dispostable.com', 'fakeinbox.com', 'spamgourmet.com', 'trashmail.com',
            'emailondeck.com', 'mohmal.com', 'anonymbox.com', 'deadaddress.com'
        ];
        
        $email_domain = strtolower(substr(strrchr($email, "@"), 1));
        
        // Check if domain is disposable
        if (in_array($email_domain, $disposable_domains)) {
            $_SESSION['register_error'] = 'Temporary or disposable email addresses are not allowed!';
            $_SESSION['active_form'] = 'register';
            header("Location: index.php");
            exit();
        }
        
        // Check if domain is in allowed list
        if (!in_array($email_domain, $allowed_domains)) {
            $_SESSION['register_error'] = 'Please use a legitimate email provider (Gmail, Outlook, Yahoo, iCloud, AOL, Zoho, Proton, GMX, or Mail.com)!';
            $_SESSION['active_form'] = 'register';
            header("Location: index.php");
            exit();
        }
    }

    // Validate contact number format (10 digits)
    if ($contact_number && !preg_match('/^[0-9]{10}$/', $contact_number)) {
        $_SESSION['register_error'] = 'Contact number must be exactly 10 digits!';
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // Format contact number with +63 prefix
    $full_contact_number = $contact_number ? '+63' . $contact_number : '';

    // Validate terms and conditions agreement
    if (!$terms_agreement || $terms_agreement !== 'on') {
        $_SESSION['register_error'] = 'You must agree to the Terms & Conditions to register!';
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // Check if all fields are filled
    if ($first_name && $last_name && $username && $email && $password && $contact_number) {
        // Check if the email is already registered
        $checkEmailStmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
        $checkEmailStmt->bind_param("s", $email);
        $checkEmailStmt->execute();
        $checkEmail = $checkEmailStmt->get_result();
        
        if ($checkEmail && $checkEmail->num_rows > 0) {
            // Email already exists
            $_SESSION['register_error'] = 'Email is already registered!';
            $_SESSION['active_form'] = 'register';
        } else {
            // Check if the username is already taken
            $checkUsernameStmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
            $checkUsernameStmt->bind_param("s", $username);
            $checkUsernameStmt->execute();
            $checkUsername = $checkUsernameStmt->get_result();
            
            if ($checkUsername && $checkUsername->num_rows > 0) {
                // Username already exists
                $_SESSION['register_error'] = 'Username is already taken!';
                $_SESSION['active_form'] = 'register';
            } else {
                // Generate email verification token
                $verification_token = generateVerificationToken();
                
                // Insert new user into the database with email verification token and unverified status
                $insertStmt = $conn->prepare("INSERT INTO users (username, firstname, lastname, email, contact_number, password, role, email_verification_token, is_email_verified) VALUES (?, ?, ?, ?, ?, ?, 'user', ?, FALSE)");
                $insertStmt->bind_param("sssssss", $username, $first_name, $last_name, $email, $full_contact_number, $password, $verification_token);
                
                if ($insertStmt->execute()) {
                    // Get the new user ID for audit logging
                    $new_user_id = $conn->insert_id;
                    
                    // Send verification email
                    if (sendVerificationEmail($email, $username, $verification_token)) {
                        // Log registration event
                        logRegistrationEvent($new_user_id, $username);
                        
                        // Trigger cleanup of old unverified accounts (run in background)
                        try {
                            require_once 'config/database.php';
                            $pdo = Database::getConnection();
                            $cleanup = new UnverifiedAccountCleanup($pdo, 24); // 24 hours
                            $cleanup_result = $cleanup->cleanupExpiredAccounts();
                            if ($cleanup_result['success'] && $cleanup_result['deleted_count'] > 0) {
                                error_log("Auto-cleanup during registration: Removed {$cleanup_result['deleted_count']} expired unverified accounts");
                            }
                        } catch (Exception $e) {
                            error_log("Auto-cleanup error during registration: " . $e->getMessage());
                        }
                        
                        // Registration successful - email sent
                        $_SESSION['register_success'] = 'Registration successful! Please check your email to verify your account before logging in.';
                        $_SESSION['active_form'] = 'login';
                    } else {
                        // Email sending failed - delete the user record
                        $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $deleteStmt->bind_param("i", $new_user_id);
                        $deleteStmt->execute();
                        
                        $_SESSION['register_error'] = 'Registration failed. Unable to send verification email. Please try again.';
                        $_SESSION['active_form'] = 'register';
                    }
                } else {
                    // Database insert failed
                    $_SESSION['register_error'] = 'Registration failed. Please try again.';
                    $_SESSION['active_form'] = 'register';
                }
            }
        }
    } else {
        // Not all fields are filled
        $_SESSION['register_error'] = 'All fields are required!';
        $_SESSION['active_form'] = 'register';
    }

    // Redirect back to index.php after registration attempt
    header("Location: index.php");
    exit();
}

// Handle login form submission
if (isset($_POST['login'])) {
    // Validate CSRF token first
    if (!CSRFToken::validate($_POST['csrf_token'] ?? '')) {
        $_SESSION['login_error'] = 'Invalid security token. Please try again.';
        $_SESSION['active_form'] = 'login';
        header("Location: index.php");
        exit();
    }

    // Get login form data
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Check if both fields are filled
    if ($username && $password) {
        // Look up the user by username
        $loginStmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $loginStmt->bind_param("s", $username);
        $loginStmt->execute();
        $result = $loginStmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // Verify the password
            if (password_verify($password, $user['password'])) {
                // Check if account is soft deleted (deactivated)
                if (isset($user['deleted_at']) && $user['deleted_at'] !== null) {
                    $_SESSION['login_error'] = 'Your account has been deactivated by an administrator. Please contact support for assistance.';
                    $_SESSION['active_form'] = 'login';
                    header("Location: index.php");
                    exit();
                }
                
                // Check if account is active
                if (isset($user['status']) && $user['status'] === 'inactive') {
                    $_SESSION['login_error'] = 'Your account has been deactivated. Please contact an administrator.';
                    $_SESSION['active_form'] = 'login';
                    header("Location: index.php");
                    exit();
                }
                
                // Check if email is verified
                if (isset($user['is_email_verified']) && !$user['is_email_verified']) {
                    $_SESSION['login_error'] = 'Please verify your email address before logging in. Check your email for the verification link.';
                    $_SESSION['active_form'] = 'login';
                    header("Location: index.php");
                    exit();
                }
                
                // Check maintenance mode - block regular users during maintenance
                if (isMaintenanceModeEnabled()) {
                    $user_role = trim($user['role']);
                    $allowed_roles = ['admin', 'super_admin', 'cashier', 'developer'];
                    
                    if (!in_array($user_role, $allowed_roles)) {
                        $_SESSION['login_error'] = 'System is currently under maintenance. Please try again later.';
                        $_SESSION['active_form'] = 'login';
                        header("Location: index.php");
                        exit();
                    }
                }
                
                // Debug: Log the user role for troubleshooting
                error_log("Login Debug - User: " . $user['username'] . ", Role: '" . $user['role'] . "', Role Length: " . strlen($user['role']));
                
                // Regenerate session ID on successful login for security
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }

                // Update last login timestamp
                $updateLoginStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateLoginStmt->bind_param("i", $user['id']);
                $updateLoginStmt->execute();
                
                // Log successful login event
                logLoginEvent($user['id'], $user['username'], $role);
                
                // Set role-specific session variables and standard session variables
                $role = trim($user['role']); // Trim any whitespace
                $_SESSION[$role . '_user_id'] = $user['id'];
                $_SESSION[$role . '_name'] = $user['username'];
                $_SESSION[$role . '_email'] = $user['email'];
                $_SESSION[$role . '_role'] = $user['role'];
                
                // Also set standard session variables for compatibility
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $role;
                
                // Set login time for force logout comparison
                $_SESSION['login_time'] = time();
                
                // Store session for SSE real-time updates
                require_once __DIR__ . '/includes/session_manager.php';
                storeUserSession($user['id'], $role, 24); // Store for 24 hours
                error_log("Login: Stored SSE session for user " . $user['id'] . " with role " . $role);

                // Debug: Log redirect decision
                error_log("Login Debug - Redirect check: Role is '" . $role . "', Admin check: " . ($role === 'admin' ? 'true' : 'false') . ", Cashier check: " . ($role === 'cashier' ? 'true' : 'false'));

                // Redirect based on user role
                if ($role === 'admin' || $role === 'cashier' || $role === 'super_admin') {
                    error_log("Login Debug - Redirecting to admin_page.php for role: {$role}");
                    header("Location: admin_page.php");
                } else {
                    error_log("Login Debug - Redirecting to user_page.php for role: {$role}");
                    header("Location: user_page.php");
                }
                exit();
            } else {
                error_log("Login Debug - Password verification failed for user: " . $username);
                // Log failed login attempt
                logFailedLoginEvent($username, 'Invalid password');
            }
        } else {
            error_log("Login Debug - User not found: " . $username);
            // Log failed login attempt
            logFailedLoginEvent($username, 'User not found');
        }
    }

    // If login fails, set error and redirect back
    $_SESSION['login_error'] = 'Incorrect username or password';
    $_SESSION['active_form'] = 'login';
    header("Location: index.php");
    exit();
}

?>
