/**
 * AI Photo Editor Module
 * Handles AI photo editing functionality
 */

import { API_ENDPOINTS, FILE_CONSTRAINTS } from './config-module.js';
import { csrfService } from './csrf-module.js';

export class AIPhotoEditor {
    constructor() {
        this.modal = null;
        this.photoUpload = null;
        this.uploadArea = null;
        this.uploadPlaceholder = null;
        this.uploadedImagePreview = null;
        this.previewImage = null;
        this.removeImageBtn = null;
        this.editPrompt = null;
        this.editPhotoBtn = null;
        this.clearEditBtn = null;
        this.closeBtn = null;
        
        this.resultSection = null;
        this.loadingSection = null;
        this.imageResultSection = null;
        this.errorSection = null;
        this.editedImage = null;
        this.usedEditPromptSpan = null;
        this.errorText = null;
        this.downloadEditedBtn = null;
        this.editNewBtn = null;
        this.retryEditBtn = null;
        
        this.isEditing = false;
        this.currentImageUrl = null;
        this.uploadedFile = null;
        
        this.init();
    }
    
    init() {
        this.initializeElements();
        this.setupEventListeners();
    }
    
    initializeElements() {
        this.modal = document.getElementById('aiPhotoEditorModal');
        this.photoUpload = document.getElementById('photoUpload');
        this.uploadArea = document.getElementById('uploadArea');
        this.uploadPlaceholder = this.uploadArea?.querySelector('.upload-placeholder');
        this.uploadedImagePreview = document.getElementById('uploadedImagePreview');
        this.previewImage = document.getElementById('previewImage');
        this.removeImageBtn = document.getElementById('removeImageBtn');
        this.editPrompt = document.getElementById('editPrompt');
        this.editPhotoBtn = document.getElementById('editPhotoBtn');
        this.clearEditBtn = document.getElementById('clearEditBtn');
        this.closeBtn = document.getElementById('aiEditorModalClose');
        
        this.resultSection = document.getElementById('aiEditorResultSection');
        this.loadingSection = document.getElementById('aiEditorLoading');
        this.imageResultSection = document.getElementById('aiEditorImageResult');
        this.errorSection = document.getElementById('aiEditorError');
        this.editedImage = document.getElementById('editedImage');
        this.usedEditPromptSpan = document.getElementById('usedEditPrompt');
        this.errorText = document.getElementById('editorErrorText');
        this.downloadEditedBtn = document.getElementById('downloadEditedBtn');
        this.editNewBtn = document.getElementById('editNewBtn');
        this.retryEditBtn = document.getElementById('retryEditBtn');
    }
    
