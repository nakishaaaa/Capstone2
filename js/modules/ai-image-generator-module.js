/**
 * AI Image Generator Module
 * Handles AI image generation functionality
 */

import { API_ENDPOINTS, FILE_CONSTRAINTS } from './config-module.js';
import { csrfService } from './csrf-module.js';

export class AIImageGenerator {
    constructor() {
        this.modal = null;
        this.promptTextarea = null;
        this.generateBtn = null;
        this.clearBtn = null;
        this.downloadBtn = null;
        this.generateNewBtn = null;
        this.retryBtn = null;
        this.closeBtn = null;
        this.resultSection = null;
        this.loadingSection = null;
        this.imageResultSection = null;
        this.errorSection = null;
        this.generatedImage = null;
        this.usedPromptSpan = null;
        this.errorText = null;
        
        this.isGenerating = false;
        this.currentImageUrl = null;
        
        this.init();
    }
    
    init() {
        this.initializeElements();
        this.setupEventListeners();
    }
    
    initializeElements() {
        this.modal = document.getElementById('aiImageModal');
        this.designTypeSelect = document.getElementById('designType');
        this.promptTextarea = document.getElementById('aiPrompt');
        this.generateBtn = document.getElementById('generateImageBtn');
        this.clearBtn = document.getElementById('clearPromptBtn');
        this.downloadBtn = document.getElementById('downloadImageBtn');
        this.generateNewBtn = document.getElementById('generateNewBtn');
        this.retryBtn = document.getElementById('retryBtn');
        this.closeBtn = document.getElementById('aiModalClose');
        
        this.resultSection = document.getElementById('aiResultSection');
        this.loadingSection = document.getElementById('aiLoading');
        this.imageResultSection = document.getElementById('aiImageResult');
        this.errorSection = document.getElementById('aiError');
        this.generatedImage = document.getElementById('generatedImage');
        this.usedPromptSpan = document.getElementById('usedPrompt');
        this.errorText = document.getElementById('errorText');
    }
    
