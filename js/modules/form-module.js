/**
 * Form Management Module
 * Handles request form functionality, validation, and submission
 */

import { categoryOptions, API_ENDPOINTS, ANIMATION_SETTINGS } from './config-module.js';

export class FormManager {
    constructor() {
        this.categorySelect = null;
        this.sizeSelect = null;
        this.sizeLabel = null;
        this.requestForm = null;
        this.showFormBtn = null;
        this.closeFormBtn = null;
        this.requestFormContainer = null;
        this.requestButtonContainer = null;
        this.heroOverlay = null;
        this.csrfToken = null;
        
        this.init();
    }
    
    init() {
        this.initializeElements();
        this.setupEventListeners();
        this.loadCSRFToken();
    }
    
    initializeElements() {
        this.categorySelect = document.getElementById('category');
        this.sizeSelect = document.getElementById('size');
        this.sizeLabel = document.getElementById('sizeLabel');
        this.requestForm = document.getElementById('requestForm');
        this.showFormBtn = document.getElementById('showRequestFormBtn');
        this.closeFormBtn = document.getElementById('closeRequestFormBtn');
        this.requestFormContainer = document.getElementById('requestFormContainer');
        this.requestButtonContainer = document.querySelector('.request-button-container');
        this.heroOverlay = document.querySelector('.hero-overlay');
        
        // T-shirt specific elements
        this.tshirtFields = document.getElementById('tshirtFields');
        this.regularImageField = document.getElementById('regularImageField');
        this.frontImageInput = document.getElementById('frontImage');
        this.backImageInput = document.getElementById('backImage');
        this.tagImageInput = document.getElementById('tagImage');
        this.tagLocationSelect = document.getElementById('tagLocation');
    }
    
