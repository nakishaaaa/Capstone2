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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="css/reset_style.css">


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
  <div class="main-container">
        <div class="logo-center">
      <img src="images/053logo.png" alt="053Prints Logo">
    </div>
        <!-- Login Form  -->
        <div class="login-content">
          <div class="login-card <?= isActiveForm('login', $activeForm); ?>" id="login-form">
            <form action="login_register.php" method="post">
              <div class="form-title">Login</div>
              <?php showError($errors['login']); ?>
              <input type="text" name="username" placeholder="Username" required>
              <div class="input-container">
                <input type="password" name="password" id="login-password" placeholder="Password" required>
                <button type="button" id="toggle-login-password" style="position:absolute;right:15px;top:43%;transform:translateY(-50%);background:none;border:none;outline:none;cursor:pointer;padding:0;">
                  <img id="login-eye-icon" src="svg/eye.svg" alt="Show Password" width="20" height="20">
                </button>
              </div>
              <p style="text-align:right; margin-bottom: 1rem;">                 <!-- wip -->
                <a href="forgot_password.php" class="forgot-password-link">Forgot Password?</a>
              </p>
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
              <div style="position:relative;">
                <input type="password" name="password" id="register-password" placeholder="Password">
                <button type="button" id="toggle-register-password" style="position:absolute;right:15px;top:43%;transform:translateY(-50%);background:none;border:none;outline:none;cursor:pointer;padding:0;">
                  <img id="register-eye-icon" src="svg/eye.svg" alt="Show Password" width="20" height="20">
                </button>
              </div>
                <select name="role" id="role" required>
                  <option value="" disabled selected> --SELECT ROLE-- </option>
                  <option value="admin">Admin</option>
                  <option value="user">User</option>
                </select>
                <style>
                  select {
                    width: 100%;
                    padding: 0.75rem 1rem;
                    margin-bottom: 1rem;
                    border: 1px solid #ddd;
                    border-radius: 0.75rem;
                    font-size: 1rem;
                    outline: none;
                  }
                </style>
              <button type="submit" name="register">Register</button>

              <p>Already have an account? <a href="#" onclick="showForm('login-form'); return false;">Login</a></p>
            </form>
          </div>
        </div>
  </div>
  
  <script>
  function showForm(formId) {
    document.querySelectorAll(".login-card").forEach(form => form.classList.remove("active")); 
    document.getElementById(formId).classList.add("active");
  }

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
    
    function setupPasswordToggle(inputId, buttonId, iconId) {
      var input = document.getElementById(inputId);
      var button = document.getElementById(buttonId);
      var iconImg = document.getElementById(iconId);
      var eyeSrc = 'svg/eye.svg';
      var eyeSlashSrc = 'svg/eye-slash.svg';
      if (input && button && iconImg) {
        button.addEventListener('click', function() {
          if (input.type === 'password') {
            input.type = 'text';
            iconImg.src = eyeSrc;
            iconImg.alt = 'Hide Password';
          } else {
            input.type = 'password';
            iconImg.src = eyeSlashSrc;
            iconImg.alt = 'Show Password';
          }
        });
        // Set initial icon state
        iconImg.src = eyeSlashSrc;
        iconImg.alt = 'Show Password';
      }
    }
    setupPasswordToggle('login-password', 'toggle-login-password', 'login-eye-icon');
    setupPasswordToggle('register-password', 'toggle-register-password', 'register-eye-icon');
  });
  </script>
</body>
</html>