    setupEventListeners() {
        // Modal close events
        if (this.closeBtn) {
            this.closeBtn.addEventListener('click', () => this.hideModal());
        }
        
        // Click outside modal to close
        if (this.modal) {
            this.modal.addEventListener('click', (e) => {
                if (e.target === this.modal) {
                    this.hideModal();
                }
            });
        }
        
        // Escape key to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal && this.modal.style.display === 'block') {
                this.hideModal();
            }
        });
        
        // Generate button
        if (this.generateBtn) {
            this.generateBtn.addEventListener('click', () => this.generateImage());
        }
        
        // Clear button
        if (this.clearBtn) {
            this.clearBtn.addEventListener('click', () => this.clearPrompt());
        }
        
        // Download button
        if (this.downloadBtn) {
            this.downloadBtn.addEventListener('click', () => this.downloadImage());
        }
        
        // Generate new button
        if (this.generateNewBtn) {
            this.generateNewBtn.addEventListener('click', () => this.generateNew());
        }
        
        // Retry button
        if (this.retryBtn) {
            this.retryBtn.addEventListener('click', () => this.generateImage());
        }
        
        // Prompt textarea character count and validation
        if (this.promptTextarea) {
            this.promptTextarea.addEventListener('input', () => this.validatePrompt());
        }
        
        // Design type dropdown change event
        if (this.designTypeSelect) {
            this.designTypeSelect.addEventListener('change', () => this.handleDesignTypeChange());
        }
    }
    
    showModal() {
        if (this.modal) {
            this.modal.style.display = 'block';
            this.resetModal();
            // Focus on textarea
            if (this.promptTextarea) {
                setTimeout(() => this.promptTextarea.focus(), 100);
            }
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
        this.isGenerating = false;
        this.updateGenerateButton();
        
        // Clear current image
        this.currentImageUrl = null;
        
        // Reset dropdown
        if (this.designTypeSelect) {
            this.designTypeSelect.value = '';
        }
    }
    
    clearPrompt() {
        if (this.promptTextarea) {
            this.promptTextarea.value = '';
            this.validatePrompt();
            this.promptTextarea.focus();
        }
        if (this.designTypeSelect) {
            this.designTypeSelect.value = '';
        }
    }
    
    handleDesignTypeChange() {
        // This method is called when the design type dropdown changes
        // The actual prompt combination happens in generateImage method
        this.validatePrompt();
    }
    
    getFullPrompt() {
        const designType = this.designTypeSelect ? this.designTypeSelect.value : '';
        const userPrompt = this.promptTextarea ? this.promptTextarea.value.trim() : '';
        
        if (designType && userPrompt) {
            return `${designType}: ${userPrompt}`;
        } else if (designType) {
            return designType;
        } else {
            return userPrompt;
        }
    }
    
    validatePrompt() {
        const prompt = this.promptTextarea ? this.promptTextarea.value.trim() : '';
        const isValid = prompt.length > 0 && prompt.length <= FILE_CONSTRAINTS.PROMPT_MAX_LENGTH;
        
        if (this.generateBtn) {
            this.generateBtn.disabled = !isValid || this.isGenerating;
        }
        
        return isValid;
    }
    
    updateGenerateButton() {
        if (!this.generateBtn) return;
        
        if (this.isGenerating) {
            this.generateBtn.innerHTML = '<div class="loading-circle" style="width: 16px; height: 16px; margin-right: 8px; display: inline-block;"></div> Generating...';
            this.generateBtn.disabled = true;
        } else {
            this.generateBtn.innerHTML = '<i class="fas fa-magic"></i> Generate Image';
            this.generateBtn.disabled = !this.validatePrompt();
        }
    }
    
    async generateImage() {
        if (this.isGenerating || !this.validatePrompt()) return;
        
        const prompt = this.getFullPrompt();
        
        this.isGenerating = true;
        this.updateGenerateButton();
        
        // Show loading state
        if (this.resultSection) this.resultSection.style.display = 'block';
        if (this.loadingSection) this.loadingSection.style.display = 'block';
        if (this.imageResultSection) this.imageResultSection.style.display = 'none';
        if (this.errorSection) this.errorSection.style.display = 'none';
        
        try {
            const formData = new FormData();
            formData.append('action', 'generate_image');
            formData.append('prompt', prompt);
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
                this.showError(data.error || 'Failed to generate image');
            }
        } catch (error) {
            console.error('Error generating image:', error);
            this.showError('Network error. Please check your connection and try again.');
        } finally {
            this.isGenerating = false;
            this.updateGenerateButton();
        }
    }
    
    showImageResult(imageUrl, prompt) {
        this.currentImageUrl = imageUrl;
        
        // Hide loading, show result
        if (this.loadingSection) this.loadingSection.style.display = 'none';
        if (this.imageResultSection) this.imageResultSection.style.display = 'block';
        if (this.errorSection) this.errorSection.style.display = 'none';
        
        // Set image and prompt
        if (this.generatedImage) {
            this.generatedImage.src = imageUrl;
            this.generatedImage.onload = () => {
                console.log('Image loaded successfully');
            };
            this.generatedImage.onerror = () => {
                this.showError('Failed to load generated image');
            };
        }
        
        if (this.usedPromptSpan) {
            this.usedPromptSpan.textContent = prompt;
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
            const originalText = this.downloadBtn.innerHTML;
            this.downloadBtn.innerHTML = '<div class="loading-circle" style="width: 16px; height: 16px; margin-right: 8px; display: inline-block;"></div> Downloading...';
            this.downloadBtn.disabled = true;
            
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
                let filename = 'ai_generated_image.jpg';
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
            if (this.downloadBtn) {
                this.downloadBtn.innerHTML = '<i class="fas fa-download"></i> Download Image';
                this.downloadBtn.disabled = false;
            }
        }
    }
    
    generateNew() {
        // Reset to prompt section
        this.resetModal();
        if (this.promptTextarea) {
            this.promptTextarea.focus();
        }
    }
}
