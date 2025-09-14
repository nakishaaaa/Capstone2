<?php
session_start();

// Require only user-specific session variables (no legacy fallback)
$isUserLoggedIn = false;
$userName = '';
$userEmail = '';

if (isset($_SESSION['user_name']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'user') {
    $isUserLoggedIn = true;
    $userName = $_SESSION['user_name'];
    $userEmail = $_SESSION['user_email'] ?? '';
}

if (!$isUserLoggedIn) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=UnifrakturMaguntia&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=UnifrakturCook:wght@700&display=swap" rel="stylesheet">
    <title>053 PRINTS - User Dashboard</title>
    <link rel="stylesheet" href="css/user_page.css">
    <link rel="stylesheet" href="css/user-support-tickets.css">
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
                    <div class="ai-dropdown-menu" id="aiDropdownMenu">
                        <div class="ai-option" id="aiGeneratorOption">
                            <i class="fas fa-magic"></i>
                            <span>Generate Image</span>
                        </div>
                        <div class="ai-option" id="aiEditorOption">
                            <i class="fas fa-wand-magic-sparkles"></i>
                            <span>Enhance Image</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="brand">
                <span></span>
            </div>
            <nav class="navbar-nav">
                <a href="#contact" class="nav-link">Contact</a>
                <a href="#services" class="nav-link">Services</a>
                <a href="#gallery" class="nav-link">Gallery</a>
                <a href="#how" class="nav-link">How It Works</a>
                <a href="#testimonials" class="nav-link">Testimonials</a>
            </nav>
            <div class="user-info">
                <div class="user-dropdown">
                    <button class="user-dropdown-btn" id="userDropdownBtn">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </button>
                    <div class="user-dropdown-menu" id="userDropdownMenu">
                        <div class="dropdown-header">
                            <div class="user-profile">
                                <div class="profile-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="profile-info">
                                    <div class="profile-name"><?php echo htmlspecialchars($userName); ?></div>
                                    <div class="profile-email"><?php echo htmlspecialchars($userEmail); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-links">
                            <a href="#" class="dropdown-link" id="myAccountBtn">
                                <i class="fas fa-user-cog"></i>
                                My Account
                            </a>
                        </div>
                        <div class="dropdown-links">
                            <a href="my_orders.php" class="dropdown-link" id="myOrdersBtn">
                                <i class="fas fa-shopping-cart"></i>
                                My Orders
                            </a>
                        </div>
                        <div class="dropdown-links">
                            <a href="#" class="dropdown-link" onclick="openSupportTicketsModal()">
                                <i class="fas fa-ticket-alt"></i>
                                My Tickets
                            </a>
                        </div>
                        <div class="dropdown-divider"></div>
                        <div class="dropdown-actions">
                            <a href="#" class="dropdown-action logout-action" onclick="handleLogout('user')">
                                <i class="fas fa-right-from-bracket" style="color: #ff4757;"></i>
                                Sign out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>


        <!-- Main Content -->
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
                    <!-- Centered logo over slideshow -->
                    <div class="hero-logo-container">
                        <img src="images/053logo.png" alt="053 Prints Logo" class="hero-logo" />
                    </div>
                    <!-- Request Order Button -->
                    <div class="request-button-container">
                        <button id="showRequestFormBtn" class="btn-show-form">
                            <span class="button_top">Request an Order</span>
                        </button>
                    </div>
                    
                    <!-- Request Form Container -->
                    <div class="request-form-container" id="requestFormContainer" style="display: none;">
                        <div class="form-header">
                            <h1 class="form-title">Requesting an Order</h1>
                            <button type="button" class="btn-close-form" id="closeRequestFormBtn">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <form id="requestForm" class="request-form" enctype="multipart/form-data">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="category">Service Category</label>
                                    <select id="category" name="category" required>
                                        <option value="" disabled selected> Select Service </option>
                                        <option value="t-shirt-print">T-Shirt Print</option>
                                        <option value="tag-print">Tag Print</option>
                                        <option value="sticker-print">Sticker Print</option>
                                        <option value="card-print">Card Print</option>
                                        <option value="document-print">Document Print</option>
                                        <option value="photo-print">Photo Print</option>
                                        <option value="photo-copy">Photo Copy</option>
                                        <option value="lamination">Lamination</option>
                                        <option value="typing-job">Typing Job</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="size" id="sizeLabel">Size</label>
                                    <select id="size" name="size" required>
                                        <option value="" disabled selected>Select Size</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="quantity">Quantity</label>
                                    <input type="number" id="quantity" name="quantity" 
                                           placeholder="Enter quantity" min="1" required>
                                </div>
                                
                                <!-- Design Option Field for T-shirt Print -->
                                <div class="form-group" id="designOptionGroup" style="display: none;">
                                    <label for="designOption">Design Option</label>
                                    <select id="designOption" name="design_option">
                                        <option value="" disabled selected>Choose Design Option</option>
                                        <option value="customize">I want to customize (separate front/back designs)</option>
                                        <option value="ready">I have a ready design</option>
                                    </select>
                                </div>
                            </div>

                            <!-- T-shirt Print Specific Fields -->
                            <div id="tshirtFields" class="tshirt-customization-fields" style="display: none;">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="frontImage">Front Design</label>
                                        <div class="file-upload">
                                            <input type="file" id="frontImage" name="front_image" accept="image/*,.pdf">
                                            <label for="frontImage" class="file-upload-label">
                                                <i class="fas fa-download"></i>
                                                <span>Choose Front Design</span>
                                            </label>
                                            <span class="file-name">No file chosen</span>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="backImage">Back Design</label>
                                        <div class="file-upload">
                                            <input type="file" id="backImage" name="back_image" accept="image/*,.pdf">
                                            <label for="backImage" class="file-upload-label">
                                                <i class="fas fa-download"></i>
                                                <span>Choose Back Design</span>
                                            </label>
                                            <span class="file-name">No file chosen</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="tagImage">Tag (if applicable)</label>
                                        <div class="file-upload">
                                            <input type="file" id="tagImage" name="tag_image" accept="image/*,.pdf">
                                            <label for="tagImage" class="file-upload-label">
                                                <i class="fas fa-download"></i>
                                                <span>Choose Tag Design</span>
                                            </label>
                                            <span class="file-name">No file chosen</span>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="tagLocation">Tag Location</label>
                                        <select id="tagLocation" name="tag_location">
                                            <option value="" disabled selected>Select Tag Location</option>
                                            <option value="full-front">Full Front</option>
                                            <option value="medium-front">Medium Front</option>
                                            <option value="center-chest">Center Chest</option>
                                            <option value="across-chest">Across Chest</option>
                                            <option value="right-chest">Right Chest</option>
                                            <option value="left-chest">Left Chest</option>
                                            <option value="right-sleeve">Right Sleeve</option>
                                            <option value="left-sleeve">Left Sleeve</option>
                                            <option value="right-vertical">Right Vertical</option>
                                            <option value="left-vertical">Left Vertical</option>
                                            <option value="front-bottom-right">Front Bottom Right</option>
                                            <option value="front-bottom-left">Front Bottom Left</option>
                                            <option value="full-back">Full Back</option>
                                            <option value="medium-back">Medium Back</option>
                                            <option value="locker-patch-area">Locker Patch Area</option>
                                            <option value="across-shoulders">Across Shoulders</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Regular Image Upload (for non-tshirt categories) -->
                            <div id="regularImageField" class="form-row">
                                <div class="form-group">
                                    <label for="image">Image</label>
                                    <div class="file-upload">
                                        <input type="file" id="image" name="image" accept="image/*,.pdf">
                                        <label for="image" class="file-upload-label">
                                            <i class="fas fa-download"></i>
                                            <span>Choose File</span>
                                        </label>
                                        <span class="file-name">No file chosen</span>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="name">Full Name</label>
                                    <input type="text" id="name" name="name" 
                                           placeholder="Enter Full Name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="contact_number">Contact Number</label>
                                    <div class="contact-input-group">
                                        <span class="country-prefix">+63</span>
                                        <input type="tel"
                                               id="contact_number"
                                               name="contact_number"
                                               placeholder="9XXXXXXXXX"
                                               required
                                               pattern="^\d{10}$"
                                               maxlength="10"
                                               title="Please enter exactly 10 digits"
                                               oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group full-width">
                                <label for="notes">Notes (more details about the order)</label>
                                <textarea id="notes" name="notes" rows="4" 
                                          placeholder="Please provide any additional details about your order..."></textarea>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-request">
                                    Request
                                </button>
                                <button type="button" class="btn btn-clear" onclick="clearForm()">
                                    Clear
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Social Links over the slideshow -->
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
                                <p><a href="tel:+639388177779">+63 938 817 7779</a></p>
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
                    <p> </p>
                </div>
                <div class="testimonials-grid">
                    <div class="testimonial-card">
                        <p class="quote" style="color: #ffffff;">"Top-notch quality and very fast turnaround!"</p>
                        <div class="author" style="color: #ffffff;">— Jamie R.</div>
                    </div>
                    <div class="testimonial-card">
                        <p class="quote" style="color: #ffffff;">"They handled my custom shirt order perfectly."</p>
                        <div class="author" style="color: #ffffff;">— Marco D.</div>
                    </div>
                    <div class="testimonial-card">
                        <p class="quote" style="color: #ffffff;">"Great customer service. Highly recommended."</p>
                        <div class="author" style="color: #ffffff;">— Aira P.</div>
                    </div>
                </div>
            </section>

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

            <div class="chat-button" onclick="openSupportModal()">
                <span>Support</span>
                <div class="chat-icon">
                    <i class="fas fa-comments"></i>
                </div>
            </div>
            
            <!-- Dev Team Ticket Button -->
            <div class="dev-ticket-button" onclick="openDevTicketModal()">
                <span>Report to Dev Team</span>
                <div class="ticket-icon">
                    <i class="fas fa-bug"></i>
                </div>
            </div>
        </main>
    </div>

    <!-- My Account Card -->
    <div id="myAccountCard" class="account-card" style="display: none;">
        <div class="account-card-content">
            <div class="account-card-header">
                <h2>My Account</h2>
                <button class="account-card-close" id="closeAccountCard">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="account-tabs">
                <button class="tab-btn active" data-tab="info">
                    <i class="fas fa-info-circle"></i>
                    Account Info
                </button>
                <button class="tab-btn" data-tab="security">
                    <i class="fas fa-shield-alt"></i>
                    Security
                </button>
            </div>
            
            <div class="account-tab-content">
                <!-- Info Tab -->
                <div id="infoTab" class="tab-panel active">
                    <div class="info-section">
                        <h3>Account Information</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Username</label>
                                <span id="accountUsername">Loading...</span>
                            </div>
                            <div class="info-item">
                                <label>Full Name</label>
                                <span id="accountName">Loading...</span>
                            </div>
                            <div class="info-item">
                                <label>Email Address</label>
                                <span id="accountEmail">Loading...</span>
                            </div>
                            <div class="info-item">
                                <label>Contact Number</label>
                                <span id="accountContact">Loading...</span>
                            </div>
                            <div class="info-item">
                                <label>Member Since</label>
                                <span id="accountCreated">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Security Tab -->
                <div id="securityTab" class="tab-panel">
                    <div class="security-section">
                        <h3>Change Password</h3>
                        <form id="changePasswordForm" class="password-form">
                            <div class="form-group">
                                <label for="currentPassword">Current Password</label>
                                <div class="password-input-group">
                                    <input type="password" id="currentPassword" name="current_password" required>
                                    <button type="button" class="password-toggle" data-target="currentPassword">
                                        <img src="images/svg/eye-slash-black.svg" alt="Show password" width="20" height="20">
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="newPassword">New Password</label>
                                <div class="password-input-group">
                                    <input type="password" id="newPassword" name="new_password" required minlength="6">
                                    <button type="button" class="password-toggle" data-target="newPassword">
                                        <img src="images/svg/eye-slash-black.svg" alt="Show password" width="20" height="20">
                                    </button>
                                </div>
                                <small class="form-help">Password must be at least 6 characters long</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirmPassword">Confirm New Password</label>
                                <div class="password-input-group">
                                    <input type="password" id="confirmPassword" name="confirm_password" required minlength="6">
                                    <button type="button" class="password-toggle" data-target="confirmPassword">
                                        <img src="images/svg/eye-slash-black.svg" alt="Show password" width="20" height="20">
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Update Password
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="resetPasswordForm()">
                                    <i class="fas fa-undo"></i>
                                    Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Support Modal -->
    <div id="supportModal" class="support-modal" style="display: none;">
        <div class="support-modal-content">
            <div class="support-modal-header">
                <button class="support-back-btn" onclick="closeSupportModal()">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <h2>Send a message</h2>
            </div>
            
            <div class="support-modal-body">
                <div class="support-header-info">
                    <h3>How can we help?</h3>
                    <p>We usually respond in a few hours</p>
                </div>
                
                <form id="supportForm" class="support-form">
                    <div class="support-form-group">
                        <label for="supportSubject">Subject</label>
                        <input type="text" id="supportSubject" name="subject" required>
                    </div>
                    
                    <div class="support-form-group">
                        <label for="supportMessage">How can we help?</label>
                        <textarea id="supportMessage" name="message" rows="6" required placeholder="Describe your issue or question..."></textarea>
                        <div class="support-message-actions">
                            <button type="button" class="support-attachment-btn">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <button type="button" class="support-emoji-btn">
                                <i class="fas fa-smile"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="support-send-btn">
                        Send a message
                    </button>
                    <button type="button" class="support-prev-btn" id="openPreviousConversationsBtn" aria-label="View previous conversations">
                        <i class="fas fa-clock-rotate-left"></i>
                        Previous Conversations
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Dev Team Ticket Modal -->
    <div id="devTicketModal" class="support-modal" style="display: none;">
        <div class="support-modal-content">
            <div class="support-modal-header">
                <button class="support-back-btn" onclick="closeDevTicketModal()">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <h2>Report to Dev Team</h2>
            </div>
            
            <div class="support-modal-body">
                <div class="support-header-info">
                    <h3>Submit a Bug Report or Feature Request</h3>
                    <p>Connect directly with our development team - we usually respond within a few hours</p>
                </div>
                
                <form id="devTicketForm" class="support-form">
                    <div class="support-form-group">
                        <label for="devTicketPriority">Priority Level</label>
                        <select id="devTicketPriority" name="priority" required>
                            <option value="low">Low - Feature request or minor issue</option>
                            <option value="medium" selected>Medium - Bug or improvement</option>
                            <option value="high">High - Critical bug or urgent issue</option>
                        </select>
                    </div>
                    
                    <div class="support-form-group">
                        <label for="devTicketSubject">Subject</label>
                        <input type="text" id="devTicketSubject" name="subject" required placeholder="Brief description of the issue or request">
                    </div>
                    
                    <div class="support-form-group">
                        <label for="devTicketMessage">Detailed Description</label>
                        <textarea id="devTicketMessage" name="message" rows="6" required placeholder="Please provide detailed information about the bug, feature request, or technical issue..."></textarea>
                        <div class="support-message-actions">
                            <button type="button" class="dev-attachment-btn" title="Attach screenshot or file">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <input type="file" id="devTicketAttachment" name="attachment" style="display: none;" accept="image/*,.pdf,.doc,.docx,.txt">
                            <span class="attachment-name" id="devAttachmentName"></span>
                        </div>
                    </div>
                    
                    <button type="submit" class="support-send-btn">
                        <i class="fas fa-bug"></i>
                        Submit to Dev Team
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- AI Image Generator Modal -->
    <div id="aiImageModal" class="modal ai-modal">
        <div class="modal-content ai-modal-content">
            <div class="ai-modal-header">
                <h2><i class="fas fa-magic"></i> AI Image Generator</h2>
                <span class="close ai-modal-close" id="aiModalClose">&times;</span>
            </div>
            
            <div class="ai-modal-body">
                <div class="ai-prompt-section">
                    <label for="aiPrompt">Describe the image you want to generate:</label>
                    <textarea id="aiPrompt" placeholder="e.g., A beautiful sunset over mountains with vibrant colors..." rows="4"></textarea>
                    <div class="ai-prompt-actions">
                        <button id="generateImageBtn" class="btn btn-primary ai-generate-btn">
                            <i class="fas fa-magic"></i>
                            Generate Image
                        </button>
                        <button id="clearPromptBtn" class="btn btn-secondary">
                            <i class="fas fa-eraser"></i>
                            Clear
                        </button>
                    </div>
                </div>
                
                <div class="ai-result-section" id="aiResultSection" style="display: none;">
                    <div class="ai-loading" id="aiLoading" style="display: none;">
                        <div class="loading-spinner"></div>
                        <p>Generating your image... This may take a few moments.</p>
                    </div>
                    
                    <div class="ai-image-result" id="aiImageResult" style="display: none;">
                        <div class="generated-image-container">
                            <img id="generatedImage" src="" alt="Generated Image" />
                        </div>
                        <div class="ai-image-actions">
                            <button id="downloadImageBtn" class="btn btn-success">
                                <i class="fas fa-download"></i>
                                Download Image
                            </button>
                        </div>
                        <div class="ai-image-info">
                            <p><strong>Prompt:</strong> <span id="usedPrompt"></span></p>
                        </div>
                    </div>
                    
                    <div class="ai-error" id="aiError" style="display: none;">
                        <div class="error-message">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span id="errorText"></span>
                        </div>
                        <button id="retryBtn" class="btn btn-primary">
                            <i class="fas fa-retry"></i>
                            Try Again
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- AI Photo Editor Modal -->
    <div id="aiPhotoEditorModal" class="modal ai-modal">
        <div class="modal-content ai-modal-content">
            <div class="ai-modal-header">
                <h2><i class="fas fa-wand-magic-sparkles"></i> AI Image Editor</h2>
                <span class="close ai-modal-close" id="aiEditorModalClose">&times;</span>
            </div>
            
            <div class="ai-modal-body">
                <div class="ai-edit-section">
                    <div class="upload-section">
                        <label for="photoUpload">Upload your photo to edit:</label>
                        <div class="upload-area" id="uploadArea">
                            <input type="file" id="photoUpload" accept="image/*" style="display: none;">
                            <div class="upload-placeholder">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click to upload or drag & drop your image</p>
                                <small>Supports JPG, PNG, GIF (Max 10MB)</small>
                            </div>
                            <div class="uploaded-image" id="uploadedImagePreview" style="display: none;">
                                <img id="previewImage" src="" alt="Uploaded Image">
                                <button class="remove-image" id="removeImageBtn">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="edit-prompt-section">
                        <label for="editPrompt">Describe how you want to edit the image:</label>
                        <textarea id="editPrompt" placeholder="e.g., Remove the background, change the sky to sunset, add flowers..." rows="3"></textarea>
                        <div class="edit-actions">
                            <button id="editPhotoBtn" class="btn btn-primary ai-edit-btn" disabled>
                                <i class="fas fa-edit"></i>
                                Edit Photo
                            </button>
                            <button id="clearEditBtn" class="btn btn-secondary">
                                <i class="fas fa-eraser"></i>
                                Clear All
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="ai-result-section" id="aiEditorResultSection" style="display: none;">
                    <div class="ai-loading" id="aiEditorLoading" style="display: none;">
                        <div class="loading-spinner"></div>
                        <p>Editing your photo... This may take a few moments.</p>
                    </div>
                    
                    <div class="ai-image-result" id="aiEditorImageResult" style="display: none;">
                        <div class="generated-image-container">
                            <img id="editedImage" src="" alt="Edited Image" />
                        </div>
                        <div class="ai-image-actions">
                            <button id="downloadEditedBtn" class="btn btn-success">
                                <i class="fas fa-download"></i>
                                Download Image
                            </button>
                            <button id="editNewBtn" class="btn btn-primary">
                                <i class="fas fa-redo"></i>
                                Edit New Photo
                            </button>
                        </div>
                        <div class="ai-image-info">
                            <p><strong>Edit Instructions:</strong> <span id="usedEditPrompt"></span></p>
                        </div>
                    </div>
                    
                    <div class="ai-error" id="aiEditorError" style="display: none;">
                        <div class="error-message">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span id="editorErrorText"></span>
                        </div>
                        <button id="retryEditBtn" class="btn btn-primary">
                            <i class="fas fa-retry"></i>
                            Try Again
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="messageModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div id="modalMessage"></div>
        </div>
    </div>

    <!-- Previous Conversations Modal -->
    <div id="previousConversationsModal" class="support-modal" style="display: none;">
        <div class="support-modal-content">
            <div class="support-modal-header">
                <button class="support-back-btn" id="closePreviousConversations">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <h2>Previous Conversations</h2>
            </div>

            <div class="support-modal-body previous-conversations-body">
                <div id="conversationsList" class="conversations-list" aria-live="polite"></div>
                <div id="conversationDetail" class="conversation-detail" style="display:none;"></div>
            </div>
        </div>
    </div>

    <script src="js/slideshow.js"></script>
    <script type="module" src="js/user_page.main.js"></script>
    <script>
    // Gallery Lightbox functionality
    document.addEventListener('DOMContentLoaded', function() {
        const images = Array.from(document.querySelectorAll('.gallery-grid img'));
        if (!images.length) return;

        // Add cursor hint
        images.forEach(img => img.style.cursor = 'zoom-in');

        const overlay = document.getElementById('lightbox');
        const lbImg = document.getElementById('lightboxImage');
        const lbCaption = document.getElementById('lightboxCaption');
        const btnClose = overlay ? overlay.querySelector('.lightbox-close') : null;
        const btnPrev = overlay ? overlay.querySelector('.lightbox-nav.prev') : null;
        const btnNext = overlay ? overlay.querySelector('.lightbox-nav.next') : null;
        let current = 0;

        function openAt(index) {
            current = index;
            const src = images[current].getAttribute('src');
            const alt = images[current].getAttribute('alt') || '';
            if (lbImg) {
                lbImg.src = src;
                lbImg.alt = alt;
            }
            if (lbCaption) lbCaption.textContent = alt;
            if (overlay) {
                overlay.classList.add('open');
                overlay.setAttribute('aria-hidden', 'false');
            }
            document.body.style.overflow = 'hidden';
        }

        function close() {
            if (overlay) {
                overlay.classList.remove('open');
                overlay.setAttribute('aria-hidden', 'true');
            }
            document.body.style.overflow = '';
        }

        function show(delta) {
            const len = images.length;
            const next = (current + delta + len) % len;
            openAt(next);
        }

        images.forEach((img, idx) => {
            img.addEventListener('click', () => openAt(idx));
        });

        if (btnClose) btnClose.addEventListener('click', close);
        if (btnPrev) btnPrev.addEventListener('click', () => show(-1));
        if (btnNext) btnNext.addEventListener('click', () => show(1));

        if (overlay) {
            overlay.addEventListener('click', function(e) {
                // Close only when clicking outside the content
                const content = overlay.querySelector('.lightbox-content');
                if (e.target === overlay || (content && !content.contains(e.target) && !e.target.classList.contains('lightbox-nav'))) {
                    close();
                }
            });
        }

        document.addEventListener('keydown', function(e) {
            if (!overlay || !overlay.classList.contains('open')) return;
            if (e.key === 'Escape') close();
            else if (e.key === 'ArrowRight') show(1);
            else if (e.key === 'ArrowLeft') show(-1);
        });
        
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

        // Prevent page scrolling when modal is open and cursor is over modal
        const requestFormContainer = document.getElementById('requestFormContainer');
        if (requestFormContainer) {
            requestFormContainer.addEventListener('wheel', function(e) {
                const container = this;
                const scrollTop = container.scrollTop;
                const scrollHeight = container.scrollHeight;
                const height = container.clientHeight;
                const delta = e.deltaY;
                
                // If scrolling up and already at top, prevent page scroll
                if (delta < 0 && scrollTop === 0) {
                    e.preventDefault();
                }
                // If scrolling down and already at bottom, prevent page scroll
                else if (delta > 0 && scrollTop + height >= scrollHeight) {
                    e.preventDefault();
                }
            });
        }
    });
    </script>
</body>
</html>
