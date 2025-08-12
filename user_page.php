<?php
session_start();

if (!isset($_SESSION['name'])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>053 PRINTS - User Dashboard</title>
    <link rel="stylesheet" href="css/user_page.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="logo">
                <i class="fas fa-atom"></i>
                <span>AI</span>
            </div>
            <div class="brand">
                <i class="fas fa-store"></i>
                <span>053 PRINTS</span>
            </div>
            <div class="services">
                <span>SERVICES</span>
            </div>
            <div class="user-info">
                <div class="user-dropdown">
                    <button class="user-dropdown-btn" id="userDropdownBtn">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </button>
                    <div class="user-dropdown-menu" id="userDropdownMenu">
                        <div class="dropdown-header">
                            <div class="user-profile">
                                <div class="profile-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="profile-info">
                                    <div class="profile-name"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
                                    <div class="profile-email"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-links">
                            <a href="#" class="dropdown-link" id="myAccountBtn">
                                <i class="fas fa-user-cog"></i>
                                My Account
                            </a>

                        </div>
                        <div class="dropdown-divider"></div>
                        <div class="dropdown-actions">
                            <a href="index.php" class="dropdown-action logout-action">
                                <i class="fas fa-sign-out-alt" style="color: #ff4757;"></i>
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
                                    <input type="tel"
                                           id="contact_number"
                                           name="contact_number"
                                           placeholder="Contact Number"
                                           required
                                           pattern="^\d{11}$"
                                           maxlength="11"
                                           title="Please enter exactly 11 digits"
                                           oninput="this.value = this.value.replace(/[^0-9]/g, '')">
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
            </div> 

            <div class="chat-button">
                <span>Chat with the Seller?</span>
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
                                <label>Full Name</label>
                                <span id="accountName">Loading...</span>
                            </div>
                            <div class="info-item">
                                <label>Email Address</label>
                                <span id="accountEmail">Loading...</span>
                            </div>
                            <div class="info-item">
                                <label>Account Type</label>
                                <span id="accountRole">Loading...</span>
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
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="newPassword">New Password</label>
                                <div class="password-input-group">
                                    <input type="password" id="newPassword" name="new_password" required minlength="6">
                                    <button type="button" class="password-toggle" data-target="newPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="form-help">Password must be at least 6 characters long</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirmPassword">Confirm New Password</label>
                                <div class="password-input-group">
                                    <input type="password" id="confirmPassword" name="confirm_password" required minlength="6">
                                    <button type="button" class="password-toggle" data-target="confirmPassword">
                                        <i class="fas fa-eye"></i>
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

    <div id="messageModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div id="modalMessage"></div>
        </div>
    </div>

    <script src="js/user_page.js"></script>
    <script src="js/slideshow.js"></script>
</body>
</html>