    setupEventListeners() {
        // Category change event
        if (this.categorySelect) {
            this.categorySelect.addEventListener('change', () => this.handleCategoryChange());
        }
        
        // Design option change event (for T-shirt customization choice)
        const designOptionSelect = document.getElementById('designOption');
        if (designOptionSelect) {
            designOptionSelect.addEventListener('change', () => this.handleDesignOptionChange());
        }
        
        
        // Form submission
        if (this.requestForm) {
            this.requestForm.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }
        
        // File upload display for regular image
        const fileInput = document.getElementById('image');
        const fileName = document.querySelector('#regularImageField .file-name');
        if (fileInput && fileName) {
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    fileName.textContent = this.files[0].name;
                } else {
                    fileName.textContent = 'No file chosen';
                }
            });
        }
        
        // File upload display for T-shirt specific fields
        this.setupTshirtFileUploads();
        
        // Form toggle events
        if (this.showFormBtn) {
            this.showFormBtn.addEventListener('click', () => this.showRequestForm());
        }
        
        if (this.closeFormBtn) {
            this.closeFormBtn.addEventListener('click', () => this.hideRequestForm());
        }
    }
    
    handleCategoryChange() {
        const selectedCategory = this.categorySelect.value;
        
        if (selectedCategory && categoryOptions[selectedCategory]) {
            this.populateSizeOptions(selectedCategory);
            this.enableSizeSelect();
            this.toggleTshirtFields(selectedCategory);
        } else {
            this.clearSizeOptions();
            this.disableSizeSelect();
            this.toggleTshirtFields('');
        }
    }
    
    handleDesignOptionChange() {
        const designOption = document.getElementById('designOption').value;
        const selectedCategory = this.categorySelect.value;
        
        if (selectedCategory === 't-shirt-print') {
            this.toggleTshirtFields(selectedCategory, designOption);
        }
    }
    
    populateSizeOptions(category) {
        const options = categoryOptions[category];
        
        // Update label
        this.sizeLabel.textContent = options.label;
        
        this.sizeSelect.innerHTML = '<option value="" disabled selected>Select ' + options.label + '</option>';
        
        // Add new options
        options.options.forEach(option => {
            const optionElement = document.createElement('option');
            optionElement.value = option.value;
            optionElement.textContent = option.text;
            this.sizeSelect.appendChild(optionElement);
        });
    }
    
    clearSizeOptions() {
        this.sizeSelect.innerHTML = '<option value="" disabled selected>Select Size</option>';
        this.sizeLabel.textContent = 'Size';
    }
    
    enableSizeSelect() {
        this.sizeSelect.disabled = false;
        this.sizeSelect.required = true;
    }
    
    disableSizeSelect() {
        this.sizeSelect.disabled = true;
        this.sizeSelect.required = false;
    }
    
    showRequestForm() {
        if (this.requestButtonContainer && this.requestFormContainer) {
            this.requestButtonContainer.style.display = 'none';
            this.requestFormContainer.style.display = 'block';
            
            // Add smooth animation
            this.requestFormContainer.style.opacity = '0';
            this.requestFormContainer.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                this.requestFormContainer.style.transition = 'all 0.3s ease';
                this.requestFormContainer.style.opacity = '1';
                this.requestFormContainer.style.transform = 'translateY(0)';
            }, 10);

            // Mark overlay as form-open to hide logo and adjust stacking
            if (this.heroOverlay) {
                this.heroOverlay.classList.add('form-open');
            }
            
            // Auto-populate full name with user's firstname and lastname
            this.populateUserInfo();
        }
    }
    
    hideRequestForm() {
        if (this.requestButtonContainer && this.requestFormContainer) {
            this.requestFormContainer.style.transition = 'all 0.3s ease';
            this.requestFormContainer.style.opacity = '0';
            this.requestFormContainer.style.transform = 'translateY(-20px)';
            
            setTimeout(() => {
                this.requestFormContainer.style.display = 'none';
                this.requestButtonContainer.style.display = 'flex';
                
                // Reset form when hiding
                this.clearForm();

                // Remove form-open state on overlay
                if (this.heroOverlay) {
                    this.heroOverlay.classList.remove('form-open');
                }
            }, ANIMATION_SETTINGS.FORM_TRANSITION_DURATION);
        }
    }
    
    async loadCSRFToken() {
        try {
            const response = await fetch(API_ENDPOINTS.CSRF_TOKEN, {
                credentials: 'include'
            });
            const data = await response.json();
            if (data.success) {
                this.csrfToken = data.token;
            }
        } catch (error) {
            console.error('Failed to load CSRF token:', error);
        }
    }
    
    async handleFormSubmit(event) {
        event.preventDefault();
        
        // Validate form
        if (!this.validateForm()) {
            return;
        }
        
        // Show loading state
        const submitBtn = this.requestForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        submitBtn.disabled = true;
        
        try {
            // Prepare form data
            const formData = new FormData(this.requestForm);
            
            // Add CSRF token
            if (this.csrfToken) {
                formData.append('csrf_token', this.csrfToken);
            }
            
            // Submit request
            const response = await fetch(API_ENDPOINTS.REQUESTS, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });
            
            const result = await response.json();
            
            if (result.success) {
                window.showModal('success', result.message);
                this.clearForm();
                // Reload CSRF token for next request
                await this.loadCSRFToken();
            } else {
                window.showModal('error', result.message || 'Failed to submit request');
                // If CSRF error, reload token
                if (response.status === 403) {
                    await this.loadCSRFToken();
                }
            }
            
        } catch (error) {
            console.error('Request submission error:', error);
            window.showModal('error', 'Network error. Please check your connection and try again.');
        } finally {
            // Restore button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }
    
    validateForm() {
        const category = this.categorySelect.value;
        const size = this.sizeSelect.value;
        const quantity = document.getElementById('quantity').value;
        const contactNumber = document.getElementById('contact_number').value;
        
        if (!category) {
            window.showModal('error', 'Please select a service category.');
            return false;
        }
        
        if (!size) {
            window.showModal('error', 'Please select a size/type option.');
            return false;
        }
        
        if (!quantity || quantity < 1) {
            window.showModal('error', 'Please enter a valid quantity.');
            return false;
        }
        
        if (!contactNumber) {
            window.showModal('error', 'Please enter your contact number.');
            return false;
        }
        
        return true;
    }
    
    async populateUserInfo() {
        try {
            const response = await fetch(API_ENDPOINTS.USER_ACCOUNT, {
                method: 'GET',
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                const user = data.user;
                // Combine firstname and lastname for full name with proper capitalization
                const capitalizeWords = (str) => {
                    return str.split(' ').map(word => 
                        word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
                    ).join(' ');
                };
                
                const fullName = `${user.firstname || ''} ${user.lastname || ''}`.trim();
                const capitalizedFullName = fullName ? capitalizeWords(fullName) : '';
                
                // Populate the name field if it exists and is empty
                const nameField = document.getElementById('name');
                if (nameField && !nameField.value) {
                    nameField.value = capitalizedFullName;
                }
                
                // Populate the contact number field if it exists and is empty
                const contactField = document.getElementById('contact_number');
                if (contactField && !contactField.value && user.contact_number) {
                    // Remove +63 prefix if present for display in the form
                    let contactNumber = user.contact_number;
                    if (contactNumber.startsWith('+63')) {
                        contactNumber = contactNumber.substring(3);
                    }
                    contactField.value = contactNumber;
                }
            }
        } catch (error) {
            console.error('Error loading user info for form:', error);
            // Don't show error to user, just continue without auto-populating
        }
    }
    
    clearForm() {
        this.requestForm.reset();
        this.clearSizeOptions();
        this.disableSizeSelect();
        this.toggleTshirtFields('');
        
        // Reset all file name displays
        const fileNames = document.querySelectorAll('.file-name');
        fileNames.forEach(fileName => {
            fileName.textContent = 'No file chosen';
        });
    }
    
    setupTshirtFileUploads() {
        // Front image upload
        if (this.frontImageInput) {
            const frontFileName = this.frontImageInput.closest('.file-upload').querySelector('.file-name');
            this.frontImageInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    frontFileName.textContent = this.files[0].name;
                } else {
                    frontFileName.textContent = 'No file chosen';
                }
            });
        }
        
        // Back image upload
        if (this.backImageInput) {
            const backFileName = this.backImageInput.closest('.file-upload').querySelector('.file-name');
            this.backImageInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    backFileName.textContent = this.files[0].name;
                } else {
                    backFileName.textContent = 'No file chosen';
                }
            });
        }
        
        // Tag image upload
        if (this.tagImageInput) {
            const tagFileName = this.tagImageInput.closest('.file-upload').querySelector('.file-name');
            this.tagImageInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    tagFileName.textContent = this.files[0].name;
                } else {
                    tagFileName.textContent = 'No file chosen';
                }
            });
        }
    }
    
    toggleTshirtFields(category, designOption = null) {
        const tshirtFields = document.getElementById('tshirtFields');
        const regularImageGroup = document.querySelector('.form-group:has(#image)');
        const designOptionGroup = document.getElementById('designOptionGroup');
        
        if (category === 't-shirt-print') {
            // Show design option dropdown for T-shirt category
            if (designOptionGroup) {
                designOptionGroup.style.display = 'block';
            }
            
            // Get design option if not provided
            if (!designOption) {
                const designOptionSelect = document.getElementById('designOption');
                designOption = designOptionSelect ? designOptionSelect.value : '';
            }
            
            if (designOption === 'customize') {
                // Show customization fields for customize option
                if (tshirtFields) {
                    tshirtFields.style.display = 'block';
                }
                if (regularImageGroup) {
                    regularImageGroup.style.display = 'none';
                }
            } else if (designOption === 'ready') {
                // Show regular image upload for ready design
                if (tshirtFields) {
                    tshirtFields.style.display = 'none';
                }
                if (regularImageGroup) {
                    regularImageGroup.style.display = 'block';
                }
            } else {
                // No design option selected yet, hide both upload sections
                if (tshirtFields) {
                    tshirtFields.style.display = 'none';
                }
                if (regularImageGroup) {
                    regularImageGroup.style.display = 'none';
                }
            }
        } else {
            // For non-tshirt categories, hide design option and customization fields, show regular image
            if (designOptionGroup) {
                designOptionGroup.style.display = 'none';
            }
            if (tshirtFields) {
                tshirtFields.style.display = 'none';
            }
            if (regularImageGroup) {
                regularImageGroup.style.display = 'block';
            }
        }
    }
}

// Make clearForm available globally for backward compatibility
window.clearForm = function() {
    if (window.formManager) {
        window.formManager.clearForm();
    }
};
