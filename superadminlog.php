<?php
session_start();

// Check if already logged in as super admin
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'super_admin') {
    header('Location: super_admin_dashboard.php');
    exit();
}

$error = $_SESSION['super_admin_error'] ?? '';
unset($_SESSION['super_admin_error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Portal - 053 Prints</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            min-height: 100vh;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(
                90deg,
                transparent,
                transparent 98px,
                #f0f0f0 100px
            ),
            repeating-linear-gradient(
                0deg,
                transparent,
                transparent 98px,
                #f0f0f0 100px
            );
            z-index: 1;
        }

        .developer-container {
            background: #ffffff;
            padding: 3rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e5e5;
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 2;
            border-radius: 8px;
        }

        .developer-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .developer-icon {
            width: 80px;
            height: 80px;
            background: #000000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .developer-icon i {
            font-size: 2rem;
            color: white;
        }

        .developer-title {
            font-size: 2rem;
            font-weight: 700;
            color: #000000;
            margin-bottom: 0.5rem;
        }

        .developer-subtitle {
            color: #666666;
            font-size: 1rem;
            font-weight: 400;
        }

        .error-message {
            background: #f8f8f8;
            color: #000000;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid #e5e5e5;
        }

        .error-message i {
            color: #666666;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #000000;
            font-size: 0.95rem;
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #ffffff;
            color: #000000;
        }

        .form-group input:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
        }

        .password-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666666;
            cursor: pointer;
            padding: 0.25rem;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #000000;
        }

        .developer-btn {
            background: #000000;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 1.5rem;
        }

        .developer-btn:hover {
            background: #333333;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .developer-btn:active {
            transform: translateY(0);
        }

        .back-link {
            text-align: center;
            margin-top: 1rem;
        }

        .back-link a {
            color: #000000;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: #333333;
        }

        .security-notice {
            background: #f8f8f8;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: #666666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="developer-container">
        <div class="developer-header">
            <div class="developer-icon">
                <i class="fas fa-code"></i>
            </div>
            <h1 class="developer-title">Developer Portal</h1>
            <p class="developer-subtitle">System Administration & Technical Management</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form id="superAdminForm" method="POST" action="api/superadmin_api/super_admin_login.php">
            <div class="form-group">
                <label for="username">Developer Username</label>
                <input type="text" id="username" name="username" required placeholder="Enter developer username">
            </div>

            <div class="form-group password-group">
                <label for="password">Access Key</label>
                <input type="password" id="password" name="password" required placeholder="Enter access key">
                <button type="button" class="password-toggle" onclick="togglePassword()">
                    <i class="fas fa-eye" id="toggleIcon"></i>
                </button>
            </div>

            <button type="submit" class="developer-btn">
                <i class="fas fa-sign-in-alt"></i>
                Access Developer Panel
            </button>
        </form>

        <div class="security-notice">
            <i class="fas fa-shield-alt"></i>
            This portal is restricted to authorized developers only. All access attempts are logged.
        </div>

        <div class="back-link">
            <a href="index.php">
                <i class="fas fa-arrow-left"></i>
                Back to Main Site
            </a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }

        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>