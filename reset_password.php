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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles1.css">

    
</head>
<body>
    <div class="main-container">
        <div class="logo-center">
            <img src="images/053logo.png" alt="053Prints Logo">
        </div>
        
        <div class="login-card active">
            <div class="form-header">
                <div class="form-subtitle">Reset Password</div>
                <div class="form-title">Create new password.</div>
                <div class="login-link">
                    Remember your password? <a href="index.php">Log In</a>
                </div>
            </div>

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
                            <i class="fas fa-eye" id="password-icon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <div class="input-container">
                        <input type="password" id="password_confirmation" name="password_confirmation" required placeholder="Confirm new password">
                        <button type="button" class="password-toggle" onclick="togglePassword('password_confirmation')">
                            <i class="fas fa-eye" id="password_confirmation-icon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
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