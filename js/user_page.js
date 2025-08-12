// Dynamic category options based on printing shop services
const categoryOptions = {
    't-shirt-print': {
        label: 'Process Type',
        options: [
            { value: 'silkscreen', text: 'Silkscreen Process' },
            { value: 'dtf', text: 'DTF Process' },
            { value: 'vinyl', text: 'Vinyl' }
        ]
    },
    'tag-print': {
        label: 'Tag Type',
        options: [
            { value: 'hangtag', text: 'Hangtag' },
            { value: 'etikta', text: 'Etikta (Sublimate)' }
        ]
    },
    'sticker-print': {
        label: 'Sticker Type',
        options: [
            { value: 'die-cut', text: 'Die Cut' },
            { value: 'kiss-cut', text: 'Kiss Cut' },
            { value: 'decals', text: 'Decals' },
            { value: 'product-label', text: 'Product Label' }
        ]
    },
    'card-print': {
        label: 'Card Type',
        options: [
            { value: 'thank-you', text: 'Thank You Card' },
            { value: 'calling', text: 'Calling Card' },
            { value: 'business', text: 'Business Card' },
            { value: 'invitation', text: 'Invitation Card' }
        ]
    },
    'document-print': {
        label: 'Document Size',
        options: [
            { value: 'short', text: 'Short' },
            { value: 'long', text: 'Long' },
            { value: 'a4', text: 'A4' }
        ]
    },
    'photo-print': {
        label: 'Photo Size',
        options: [
            { value: 'a4', text: 'A4' },
            { value: '8r', text: '8R' },
            { value: '6r', text: '6R' },
            { value: '5r', text: '5R' },
            { value: '3r', text: '3R' },
            { value: 'wallet', text: 'Wallet Size' }
        ]
    },
    'photo-copy': {
        label: 'Paper Size',
        options: [
            { value: 'long', text: 'Long' },
            { value: 'short', text: 'Short' }
        ]
    },
    'lamination': {
        label: 'Lamination Size',
        options: [
            { value: 'a4', text: 'A4' },
            { value: '8r', text: '8R' },
            { value: '6r', text: '6R' },
            { value: '5r', text: '5R' },
            { value: '3r', text: '3R' },
            { value: 'wallet', text: 'Wallet Size' },
            { value: 'id', text: 'ID' }
        ]
    },
    'typing-job': {
        label: 'Document Type',
        options: [
            { value: 'document', text: 'Document' },
            { value: 'resume', text: 'Resume' }
        ]
    }
};

// DOM elements
let categorySelect, sizeSelect, sizeLabel, requestForm, modal, modalMessage, closeBtn;
let showFormBtn, closeFormBtn, requestFormContainer, requestButtonContainer;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeElements();
    setupEventListeners();
    loadCSRFToken();
    initializeUserDropdown();
});

function initializeElements() {
    categorySelect = document.getElementById('category');
    sizeSelect = document.getElementById('size');
    sizeLabel = document.getElementById('sizeLabel');
    requestForm = document.getElementById('requestForm');
    modal = document.getElementById('messageModal');
    modalMessage = document.getElementById('modalMessage');
    closeBtn = document.querySelector('.close');
    
    // Form toggle elements
    showFormBtn = document.getElementById('showRequestFormBtn');
    closeFormBtn = document.getElementById('closeRequestFormBtn');
    requestFormContainer = document.getElementById('requestFormContainer');
    requestButtonContainer = document.querySelector('.request-button-container');
}

function setupEventListeners() {
    // Category change event
    categorySelect.addEventListener('change', handleCategoryChange);
    
    // Form submission
    requestForm.addEventListener('submit', handleFormSubmit);
    
    // File upload display
    const fileInput = document.getElementById('image');
    const fileName = document.querySelector('.file-name');
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            fileName.textContent = this.files[0].name;
        } else {
            fileName.textContent = 'No file chosen';
        }
    });
    
    // Form toggle events
    if (showFormBtn) {
        showFormBtn.addEventListener('click', showRequestForm);
    }
    
    if (closeFormBtn) {
        closeFormBtn.addEventListener('click', hideRequestForm);
    }
    
    // Modal close events
    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });
}

function handleCategoryChange() {
    const selectedCategory = categorySelect.value;
    
    if (selectedCategory && categoryOptions[selectedCategory]) {
        populateSizeOptions(selectedCategory);
        enableSizeSelect();
    } else {
        clearSizeOptions();
        disableSizeSelect();
    }
}

