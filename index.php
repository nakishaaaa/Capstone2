<?php

session_start();

$errors = [
  'login' => $_SESSION['login_error'] ?? '',
  'register' => $_SESSION['register_error'] ?? ''
];
$activeForm = $_SESSION['active_form'] ?? 'login';
$registerSuccess = $_SESSION['register_success'] ?? '';
// Clear only flash keys instead of the entire session to avoid logging out other tabs
unset($_SESSION['login_error']);
unset($_SESSION['register_error']);
unset($_SESSION['active_form']);
unset($_SESSION['register_success']);

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
  <title>053 Login</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="css/index.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=UnifrakturMaguntia&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=UnifrakturCook:wght@700&display=swap" rel="stylesheet">

</head>
<body>
  <div class="container">
    <header class="header">
      <div class="ai-tools">
        <div class="ai-dropdown">
          <div class="ai-trigger" id="aiTrigger" title="AI Tools">
            <i class="fas fa-atom"></i>
            <span>AI</span>
          </div>
        </div>
      </div>
      <div class="brand">
        <span>053</span>
      </div>
      <div class="user-info">
        <div class="user-dropdown">
          <button class="user-dropdown-btn" id="userDropdownBtn">
            <div class="user-avatar">
              <i class="fas fa-user"></i>
            </div>
            <span class="user-name">Guest</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </button>
          <div class="user-dropdown-menu" id="userDropdownMenu">
            <div class="dropdown-header">
              <div class="user-profile">
                <div class="profile-avatar">
                  <i class="fas fa-user"></i>
                </div>
                <div class="profile-info">
                  <div class="profile-name">Welcome</div>
                  <div class="profile-email">Please sign in to continue</div>
                </div>
              </div>
            </div>
            <div class="dropdown-actions">
              <a href="#" class="dropdown-action" id="goToLoginFromDropdown">
                <i class="fas fa-sign-in-alt"></i>
                Sign in
              </a>
            </div>
          </div>
        </div>
      </div>
    </header>
  </div>
  <!-- AI Promo Callout -->
  <div id="aiPromo" class="ai-promo-callout" style="display:none;">
    <div class="arrow" aria-hidden="true"></div>
    <i class="fas fa-robot" aria-hidden="true"></i>
    <div>
      <div><b>New:</b> Try our AI tools powered by <b>DeepAI</b>.</div>
      <div>Click the <b>AI</b> button in the top-left to generate or enhance images.</div>
    </div>
    <button class="close-callout" id="closeAiPromo" title="Dismiss">&times;</button>
  </div>
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
  
  <!-- Visuals from user_page: hero slideshow and sections -->
  <main class="main-content">
    <div class="hero-section">
      <div class="slideshow-container">
        <div class="slide active"></div>
        <div class="slide"></div>
        <div class="slide"></div>
        <div class="slide"></div>
        <div class="slide"></div>
      </div>
      <div class="hero-overlay">
        <!-- Login/Register card shown over the slideshow when required -->
        <div id="loginOverlay" class="login-overlay active">
          <div class="login-content">
            <div class="login-card <?= isActiveForm('login', $activeForm); ?>" id="login-form">
              <form action="login_register.php" method="post">
                <div class="form-title">Login</div>
                <?php showError($errors['login']); ?>
                <input type="text" name="username" placeholder="Username" required>
                <div class="input-container">
                  <input type="password" name="password" id="login-password" placeholder="Password" required>
                  <button type="button" id="toggle-login-password" style="position:absolute;right:15px;top:43%;transform:translateY(-50%);background:none;border:none;outline:none;cursor:pointer;padding:0;">
                    <img id="login-eye-icon" src="images/svg/eye.svg" alt="Show Password" width="20" height="20">
                  </button>
                </div>
                <p style="text-align:right; margin-bottom: 1rem;">
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
                    <img id="register-eye-icon" src="images/svg/eye.svg" alt="Show Password" width="20" height="20">
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
      </div>
      <div class="social-links" role="navigation" aria-label="Social Media Links">
        <a href="https://www.facebook.com/053printingservice" target="_blank" rel="noopener" aria-label="Facebook">
          <i class="fab fa-facebook-f"></i>
        </a>
        <a href="https://www.instagram.com/053prints" target="_blank" rel="noopener" aria-label="Instagram">
          <i class="fab fa-instagram"></i>
        </a>
        <a href="https://www.tiktok.com/@053.prints?lang=en" target="_blank" rel="noopener" aria-label="TikTok">
          <i class="fab fa-tiktok"></i>
        </a>
      </div>
    </div>

    <section id="services" class="content-section services-section">
      <div class="section-header">
        <h2>Our Services</h2>
        <p>High-quality prints and office services tailored to your needs</p>
      </div>
      <div class="services-grid">
        <div class="service-card">
          <div class="service-icon"><i class="fas fa-shirt"></i></div>
          <h3>T-Shirt Print</h3>
          <p>Custom designs with vibrant colors and durable materials.</p>
        </div>
        <div class="service-card">
          <div class="service-icon"><i class="fas fa-tags"></i></div>
          <h3>Tag & Sticker</h3>
          <p>Labels and stickers for branding, packaging, and events.</p>
        </div>
        <div class="service-card">
          <div class="service-icon"><i class="fas fa-id-card"></i></div>
          <h3>Cards</h3>
          <p>ID, business, and custom cards with premium finishes.</p>
        </div>
        <div class="service-card">
          <div class="service-icon"><i class="fas fa-print"></i></div>
          <h3>Document & Photo</h3>
          <p>Clear document prints and photo prints in all standard sizes.</p>
        </div>
        <div class="service-card">
          <div class="service-icon"><i class="fas fa-copy"></i></div>
          <h3>Photo Copy</h3>
          <p>Fast and accurate photocopy with high-resolution output.</p>
        </div>
        <div class="service-card">
          <div class="service-icon"><i class="fas fa-layer-group"></i></div>
          <h3>Lamination</h3>
          <p>Protect your prints with high-quality lamination.</p>
        </div>
      </div>
    </section>

    <section id="how" class="content-section how-section">
      <div class="section-header">
        <h2>How It Works</h2>
        <p>Simple steps from request to delivery</p>
      </div>
      <div class="how-grid">
        <div class="step-card">
          <span class="step-number">1</span>
          <h3>Submit Request</h3>
          <p>Click "Request an Order" and provide the details and files.</p>
        </div>
        <div class="step-card">
          <span class="step-number">2</span>
          <h3>We Review</h3>
          <p>Our team confirms specs, timeline, and pricing.</p>
        </div>
        <div class="step-card">
          <span class="step-number">3</span>
          <h3>Production</h3>
          <p>We print and prepare your order with care and quality.</p>
        </div>
        <div class="step-card">
          <span class="step-number">4</span>
          <h3>Pick Up / Delivery</h3>
          <p>Receive your finished order on schedule.</p>
        </div>
      </div>
    </section>

    <section id="testimonials" class="content-section testimonials-section">
      <div class="section-header">
        <h2>What Customers Say</h2>
        <p>Real feedback from our satisfied clients</p>
      </div>
      <div class="testimonials-grid">
        <div class="testimonial-card">
          <p class="quote">“Top-notch quality and very fast turnaround!”</p>
          <div class="author">— Jamie R.</div>
        </div>
        <div class="testimonial-card">
          <p class="quote">“They handled my custom shirt order perfectly.”</p>
          <div class="author">— Marco D.</div>
        </div>
        <div class="testimonial-card">
          <p class="quote">“Great customer service. Highly recommended.”</p>
          <div class="author">— Aira P.</div>
        </div>
      </div>
    </section>

    <div class="chat-button" id="supportBtn">
      <span>Support</span>
      <div class="chat-icon">
        <i class="fas fa-comments"></i>
      </div>
    </div>
  </main>

  
  <script>
  function showForm(formId) {
    document.querySelectorAll(".login-card").forEach(form => form.classList.remove("active")); 
    document.getElementById(formId).classList.add("active");
    var overlay = document.getElementById('loginOverlay');
    if (overlay) overlay.classList.add('active');
  }

  document.addEventListener('DOMContentLoaded', function() {
    // Intercept gated features and prompt login
    function requireLogin(e) {
      if (e) e.preventDefault();
      showForm('login-form');
      const hero = document.querySelector('.hero-section');
      if (hero) hero.scrollIntoView({ behavior: 'smooth', block: 'center' });
      // Shake the login card for visual feedback
      const card = document.getElementById('login-form');
      if (card) {
        card.classList.remove('shake');
        // force reflow to restart animation
        void card.offsetWidth;
        card.classList.add('shake');
        const cleanup = () => {
          card.classList.remove('shake');
          card.removeEventListener('animationend', cleanup);
        };
        card.addEventListener('animationend', cleanup);
      }
    }

    const aiTrigger = document.getElementById('aiTrigger');
    const aiGen = document.getElementById('aiGeneratorOption');
    const aiEdit = document.getElementById('aiEditorOption');
    const reqBtn = document.getElementById('showRequestFormBtn');
    const supportBtn = document.getElementById('supportBtn');
    const goLogin = document.getElementById('goToLoginFromDropdown');
    [aiTrigger, aiGen, aiEdit, reqBtn, supportBtn, goLogin].forEach(btn => {
      if (btn) btn.addEventListener('click', requireLogin);
    });

    // Highlight AI button and show promo (once per session)
    const PROMO_KEY = 'ai_promo_dismissed';
    const aiPromo = document.getElementById('aiPromo');
    const closeAiPromo = document.getElementById('closeAiPromo');
    const dismissed = sessionStorage.getItem(PROMO_KEY) === '1';
    if (!dismissed && aiTrigger) {
      aiTrigger.classList.add('ai-highlight');
      if (aiPromo) aiPromo.style.display = 'flex';
      // Remove highlight after first interaction
      const clearHighlight = () => aiTrigger.classList.remove('ai-highlight');
      aiTrigger.addEventListener('mouseenter', clearHighlight, { once: true });
      aiTrigger.addEventListener('click', () => {
        clearHighlight();
        if (aiPromo) aiPromo.style.display = 'none';
        sessionStorage.setItem(PROMO_KEY, '1');
      }, { once: true });
    }
    if (closeAiPromo) {
      closeAiPromo.addEventListener('click', function(){
        const box = document.getElementById('aiPromo');
        if (box) box.style.display = 'none';
        sessionStorage.setItem(PROMO_KEY, '1');
      });
    }

    // Basic dropdown toggle for guest menu
    const userDropdownBtn = document.getElementById('userDropdownBtn');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    if (userDropdownBtn && userDropdownMenu) {
      userDropdownBtn.addEventListener('click', function(e){
        e.preventDefault();
        userDropdownMenu.style.display = userDropdownMenu.style.display === 'block' ? 'none' : 'block';
      });
      document.addEventListener('click', function(e){
        if (!userDropdownBtn.contains(e.target) && !userDropdownMenu.contains(e.target)) {
          userDropdownMenu.style.display = 'none';
        }
      });
    }

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
      }, 5000);
    }
    
    function setupPasswordToggle(inputId, buttonId, iconId) {
      var input = document.getElementById(inputId);
      var button = document.getElementById(buttonId);
      var iconImg = document.getElementById(iconId);
      var eyeSrc = 'images/svg/eye.svg';
      var eyeSlashSrc = 'images/svg/eye-slash.svg';
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
  <script src="js/slideshow.js"></script>
</body>
</html>