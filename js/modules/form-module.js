/**
 * Form Management Module
 * Handles request form functionality, validation, and submission
 */

import { categoryOptions, API_ENDPOINTS, ANIMATION_SETTINGS } from './config-module.js';
import { csrfService } from './csrf-module.js';

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
        
        this.init();
    }
    
    init() {
        this.initializeElements();
        this.setupEventListeners();
        this.initializeFormState();
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
        
        // Size breakdown elements
        this.sizeBreakdownGroup = document.getElementById('sizeBreakdownGroup');
        this.sizeBreakdownGrid = document.getElementById('sizeBreakdownGrid');
        this.sizeBreakdownTotal = document.getElementById('sizeBreakdownTotal');
        this.quantityInput = document.getElementById('quantity');
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
        
        // Quantity change event (for size breakdown)
        if (this.quantityInput) {
            this.quantityInput.addEventListener('input', () => this.handleQuantityChange());
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
        
        // File upload display for card specific fields
        this.setupCardFileUploads();
        
        // Form toggle events
        if (this.showFormBtn) {
            this.showFormBtn.addEventListener('click', () => this.showRequestForm());
        }
        
        if (this.closeFormBtn) {
            this.closeFormBtn.addEventListener('click', () => this.hideRequestForm());
        }
    }
    
    initializeFormState() {
        // Ensure custom size field is hidden on initialization
        const customSizeGroup = document.getElementById('customSizeGroup');
        const customSizeInput = document.getElementById('customSize');
        
        if (customSizeGroup) {
            customSizeGroup.style.display = 'none';
        }
        if (customSizeInput) {
            customSizeInput.required = false;
            customSizeInput.value = '';
        }
        
        // Ensure T-shirt fields are hidden on initialization
        this.toggleTshirtFields('');
        
        console.log('Form state initialized - custom size field hidden');
    }
    
    handleCategoryChange() {
        const selectedCategory = this.categorySelect.value;
        
        // Clear all uploaded files when category changes
        this.clearAllUploadedFiles();
        
        // Clear custom size field when category changes
        const customSizeInput = document.getElementById('customSize');
        if (customSizeInput) {
            customSizeInput.required = false;
            customSizeInput.value = '';
        }
        
        if (selectedCategory && categoryOptions[selectedCategory]) {
            this.populateSizeOptions(selectedCategory);
            this.enableSizeSelect();
            this.toggleTshirtFields(selectedCategory);
            this.toggleCardFields(selectedCategory);
            this.toggleSizeBreakdown(selectedCategory);
            this.updateFileRequirements(selectedCategory);
        } else {
            this.clearSizeOptions();
            this.disableSizeSelect();
            this.toggleTshirtFields('');
            this.toggleCardFields('');
            this.toggleSizeBreakdown('');
            this.updateFileRequirements('');
        }
    }
    
    handleSizeChange() {
        const selectedSize = this.sizeSelect.value;
        const selectedCategory = this.categorySelect.value;
        const customSizeGroup = document.getElementById('customSizeGroup');
        const customSizeInput = document.getElementById('customSize');
        
        // Only show custom size input for card-print category
        if (selectedSize === 'custom' && selectedCategory === 'card-print') {
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
        
        // Toggle card fields for card-print category
        if (selectedCategory === 'card-print') {
            this.toggleCardFields(selectedCategory);
        }
    }
    
    handleDesignOptionChange() {
        const designOption = document.getElementById('designOption').value;
        const selectedCategory = this.categorySelect.value;
        
        if (selectedCategory === 't-shirt-print') {
            this.toggleTshirtFields(selectedCategory, designOption);
            this.updateFileRequirements(selectedCategory);
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
            // Ensure CSRF token is available
            await csrfService.ensure();
            
            // Prepare form data
            const formData = new FormData(this.requestForm);
            
            // Debug: Log all files being sent
            const frontImage = document.getElementById('frontImage');
            const backImage = document.getElementById('backImage');
            const tagImage = document.getElementById('tagImage');
            const regularImage = document.getElementById('image');
            
            console.log('Files being sent:');
            console.log('Front image files:', frontImage?.files?.length || 0);
            console.log('Back image files:', backImage?.files?.length || 0);
            console.log('Tag image files:', tagImage?.files?.length || 0);
            console.log('Regular image files:', regularImage?.files?.length || 0);
            
            // Log FormData contents
            console.log('FormData contents:');
            for (let [key, value] of formData.entries()) {
                if (value instanceof File) {
                    console.log(`${key}: File - ${value.name} (${value.size} bytes)`);
                } else {
                    console.log(`${key}: ${value}`);
                }
            }
            
            // Add size breakdown data for T-shirt orders
            const sizeBreakdownData = this.getSizeBreakdownData();
            if (sizeBreakdownData) {
                formData.append('size_breakdown', JSON.stringify(sizeBreakdownData));
                console.log('Size breakdown data:', sizeBreakdownData);
            }
            
            // Add CSRF token
            const token = csrfService.getToken();
            console.log('CSRF token:', token ? 'Present' : 'Missing');
            if (token) {
                formData.append('csrf_token', token);
            } else {
                console.error('No CSRF token available');
                window.showModal('error', 'Security token missing. Please refresh the page and try again.');
                return;
            }
            
            // Submit request
            const response = await fetch(API_ENDPOINTS.REQUESTS, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });
            
            console.log('Response status:', response.status);
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Response error:', errorText);
                
                if (response.status === 403) {
                    // CSRF token issue - reload token and show specific error
                    await csrfService.load();
                    window.showModal('error', 'Security token expired. Please try submitting again.');
                } else {
                    window.showModal('error', `Server error (${response.status}): Please try again.`);
                }
                return;
            }
            
            const result = await response.json();
            console.log('Response result:', result);
            
            if (result.success) {
                window.showModal('success', result.message);
                this.clearForm();
                // Load new CSRF token for next request
                await csrfService.load();
            } else {
                window.showModal('error', result.message || 'Failed to submit request');
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
        
        // Validate custom size input if custom is selected and category is card-print
        if (size === 'custom' && category === 'card-print' && customSizeInput) {
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
        
        // Validate T-shirt tag requirements
        if (category === 't-shirt-print') {
            const designOption = document.getElementById('designOption').value;
            if (designOption === 'customize') {
                const tagImageInput = document.getElementById('tagImage');
                const tagLocationSelect = document.getElementById('tagLocation');
                
                // Check if tag file is uploaded
                const hasTagFile = tagImageInput && tagImageInput.files && tagImageInput.files.length > 0;
                
                // Tag is optional, but if tag file is uploaded, tag location is required
                if (hasTagFile && tagLocationSelect && !tagLocationSelect.value) {
                    window.showModal('error', 'Please select a tag location for your tag design.');
                    return false;
                }
            }
        }
        
        // Validate file upload requirements
        const servicesRequiringFiles = [
            't-shirt-print', 'tag-print', 'sticker-print', 'card-print', 
            'document-print', 'photo-print', 'photo-copy', 'lamination'
        ];
        
        if (servicesRequiringFiles.includes(category)) {
            let hasFiles = false;
            
            if (category === 't-shirt-print') {
                const designOption = document.getElementById('designOption').value;
                
                if (designOption === 'customize') {
                    // For T-shirt customization, check if at least one of front, back, or tag is uploaded
                    const frontImageInput = document.getElementById('frontImage');
                    const backImageInput = document.getElementById('backImage');
                    const tagImageInput = document.getElementById('tagImage');
                    
                    const hasFrontImage = frontImageInput && frontImageInput.files && frontImageInput.files.length > 0;
                    const hasBackImage = backImageInput && backImageInput.files && backImageInput.files.length > 0;
                    const hasTagImage = tagImageInput && tagImageInput.files && tagImageInput.files.length > 0;
                    
                    hasFiles = hasFrontImage || hasBackImage || hasTagImage;
                    
                    if (!hasFiles) {
                        window.showModal('error', 'Please upload at least one design file (front design, back design, or tag).');
                        return false;
                    }
                } else {
                    // For ready design option, check regular image upload
                    const regularImageInput = document.getElementById('image');
                    hasFiles = regularImageInput && regularImageInput.files && regularImageInput.files.length > 0;
                    
                    if (!hasFiles) {
                        window.showModal('error', 'Please upload your design file.');
                        return false;
                    }
                }
            } else if (category === 'card-print' && (size === 'calling' || size === 'business')) {
                // For calling/business cards, check front/back design uploads
                const cardFrontImageInput = document.getElementById('cardFrontImage');
                const cardBackImageInput = document.getElementById('cardBackImage');
                const regularImageInput = document.getElementById('image');
                
                const hasCardFrontImage = cardFrontImageInput && cardFrontImageInput.files && cardFrontImageInput.files.length > 0;
                const hasCardBackImage = cardBackImageInput && cardBackImageInput.files && cardBackImageInput.files.length > 0;
                const hasRegularImage = regularImageInput && regularImageInput.files && regularImageInput.files.length > 0;
                
                // Front design is required for calling/business cards
                if (!hasCardFrontImage) {
                    window.showModal('error', 'Front design is required for calling and business cards.');
                    return false;
                }
                
                hasFiles = hasCardFrontImage || hasCardBackImage || hasRegularImage;
                
                if (!hasFiles) {
                    window.showModal('error', 'Please upload at least the front design for your card.');
                    return false;
                }
            } else {
                // For other services, check regular image upload
                const regularImageInput = document.getElementById('image');
                hasFiles = regularImageInput && regularImageInput.files && regularImageInput.files.length > 0;
                
                if (!hasFiles) {
                    window.showModal('error', 'File upload is required for this service. Please select at least one image or document file.');
                    return false;
                }
            }
        }
        
        // Validate size breakdown for T-shirt orders
        if (category === 't-shirt-print') {
            if (!this.validateSizeBreakdown()) {
                window.showModal('error', 'Size breakdown total must match the quantity and be greater than 0. Please adjust the size quantities.');
                return false;
            }
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
        this.toggleCardFields('');
        
        // Hide custom size input (should only be visible for card-print category)
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
        
        // Reset required attributes to default state
        const regularImageInput = document.getElementById('image');
        const cardFrontInput = document.getElementById('cardFrontImage');
        if (regularImageInput) {
            regularImageInput.required = true; // Default state
        }
        if (cardFrontInput) {
            cardFrontInput.required = false; // Default state
        }
    }
    
    setupTshirtFileUploads() {
        // Front image upload
        if (this.frontImageInput) {
            const frontFileContainer = this.frontImageInput.closest('.file-upload').querySelector('.file-name');
            this.frontImageInput.addEventListener('change', (event) => {
                this.displaySingleTshirtFile(event.target.files, frontFileContainer, 'front');
            });
        }
        
        // Back image upload
        if (this.backImageInput) {
            const backFileContainer = this.backImageInput.closest('.file-upload').querySelector('.file-name');
            this.backImageInput.addEventListener('change', (event) => {
                this.displaySingleTshirtFile(event.target.files, backFileContainer, 'back');
            });
        }
        
        // Tag image upload with tag location validation
        if (this.tagImageInput) {
            const tagFileContainer = this.tagImageInput.closest('.file-upload').querySelector('.file-name');
            const tagLocationSelect = document.getElementById('tagLocation');
            
            this.tagImageInput.addEventListener('change', (event) => {
                this.displaySingleTshirtFile(event.target.files, tagFileContainer, 'tag');
                
                // Handle tag location requirements
                if (event.target.files.length > 0) {
                    // Make tag location required when tag file is uploaded
                    if (tagLocationSelect) {
                        tagLocationSelect.required = true;
                        tagLocationSelect.style.borderColor = '#007bff'; // Blue border to indicate required
                    }
                } else {
                    // Make tag location optional when no tag file
                    if (tagLocationSelect) {
                        tagLocationSelect.required = false;
                        tagLocationSelect.value = ''; // Clear selection
                        tagLocationSelect.style.borderColor = ''; // Reset border
                    }
                }
            });
            
            // Add validation for tag location selection
            if (tagLocationSelect) {
                tagLocationSelect.addEventListener('change', function() {
                    const tagImageInput = document.getElementById('tagImage');
                    const hasTagFile = tagImageInput && tagImageInput.files && tagImageInput.files.length > 0;
                    
                    // If user selects tag location but no tag file uploaded, show warning
                    if (this.value && !hasTagFile) {
                        window.showModal('warning', 'Please upload a tag design file first before selecting tag location.');
                        this.value = ''; // Clear the selection
                        this.style.borderColor = '#ffc107'; // Yellow border for warning
                        setTimeout(() => {
                            this.style.borderColor = ''; // Reset border after 3 seconds
                        }, 3000);
                    }
                });
            }
        }
    }
    
    toggleTshirtFields(category, designOption = null) {
        const tshirtFields = document.getElementById('tshirtFields');
        const regularImageGroup = document.getElementById('regularImageField');
        const designOptionGroup = document.getElementById('designOptionGroup');
        
        console.log('toggleTshirtFields called with category:', category, 'designOption:', designOption);
        
        if (category === 't-shirt-print') {
            console.log('Showing design option group for T-shirt');
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
                
                // Clear tag-related fields since they're not needed for ready designs
                const tagImageInput = document.getElementById('tagImage');
                const tagLocationSelect = document.getElementById('tagLocation');
                if (tagImageInput) {
                    tagImageInput.value = '';
                    tagImageInput.required = false;
                    // Clear file name display
                    const fileNameSpan = tagImageInput.parentElement?.querySelector('.file-name');
                    if (fileNameSpan) {
                        fileNameSpan.textContent = 'No file chosen';
                    }
                }
                if (tagLocationSelect) {
                    tagLocationSelect.value = '';
                    tagLocationSelect.required = false;
                    tagLocationSelect.style.borderColor = ''; // Reset border styling
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
    
    setupCardFileUploads() {
        // Card front image upload
        const cardFrontImageInput = document.getElementById('cardFrontImage');
        if (cardFrontImageInput) {
            const frontFileContainer = cardFrontImageInput.closest('.file-upload').querySelector('.file-name');
            cardFrontImageInput.addEventListener('change', (event) => {
                this.displaySingleCardFile(event.target.files, frontFileContainer, 'front');
            });
        }
        
        // Card back image upload
        const cardBackImageInput = document.getElementById('cardBackImage');
        if (cardBackImageInput) {
            const backFileContainer = cardBackImageInput.closest('.file-upload').querySelector('.file-name');
            cardBackImageInput.addEventListener('change', (event) => {
                this.displaySingleCardFile(event.target.files, backFileContainer, 'back');
            });
        }
    }
    
    displaySingleCardFile(files, container, type) {
        if (!files || files.length === 0) {
            container.textContent = 'No file chosen';
            return;
        }
        
        const file = files[0];
        container.textContent = file.name;
        console.log(`Card ${type} file selected:`, file.name);
    }
    
    toggleCardFields(category) {
        const cardFields = document.getElementById('cardFields');
        const regularImageGroup = document.getElementById('regularImageField');
        const regularImageInput = document.getElementById('image');
        const cardFrontInput = document.getElementById('cardFrontImage');
        const sizeSelect = this.sizeSelect;
        
        console.log('toggleCardFields called with category:', category);
        
        if (category === 'card-print') {
            const selectedSize = sizeSelect ? sizeSelect.value : '';
            console.log('Card print category selected, size:', selectedSize);
            
            // Show card fields only for calling and business cards
            if (selectedSize === 'calling' || selectedSize === 'business') {
                console.log('Showing card fields for:', selectedSize);
                if (cardFields) {
                    cardFields.style.display = 'block';
                }
                if (regularImageGroup) {
                    regularImageGroup.style.display = 'none';
                }
                // Remove required from regular image input and add to card front input
                if (regularImageInput) {
                    regularImageInput.required = false;
                }
                if (cardFrontInput) {
                    cardFrontInput.required = true;
                }
            } else {
                console.log('Hiding card fields, showing regular upload');
                if (cardFields) {
                    cardFields.style.display = 'none';
                }
                if (regularImageGroup) {
                    regularImageGroup.style.display = 'block';
                }
                // Restore required to regular image input and remove from card inputs
                if (regularImageInput) {
                    regularImageInput.required = true;
                }
                if (cardFrontInput) {
                    cardFrontInput.required = false;
                }
            }
        } else {
            // For non-card categories, hide card fields
            console.log('Non-card category, hiding card fields');
            if (cardFields) {
                cardFields.style.display = 'none';
            }
            // Ensure regular image input is required for other categories
            if (regularImageInput) {
                regularImageInput.required = true;
            }
            if (cardFrontInput) {
                cardFrontInput.required = false;
            }
        }
    }
    
    displayMultipleFiles(files, container) {
        console.log('displayMultipleFiles called with:', files, container);
        
        if (!files || files.length === 0) {
            container.innerHTML = '<span class="file-name">No files chosen</span>';
            return;
        }
        
        // Create file list for both single and multiple files
        let fileListHTML = '<div class="multiple-files-container">';
        
        if (files.length === 1) {
            // Single file display with remove button
            fileListHTML += `<div class="file-count">1 file selected</div>`;
        } else {
            // Multiple files display
            fileListHTML += `<div class="file-count">${files.length} files selected</div>`;
        }
        
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
    
    displaySingleTshirtFile(files, container, fileType) {
        if (!files || files.length === 0) {
            container.innerHTML = 'No file chosen';
            return;
        }
        
        const file = files[0];
        const fileSize = this.formatFileSize(file.size);
        const fileIcon = this.getFileIcon(file.type);
        
        // Create single file display with remove button
        const fileHTML = `
            <div class="single-file-container">
                <div class="file-item">
                    <div class="file-info">
                        <i class="${fileIcon}"></i>
                        <span class="file-name-text">${file.name}</span>
                        <span class="file-size">${fileSize}</span>
                    </div>
                    <button type="button" class="remove-file-btn" onclick="window.removeTshirtFile('${fileType}')" title="Remove file">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
        
        container.innerHTML = fileHTML;
    }
    
    removeTshirtFile(fileType) {
        let fileInput;
        let container;
        
        switch(fileType) {
            case 'front':
                fileInput = document.getElementById('frontImage');
                container = fileInput.closest('.file-upload').querySelector('.file-name');
                break;
            case 'back':
                fileInput = document.getElementById('backImage');
                container = fileInput.closest('.file-upload').querySelector('.file-name');
                break;
            case 'tag':
                fileInput = document.getElementById('tagImage');
                container = fileInput.closest('.file-upload').querySelector('.file-name');
                // Also handle tag location requirements
                const tagLocationSelect = document.getElementById('tagLocation');
                if (tagLocationSelect) {
                    tagLocationSelect.required = false;
                    tagLocationSelect.value = '';
                    tagLocationSelect.style.borderColor = '';
                }
                break;
        }
        
        if (fileInput && container) {
            // Clear the file input
            fileInput.value = '';
            
            // Reset display
            container.innerHTML = 'No file chosen';
        }
    }
    
    clearAllUploadedFiles() {
        // Clear regular image upload (multiple files)
        const regularFileInput = document.getElementById('image');
        const regularFileList = document.querySelector('#regularImageField .file-list');
        if (regularFileInput && regularFileList) {
            regularFileInput.value = '';
            this.accumulatedFiles = [];
            regularFileList.innerHTML = '<span class="file-name">No files chosen</span>';
        }
        
        // Clear T-shirt specific uploads
        const tshirtUploads = [
            { id: 'frontImage', type: 'front' },
            { id: 'backImage', type: 'back' },
            { id: 'tagImage', type: 'tag' }
        ];
        
        tshirtUploads.forEach(upload => {
            const fileInput = document.getElementById(upload.id);
            if (fileInput) {
                const container = fileInput.closest('.file-upload')?.querySelector('.file-name');
                if (container) {
                    fileInput.value = '';
                    container.innerHTML = 'No file chosen';
                }
            }
        });
        
        // Reset tag location requirements
        const tagLocationSelect = document.getElementById('tagLocation');
        if (tagLocationSelect) {
            tagLocationSelect.required = false;
            tagLocationSelect.value = '';
            tagLocationSelect.style.borderColor = '';
        }
    }
    
    updateFileRequirements(category) {
        // Services that require file uploads
        const servicesRequiringFiles = [
            't-shirt-print', 'tag-print', 'sticker-print', 'card-print', 
            'document-print', 'photo-print', 'photo-copy', 'lamination'
        ];
        
        const requiresFiles = servicesRequiringFiles.includes(category);
        
        // Update regular image input
        const regularImageInput = document.getElementById('image');
        
        // Update T-shirt specific inputs
        const frontImageInput = document.getElementById('frontImage');
        const backImageInput = document.getElementById('backImage');
        
        if (category === 't-shirt-print') {
            const designOption = document.getElementById('designOption').value;
            
            if (designOption === 'customize') {
                // For customize option, front/back images are not strictly required (at least one will be validated in form validation)
                if (frontImageInput) frontImageInput.required = false;
                if (backImageInput) backImageInput.required = false;
                if (regularImageInput) regularImageInput.required = false;
            } else if (designOption === 'ready') {
                // For ready design option, only regular image is required
                if (frontImageInput) frontImageInput.required = false;
                if (backImageInput) backImageInput.required = false;
                if (regularImageInput) regularImageInput.required = true;
            } else {
                // No design option selected yet, no fields required
                if (frontImageInput) frontImageInput.required = false;
                if (backImageInput) backImageInput.required = false;
                if (regularImageInput) regularImageInput.required = false;
            }
        } else {
            // For other categories, T-shirt inputs are not required
            if (frontImageInput) frontImageInput.required = false;
            if (backImageInput) backImageInput.required = false;
            if (regularImageInput) regularImageInput.required = requiresFiles;
        }
        
        // Update labels to show requirement
        this.updateFileLabels(category, requiresFiles);
    }
    
    updateFileLabels(category, requiresFiles) {
        const regularImageLabel = document.querySelector('label[for="image"]');
        const frontImageLabel = document.querySelector('label[for="frontImage"]');
        const backImageLabel = document.querySelector('label[for="backImage"]');
        
        if (regularImageLabel && category !== 't-shirt-print') {
            const asterisk = requiresFiles ? ' <span style="color: red;">*</span>' : '';
            regularImageLabel.innerHTML = `Images/Files${asterisk}`;
        }
        
        if (category === 't-shirt-print') {
            if (frontImageLabel) {
                frontImageLabel.innerHTML = 'Front Design <span style="color: red;">*</span>';
            }
            if (backImageLabel) {
                backImageLabel.innerHTML = 'Back Design <span style="color: red;">*</span>';
            }
        } else {
            if (frontImageLabel) {
                frontImageLabel.innerHTML = 'Front Design';
            }
            if (backImageLabel) {
                backImageLabel.innerHTML = 'Back Design';
            }
        }
    }
    
    // Size breakdown methods
    toggleSizeBreakdown(category) {
        if (!this.sizeBreakdownGroup) return;
        
        if (category === 't-shirt-print') {
            this.sizeBreakdownGroup.style.display = 'block';
            this.generateSizeBreakdownInputs();
        } else {
            this.sizeBreakdownGroup.style.display = 'none';
            this.clearSizeBreakdown();
        }
    }
    
    generateSizeBreakdownInputs() {
        if (!this.sizeBreakdownGrid) return;
        
        // T-shirt sizes
        const tshirtSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
        
        this.sizeBreakdownGrid.innerHTML = '';
        
        tshirtSizes.forEach(size => {
            const inputGroup = document.createElement('div');
            inputGroup.className = 'size-input-group';
            
            const label = document.createElement('label');
            label.textContent = size;
            label.setAttribute('for', `size_${size}`);
            
            const input = document.createElement('input');
            input.type = 'number';
            input.id = `size_${size}`;
            input.name = `size_${size}`;
            input.min = '0';
            input.value = '0';
            input.addEventListener('input', () => this.updateSizeBreakdownTotal());
            
            inputGroup.appendChild(label);
            inputGroup.appendChild(input);
            this.sizeBreakdownGrid.appendChild(inputGroup);
        });
        
        this.updateSizeBreakdownTotal();
    }
    
    handleQuantityChange() {
        if (this.categorySelect.value === 't-shirt-print') {
            this.updateSizeBreakdownTotal();
        }
    }
    
    updateSizeBreakdownTotal() {
        if (!this.sizeBreakdownTotal || !this.quantityInput) return;
        
        const sizeInputs = this.sizeBreakdownGrid.querySelectorAll('input[type="number"]');
        let total = 0;
        
        sizeInputs.forEach(input => {
            total += parseInt(input.value) || 0;
        });
        
        const targetQuantity = parseInt(this.quantityInput.value) || 0;
        
        this.sizeBreakdownTotal.textContent = total;
        
        // Update styling based on match
        this.sizeBreakdownTotal.parentElement.classList.remove('error', 'success');
        
        if (targetQuantity > 0) {
            if (total === targetQuantity) {
                this.sizeBreakdownTotal.parentElement.classList.add('success');
            } else if (total !== targetQuantity) {
                this.sizeBreakdownTotal.parentElement.classList.add('error');
            }
        }
    }
    
    clearSizeBreakdown() {
        if (this.sizeBreakdownGrid) {
            this.sizeBreakdownGrid.innerHTML = '';
        }
        if (this.sizeBreakdownTotal) {
            this.sizeBreakdownTotal.textContent = '0';
            this.sizeBreakdownTotal.parentElement.classList.remove('error', 'success');
        }
    }
    
    getSizeBreakdownData() {
        if (this.categorySelect.value !== 't-shirt-print') {
            return null;
        }
        
        const sizeInputs = this.sizeBreakdownGrid.querySelectorAll('input[type="number"]');
        const breakdown = [];
        
        sizeInputs.forEach(input => {
            const quantity = parseInt(input.value) || 0;
            if (quantity > 0) {
                const size = input.id.replace('size_', '');
                breakdown.push({ size, quantity });
            }
        });
        
        return breakdown.length > 0 ? breakdown : null;
    }
    
    validateSizeBreakdown() {
        if (this.categorySelect.value !== 't-shirt-print') {
            return true; // No validation needed for non-T-shirt orders
        }
        
        const targetQuantity = parseInt(this.quantityInput.value) || 0;
        const sizeInputs = this.sizeBreakdownGrid.querySelectorAll('input[type="number"]');
        let total = 0;
        
        sizeInputs.forEach(input => {
            total += parseInt(input.value) || 0;
        });
        
        return total === targetQuantity && total > 0;
    }
    
    clearAllUploadedFiles() {
        // List of all file input IDs
        const fileInputs = [
            'image',           // Regular image uploads
            'frontImage',      // T-shirt front design
            'backImage',       // T-shirt back design
            'tagImage',        // T-shirt tag design
            'cardFrontImage',  // Card front design
            'cardBackImage'    // Card back design
        ];
        
        fileInputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            if (input) {
                // Clear the file input
                input.value = '';
                
                // Clear the file name display
                const fileNameSpan = input.parentElement?.querySelector('.file-name');
                if (fileNameSpan) {
                    fileNameSpan.textContent = 'No file chosen';
                }
                
                // Clear any file preview displays
                const previewContainer = input.parentElement?.querySelector('.file-preview');
                if (previewContainer) {
                    previewContainer.innerHTML = '';
                }
            }
        });
        
        // Clear the regular image file display area
        const fileDisplay = document.getElementById('fileDisplay');
        if (fileDisplay) {
            fileDisplay.innerHTML = '';
        }
        
        console.log('All uploaded files cleared due to category change');
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

window.removeTshirtFile = function(fileType) {
    if (window.formManager) {
        window.formManager.removeTshirtFile(fileType);
    }
};
