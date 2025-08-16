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
    }
    
    setupEventListeners() {
        // Category change event
        if (this.categorySelect) {
            this.categorySelect.addEventListener('change', () => this.handleCategoryChange());
        }
        
        // Form submission
        if (this.requestForm) {
            this.requestForm.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }
        
        // File upload display
        const fileInput = document.getElementById('image');
        const fileName = document.querySelector('.file-name');
        if (fileInput && fileName) {
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    fileName.textContent = this.files[0].name;
                } else {
                    fileName.textContent = 'No file chosen';
                }
            });
        }
        
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
        } else {
            this.clearSizeOptions();
            this.disableSizeSelect();
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
    
    clearForm() {
        this.requestForm.reset();
        this.clearSizeOptions();
        this.disableSizeSelect();
        const fileName = document.querySelector('.file-name');
        if (fileName) {
            fileName.textContent = 'No file chosen';
        }
    }
}

// Make clearForm available globally for backward compatibility
window.clearForm = function() {
    if (window.formManager) {
        window.formManager.clearForm();
    }
};
