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
                <span>053</span>
            </div>
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
                            </div>

                            <div class="form-row">
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


            <div class="chat-button" onclick="openSupportModal()">
                <span>Support</span>
                <div class="chat-icon">
                    <i class="fas fa-comments"></i>
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
                            <button id="generateNewBtn" class="btn btn-primary">
                                <i class="fas fa-redo"></i>
                                Generate New
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

    <script type="module" src="js/user_page.main.js"></script>
    <script src="js/slideshow.js"></script>
</body>
</html>
