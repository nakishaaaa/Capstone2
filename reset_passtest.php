<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
// Get token from URL
$token = $_GET["token"] ?? null;
if (!$token) {
    die("No token provided");
}
$token_hash = hash("sha256", $token);

$conn = require __DIR__ . "/config.php";

$sql = "SELECT * FROM users WHERE reset_token_hash = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user === null) {
    die("token not found");
}

if (strtotime($user["reset_token_expires_at"]) <= time()) {
    die("token has expired");
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            overflow: hidden;
            background: url('images/053 bg1.jpg') center/cover;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1;
        }

        .main-container {
            position: relative;
            z-index: 2;
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .logo-center {
            margin-bottom: 40px;
            text-align: center;
        }

        .logo-center img {
            max-width: 200px;
            height: auto;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            background: rgba(20, 20, 20, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .login-card h2 {
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 2rem;
            color: #3366cc;
        }

        .login-card input[type="password"] {
            width: 100%;
            padding: 15px 45px 15px 15px;
            background: #2a2a2a;
            border: 2px solid transparent;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .login-card input[type="password"]:focus {
            outline: none;
            border-color: #4facfe;
            background: #333;
        }

        .login-card input[type="password"]::placeholder {
            color: #666;
        }

        .login-card button[type="submit"] {
            width: 100%;
            padding: 0.75rem 0;
            background: #3366cc;
            color: #fff;
            border: none;
            border-radius: 0.75rem;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 1rem;
            transition: background 0.2s;
        }

        .login-card button[type="submit"]:hover {
            background: #254a91;
        }

        .login-card p {
            text-align: center;
        }

        .login-card a {
            color: #764ba2;
            text-decoration: none;
        }

        .login-card a:hover {
            text-decoration: underline;
        }

        .status-message {
            padding: 12px;
            margin: 15px 0;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
            font-size: 14px;
        }

        .status-success {
            background-color: rgba(76, 175, 80, 0.2);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .status-error {
            background-color: rgba(244, 67, 54, 0.2);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }


    </style>
</head>
<body>
    <div class="main-container">
        <div class="logo-center">
            <img src="053Prints-white.png" alt="053Prints Logo">
        </div>
        
        <div class="login-card active">
            <div style="text-align:center; margin-bottom: 1rem;">
                <i class="fas fa-key" style="font-size:2rem; color:#764ba2;"></i>
            </div>
            <h2>Reset Password</h2>
            <p style="text-align:center; color:#555; margin-bottom:1rem;">
                Enter your new password below.
            </p>

            <?php
            if (isset($_GET['status']) && isset($_GET['message'])) {
                $status = $_GET['status'];
                $message = $_GET['message'];
                $statusClass = ($status === 'success') ? 'status-success' : 'status-error';
                echo "<div class='status-message $statusClass'>$message</div>";
            }
            ?>

            <form method="post" action="process_reset_password.php">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                
                <div style="position:relative;">
                    <input type="password" name="password" id="password" placeholder="New Password" required>
                    <button type="button" id="toggle-password" style="position:absolute;right:15px;top:43%;transform:translateY(-50%);background:none;border:none;outline:none;cursor:pointer;padding:0;">
                        <img id="password-icon" src="svg/eye.svg" alt="Show Password" width="20" height="20">
                    </button>
                </div>
                
                <div style="position:relative;">
                    <input type="password" name="password_confirmation" id="password_confirmation" placeholder="Confirm Password" required>
                    <button type="button" id="toggle-password-confirmation" style="position:absolute;right:15px;top:43%;transform:translateY(-50%);background:none;border:none;outline:none;cursor:pointer;padding:0;">
                        <img id="password-confirmation-icon" src="svg/eye.svg" alt="Show Password" width="20" height="20">
                    </button>
                </div>
                
                <button type="submit" name="reset">Reset Password</button>
            </form>
            <p>Remember your password? <a href="index.php">Login</a></p>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + '-icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>