<?php
session_start(); // Start the session to use session variables
require_once 'includes/config.php'; // Include the database connection
require_once 'includes/csrf.php'; // Include CSRF protection
require_once 'includes/audit_helper.php'; // Include audit logging functions

// Handle registration form submission
if (isset($_POST['register'])) {
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
    // Hash the password for security
    $password = isset($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : '';

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

    // Validate contact number format (10 digits)
    if ($contact_number && !preg_match('/^[0-9]{10}$/', $contact_number)) {
        $_SESSION['register_error'] = 'Contact number must be exactly 10 digits!';
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // Format contact number with +63 prefix
    $full_contact_number = $contact_number ? '+63' . $contact_number : '';

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
                // Insert new user into the database with contact number and default role 'user'
                $insertStmt = $conn->prepare("INSERT INTO users (username, firstname, lastname, email, contact_number, password, role) VALUES (?, ?, ?, ?, ?, ?, 'user')");
                $insertStmt->bind_param("ssssss", $username, $first_name, $last_name, $email, $full_contact_number, $password);
                
                if ($insertStmt->execute()) {
                    // Get the new user ID for audit logging
                    $new_user_id = $conn->insert_id;
                    
                    // Log registration event
                    logRegistrationEvent($new_user_id, $username);
                    
                    // Registration successful
                    $_SESSION['register_success'] = 'Registration successful! You can now log in.';
                    $_SESSION['active_form'] = 'login';
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
                // Check if account is active
                if (isset($user['status']) && $user['status'] === 'inactive') {
                    $_SESSION['login_error'] = 'Your account has been deactivated. Please contact an administrator.';
                    $_SESSION['active_form'] = 'login';
                    header("Location: index.php");
                    exit();
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

                // Debug: Log redirect decision
                error_log("Login Debug - Redirect check: Role is '" . $role . "', Admin check: " . ($role === 'admin' ? 'true' : 'false') . ", Cashier check: " . ($role === 'cashier' ? 'true' : 'false'));

                // Redirect based on user role
                if ($role === 'admin' || $role === 'cashier') {
                    error_log("Login Debug - Redirecting to admin_page.php");
                    header("Location: admin_page.php");
                } else {
                    error_log("Login Debug - Redirecting to user_page.php");
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
