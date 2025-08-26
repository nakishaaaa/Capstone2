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

$conn = require __DIR__ . "/includes/config.php";

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
    </style>
    
</head>
<body>
    <div class="main-container">
        <div class="logo-center">
            <img src="images/053logo.png" alt="053Prints Logo">
        </div>
        
        <div class="login-card active">
            <div class="form-title">Create new password</div>

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
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-container">
                        <input type="password" id="password" name="password" required placeholder="Enter new password">
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <img src="images/svg/eye-slash.svg" id="password-icon" alt="Show password" width="20" height="20">
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <div class="input-container">
                        <input type="password" id="password_confirmation" name="password_confirmation" required placeholder="Confirm new password">
                        <button type="button" class="password-toggle" onclick="togglePassword('password_confirmation')">
                            <img src="images/svg/eye-slash.svg" id="password_confirmation-icon" alt="Show password" width="20" height="20">
                        </button>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>

                <div class="login-link" style="margin: 0.25rem 0 0.75rem; text-align: right; color: #bbb; font-size: 13px;">
                    Remember your password? <a href="index.php" style="color:#4facfe; font-weight:600;">Log In</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const iconImg = document.getElementById(inputId + '-icon');
            if (!input || !iconImg) return;
            if (input.type === 'password') {
                input.type = 'text';
                iconImg.src = 'images/svg/eye.svg';
                iconImg.alt = 'Hide password';
            } else {
                input.type = 'password';
                iconImg.src = 'images/svg/eye-slash.svg';
                iconImg.alt = 'Show password';
            }
        }
    </script>
</body>
</html>