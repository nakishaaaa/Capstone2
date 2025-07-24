<?php

session_start();

$errors = [
  'login' => $_SESSION['login_error'] ?? '',
  'register' => $_SESSION['register_error'] ?? ''
];
$activeForm = $_SESSION['active_form'] ?? 'login';
$registerSuccess = $_SESSION['register_success'] ?? '';
session_unset();

function showError($error) {
  return !empty($error) ? "<p class='error-message'>$error</p>" : '';
}

function isActiveForm($formName, $activeForm) {
  return $formName === $activeForm ? 'active': '';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Login</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php if (!empty($registerSuccess)): ?>
  <div id="register-success-message" class="success-message" style="background:#d4edda;color:#155724;padding:10px;margin:10px 0;border:1px solid #c3e6cb;border-radius:4px;position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:9999;transition:opacity 0.5s;">
    <?= htmlspecialchars($registerSuccess) ?>
  </div>
<?php endif; ?>
<?php if (!empty($errors['register'])): ?>
  <div id="register-error-message" class="error-message" style="background:#f8d7da;color:#721c24;padding:10px;margin:10px 0;border:1px solid #f5c6cb;border-radius:4px;position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:9999;transition:opacity 0.5s;">
    <?= htmlspecialchars($errors['register']) ?>
  </div>
<?php endif; ?>
<?php if (!empty($errors['login'])): ?>
  <div id="login-error-message" class="error-message" style="background:#f8d7da;color:#721c24;padding:10px;margin:10px 0;border:1px solid #f5c6cb;border-radius:4px;position:fixed;top:60px;left:50%;transform:translateX(-50%);z-index:9999;transition:opacity 0.5s;">
    <?= htmlspecialchars($errors['login']) ?>
  </div>
<?php endif; ?>
  <div class="split-container">
    <!-- Left Box -->
    <div class="left-box">
        <img src="bg.svg" alt="abstract contour background" class="abstract-bg" style="position:absolute;top:0;left:0;width:100%;height:100%;z-index:1;object-fit:cover;opacity:0.5;" />
        
        <!-- Login Form Container Centered -->
        <div class="login-content">
          <div class="login-card <?= isActiveForm('login', $activeForm); ?>" id="login-form">
            <form action="login_register.php" method="post">
              <h2>Login</h2>
              <?php showError($errors['login']); ?>
              <input type="text" name="username" placeholder="Username">
              <input type="password" name="password" placeholder="Password">
              <button type="submit" name="login">Login</button>
              <p>Don't have an account? <a href="#" onclick="showForm('register-form'); return false;">Register</a></p>
            </form>
          </div>
          
          <div class="login-card <?= isActiveForm('register', $activeForm); ?>" id="register-form">
            <form action="login_register.php" method="post">
              <h2>Register</h2>
              <?php showError($errors['register']); ?>
              <input type="text" name="username" placeholder="Username">
              <input type="email" name="email" placeholder="Email">
              <input type="password" name="password" placeholder="Password">
              <select name="role" required> 
                <option value="" disabled selected> --SELECT ROLE-- </option>
                <option value="admin">Admin</option>
                <option value="user">User</option>
              </select>
              <button type="submit" name="register">Register</button>

              <p>Already have an account? <a href="#" onclick="showForm('login-form'); return false;">Login</a></p>
            </form>
          </div>
        
        </div>
    </div>

    <!-- Right Box -->
    <div class="right-box">
      <img src="053 bg.jpg" alt="053bg" class="split-image">
    </div>
  </div>

  <script src="script.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    var msg = document.getElementById('register-success-message');
    if (msg) {
      setTimeout(function() {
        msg.style.opacity = '0';
        setTimeout(function() {
          msg.style.display = 'none';
        }, 500);
      }, 3000);
    }
    var err = document.getElementById('register-error-message');
    if (err) {
      setTimeout(function() {
        err.style.opacity = '0';
        setTimeout(function() {
          err.style.display = 'none';
        }, 500);
      }, 3000);
    }
    var loginErr = document.getElementById('login-error-message');
    if (loginErr) {
      setTimeout(function() {
        loginErr.style.opacity = '0';
        setTimeout(function() {
          loginErr.style.display = 'none';
        }, 500);
      }, 3000);
    }
  });
  </script>
</body>
</html>