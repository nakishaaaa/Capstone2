/**
 * Dev Team Ticket Module
 * Handles the dev team ticket modal functionality and submission
 */

export class DevTicketManager {
    constructor() {
        // Singleton pattern to prevent multiple instances
        if (DevTicketManager.instance) {
            return DevTicketManager.instance;
        }
        
        this.modal = document.getElementById('devTicketModal');
        this.form = document.getElementById('devTicketForm');
        this.isSubmitting = false;
        this.lastSubmitTime = 0;
        
        if (!this.modal || !this.form) {
            console.warn('Dev ticket modal or form not found');
            return;
        }
        
        DevTicketManager.instance = this;
        this.init();
    }
    
    init() {
        // Make functions globally available for onclick handlers
        window.openDevTicketModal = () => this.openModal();
        window.closeDevTicketModal = () => this.closeModal();
        
        this.bindEvents();
    }
    
    bindEvents() {
        // Remove any existing event listeners to prevent duplicates
        this.form.removeEventListener('submit', this.boundHandleSubmit);
        
        // Bind the handler to preserve 'this' context
        this.boundHandleSubmit = (e) => this.handleSubmit(e);
        
        // Form submission
        this.form.addEventListener('submit', this.boundHandleSubmit);
        
        // Handle attachment button
        const attachBtn = document.querySelector('.dev-attachment-btn');
        const attachInput = document.getElementById('devTicketAttachment');
        const attachName = document.getElementById('devAttachmentName');
        
        if (attachBtn && attachInput) {
            attachBtn.addEventListener('click', () => {
                attachInput.click();
            });
            
            attachInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file && attachName) {
                    attachName.textContent = file.name;
                    attachName.style.display = 'inline';
                } else if (attachName) {
                    attachName.textContent = '';
                    attachName.style.display = 'none';
                }
            });
        }
        
        // Close modal when clicking outside
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.closeModal();
            }
        });
        
        // Escape key to close modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.style.display === 'block') {
                this.closeModal();
            }
        });
    }
    
    openModal() {
        if (this.modal) {
            this.modal.style.display = 'block';
            this.modal.classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
            
            // Focus on first input
            const firstInput = this.modal.querySelector('input, textarea');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 300);
            }
        }
    }
    
    closeModal() {
        if (this.modal) {
            this.modal.style.display = 'none';
            this.modal.classList.remove('active');
            document.body.style.overflow = ''; // Restore scrolling
            this.resetForm();
        }
    }
    
    async handleSubmit(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        
        if (this.isSubmitting) {
            console.log('Already submitting, ignoring duplicate');
            return;
        }
        
        // Additional duplicate prevention with timestamp
        const now = Date.now();
        if (this.lastSubmitTime && (now - this.lastSubmitTime) < 3000) {
            console.log('Duplicate submission prevented - too soon');
            return;
        }
        
        this.lastSubmitTime = now;
        
        const formData = new FormData(this.form);
        const priority = formData.get('priority')?.trim();
        const subject = formData.get('subject')?.trim();
        const message = formData.get('message')?.trim();
        const attachment = formData.get('attachment');
        
        // Validation
        if (!priority || !subject || !message) {
            this.showError('Please fill in all required fields.');
            return;
        }
        
        if (message.length < 10) {
            this.showError('Please provide a more detailed message (at least 10 characters).');
            return;
        }
        
        try {
            this.isSubmitting = true;
            this.setSubmitButtonState(true);
            
            // Completely disable form to prevent any further submissions
            this.form.style.pointerEvents = 'none';
            this.form.querySelectorAll('input, textarea, button, select').forEach(el => {
                el.disabled = true;
            });
            
            // Prepare FormData for submission (to handle file uploads)
            const submitFormData = new FormData();
            submitFormData.append('priority', priority);
            submitFormData.append('subject', subject);
            submitFormData.append('message', message);
            
            if (attachment && attachment.size > 0) {
                submitFormData.append('attachment', attachment);
            }
            
            const response = await fetch('/Capstone2/api/submit_support_ticket.php', {
                method: 'POST',
                body: submitFormData
            });
            
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const responseText = await response.text();
            console.log('Raw response:', responseText);
            console.log('Response length:', responseText.length);
            
            // Clean the response text by removing any leading/trailing whitespace
            const cleanedResponse = responseText.trim();
            console.log('Cleaned response:', cleanedResponse);
            
            let result;
            try {
                result = JSON.parse(cleanedResponse);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', responseText);
                console.error('Cleaned response:', cleanedResponse);
                throw new Error('Invalid JSON response from server');
            }
            
            console.log('Parsed result:', result);
            
            if (result && result.success === true) {
                this.showSuccess(`Dev ticket #${result.ticket_id} submitted successfully! Our development team will review and respond soon.`);
                this.form.reset(); // Clear form immediately after success
                
                // Clear attachment display
                const attachName = document.getElementById('devAttachmentName');
                if (attachName) {
                    attachName.textContent = '';
                    attachName.style.display = 'none';
                }
                
                setTimeout(() => {
                    this.closeModal();
                }, 2000);
            } else {
                console.error('Submission failed:', result);
                this.showError(result?.message || 'Failed to submit ticket. Please try again.');
            }
            
        } catch (error) {
            console.error('Dev ticket submission error:', error);
            this.showError('Network error. Please check your connection and try again.');
        } finally {
            this.isSubmitting = false;
            this.setSubmitButtonState(false);
            
            // Re-enable form elements
            this.form.style.pointerEvents = '';
            this.form.querySelectorAll('input, textarea, button, select').forEach(el => {
                el.disabled = false;
            });
        }
    }
    
    setSubmitButtonState(isLoading) {
        const submitBtn = this.form.querySelector('.support-send-btn');
        if (submitBtn) {
            submitBtn.disabled = isLoading;
            submitBtn.innerHTML = isLoading ? 
                '<i class="fas fa-spinner fa-spin"></i> Submitting to Dev Team...' : 
                '<i class="fas fa-bug"></i> Submit to Dev Team';
        }
    }
    
    showError(message) {
        this.showNotification(message, 'error');
    }
    
    showSuccess(message) {
        this.showNotification(message, 'success');
    }
    
    showNotification(message, type) {
        // Remove existing notifications
        const existingNotification = this.modal.querySelector('.support-notification');
        if (existingNotification) {
            existingNotification.remove();
        }
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `support-notification support-notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
            <span>${message}</span>
        `;
        
        // Insert at the top of modal body
        const modalBody = this.modal.querySelector('.support-modal-body');
        modalBody.insertBefore(notification, modalBody.firstChild);
        
        // Auto-remove after 5 seconds for success messages
        if (type === 'success') {
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
    }
    
    resetForm() {
        if (this.form) {
            this.form.reset();
            
            // Remove notifications
            const notification = this.modal.querySelector('.support-notification');
            if (notification) {
                notification.remove();
            }
            
            // Clear attachment display
            const attachName = document.getElementById('devAttachmentName');
            if (attachName) {
                attachName.textContent = '';
                attachName.style.display = 'none';
            }
        }
    }
}

// Remove the DOMContentLoaded listener since the module is now imported
// The main app will handle initialization
