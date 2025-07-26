<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="split-container">
        <div class="left-box">
            <img src="bg.svg" alt="abstract contour background" style="position:absolute;top:0;left:0;width:100%;height:100%;z-index:1;object-fit:cover;opacity:0.5;" />
            <div class="login-content">
                <div class="login-card active">
                    <div style="text-align:center; margin-bottom: 1rem;">
                        <i class="fas fa-unlock-alt" style="font-size:2rem; color:#764ba2;"></i>
                    </div>
                    <h2 style="color:#3366cc;">Forgot Password</h2>
                    <p style="text-align:center; color:#555; margin-bottom:1rem;">
                        Enter your email and we'll send you a link to reset your password.
                    </p>
                    <form method="post" action="send_password_reset.php">
                        <label for="email" style="display:block;margin-bottom:0.5rem;">Email</label>
                        <input type="email" name="email" id="email" required>
                        <button type="submit">Send</button>
                    </form>
                    <p><a href="index.php">Back to Login</a></p>
                </div>
            </div>
        </div>
        <div class="right-box">
            <img src="images/053 bg.jpg" alt="053bg" class="split-image">
        </div>
    </div>
</body>
</html>