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
                <a href="index.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
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
