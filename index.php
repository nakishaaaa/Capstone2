<?php

session_start();
require_once 'includes/csrf.php'; // Include CSRF protection

$errors = [
  'login' => $_SESSION['login_error'] ?? '',
  'register' => $_SESSION['register_error'] ?? ''
];
$activeForm = $_SESSION['active_form'] ?? 'login';
$registerSuccess = $_SESSION['register_success'] ?? '';
$forgotSuccess = $_SESSION['forgot_success'] ?? '';
$forgotError = $_SESSION['forgot_error'] ?? '';
$verificationSuccess = $_SESSION['verification_success'] ?? '';
$verificationError = $_SESSION['verification_error'] ?? '';
unset($_SESSION['login_error']);
unset($_SESSION['register_error']);
unset($_SESSION['active_form']);
unset($_SESSION['register_success']);
unset($_SESSION['forgot_success']);
unset($_SESSION['forgot_error']);
unset($_SESSION['verification_success']);
unset($_SESSION['verification_error']);

// Generate CSRF token for forms
$csrfToken = CSRFToken::getToken();

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
  <title>053prints</title>
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
      <div class="home-icon">
        <button class="home-btn" id="homeBtn" title="Back to Top">
          <i class="fas fa-home"></i>
        </button>
      </div>
      <div class="brand">
        <span></span>
      </div>
      <nav class="navbar-nav">
        <a href="#contact" class="nav-link">Contact</a>
        <a href="#services" class="nav-link">Services</a>
        <a href="#gallery" class="nav-link">Gallery</a>
        <a href="#how" class="nav-link">How It Works</a>
        <a href="#reviews" class="nav-link">Reviews</a>
      </nav>
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
<?php if (!empty($verificationSuccess)): ?>
  <div id="verification-success-message" class="success-message" style="background:#d4edda;color:#155724;padding:10px;margin:10px 0;border:1px solid #c3e6cb;border-radius:4px;position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:9999;transition:opacity 0.5s;">
    <?= htmlspecialchars($verificationSuccess) ?>
  </div>