function populateSizeOptions(category) {
    const options = categoryOptions[category];
    
    // Update label
    sizeLabel.textContent = options.label;
    
    sizeSelect.innerHTML = '<option value="" disabled selected>Select ' + options.label + '</option>';
    
    // Add new options
    options.options.forEach(option => {
        const optionElement = document.createElement('option');
        optionElement.value = option.value;
        optionElement.textContent = option.text;
        sizeSelect.appendChild(optionElement);
    });
}

function clearSizeOptions() {
    sizeSelect.innerHTML = '<option value="" disabled selected>Select Size</option>';
    sizeLabel.textContent = 'Size';
}

function enableSizeSelect() {
    sizeSelect.disabled = false;
    sizeSelect.required = true;
}

function disableSizeSelect() {
    sizeSelect.disabled = true;
    sizeSelect.required = false;
}

// Form toggle functions
function showRequestForm() {
    if (requestButtonContainer && requestFormContainer) {
        requestButtonContainer.style.display = 'none';
        requestFormContainer.style.display = 'block';
        
        // Add smooth animation
        requestFormContainer.style.opacity = '0';
        requestFormContainer.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            requestFormContainer.style.transition = 'all 0.3s ease';
            requestFormContainer.style.opacity = '1';
            requestFormContainer.style.transform = 'translateY(0)';
        }, 10);
    }
}

function hideRequestForm() {
    if (requestButtonContainer && requestFormContainer) {
        requestFormContainer.style.transition = 'all 0.3s ease';
        requestFormContainer.style.opacity = '0';
        requestFormContainer.style.transform = 'translateY(-20px)';
        
        setTimeout(() => {
            requestFormContainer.style.display = 'none';
            requestButtonContainer.style.display = 'flex';
            
            // Reset form when hiding
            clearForm();
        }, 300);
    }
}

// CSRF Token management
let csrfToken = null;

async function loadCSRFToken() {
    try {
        const response = await fetch('api/csrf_token.php', {
            credentials: 'include'
        });
        const data = await response.json();
        if (data.success) {
            csrfToken = data.token;
        }
    } catch (error) {
        console.error('Failed to load CSRF token:', error);
    }
}

// Form submission handler
async function handleFormSubmit(event) {
    event.preventDefault();
    
    // Validate form
    if (!validateForm()) {
        return;
    }
    
    // Show loading state
    const submitBtn = requestForm.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    submitBtn.disabled = true;
    
    try {
        // Prepare form data
        const formData = new FormData(requestForm);
        
        // Add CSRF token
        if (csrfToken) {
            formData.append('csrf_token', csrfToken);
        }
        
        // Submit request
        const response = await fetch('api/requests.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showModal('success', result.message);
            clearForm();
            // Reload CSRF token for next request
            await loadCSRFToken();
        } else {
            showModal('error', result.message || 'Failed to submit request');
            // If CSRF error, reload token
            if (response.status === 403) {
                await loadCSRFToken();
            }
        }
        
    } catch (error) {
        console.error('Request submission error:', error);
        showModal('error', 'Network error. Please check your connection and try again.');
    } finally {
        // Restore button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

function validateForm() {
    const category = categorySelect.value;
    const size = sizeSelect.value;
    const quantity = document.getElementById('quantity').value;
    const contactNumber = document.getElementById('contact_number').value;
    
    if (!category) {
        showModal('error', 'Please select a service category.');
        return false;
    }
    
    if (!size) {
        showModal('error', 'Please select a size/type option.');
        return false;
    }
    
    if (!quantity || quantity < 1) {
        showModal('error', 'Please enter a valid quantity.');
        return false;
    }
    
    if (!contactNumber) {
        showModal('error', 'Please enter your contact number.');
        return false;
    }
    
    return true;
}

function showModal(type, message) {
    modalMessage.innerHTML = `
        <div class="alert alert-${type}">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
            <span>${message}</span>
        </div>
    `;
    modal.style.display = 'block';
    
    // Auto-close success messages after 3 seconds
    if (type === 'success') {
        setTimeout(closeModal, 3000);
    }
}

function closeModal() {
    modal.style.display = 'none';
}

function clearForm() {
    requestForm.reset();
    clearSizeOptions();
    disableSizeSelect();
    document.querySelector('.file-name').textContent = 'No file chosen';
}

// Global function for clear button
window.clearForm = clearForm;

// Microsoft-Style User Dropdown Functionality
function initializeUserDropdown() {
    const userDropdownBtn = document.getElementById('userDropdownBtn');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    const myAccountBtn = document.getElementById('myAccountBtn');
    const myProfileBtn = document.getElementById('myProfileBtn');

    if (!userDropdownBtn || !userDropdownMenu) {
        return;
    }

    // Toggle dropdown on button click
    userDropdownBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleDropdown();
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!userDropdownBtn.contains(e.target) && !userDropdownMenu.contains(e.target)) {
            closeDropdown();
        }
    });

    // Handle my account click
    if (myAccountBtn) {
        myAccountBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showAccountCard();
            closeDropdown();
        });
    }

    // Handle my profile click
    if (myProfileBtn) {
        myProfileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showModal('info', 'My profile functionality will be implemented in a future update.');
            closeDropdown();
        });
    }

    // Close dropdown on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDropdown();
        }
    });

    // Initialize account card functionality
    initializeAccountCard();
}

