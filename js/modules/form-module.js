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
        
        // Size change event (for custom size handling)
        if (this.sizeSelect) {
            this.sizeSelect.addEventListener('change', () => this.handleSizeChange());
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
        
        // File upload display for regular image (multiple files)
        const fileInput = document.getElementById('image');
        const fileList = document.querySelector('#regularImageField .file-list');
        if (fileInput && fileList) {
            // Initialize accumulated files array
            this.accumulatedFiles = [];
            
            fileInput.addEventListener('change', (event) => {
                console.log('File input changed:', event.target.files);
                this.handleFileSelection(event.target.files, fileList, fileInput);
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
    
    handleSizeChange() {
        const selectedSize = this.sizeSelect.value;
        const customSizeGroup = document.getElementById('customSizeGroup');
        const customSizeInput = document.getElementById('customSize');
        
        if (selectedSize === 'custom') {
            if (customSizeGroup) {
                customSizeGroup.style.display = 'block';
                if (customSizeInput) {
                    customSizeInput.required = true;
                }
            }
        } else {
            if (customSizeGroup) {
                customSizeGroup.style.display = 'none';
                if (customSizeInput) {
                    customSizeInput.required = false;
                    customSizeInput.value = '';
                }
            }
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

        // If this category has sizes (like card-print), add them after the main options
        if (options.sizes) {
            // Add a separator
            const separator = document.createElement('option');
            separator.disabled = true;
            separator.textContent = '--- Card Sizes ---';
            this.sizeSelect.appendChild(separator);
            
            // Add size options
            options.sizes.forEach(size => {
                const sizeElement = document.createElement('option');
                sizeElement.value = size.value;
                sizeElement.textContent = size.text;
                this.sizeSelect.appendChild(sizeElement);
            });
        }
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
        const customSizeInput = document.getElementById('customSize');
        
        if (!category) {
            window.showModal('error', 'Please select a service category.');
            return false;
        }
        
        if (!size) {
            window.showModal('error', 'Please select a size/type option.');
            return false;
        }
        
        // Validate custom size input if custom is selected
        if (size === 'custom' && customSizeInput) {
            if (!customSizeInput.value.trim()) {
                window.showModal('error', 'Please enter the custom size.');
                return false;
            }
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
        
        // Hide custom size input
        const customSizeGroup = document.getElementById('customSizeGroup');
        const customSizeInput = document.getElementById('customSize');
        if (customSizeGroup) {
            customSizeGroup.style.display = 'none';
        }
        if (customSizeInput) {
            customSizeInput.required = false;
            customSizeInput.value = '';
        }
        
        // Reset all file name displays
        const fileNames = document.querySelectorAll('.file-name');
        fileNames.forEach(fileName => {
            fileName.textContent = 'No files chosen';
        });
        
        // Reset file lists
        const fileLists = document.querySelectorAll('.file-list');
        fileLists.forEach(fileList => {
            fileList.innerHTML = '<span class="file-name">No files chosen</span>';
        });
        
        // Reset accumulated files
        this.accumulatedFiles = [];
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
    
    displayMultipleFiles(files, container) {
        console.log('displayMultipleFiles called with:', files, container);
        
        if (!files || files.length === 0) {
            container.innerHTML = '<span class="file-name">No files chosen</span>';
            return;
        }
        
        if (files.length === 1) {
            container.innerHTML = `<span class="file-name">${files[0].name}</span>`;
            return;
        }
        
        // Create file list for multiple files
        let fileListHTML = '<div class="multiple-files-container">';
        fileListHTML += `<div class="file-count">${files.length} files selected</div>`;
        fileListHTML += '<div class="file-items">';
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const fileSize = this.formatFileSize(file.size);
            const fileIcon = this.getFileIcon(file.type);
            
            fileListHTML += `
                <div class="file-item">
                    <div class="file-info">
                        <i class="${fileIcon}"></i>
                        <span class="file-name-text">${file.name}</span>
                        <span class="file-size">${fileSize}</span>
                    </div>
                    <button type="button" class="remove-file-btn" onclick="window.removeFile(${i})" title="Remove file">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        }
        
        fileListHTML += '</div></div>';
        container.innerHTML = fileListHTML;
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    getFileIcon(fileType) {
        if (fileType.startsWith('image/')) {
            return 'fas fa-image';
        } else if (fileType === 'application/pdf') {
            return 'fas fa-file-pdf';
        } else {
            return 'fas fa-file';
        }
    }
    
    handleFileSelection(newFiles, container, fileInput) {
        // Add new files to accumulated files array
        for (let i = 0; i < newFiles.length; i++) {
            const file = newFiles[i];
            // Check if file already exists (by name and size)
            const exists = this.accumulatedFiles.some(existingFile => 
                existingFile.name === file.name && existingFile.size === file.size
            );
            
            if (!exists) {
                this.accumulatedFiles.push(file);
            }
        }
        
        // Update the file input with all accumulated files
        this.updateFileInput(fileInput);
        
        // Display all accumulated files
        this.displayMultipleFiles(this.accumulatedFiles, container);
    }
    
    updateFileInput(fileInput) {
        const dt = new DataTransfer();
        this.accumulatedFiles.forEach(file => {
            dt.items.add(file);
        });
        fileInput.files = dt.files;
    }
    
    removeFile(index) {
        const fileInput = document.getElementById('image');
        const fileList = document.querySelector('#regularImageField .file-list');
        
        if (fileInput && fileList && this.accumulatedFiles) {
            // Remove file from accumulated files array
            this.accumulatedFiles.splice(index, 1);
            
            // Update file input
            this.updateFileInput(fileInput);
            
            // Update display
            this.displayMultipleFiles(this.accumulatedFiles, fileList);
        }
    }
}

// Make functions available globally for onclick handlers
window.clearForm = function() {
    if (window.formManager) {
        window.formManager.clearForm();
    }
};

window.removeFile = function(index) {
    if (window.formManager) {
        window.formManager.removeFile(index);
    }
};