<?php endif; ?>
<?php if (!empty($verificationError)): ?>
  <div id="verification-error-message" class="error-message" style="background:#f8d7da;color:#721c24;padding:10px;margin:10px 0;border:1px solid #f5c6cb;border-radius:4px;position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:9999;transition:opacity 0.5s;">
    <?= htmlspecialchars($verificationError) ?>
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
          <div class="hero-tagline">
            <p>Custom prints and designs made to bring your ideas to life.</p>
          </div>
        </div>
        
        <!-- Login/Register card shown over the slideshow when required -->
        <div id="loginOverlay" class="login-overlay">
          <div class="login-content">
            <div class="login-card <?= isActiveForm('login', $activeForm); ?>" id="login-form">
              <button type="button" class="close-btn" onclick="hideLoginOverlay()" title="Close">
                <i class="fas fa-times"></i>
              </button>
              <form action="login_register.php" method="post">
                <div class="form-title">Login</div>
                <?php showError($errors['login']); ?>
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="text" name="username" placeholder="Username" required>
                <div class="input-container">
                  <input type="password" name="password" id="login-password" placeholder="Password" style="padding-right: 50px;" required>
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
              <button type="button" class="close-btn" onclick="hideLoginOverlay()" title="Close">
                <i class="fas fa-times"></i>
              </button>
              <div class="form-title">Forgot Password</div>
              
              <p style="text-align:center; color:#888; margin-bottom:2rem; font-size: 14px;">
                Enter your email and we'll send you a link to reset your password.
              </p>
              
              <form method="post" action="send_password_reset.php" id="forgotPasswordForm">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="email" name="email" id="fp-email" placeholder="Enter your email address" required>
                <button type="submit">Send Reset Link</button>
              </form>
              
              <p style="margin-top: 1rem;">
                <a href="#" onclick="hideForgotForm(); return false;">Back to Login</a>
              </p>
            </div>


            <div class="login-card <?= isActiveForm('register', $activeForm); ?>" id="register-form">
              <button type="button" class="close-btn" onclick="hideLoginOverlay()" title="Close">
                <i class="fas fa-times"></i>
              </button>
              <form action="login_register.php" method="post">
                <h2>Register <span style="color: #4facfe;">•</span></h2>
                <?php showError($errors['register']); ?>
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div class="form-row">
                  <div class="form-group half-width">
                    <label for="first_name">First Name <span style="color: #4facfe;">*</span></label>
                    <input type="text" name="first_name" id="first_name" placeholder="First Name" minlength="2" maxlength="50" required>
                  </div>
                  <div class="form-group half-width">
                    <label for="last_name">Last Name <span style="color: #4facfe;">*</span></label>
                    <input type="text" name="last_name" id="last_name" placeholder="Last Name" minlength="2" maxlength="50" required>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group half-width">
                    <label for="username">Username <span style="color: #4facfe;">*</span></label>
                    <input type="text" name="username" id="username" placeholder="Username (3-20 characters)" minlength="3" maxlength="20" pattern="[a-zA-Z0-9]{3,20}" title="Username must be 3-20 characters long and contain only letters and numbers" required>
                  </div>
                  <div class="form-group half-width">
                    <label for="contact_number">Contact Number <span style="color: #4facfe;">*</span></label>
                    <div class="contact-input-container">
                      <span class="country-code">+63</span>
                      <input type="tel" name="contact_number" id="contact_number" placeholder="9123456789" maxlength="10" pattern="[1-9][0-9]{9}" required>
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label for="email">Email <span style="color: #4facfe;">*</span></label>
                  <div class="email-requirement" id="email-requirement">
                    <i class="fas fa-times requirement-icon"></i>
                    <span>Email required</span>
                  </div>
                  <input type="email" name="email" id="email" placeholder="Use Gmail, Outlook, Yahoo, etc." required>
                </div>
                <div class="form-group">
                  <label for="register-password">Password <span style="color: #4facfe;">*</span></label>
                  <div class="password-input-row">
                    <div class="password-input-container">
                      <div style="position:relative;">
                        <input type="password" name="password" id="register-password" placeholder="Password" minlength="8" maxlength="64" style="padding-right: 50px;" required>
                        <button type="button" id="toggle-register-password" style="position:absolute;right:15px;top:43%;transform:translateY(-50%);background:none;border:none;outline:none;cursor:pointer;padding:0;">
                          <img id="register-eye-icon" src="images/svg/eye.svg" alt="Show Password" width="20" height="20">
                        </button>
                      </div>
                    </div>
                    <div class="password-requirements-container">
                      <div class="password-requirement" id="length-requirement">
                        <i class="fas fa-times requirement-icon"></i>
                        <span>Minimum 8 characters</span>
                      </div>
                      <div class="password-requirement" id="lowercase-requirement">
                        <i class="fas fa-times requirement-icon"></i>
                        <span>A lowercase letter</span>
                      </div>
                      <div class="password-requirement" id="uppercase-requirement">
                        <i class="fas fa-times requirement-icon"></i>
                        <span>A capital (uppercase) letter</span>
                      </div>
                      <div class="password-requirement" id="number-requirement">
                        <i class="fas fa-times requirement-icon"></i>
                        <span>A number</span>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Terms & Conditions Checkbox -->
                <div class="form-group terms-checkbox-group">
                  <label class="checkbox-container" id="termsCheckboxContainer">
                    <input type="checkbox" name="terms_agreement" id="terms_agreement" required disabled>
                    <span class="checkmark"></span>
                    <span class="checkbox-text">
                      I agree to the <a href="#" onclick="openTermsModal(); return false;" class="terms-link">Terms & Conditions</a> of this website
                    </span>
                    <span class="checkbox-tooltip" id="termsTooltip">Please read the Terms & Conditions first</span>
                  </label>
                </div>
                
                <button type="submit" name="register">Register</button>

                <p>Already have an account? <a href="#" onclick="showForm('login-form'); return false;">Login</a></p>
              </form>
            </div>
          </div>
        </div>
      </div>
      <div class="social-section">p
        <div class="social-text">
          <span>Visit Our Socials</span>
          <i class="fas fa-arrow-down"></i>
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
    </div>

    <div class="ai-feature-banner">
      <div class="ai-banner-content">
        <i class="fas fa-atom ai-banner-icon"></i>
        <div class="ai-banner-text">
          <span class="ai-banner-title">NEW: AI Design Tools</span>
          <span class="ai-banner-subtitle">Generate & enhance images with AI • Powered by DeepAI</span>
        </div>
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

    <section id="reviews" class="content-section testimonials-section">
      <div class="section-header">
        <h2>What Customers Say</h2>
        <p> </p>
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

    <!-- Anonymous Support Modal -->
    <div id="anonymousSupportModal" class="support-modal" style="display: none;">
        <div class="support-modal-content">
            <div class="support-modal-header">
                <button class="support-back-btn" onclick="closeAnonymousSupportModal()">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <h2>Send a message</h2>
            </div>
            
            <div class="support-modal-body">
                <div id="anonymousSupportMessage" style="margin-bottom: 15px; padding: 12px; border-radius: 8px; display: none;"></div>
                
                <div class="support-header-info">
                    <h3>How can we help?</h3>
                    <p>We usually respond in a few hours</p>
                </div>
                
                <form id="anonymousSupportForm" class="support-form">
                    
                    <div class="support-form-group">
                        <label for="anonymousEmail">Email (Optional - for reply notifications)</label>
                        <input type="email" id="anonymousEmail" name="email" placeholder="your.email@example.com">
                        <small style="color: #6b7280; font-size: 0.8rem;">We'll send you a ticket ID and reply notifications</small>
                    </div>
                    
                    <div class="support-form-group">
                        <label for="anonymousSubject">Subject</label>
                        <input type="text" id="anonymousSubject" name="subject" required>
                    </div>
                    
                    <div class="support-form-group">
                        <label for="anonymousMessage">How can we help?</label>
                        <textarea id="anonymousMessage" name="message" rows="6" required placeholder="Describe your issue or question..."></textarea>
                    </div>
                    
                    <button type="submit" class="support-send-btn">
                        Send a message
                    </button>
                </form>
                
                <!-- Check Existing Ticket Section -->
                <div style="margin-top: 20px; border-top: 1px solid #e5e7eb; text-align: center;">
                    <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 10px;">Already have a ticket?</p>
                    <a href="check-ticket.php" target="_blank" style="display: inline-flex; align-items: center; gap: 8px; color: #3b82f6; text-decoration: none; font-weight: 500; padding: 8px 16px; border: 1px solid #3b82f6; border-radius: 6px; transition: all 0.2s;">
                        <i class="fas fa-search"></i>
                        Check Ticket Status
                    </a>
                </div>
            </div>
        </div>
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

  function hideLoginOverlay() {
    var overlay = document.getElementById('loginOverlay');
    var heroLogo = document.getElementById('heroLogo');
    
    // Hide overlay and center logo
    if (overlay) overlay.classList.remove('active');
    if (heroLogo) heroLogo.classList.remove('slide-right');
    
    // Hide all cards
    document.querySelectorAll(".login-card").forEach(form => form.classList.remove("active"));
  }

  function closeAnonymousSupportModal() {
    const modal = document.getElementById('anonymousSupportModal');
    const form = document.getElementById('anonymousSupportForm');
    const message = document.getElementById('anonymousSupportMessage');
    
    if (modal) {
      modal.classList.remove('active');
      setTimeout(() => {
        modal.style.display = 'none';
      }, 400);
    }
    if (form) form.reset();
    if (message) message.style.display = 'none';
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
    
    // Track button clicks for shake effect
    const buttonClickCounts = {
      aiTrigger: 0,
      userDropdownBtn: 0,
      supportBtn: 0
    };
    
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

    // Function to add shake effect to login card
    function addShakeEffect() {
      const loginCard = document.querySelector('.login-card.active') || document.getElementById('login-form');
      if (loginCard) {
        loginCard.classList.remove('shake');
        // Force reflow to restart animation
        void loginCard.offsetWidth;
        loginCard.classList.add('shake');
        const cleanup = () => {
          loginCard.classList.remove('shake');
          loginCard.removeEventListener('animationend', cleanup);
        };
        loginCard.addEventListener('animationend', cleanup);
      }
    }

    // Intercept gated features and prompt login
    function requireLogin(e, buttonType = null) {
      if (e) e.preventDefault();
      
      // Track button clicks for shake effect
      if (buttonType && buttonClickCounts.hasOwnProperty(buttonType)) {
        buttonClickCounts[buttonType]++;
        
        // Show login form
        showForm('login-form');
        const hero = document.querySelector('.hero-section');
        if (hero) hero.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Add shake effect on second click and beyond
        if (buttonClickCounts[buttonType] >= 2) {
          setTimeout(() => {
            addShakeEffect();
          }, 300); // Small delay to ensure login form is visible
        } else {
          // First time showing after refresh: no shake, then mark as shown
          if (!hasShownLoginOnce) {
            hasShownLoginOnce = true;
          }
        }
      } else {
        // Original behavior for other buttons
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
    }

    const aiTrigger = document.getElementById('aiTrigger');
    const aiGen = document.getElementById('aiGeneratorOption');
    const aiEdit = document.getElementById('aiEditorOption');
    const reqBtn = document.getElementById('showRequestFormBtn');
    const supportBtn = document.getElementById('supportBtn');
    const goLogin = document.getElementById('goToLoginFromDropdown');
    
    // Add event listeners with button type tracking for specific buttons
    if (aiTrigger) aiTrigger.addEventListener('click', (e) => requireLogin(e, 'aiTrigger'));
    
    // Support button opens anonymous support modal
    if (supportBtn) {
      supportBtn.addEventListener('click', function() {
        const modal = document.getElementById('anonymousSupportModal');
        modal.style.display = 'block';
        setTimeout(() => {
          modal.classList.add('active');
        }, 10);
      });
    }
    
    // Other buttons without shake tracking
    [aiGen, aiEdit, reqBtn, goLogin].forEach(btn => {
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
        requireLogin(e, 'userDropdownBtn');
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
      // Show register form when there's a registration error
      showForm('register-form');
      setTimeout(function() {
        err.style.opacity = '0';
        setTimeout(function() {
          err.style.display = 'none';
        }, 500);
      }, 3000);
    }
    var loginErr = document.getElementById('login-error-message');
    if (loginErr) {
      showForm('login-form');
      setTimeout(function() {
        loginErr.style.opacity = '0';
        setTimeout(function() {
          loginErr.style.display = 'none';
        }, 5000);
      }, 5000);
    }

    // Handle status messages from password reset
    var statusMsg = document.getElementById('status-message');
    if (statusMsg) {
      setTimeout(function() {
        statusMsg.style.opacity = '0';
        setTimeout(function() {
          statusMsg.style.display = 'none';
        }, 500);
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

    // Auto-hide verification messages
    const verificationSuccessMsg = document.getElementById('verification-success-message');
    const verificationErrorMsg = document.getElementById('verification-error-message');
    
    if (verificationSuccessMsg) {
      setTimeout(function() {
        verificationSuccessMsg.style.opacity = '0';
        setTimeout(function() {
          verificationSuccessMsg.style.display = 'none';
        }, 500);
      }, 5000);
    }
    
    if (verificationErrorMsg) {
      setTimeout(function() {
        verificationErrorMsg.style.opacity = '0';
        setTimeout(function() {
          verificationErrorMsg.style.display = 'none';
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
    
    // Contact number validation - prevent starting with 0
    const contactNumberInput = document.getElementById('contact_number');
    if (contactNumberInput) {
      contactNumberInput.addEventListener('input', function(e) {
        let value = e.target.value;
        
        // Remove any non-digit characters
        value = value.replace(/\D/g, '');
        
        // If first digit is 0, remove it
        if (value.startsWith('0')) {
          value = value.substring(1);
        }
        
        // Update the input value
        e.target.value = value;
      });
      
      contactNumberInput.addEventListener('keydown', function(e) {
        // If input is empty and user tries to type 0, prevent it
        if (e.target.value === '' && e.key === '0') {
          e.preventDefault();
          // Show a brief visual feedback
          e.target.style.borderColor = '#ff4757';
          setTimeout(() => {
            e.target.style.borderColor = '';
          }, 1000);
        }
      });
      
      contactNumberInput.addEventListener('paste', function(e) {
        setTimeout(() => {
          let value = e.target.value;
          // Remove any non-digit characters
          value = value.replace(/\D/g, '');
          // If first digit is 0, remove it
          if (value.startsWith('0')) {
            value = value.substring(1);
          }
          e.target.value = value;
        }, 0);
      });
    }
    
    // Username validation - 3-20 characters, letters and numbers only
    const usernameInput = document.getElementById('username');
    if (usernameInput) {
      usernameInput.addEventListener('input', function(e) {
        let value = e.target.value;
        
        // Remove invalid characters (keep only letters and numbers)
        value = value.replace(/[^a-zA-Z0-9]/g, '');
        
        // Trim to 20 characters if longer
        if (value.length > 20) {
          value = value.substring(0, 20);
        }
        
        // Update the input value
        e.target.value = value;
      });
      
      usernameInput.addEventListener('keydown', function(e) {
        // Prevent invalid characters from being typed
        const invalidChars = /[^a-zA-Z0-9]/;
        if (invalidChars.test(e.key) && !['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) {
          e.preventDefault();
        }
      });
    }
    
    // First Name and Last Name validation - 2-50 characters, letters only
    const firstNameInput = document.getElementById('first_name');
    const lastNameInput = document.getElementById('last_name');
    
    function setupNameValidation(input) {
      if (input) {
        input.addEventListener('input', function(e) {
          let value = e.target.value;
          
          // Remove invalid characters (keep only letters and spaces)
          value = value.replace(/[^a-zA-Z\s]/g, '');
          
          // Trim to 50 characters if longer
          if (value.length > 50) {
            value = value.substring(0, 50);
          }
          
          // Update the input value
          e.target.value = value;
        });
        
        input.addEventListener('keydown', function(e) {
          // Prevent invalid characters from being typed
          const invalidChars = /[^a-zA-Z\s]/;
          if (invalidChars.test(e.key) && !['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) {
            e.preventDefault();
          }
        });
      }
    }
    
    setupNameValidation(firstNameInput);
    setupNameValidation(lastNameInput);
    
    // Email validation - legitimate providers only
    const emailInput = document.getElementById('email');
    const emailRequirement = document.getElementById('email-requirement');
    
    if (emailInput && emailRequirement) {
      // List of allowed legitimate email domains
      const allowedDomains = [
        // Gmail
        'gmail.com',
        // Outlook / Hotmail / Live / MSN
        'outlook.com', 'hotmail.com', 'live.com', 'msn.com',
        // Yahoo Mail
        'yahoo.com', 'ymail.com', 'rocketmail.com',
        // iCloud Mail
        'icloud.com', 'me.com', 'mac.com',
        // AOL Mail
        'aol.com',
        // Zoho Mail
        'zoho.com',
        // Proton Mail
        'protonmail.com', 'proton.me',
        // GMX Mail
        'gmx.com', 'gmx.net',
        // Mail.com
        'mail.com'
      ];
      
      // List of disposable/temporary email domains to block
      const disposableDomains = [
        '10minutemail.com', 'tempmail.org', 'guerrillamail.com', 'mailinator.com',
        'yopmail.com', 'temp-mail.org', 'throwaway.email', 'getnada.com',
        'maildrop.cc', 'sharklasers.com', 'guerrillamailblock.com', 'tempail.com',
        'dispostable.com', 'fakeinbox.com', 'spamgourmet.com', 'trashmail.com',
        'emailondeck.com', 'mohmal.com', 'anonymbox.com', 'deadaddress.com'
      ];
      
      function updateEmailRequirements() {
        const email = emailInput.value.trim();
        
        // Basic email format validation
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        const isValidFormat = emailRegex.test(email);
        
        let isValidProvider = false;
        let isDisposable = false;
        
        if (email.includes('@')) {
          const domain = email.split('@')[1].toLowerCase();
          isValidProvider = allowedDomains.includes(domain);
          isDisposable = disposableDomains.includes(domain);
        }
        
        // Update requirement indicator
        if (email.length === 0) {
          // Empty field - show neutral state
          emailRequirement.classList.remove('valid', 'invalid');
          emailRequirement.classList.add('invalid');
        } else if (isValidFormat && isValidProvider && !isDisposable) {
          // Valid email with legitimate provider
          emailRequirement.classList.remove('invalid');
          emailRequirement.classList.add('valid');
        } else {
          // Invalid email or not allowed provider
          emailRequirement.classList.remove('valid');
          emailRequirement.classList.add('invalid');
        }
      }
      
      emailInput.addEventListener('input', updateEmailRequirements);
      emailInput.addEventListener('blur', updateEmailRequirements);
      
      // Initial check
      updateEmailRequirements();
    }
    
    // Password validation - comprehensive requirements
    const passwordInput = document.getElementById('register-password');
    const lengthRequirement = document.getElementById('length-requirement');
    const lowercaseRequirement = document.getElementById('lowercase-requirement');
    const uppercaseRequirement = document.getElementById('uppercase-requirement');
    const numberRequirement = document.getElementById('number-requirement');
    
    if (passwordInput && lengthRequirement && lowercaseRequirement && uppercaseRequirement && numberRequirement) {
      function updatePasswordRequirements() {
        const password = passwordInput.value;
        
        // Check all requirements
        const isValidLength = password.length >= 8 && password.length <= 64;
        const hasLowercase = /[a-z]/.test(password);
        const hasUppercase = /[A-Z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        
        // Update length requirement
        if (isValidLength) {
          lengthRequirement.classList.remove('invalid');
          lengthRequirement.classList.add('valid');
        } else {
          lengthRequirement.classList.remove('valid');
          lengthRequirement.classList.add('invalid');
        }
        
        // Update lowercase requirement
        if (hasLowercase) {
          lowercaseRequirement.classList.remove('invalid');
          lowercaseRequirement.classList.add('valid');
        } else {
          lowercaseRequirement.classList.remove('valid');
          lowercaseRequirement.classList.add('invalid');
        }
        
        // Update uppercase requirement
        if (hasUppercase) {
          uppercaseRequirement.classList.remove('invalid');
          uppercaseRequirement.classList.add('valid');
        } else {
          uppercaseRequirement.classList.remove('valid');
          uppercaseRequirement.classList.add('invalid');
        }
        
        // Update number requirement
        if (hasNumber) {
          numberRequirement.classList.remove('invalid');
          numberRequirement.classList.add('valid');
        } else {
          numberRequirement.classList.remove('valid');
          numberRequirement.classList.add('invalid');
        }
      }
      
      passwordInput.addEventListener('input', function(e) {
        let value = e.target.value;
        
        // Trim to 64 characters if longer
        if (value.length > 64) {
          e.target.value = value.substring(0, 64);
        }
        
        // Update visual indicators
        updatePasswordRequirements();
      });
      
      // Initial check
      updatePasswordRequirements();
    }
    
    // Add form validation to prevent submission with invalid password
    const registerForm = document.querySelector('#register-form form');
    if (registerForm) {
      registerForm.addEventListener('submit', function(e) {
        const password = passwordInput ? passwordInput.value : '';
        const termsCheckbox = document.getElementById('terms_agreement');
        
        // Check all password requirements
        const isValidLength = password.length >= 8 && password.length <= 64;
        const hasLowercase = /[a-z]/.test(password);
        const hasUppercase = /[A-Z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        
        // Check if terms and conditions are agreed to
        const termsAgreed = termsCheckbox ? termsCheckbox.checked : false;
        
        // If any requirement is not met, prevent submission
        if (!isValidLength || !hasLowercase || !hasUppercase || !hasNumber) {
          e.preventDefault();
          
          // Focus on password field to draw attention to the visual indicators
          if (passwordInput) {
            passwordInput.focus();
          }
          
          return false;
        }
        
        // Check terms and conditions
        if (!termsAgreed) {
          e.preventDefault();
          alert('Please agree to the Terms & Conditions to continue.');
          
          // Focus on terms checkbox
          if (termsCheckbox) {
            termsCheckbox.focus();
          }
          
          return false;
        }
      });
    }

    // Password toggle functionality
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

    // Anonymous Support Form Handler
    const anonymousSupportForm = document.getElementById('anonymousSupportForm');
    if (anonymousSupportForm) {
      anonymousSupportForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('.support-send-btn');
        const messageDiv = document.getElementById('anonymousSupportMessage');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        submitBtn.disabled = true;

        const formData = new FormData(this);
        formData.append('action', 'anonymous_support');

        try {
          const response = await fetch('/Capstone2/api/anonymous_support.php', {
            method: 'POST',
            body: formData
          });

          const data = await response.json();

          if (data.success) {
            messageDiv.style.display = 'block';
            messageDiv.style.background = '#d4edda';
            messageDiv.style.color = '#155724';
            messageDiv.style.border = '1px solid #c3e6cb';
            
            // Create message with ticket lookup link
            const message = document.createElement('div');
            message.innerHTML = `
              <div style="margin-bottom: 10px;">${data.message || 'Message sent successfully!'}</div>
              <div style="margin-top: 10px;">
                <a href="check-ticket.php" target="_blank" style="color: #155724; text-decoration: underline; font-weight: bold;">
                  <i class="fas fa-external-link-alt"></i> Check ticket status
                </a>
              </div>
            `;
            messageDiv.innerHTML = '';
            messageDiv.appendChild(message);
            
            anonymousSupportForm.reset();
            
            // Don't auto-close modal so user can see ticket ID and use "Check Ticket Status" link
            // User can manually close with the back button
          } else {
            messageDiv.style.display = 'block';
            messageDiv.style.background = '#f8d7da';
            messageDiv.style.color = '#721c24';
            messageDiv.style.border = '1px solid #f5c6cb';
            messageDiv.textContent = data.message || 'Failed to send message.';
          }
        } catch (error) {
          console.error('Error:', error);
          messageDiv.style.display = 'block';
          messageDiv.style.background = '#f8d7da';
          messageDiv.style.color = '#721c24';
          messageDiv.style.border = '1px solid #f5c6cb';
          messageDiv.textContent = 'Network error. Please try again.';
        } finally {
          submitBtn.innerHTML = originalText;
          submitBtn.disabled = false;
        }
      });
    }
  });
  </script>
  <script src="js/slideshow.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      console.log('Google Maps embed ready');
      
      // Home button scroll to top functionality
      const homeBtn = document.getElementById('homeBtn');
      if (homeBtn) {
        homeBtn.addEventListener('click', function() {
          window.scrollTo({
            top: 0,
            behavior: 'smooth'
          });
        });
      }

      // Smooth scrolling for navigation links
      document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
          e.preventDefault();
          const targetId = this.getAttribute('href').substring(1);
          const targetElement = document.getElementById(targetId);
          
          if (targetElement) {
            targetElement.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
          }
        });
      });

      // Highlight active navigation link based on scroll position
      function updateActiveNavLink() {
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('.nav-link');
        
        let currentSection = '';
        const scrollPosition = window.scrollY + window.innerHeight / 2; // Use middle of viewport
        const documentHeight = document.documentElement.scrollHeight;
        const windowHeight = window.innerHeight;
        
        // Check if we're at the bottom of the page
        if (window.scrollY + windowHeight >= documentHeight - 50) {
          // If at bottom, highlight the last section
          const lastSection = sections[sections.length - 1];
          if (lastSection) {
            currentSection = lastSection.getAttribute('id');
          }
        } else {
          // Normal section detection
          sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.offsetHeight;
            const sectionCenter = sectionTop + (sectionHeight / 2);
            
            if (scrollPosition >= sectionTop - 200 && scrollPosition <= sectionTop + sectionHeight + 200) {
              currentSection = section.getAttribute('id');
            }
          });
        }
        
        // Remove active class from all nav links
        navLinks.forEach(link => {
          link.classList.remove('active');
        });
        
        // Add active class to current section's nav link
        if (currentSection) {
          const activeLink = document.querySelector(`.nav-link[href="#${currentSection}"]`);
          if (activeLink) {
            activeLink.classList.add('active');
          }
        }
      }
      
      // Update active nav link on scroll
      window.addEventListener('scroll', updateActiveNavLink);
      
      // Update active nav link on page load
      updateActiveNavLink();
    });

    // Terms & Conditions Modal Functions
    let hasScrolledToBottom = false;
    
    function openTermsModal() {
      document.getElementById('termsModal').style.display = 'block';
      document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }

    function closeTermsModal() {
      document.getElementById('termsModal').style.display = 'none';
      document.body.style.overflow = 'auto'; // Restore scrolling
    }

    // Enable checkbox after reading terms
    document.addEventListener('DOMContentLoaded', function() {
      const termsBody = document.querySelector('.terms-modal-body');
      const termsCheckbox = document.getElementById('terms_agreement');
      const termsContainer = document.getElementById('termsCheckboxContainer');
      
      if (termsBody) {
        termsBody.addEventListener('scroll', function() {
          const scrollTop = termsBody.scrollTop;
          const scrollHeight = termsBody.scrollHeight - termsBody.clientHeight;
          
          // Check if scrolled to bottom (with 10px tolerance)
          if (scrollTop + termsBody.clientHeight >= termsBody.scrollHeight - 10) {
            hasScrolledToBottom = true;
            
            // Enable checkbox
            if (termsCheckbox) {
              termsCheckbox.disabled = false;
              if (termsContainer) {
                termsContainer.classList.add('enabled');
              }
            }
          }
        });
      }
    });

    // Close modal when clicking outside of it
    window.onclick = function(event) {
      const modal = document.getElementById('termsModal');
      if (event.target == modal) {
        closeTermsModal();
      }
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        closeTermsModal();
      }
    });
  </script>

  <!-- Terms & Conditions Modal -->
  <div id="termsModal" class="terms-modal">
    <div class="terms-modal-content">
      <div class="terms-modal-header">
        <h2>Terms & Conditions</h2>
        <span class="terms-close" onclick="closeTermsModal()">&times;</span>
      </div>
      <div class="terms-modal-body">
        <div class="terms-text-content">
          <h3>1. Information We Collect</h3>
          <p>We collect personal information from you to provide and improve our services. The types of information we may collect include:</p>
          
          <h4>• Information You Provide Directly:</h4>
          <ul>
            <li><strong>Contact Information:</strong> Your name, email address, phone number, and physical address for order processing, shipping, and communication.</li>
            <li><strong>Account Information:</strong> Your login credentials, user profile information, and order history.</li>
            <li><strong>Custom Design Information:</strong> Any text, images, or data you upload or input into the AI-powered design generator to create your customized clothing designs.</li>
            <li><strong>Payment Information:</strong> Details required to process payments, such as credit card numbers and billing addresses.</li>
          </ul>

          <h4>• Information Collected Automatically:</h4>
          <ul>
            <li><strong>Usage Data:</strong> We collect information about how you interact with the System, including the pages you visit, the features you use, and the time and duration of your use.</li>
            <li><strong>Device Information:</strong> We may collect information about the device you use to access the System, such as your IP address, browser type, operating system, and unique device identifiers.</li>
            <li><strong>Cookies and Tracking Technologies:</strong> We use cookies and similar technologies to enhance your experience, remember your preferences, and track your activity within the System.</li>
          </ul>

          <h3>2. How We Use Your Information</h3>
          <p>We use the information we collect for the following purposes:</p>
          <ul>
            <li><strong>To Provide and Manage Our Services:</strong> To process your orders, manage your account, and provide customer support.</li>
            <li><strong>To Personalize Your Experience:</strong> To enable the AI-powered design generator to create and save your customized designs.</li>
            <li><strong>For Communication:</strong> To send you updates about your order, promotional materials (with your consent), and to respond to your inquiries.</li>
            <li><strong>To Improve the System:</strong> To analyze usage patterns, troubleshoot issues, and enhance the functionality and performance of our services, including the AI design generator.</li>
            <li><strong>For Security:</strong> To protect the integrity and security of the System and to detect and prevent fraud and unauthorized access.</li>
            <li><strong>To Comply with Legal Obligations:</strong> To meet our legal and regulatory requirements.</li>
          </ul>

          <h3>3. Sharing and Disclosure of Information</h3>
          <p>We do not sell or rent your personal information to third parties. We may share your information with trusted third parties in the following circumstances:</p>
          <ul>
            <li><strong>Service Providers:</strong> We may share your information with third-party vendors and service providers who perform functions on our behalf, such as payment processing, shipping, and data analysis. These parties are obligated to handle your information securely and confidentially.</li>
            <li><strong>Legal Requirements:</strong> We may disclose your information if required by law, subpoena, or other legal process or if we have a good faith belief that disclosure is necessary to protect our rights, your safety, or the safety of others.</li>
          </ul>

          <h3>4. Data Security</h3>
          <p>We are committed to protecting your personal information. We implement reasonable and appropriate technical and organizational measures to secure your data against unauthorized access, alteration, disclosure, or destruction. However, no method of transmission over the internet or electronic storage is 100% secure.</p>

          <h3>5. Your Rights and Choices</h3>
          <p>Depending on your location and applicable law, you may have the following rights regarding your personal data:</p>
          <ul>
            <li><strong>Access and Correction:</strong> You have the right to request access to the personal information we hold about you and to request corrections to any inaccuracies.</li>
            <li><strong>Deletion:</strong> You may request the deletion of your personal information, subject to certain legal obligations.</li>
            <li><strong>Opt-out:</strong> You can opt-out of receiving promotional communications from us by following the unsubscribe instructions in the emails we send.</li>
            <li><strong>Consent Withdrawal:</strong> You can withdraw your consent for the processing of your personal data at any time.</li>
          </ul>

          <h3>6. Contact Us</h3>
          <p>If you have any questions or concerns about this Privacy Policy or our data practices, please contact us at:</p>
          <div class="contact-info">
            <p><strong>Email:</strong> 053printsaturservice@gmail.com</p>
            <p><strong>Phone:</strong> +63 966 530 4122</p>
            <p><strong>Address:</strong> 53 San Ignacio St. Poblacion 1, San Jose del Monte, Philippines</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>