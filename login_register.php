<?php
session_start(); // Start the session to use session variables
require_once 'config.php'; // Include the database connection

// Handle registration form submission
if (isset($_POST['register'])) {
    // Get form data safely
    $name = isset($_POST['username']) ? $_POST['username'] : '';
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    // Hash the password for security
    $password = isset($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : '';
    $role = isset($_POST['role']) ? $_POST['role'] : '';

    // Check if all fields are filled
    if ($name && $email && $password && $role) {
        // Check if the email is already registered
        $checkEmail = $conn->query("SELECT email FROM users WHERE email = '$email'");
        if ($checkEmail && $checkEmail->num_rows > 0) {
            // Email already exists
            $_SESSION['register_error'] = 'Email is already registered!';
            $_SESSION['active_form'] = 'register';
        } else {
            // Insert new user into the database
            if ($conn->query("INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$password', '$role')")) {
                // Registration successful
                $_SESSION['register_success'] = 'Registration successful! You can now log in.';
                $_SESSION['active_form'] = 'login';
            } else {
                // Database insert failed
                $_SESSION['register_error'] = 'Registration failed. Please try again.';
                $_SESSION['active_form'] = 'register';
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
    // Get login form data
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Check if both fields are filled
    if ($username && $password) {
        // Look up the user by username
        $result = $conn->query("SELECT * FROM users WHERE name = '$username'");
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // Verify the password
            if (password_verify($password, $user['password'])) {
                // Set session variables for the logged-in user
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role']; // Store user role in session

                // Redirect based on user role
                if ($user['role'] === 'admin') {
                    header("Location: admin_page_fixed.php");
                } else {
                    header("Location: user_page.php");
                }
                exit();
            }
        }
    }

    // If login fails, set error and redirect back
    $_SESSION['login_error'] = 'Incorrect username or password';
    $_SESSION['active_form'] = 'login';
    header("Location: index.php");
    exit();
}

?>
