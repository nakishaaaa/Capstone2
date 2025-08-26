<?php

session_start();

$errors = [
  'login' => $_SESSION['login_error'] ?? '',
  'register' => $_SESSION['register_error'] ?? ''
];
$activeForm = $_SESSION['active_form'] ?? 'login';
$registerSuccess = $_SESSION['register_success'] ?? '';
$forgotSuccess = $_SESSION['forgot_success'] ?? '';
$forgotError = $_SESSION['forgot_error'] ?? '';
unset($_SESSION['login_error']);
unset($_SESSION['register_error']);
unset($_SESSION['active_form']);
unset($_SESSION['register_success']);
unset($_SESSION['forgot_success']);
unset($_SESSION['forgot_error']);

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
            <span class="user-name">Login (Guest)</span>
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
<?php if (!empty($forgotSuccess)): ?>
  <div id="forgot-success-message" class="success-message" style="background:#d4edda;color:#155724;padding:10px;margin:10px 0;border:1px solid #c3e6cb;border-radius:4px;position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:9999;transition:opacity 0.5s;">
    <?= htmlspecialchars($forgotSuccess) ?>
  </div>
<?php endif; ?>
<?php if (!empty($forgotError)): ?>
  <div id="forgot-error-message" class="error-message" style="background:#f8d7da;color:#721c24;padding:10px;margin:10px 0;border:1px solid #f5c6cb;border-radius:4px;position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:9999;transition:opacity 0.5s;">
    <?= htmlspecialchars($forgotError) ?>
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
<?php if (isset($_GET['status'], $_GET['message'])): 
  $status = $_GET['status'];
  $message = htmlspecialchars($_GET['message']);
  $bg = $status === 'success' ? '#d4edda' : '#f8d7da';
  $fg = $status === 'success' ? '#155724' : '#721c24';
  $bd = $status === 'success' ? '#c3e6cb' : '#f5c6cb';