    setupEventListeners() {
        // Modal close events
        if (this.closeBtn) {
            this.closeBtn.addEventListener('click', () => this.hideModal());
        }
        
        // Click outside modal to close - DISABLED
        // if (this.modal) {
        //     this.modal.addEventListener('click', (e) => {
        //         if (e.target === this.modal) {
        //             this.hideModal();
        //         }
        //     });
        // }
        
        // Escape key to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal && this.modal.style.display === 'block') {
                this.hideModal();
            }
        });
        
        // Photo upload functionality
        if (this.uploadArea) {
            this.uploadArea.addEventListener('click', () => this.photoUpload?.click());
            this.uploadArea.addEventListener('dragover', (e) => this.handleDragOver(e));
            this.uploadArea.addEventListener('dragleave', (e) => this.handleDragLeave(e));
            this.uploadArea.addEventListener('drop', (e) => this.handleDrop(e));
        }
        
        if (this.photoUpload) {
            this.photoUpload.addEventListener('change', (e) => this.handleFileSelect(e));
        }
        
        if (this.removeImageBtn) {
            this.removeImageBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.removeUploadedImage();
            });
        }
        
        if (this.editPhotoBtn) {
            this.editPhotoBtn.addEventListener('click', () => this.editPhoto());
        }
        
        if (this.clearEditBtn) {
            this.clearEditBtn.addEventListener('click', () => this.clearEditForm());
        }
        
        if (this.editPrompt) {
            this.editPrompt.addEventListener('input', () => this.validateEditForm());
        }
        
        if (this.downloadEditedBtn) {
            this.downloadEditedBtn.addEventListener('click', () => this.downloadImage());
        }
        
        if (this.editNewBtn) {
            this.editNewBtn.addEventListener('click', () => this.editNew());
        }
        
        if (this.retryEditBtn) {
            this.retryEditBtn.addEventListener('click', () => this.editPhoto());
        }
    }
    
    showModal() {
        if (this.modal) {
            this.modal.style.display = 'block';
            this.resetModal();
        }
    }
    
    hideModal() {
        if (this.modal) {
            this.modal.style.display = 'none';
            this.resetModal();
        }
    }
    
    resetModal() {
        // Hide all result sections
        if (this.resultSection) this.resultSection.style.display = 'none';
        if (this.loadingSection) this.loadingSection.style.display = 'none';
        if (this.imageResultSection) this.imageResultSection.style.display = 'none';
        if (this.errorSection) this.errorSection.style.display = 'none';
        
        // Reset button states
        this.isEditing = false;
        this.updateEditButton();
        
        // Clear current image
        this.currentImageUrl = null;
    }
    
    // File upload functionality
    handleDragOver(e) {
        e.preventDefault();
        this.uploadArea?.classList.add('dragover');
    }
    
    handleDragLeave(e) {
        e.preventDefault();
        this.uploadArea?.classList.remove('dragover');
    }
    
    handleDrop(e) {
        e.preventDefault();
        this.uploadArea?.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            this.handleFile(files[0]);
        }
    }
    
    handleFileSelect(e) {
        const file = e.target.files[0];
        if (file) {
            this.handleFile(file);
        }
    }
    
    handleFile(file) {
        // Validate file type
        if (!FILE_CONSTRAINTS.ALLOWED_TYPES.includes(file.type)) {
            window.showModal('error', 'Invalid file type. Please upload JPG, PNG, or GIF images only.');
            return;
        }
        
        // Validate file size
        if (file.size > FILE_CONSTRAINTS.MAX_SIZE) {
            window.showModal('error', 'File too large. Maximum size is 10MB.');
            return;
        }
        
        this.uploadedFile = file;
        this.showImagePreview(file);
        this.validateEditForm();
    }
    
    showImagePreview(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            if (this.previewImage) {
                this.previewImage.src = e.target.result;
            }
            if (this.uploadPlaceholder) {
                this.uploadPlaceholder.style.display = 'none';
            }
            if (this.uploadedImagePreview) {
                this.uploadedImagePreview.style.display = 'block';
            }
        };
        reader.readAsDataURL(file);
    }
    
    removeUploadedImage() {
        this.uploadedFile = null;
        if (this.photoUpload) this.photoUpload.value = '';
        if (this.previewImage) this.previewImage.src = '';
        if (this.uploadPlaceholder) this.uploadPlaceholder.style.display = 'flex';
        if (this.uploadedImagePreview) this.uploadedImagePreview.style.display = 'none';
        this.validateEditForm();
    }
    
    clearEditForm() {
        this.removeUploadedImage();
        if (this.editPrompt) {
            this.editPrompt.value = '';
            this.editPrompt.focus();
        }
        this.validateEditForm();
    }
    
    validateEditForm() {
        const hasImage = this.uploadedFile !== null;
        const hasPrompt = this.editPrompt && this.editPrompt.value.trim().length > 0;
        const isValid = hasImage && hasPrompt && !this.isEditing;
        
        if (this.editPhotoBtn) {
            this.editPhotoBtn.disabled = !isValid;
        }
        
        return isValid;
    }
    
    updateEditButton() {
        if (!this.editPhotoBtn) return;
        
        if (this.isEditing) {
            this.editPhotoBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Editing...';
            this.editPhotoBtn.disabled = true;
        } else {
            this.editPhotoBtn.innerHTML = '<i class="fas fa-edit"></i> Edit Photo';
            this.editPhotoBtn.disabled = !this.validateEditForm();
        }
    }
    
    async editPhoto() {
        if (this.isEditing || !this.validateEditForm()) return;
        
        const prompt = this.editPrompt.value.trim();
        
        this.isEditing = true;
        this.updateEditButton();
        
        // Show loading state
        if (this.resultSection) this.resultSection.style.display = 'block';
        if (this.loadingSection) this.loadingSection.style.display = 'block';
        if (this.imageResultSection) this.imageResultSection.style.display = 'none';
        if (this.errorSection) this.errorSection.style.display = 'none';
        
        try {
            const formData = new FormData();
            formData.append('action', 'edit_photo');
            formData.append('image', this.uploadedFile);
            formData.append('text', prompt);
            formData.append('csrf_token', await csrfService.ensure());
            
            const response = await fetch(API_ENDPOINTS.AI_IMAGE_GENERATOR, {
                method: 'POST',
                credentials: 'include',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showImageResult(data.image_url, data.prompt);
            } else {
                this.showError(data.error || 'Failed to edit photo');
            }
        } catch (error) {
            console.error('Error editing photo:', error);
            this.showError('Network error. Please check your connection and try again.');
        } finally {
            this.isEditing = false;
            this.updateEditButton();
        }
    }
    
    showImageResult(imageUrl, prompt) {
        this.currentImageUrl = imageUrl;
        
        // Hide loading, show result
        if (this.loadingSection) this.loadingSection.style.display = 'none';
        if (this.imageResultSection) this.imageResultSection.style.display = 'block';
        if (this.errorSection) this.errorSection.style.display = 'none';
        
        // Set image and prompt
        if (this.editedImage) {
            this.editedImage.src = imageUrl;
            this.editedImage.onload = () => {
                console.log('Edited image loaded successfully');
            };
            this.editedImage.onerror = () => {
                this.showError('Failed to load edited image');
            };
        }
        
        if (this.usedEditPromptSpan) {
            this.usedEditPromptSpan.textContent = prompt;
        }
    }
    
    showError(errorMessage) {
        // Hide loading and result, show error
        if (this.loadingSection) this.loadingSection.style.display = 'none';
        if (this.imageResultSection) this.imageResultSection.style.display = 'none';
        if (this.errorSection) this.errorSection.style.display = 'block';
        
        if (this.errorText) {
            this.errorText.textContent = errorMessage;
        }
    }
    
    async downloadImage() {
        if (!this.currentImageUrl) {
            this.showError('No image available for download');
            return;
        }
        
        try {
            // Show loading state on download button
            const originalText = this.downloadEditedBtn.innerHTML;
            this.downloadEditedBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Downloading...';
            this.downloadEditedBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'download_image');
            formData.append('csrf_token', await csrfService.ensure());
            
            const response = await fetch(API_ENDPOINTS.AI_IMAGE_GENERATOR, {
                method: 'POST',
                credentials: 'include',
                body: formData
            });
            
            if (response.ok) {
                // Get the blob
                const blob = await response.blob();
                
                // Create download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                
                // Get filename from Content-Disposition header or use default
                const contentDisposition = response.headers.get('Content-Disposition');
                let filename = 'ai_edited_image.jpg';
                if (contentDisposition) {
                    const match = contentDisposition.match(/filename="(.+)"/);
                    if (match) filename = match[1];
                }
                
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                // Show success message
                window.showModal('success', 'Image downloaded successfully!');
            } else {
                const errorData = await response.json();
                this.showError(errorData.error || 'Failed to download image');
            }
        } catch (error) {
            console.error('Error downloading image:', error);
            this.showError('Failed to download image');
        } finally {
            // Reset download button
            if (this.downloadEditedBtn) {
                this.downloadEditedBtn.innerHTML = '<i class="fas fa-download"></i> Download Image';
                this.downloadEditedBtn.disabled = false;
            }
        }
    }
    
    editNew() {
        // Reset to upload section
        this.resetModal();
        this.removeUploadedImage();
        if (this.editPrompt) this.editPrompt.value = '';
        this.validateEditForm();
    }
}
