<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/reset_style.css">
</head>
<body>
    <?php
    // Display status messages at the top like in index.php
    if (isset($_GET['status']) && isset($_GET['message'])) {
        $status = $_GET['status'];
        $message = htmlspecialchars($_GET['message']);
        $bgColor = ($status === 'success') ? '#d4edda' : '#f8d7da';
        $textColor = ($status === 'success') ? '#155724' : '#721c24';
        $borderColor = ($status === 'success') ? '#c3e6cb' : '#f5c6cb';
        
        echo "<div id='status-message' style='background:$bgColor;color:$textColor;padding:10px;margin:10px 0;border:1px solid $borderColor;border-radius:4px;position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:9999;transition:opacity 0.5s;'>$message</div>";
    }
    ?>

    <div class="main-container">
        <div class="logo-center">
            <img src="images/053logo.png" alt="053Prints Logo">
        </div>
        
        <div class="login-content">
            <div class="login-card active">
                <div style="text-align:center; margin-bottom: 2rem;">
                    <i class="fas fa-unlock-alt" style="font-size:2rem; color:#4facfe;"></i>
                </div>
                
                <div class="form-title">Forgot Password</div>
                
                <p style="text-align:center; color:#888; margin-bottom:2rem; font-size: 14px;">
                    Enter your email and we'll send you a link to reset your password.
                </p>
                
                <form method="post" action="send_password_reset.php">
                    <input type="email" name="email" id="email" placeholder="Enter your email address" required>
                    <button type="submit">Send Reset Link</button>
                </form>
                
                <p style="margin-top: 1rem;">
                    <a href="index.php">Back to Login</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide status messages like in index.php
        document.addEventListener('DOMContentLoaded', function() {
            var statusMsg = document.getElementById('status-message');
            if (statusMsg) {
                setTimeout(function() {
                    statusMsg.style.opacity = '0';
                    setTimeout(function() {
                        statusMsg.style.display = 'none';
                    }, 500);
                }, 3000);
            }
        });
    </script>
</body>
</html>