?>
  <div id="status-message" style="background:<?= $bg ?>;color:<?= $fg ?>;padding:10px;margin:10px 0;border:1px solid <?= $bd ?>;border-radius:4px;position:fixed;top:100px;left:50%;transform:translateX(-50%);z-index:9999;transition:opacity 0.5s;"><?= $message ?></div>
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
        <!-- 053 Logo in center of slideshow -->
        <div class="hero-logo-container" id="heroLogo">
          <img src="images/053logo.png" alt="053 Printing Service" class="hero-logo">
        </div>
        
        <!-- Login/Register card shown over the slideshow when required -->
        <div id="loginOverlay" class="login-overlay">
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
                  <a href="#" class="forgot-password-link" onclick="showForgotForm(); return false;">Forgot Password?</a>
                </p>
                <button type="submit" name="login">Login</button>
                <p>Don't have an account? <a href="#" onclick="showForm('register-form'); return false;">Register</a></p>
              </form>
            </div>

            <!-- Forgot Password Card -->
            <div class="login-card forgot-card" id="forgot-form">
              <div class="form-title">Forgot Password</div>
              
              <p style="text-align:center; color:#888; margin-bottom:2rem; font-size: 14px;">
                Enter your email and we'll send you a link to reset your password.
              </p>
              
              <form method="post" action="send_password_reset.php" id="forgotPasswordForm">
                <input type="email" name="email" id="fp-email" placeholder="Enter your email address" required>
                <button type="submit">Send Reset Link</button>
              </form>
              
              <p style="margin-top: 1rem;">
                <a href="#" onclick="hideForgotForm(); return false;">Back to Login</a>
              </p>
            </div>

            <div class="login-card <?= isActiveForm('register', $activeForm); ?>" id="register-form">
              <form action="login_register.php" method="post">
                <h2>Register <span style="color: #4facfe;">•</span></h2>
                <?php showError($errors['register']); ?>
                <div class="form-row">
                  <div class="form-group half-width">
                    <label for="first_name">First Name <span style="color: #4facfe;">*</span></label>
                    <input type="text" name="first_name" id="first_name" placeholder="First Name" required>
                  </div>
                  <div class="form-group half-width">
                    <label for="last_name">Last Name <span style="color: #4facfe;">*</span></label>
                    <input type="text" name="last_name" id="last_name" placeholder="Last Name" required>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group half-width">
                    <label for="username">Username <span style="color: #4facfe;">*</span></label>
                    <input type="text" name="username" id="username" placeholder="Username" required>
                  </div>
                  <div class="form-group half-width">
                    <label for="contact_number">Contact Number <span style="color: #4facfe;">*</span></label>
                    <div class="contact-input-container">
                      <span class="country-code">+63</span>
                      <input type="tel" name="contact_number" id="contact_number" placeholder="9123456789" maxlength="10" pattern="[0-9]{10}" required>
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label for="email">Email <span style="color: #4facfe;">*</span></label>
                  <input type="email" name="email" id="email" placeholder="Email" required>
                </div>
                <div class="form-group">
                  <label for="register-password">Password <span style="color: #4facfe;">*</span></label>
                  <div style="position:relative;">
                    <input type="password" name="password" id="register-password" placeholder="Password" required>
                    <button type="button" id="toggle-register-password" style="position:absolute;right:15px;top:43%;transform:translateY(-50%);background:none;border:none;outline:none;cursor:pointer;padding:0;">
                      <img id="register-eye-icon" src="images/svg/eye.svg" alt="Show Password" width="20" height="20">
                    </button>
                  </div>
                </div>
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

    <!-- Border div above contact section -->
    <div class="contact-border-divider"></div>

    <section id="contact" class="content-section contact-section">
      <div class="section-header">
        <h2>Visit Our Store</h2>
        <p>Find us at our physical location or get in touch</p>
      </div>
      <div class="contact-container">
        <div class="contact-info">
          <div class="contact-item">
            <div class="contact-icon">
              <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="contact-details">
              <h3>Address</h3>
              <p>53 San Ignacio St. Poblacion 1 3023 San Jose del Monte, Philippines</p>
            </div>
          </div>
          <div class="contact-item">
            <div class="contact-icon">
              <i class="fas fa-phone"></i>
            </div>
            <div class="contact-details">
              <h3>Phone</h3>
              <p><a href="tel:+639123456789">+63 938 817 7779</a></p>
            </div>
          </div>
          <div class="contact-item">
            <div class="contact-icon">
              <i class="fas fa-envelope"></i>
            </div>
            <div class="contact-details">
              <h3>Email</h3>
              <p><a href="mailto:info@053prints.com">info@053prints.com</a></p>
            </div>
          </div>
          <div class="contact-item">
            <div class="contact-icon">
              <i class="fas fa-clock"></i>
            </div>
            <div class="contact-details">
              <h3>Business Hours</h3>
              <p>Always Open</p>
            </div>
          </div>
        </div>
        <div class="map-container">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d964.3254926782973!2d121.04831238497772!3d14.808300904728272!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397af00461244db%3A0x40f0d4a8919fedb9!2s053%20Prints!5e0!3m2!1sen!2sph!4v1756126404023!5m2!1sen!2sph" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
      </div>
    </section>

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

    <section id="gallery" class="content-section gallery-section">
      <div class="section-header">
        <h2>From the Archive</h2>
        <p>Some of our works</p>
      </div>
      <div class="gallery-grid">
        <figure class="gallery-item">
          <img src="images/gallery/photo1.jpg">
        </figure>
        <figure class="gallery-item">
          <img src="images/gallery/photo2.jpg">
        </figure>
        <figure class="gallery-item">
          <img src="images/gallery/photo3.jpg">
        </figure>
        <figure class="gallery-item">
          <img src="images/gallery/photo4.jpg">
        </figure>
        <figure class="gallery-item">
          <img src="images/gallery/photo5.jpg">
        </figure>
        <figure class="gallery-item">
          <img src="images/gallery/photo6.jpg">
        </figure>
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
          <p class="quote" style="color: #ffffff;">“Top-notch quality and very fast turnaround!”</p>
          <div class="author" style="color: #ffffff;">— Jamie R.</div>
        </div>
        <div class="testimonial-card">
          <p class="quote" style="color: #ffffff;">“They handled my custom shirt order perfectly.”</p>
          <div class="author" style="color: #ffffff;">— Marco D.</div>
        </div>
        <div class="testimonial-card">
          <p class="quote" style="color: #ffffff;">“Great customer service. Highly recommended.”</p>
          <div class="author" style="color: #ffffff;">— Aira P.</div>
        </div>
      </div>
    </section>

    <div class="chat-button" id="supportBtn">
      <span>Support</span>
      <div class="chat-icon">
        <i class="fas fa-comments"></i>
      </div>
    </div>
    <!-- Lightbox overlay for gallery -->
    <div id="lightbox" class="lightbox-overlay" aria-hidden="true" role="dialog">
      <button class="lightbox-close" aria-label="Close">&times;</button>
      <button class="lightbox-nav prev" aria-label="Previous">&#10094;</button>
      <div class="lightbox-content">
        <img id="lightboxImage" alt="" />
        <div class="lightbox-caption" id="lightboxCaption"></div>
      </div>
      <button class="lightbox-nav next" aria-label="Next">&#10095;</button>
    </div>
  </main>

  
  <script>
  function showForm(formId) {
    document.querySelectorAll(".login-card").forEach(form => form.classList.remove("active")); 
    document.getElementById(formId).classList.add("active");
    var overlay = document.getElementById('loginOverlay');
    var heroLogo = document.getElementById('heroLogo');
    if (overlay) overlay.classList.add('active');
    if (heroLogo) heroLogo.classList.add('slide-right');
  }

  function showForgotForm() {
    var overlay = document.getElementById('loginOverlay');
    var heroLogo = document.getElementById('heroLogo');
    var forgotCard = document.getElementById('forgot-form');
    var loginCard = document.getElementById('login-form');
    
    // Show overlay and slide logo
    if (overlay) overlay.classList.add('active');
    if (heroLogo) heroLogo.classList.add('slide-right');
    
    // Hide all cards
    document.querySelectorAll(".login-card").forEach(form => form.classList.remove("active"));
    
    // Show forgot card in center (replaces login card)
    if (forgotCard) {
      forgotCard.classList.add('active');
    }
  }

  function hideForgotForm() {
    var forgotCard = document.getElementById('forgot-form');
    
    // Hide forgot card immediately
    if (forgotCard) {
      forgotCard.classList.remove('active');
      showForm('login-form');
    }
  }

  function autoHideForgotFormSuccess() {
    var forgotCard = document.getElementById('forgot-form');
    
    // Auto hide after successful submission
    if (forgotCard && forgotCard.classList.contains('active')) {
      forgotCard.classList.remove('active');
      showForm('login-form');
    }
  }

  function keepForgotFormOnError() {
    // Keep forgot card visible on error - do nothing
    // The error message will show above the card
  }

  document.addEventListener('DOMContentLoaded', function() {
    // Check if this is the first visit
    const FIRST_VISIT_KEY = 'first_visit_complete';
    const isFirstVisit = !localStorage.getItem(FIRST_VISIT_KEY);
    // Per-page flag: has the login card appeared once this load?
    let hasShownLoginOnce = false;
    
    // Show/hide login overlay based on visit status
    const loginOverlay = document.getElementById('loginOverlay');
    const heroLogo = document.getElementById('heroLogo');
    
    if (loginOverlay) {
      if (isFirstVisit) {
        // First visit - show login card and slide logo to right
        loginOverlay.classList.add('active');
        if (heroLogo) heroLogo.classList.add('slide-right');
        localStorage.setItem(FIRST_VISIT_KEY, 'true');
        // Count this as first appearance for this page load (no shake)
        hasShownLoginOnce = true;
      } else {
        // Subsequent visits - keep hidden and logo centered
        loginOverlay.classList.remove('active');
        if (heroLogo) heroLogo.classList.remove('slide-right');
      }
    }

    // Intercept gated features and prompt login
    function requireLogin(e) {
      if (e) e.preventDefault();
      showForm('login-form');
      const hero = document.querySelector('.hero-section');
      if (hero) hero.scrollIntoView({ behavior: 'smooth', block: 'center' });
      // Shake the login card for visual feedback (skip on first appearance after refresh)
      const card = document.getElementById('login-form');
      if (card) {
        if (!hasShownLoginOnce) {
          // First time showing after refresh: no shake, then mark as shown
          hasShownLoginOnce = true;
        } else {
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
        // Show login card when guest button is clicked
        requireLogin(e);
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
        }, 5000);
      }, 5000);
    }

    // Handle forgot password form messages and auto-open
    const params = new URLSearchParams(window.location.search);
    const forgotSuccessMsg = document.getElementById('forgot-success-message');
    const forgotErrorMsg = document.getElementById('forgot-error-message');
    
    if (params.get('form') === 'forgot') {
      if (forgotSuccessMsg) {
        // On success: show forgot form then hide after message
        showForgotForm();
        setTimeout(() => {
          autoHideForgotFormSuccess();
        }, 3000);
      } else if (forgotErrorMsg) {
        // On error: directly show forgot card without any animation/fade
        if (loginOverlay) loginOverlay.classList.add('active');
        if (heroLogo) heroLogo.classList.add('slide-right');
        document.querySelectorAll(".login-card").forEach(form => form.classList.remove("active"));
        const forgotCard = document.getElementById('forgot-form');
        if (forgotCard) forgotCard.classList.add('active');
      } else {
        // Default case: show forgot form normally
        showForgotForm();
      }
    }
    
    // Auto-hide status messages
    // Auto-hide forgot password messages
    if (forgotSuccessMsg) {
      setTimeout(function() {
        forgotSuccessMsg.style.opacity = '0';
        setTimeout(function() {
          forgotSuccessMsg.style.display = 'none';
        }, 500);
      }, 3000);
    }
    
    if (forgotErrorMsg) {
      setTimeout(function() {
        forgotErrorMsg.style.opacity = '0';
        setTimeout(function() {
          forgotErrorMsg.style.display = 'none';
        }, 500);
      }, 5000);
    }

    // Handle forgot password form submission
    const forgotForm = document.getElementById('forgotPasswordForm');
    if (forgotForm) {
      forgotForm.addEventListener('submit', function() {
        // Form will redirect to index.php with status, no need for immediate animation
      });
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

    // Contact number validation
    const contactInput = document.getElementById('contact_number');
    if (contactInput) {
      contactInput.addEventListener('input', function(e) {
        // Remove any non-digit characters
        let value = e.target.value.replace(/\D/g, '');
        
        // Limit to 10 digits
        if (value.length > 10) {
          value = value.slice(0, 10);
        }
        
        e.target.value = value;
      });

      contactInput.addEventListener('keypress', function(e) {
        // Only allow digits
        if (!/\d/.test(e.key) && !['Backspace', 'Delete', 'Tab', 'Enter'].includes(e.key)) {
          e.preventDefault();
        }
      });
    }

    // Gallery Lightbox
    (function(){
      var images = Array.prototype.slice.call(document.querySelectorAll('.gallery-grid img'));
      if (!images.length) return;

      // Hint cursor
      images.forEach(function(img){ img.style.cursor = 'zoom-in'; });

      var overlay = document.getElementById('lightbox');
      var lbImg = document.getElementById('lightboxImage');
      var lbCaption = document.getElementById('lightboxCaption');
      var btnClose = overlay ? overlay.querySelector('.lightbox-close') : null;
      var btnPrev = overlay ? overlay.querySelector('.lightbox-nav.prev') : null;
      var btnNext = overlay ? overlay.querySelector('.lightbox-nav.next') : null;
      var current = 0;

      function openAt(index){
        current = index;
        var src = images[current].getAttribute('src');
        var alt = images[current].getAttribute('alt') || '';
        if (lbImg) {
          lbImg.src = src;
          lbImg.alt = alt;
        }
        if (lbCaption) lbCaption.textContent = alt;
        if (overlay) {
          overlay.classList.add('open');
          overlay.setAttribute('aria-hidden','false');
        }
        document.body.style.overflow = 'hidden';
      }

      function close(){
        if (overlay) {
          overlay.classList.remove('open');
          overlay.setAttribute('aria-hidden','true');
        }
        document.body.style.overflow = '';
      }

      function show(delta){
        var len = images.length;
        var next = (current + delta + len) % len;
        openAt(next);
      }

      images.forEach(function(img, idx){
        img.addEventListener('click', function(){ openAt(idx); });
      });

      if (btnClose) btnClose.addEventListener('click', close);
      if (btnPrev) btnPrev.addEventListener('click', function(){ show(-1); });
      if (btnNext) btnNext.addEventListener('click', function(){ show(1); });

      if (overlay) {
        overlay.addEventListener('click', function(e){
          // close only when clicking outside the content
          var content = overlay.querySelector('.lightbox-content');
          if (e.target === overlay || (content && !content.contains(e.target) && !e.target.classList.contains('lightbox-nav'))) {
            close();
          }
        });
      }

      document.addEventListener('keydown', function(e){
        if (!overlay || !overlay.classList.contains('open')) return;
        if (e.key === 'Escape') close();
        else if (e.key === 'ArrowRight') show(1);
        else if (e.key === 'ArrowLeft') show(-1);
      });
    })();
  });
  </script>
  <script src="js/slideshow.js"></script>
  <script>
    // Google Maps embed loaded - no additional JavaScript needed
    document.addEventListener('DOMContentLoaded', function() {
      console.log('Google Maps embed ready');
    });
  </script>
</body>
</html>