function toggleDropdown() {
    const userDropdownBtn = document.getElementById('userDropdownBtn');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    
    if (userDropdownMenu.classList.contains('show')) {
        closeDropdown();
    } else {
        openDropdown();
    }
}

function openDropdown() {
    const userDropdownBtn = document.getElementById('userDropdownBtn');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    
    userDropdownBtn.classList.add('active');
    userDropdownMenu.classList.add('show');
}

function closeDropdown() {
    const userDropdownBtn = document.getElementById('userDropdownBtn');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    
    if (userDropdownBtn) userDropdownBtn.classList.remove('active');
    if (userDropdownMenu) userDropdownMenu.classList.remove('show');
}

// My Account Card Functionality
function initializeAccountCard() {
    const accountCard = document.getElementById('myAccountCard');
    const closeAccountCard = document.getElementById('closeAccountCard');
    const tabBtns = document.querySelectorAll('.tab-btn');
    const changePasswordForm = document.getElementById('changePasswordForm');
    const passwordToggles = document.querySelectorAll('.password-toggle');

    // Close account card
    if (closeAccountCard) {
        closeAccountCard.addEventListener('click', hideAccountCard);
    }

    // Removed outside click to close functionality
    // Account card will only close via the close button

    // Tab switching
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabName = this.dataset.tab;
            switchTab(tabName);
        });
    });

    // Password form submission
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', handlePasswordChange);
    }

    // Password visibility toggles
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const targetInput = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (targetInput.type === 'password') {
                targetInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                targetInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && accountCard && accountCard.classList.contains('show')) {
            hideAccountCard();
        }
    });
}

function showAccountCard() {
    const accountCard = document.getElementById('myAccountCard');
    if (accountCard) {
        accountCard.style.display = 'flex';
        setTimeout(() => {
            accountCard.classList.add('show');
        }, 10);
        
        // Load user info when showing the card
        loadUserInfo();
    }
}

function hideAccountCard() {
    const accountCard = document.getElementById('myAccountCard');
    if (accountCard) {
        accountCard.classList.remove('show');
        setTimeout(() => {
            accountCard.style.display = 'none';
        }, 300);
    }
}

function switchTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

    // Update tab panels
    document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.classList.remove('active');
    });
    document.getElementById(`${tabName}Tab`).classList.add('active');
}

async function loadUserInfo() {
    try {
        const response = await fetch('api/user_account.php', {
            method: 'GET',
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            const user = data.user;
            document.getElementById('accountName').textContent = user.name || 'N/A';
            document.getElementById('accountEmail').textContent = user.email || 'N/A';
            document.getElementById('accountRole').textContent = user.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : 'N/A';
            document.getElementById('accountCreated').textContent = user.created_at || 'N/A';
        } else {
            showModal('error', data.error || 'Failed to load user information');
        }
    } catch (error) {
        console.error('Error loading user info:', error);
        showModal('error', 'Failed to load user information');
    }
}

async function handlePasswordChange(event) {
    event.preventDefault();

    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    submitBtn.disabled = true;

    try {
        const formData = new FormData(event.target);
        const passwordData = {
            current_password: formData.get('current_password'),
            new_password: formData.get('new_password'),
            confirm_password: formData.get('confirm_password'),
            csrf_token: csrfToken
        };

        const response = await fetch('api/user_account.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'include',
            body: JSON.stringify(passwordData)
        });

        const data = await response.json();

        if (data.success) {
            showModal('success', data.message || 'Password updated successfully');
            resetPasswordForm();
        } else {
            showModal('error', data.error || 'Failed to update password');
        }
    } catch (error) {
        console.error('Error updating password:', error);
        showModal('error', 'Failed to update password');
    } finally {
        // Reset button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

function resetPasswordForm() {
    const form = document.getElementById('changePasswordForm');
    if (form) {
        form.reset();
        
        // Reset password visibility
        const passwordInputs = form.querySelectorAll('input[type="text"]');
        const toggles = form.querySelectorAll('.password-toggle i');
        
        passwordInputs.forEach(input => {
            input.type = 'password';
        });
        
        toggles.forEach(icon => {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        });
    }
}

// Global function for reset button
window.resetPasswordForm = resetPasswordForm;