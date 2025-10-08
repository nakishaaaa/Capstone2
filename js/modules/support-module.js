/**
 * Support Module
 * Handles the support modal functionality and message sending
 */

export default class SupportMessaging {
    constructor() {
        // Singleton pattern to prevent multiple instances
        if (SupportMessaging.instance) {
            return SupportMessaging.instance;
        }
        
        this.modal = document.getElementById('supportModal');
        this.form = document.getElementById('supportForm');
        this.isSubmitting = false;
        this.lastSubmitTime = 0;
        this.submitCount = 0;
        this.timestampUpdateInterval = null;
        // Previous conversations modal elements
        this.prevModal = document.getElementById('previousConversationsModal');
        this.prevOpenBtn = document.getElementById('openPreviousConversationsBtn');
        this.prevCloseBtn = document.getElementById('closePreviousConversations');
        this.conversationsListEl = document.getElementById('conversationsList');
        this.conversationDetailEl = document.getElementById('conversationDetail');
        
        if (!this.modal || !this.form) {
            console.warn('Support modal or form not found');
            return;
        }
        
        SupportMessaging.instance = this;
        this.init();
    }

    // Method to refresh messages (called by real-time updates)
    refreshMessages() {
        console.log('Support: Refreshing messages due to real-time update');
        console.log('Support: currentConversationId:', this.currentConversationId);
        
        // If viewing a specific conversation, refresh it immediately
        if (this.currentConversationId) {
            console.log('Support: Refreshing conversation:', this.currentConversationId);
            this.viewConversation(this.currentConversationId).then(() => {
                console.log('Support: Conversation refreshed successfully');
                // Scroll to bottom to show new message
                setTimeout(() => {
                    const messagesEl = this.conversationDetailEl?.querySelector('.conversation-messages');
                    if (messagesEl) {
                        this.scrollToBottom(messagesEl);
                        console.log('Support: Scrolled to bottom after refresh');
                    }
                }, 100);
            }).catch(error => {
                console.error('Support: Error refreshing conversation:', error);
            });
        } else if (this.prevModal && this.prevModal.style.display !== 'none') {
            console.log('Support: Refreshing conversations list');
            // Only refresh conversations list if NOT viewing a specific conversation
            this.openPreviousConversations();
        } else {
            console.warn('Support: No active conversation or modal to refresh');
        }
    }
    
    init() {
        // Make functions globally available for onclick handlers
        window.openSupportModal = () => this.openModal();
        window.closeSupportModal = () => this.closeModal();
        
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
        const attachBtn = document.querySelector('.support-attachment-btn');
        const attachInput = document.getElementById('supportAttachment');
        const attachName = document.getElementById('attachmentName');
        
        if (attachBtn && attachInput) {
            attachBtn.addEventListener('click', () => {
                attachInput.click();
            });
            
            attachInput.addEventListener('change', (e) => {
                const files = e.target.files;
                if (files.length > 0 && attachName) {
                    if (files.length === 1) {
                        attachName.textContent = files[0].name;
                    } else {
                        attachName.textContent = `${files.length} images selected`;
                    }
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
        
        // Character counter for textarea (optional enhancement)
        const textarea = document.getElementById('supportMessage');
        if (textarea) {
            textarea.addEventListener('input', () => this.updateCharacterCount());
        }

        // Previous conversations open/close
        if (this.prevOpenBtn) {
            this.prevOpenBtn.addEventListener('click', () => this.openPreviousConversations());
        }
        if (this.prevCloseBtn) {
            this.prevCloseBtn.addEventListener('click', () => this.closePreviousConversations());
        }
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
        
        // Generate unique submission ID
        const submissionId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        
        // Additional duplicate prevention with timestamp
        const now = Date.now();
        if (this.lastSubmitTime && (now - this.lastSubmitTime) < 3000) {
            console.log('Duplicate submission prevented - too soon');
            return;
        }
        
        // Increment submit count for debugging
        this.submitCount++;
        console.log(`Submit attempt #${this.submitCount}, ID: ${submissionId}`);
        
        this.lastSubmitTime = now;
        
        const formData = new FormData(this.form);
        const subject = formData.get('subject')?.trim();
        const message = formData.get('message')?.trim();
        const attachmentInput = document.getElementById('supportAttachment');
        const attachments = attachmentInput ? attachmentInput.files : null;
        
        // Validation
        if (!subject || !message) {
            this.showError('Please fill in all required fields.');
            return;
        }
        
        if (message.length < 10) {
            this.showError('Please provide a more detailed message (at least 10 characters).');
            return;
        }
        
        // Validate images if attached
        if (attachments && attachments.length > 0) {
            const maxSize = 5 * 1024 * 1024; // 5MB per image
            const maxFiles = 5; // Maximum 5 images
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            
            if (attachments.length > maxFiles) {
                this.showError(`You can upload maximum ${maxFiles} images.`);
                return;
            }
            
            for (let i = 0; i < attachments.length; i++) {
                const file = attachments[i];
                
                if (file.size > maxSize) {
                    this.showError(`Image "${file.name}" is too large. Maximum size is 5MB per image.`);
                    return;
                }
                
                if (!allowedTypes.includes(file.type)) {
                    this.showError(`"${file.name}" is not a valid image. Please upload only JPEG, PNG, GIF, or WebP images.`);
                    return;
                }
            }
        }
        
        try {
            this.isSubmitting = true;
            this.setSubmitButtonState(true);
            
            // Completely disable form to prevent any further submissions
            this.form.style.pointerEvents = 'none';
            this.form.querySelectorAll('input, textarea, button, select').forEach(el => {
                el.disabled = true;
            });
            
            // Get CSRF token for the request
            const csrfToken = await this.getCSRFToken();
            
            let response;
            
            // Check if we have image attachments
            if (attachments && attachments.length > 0) {
                // Use FormData for file upload
                const uploadFormData = new FormData();
                uploadFormData.append('subject', subject);
                uploadFormData.append('message', message);
                
                // Append all images
                for (let i = 0; i < attachments.length; i++) {
                    uploadFormData.append('attachments[]', attachments[i]);
                }
                
                uploadFormData.append('csrf_token', csrfToken);
                
                response = await fetch('/Capstone2/api/customer_support.php', {
                    method: 'POST',
                    body: uploadFormData
                });
            } else {
                // Use JSON for text-only messages
                const payload = {
                    subject: subject,
                    message: message,
                    csrf_token: csrfToken
                };
                
                response = await fetch('/Capstone2/api/customer_support.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
            }
            
            const result = await response.json();
            
            if (result.success) {
                const messageId = result.data?.message_id || 'N/A';
                const attachmentCount = result.data?.attachment_count || 0;
                const attachmentInfo = attachmentCount > 0 ? ` (with ${attachmentCount} image${attachmentCount > 1 ? 's' : ''})` : '';
                this.showSuccess(`Support message #${messageId} submitted successfully${attachmentInfo}! Our customer support team will respond soon.`);
                this.form.reset(); // Clear form immediately after success
                
                // Clear attachment display
                const attachName = document.getElementById('attachmentName');
                if (attachName) {
                    attachName.textContent = '';
                    attachName.style.display = 'none';
                }
                
                setTimeout(() => {
                    this.closeModal();
                }, 2000);
            } else {
                this.showError(result.message || 'Failed to send message. Please try again.');
            }
            
        } catch (error) {
            console.error('Support submission error:', error);
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
                '<div class="loading-circle" style="width: 16px; height: 16px; margin-right: 8px; display: inline-block;"></div> Submitting Ticket...' : 
                '<i class="fas fa-paper-plane"></i> Submit Support Ticket';
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
    
    updateCharacterCount() {
        const textarea = document.getElementById('supportMessage');
        const maxLength = 1000; // Set a reasonable limit
        
        if (textarea) {
            const currentLength = textarea.value.length;
            
            // Create or update character counter
            let counter = this.modal.querySelector('.character-counter');
            if (!counter) {
                counter = document.createElement('div');
                counter.className = 'character-counter';
                counter.style.cssText = 'font-size: 0.8rem; color: #666; text-align: right; margin-top: 0.25rem;';
                textarea.parentNode.appendChild(counter);
            }
            
            counter.textContent = `${currentLength}/${maxLength}`;
            counter.style.color = currentLength > maxLength ? '#dc3545' : '#666';
            
            // Disable submit if over limit
            const submitBtn = this.form.querySelector('.support-send-btn');
            if (submitBtn) {
                submitBtn.disabled = currentLength > maxLength || this.isSubmitting;
            }
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
            
            // Remove character counter
            const counter = this.modal.querySelector('.character-counter');
            if (counter) {
                counter.remove();
            }
        }
    }
    
    async getCSRFToken() {
        try {
            const response = await fetch('/Capstone2/api/csrf_token.php');
            const data = await response.json();
            return data.token;
        } catch (error) {
            console.error('Error getting CSRF token:', error);
            throw new Error('Failed to get CSRF token');
        }
    }

    // Previous Conversations
    openPreviousConversations() {
        if (!this.prevModal) return;
        this.prevModal.style.display = 'block';
        this.prevModal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Always reset to show conversations list when opening
        if (this.conversationDetailEl) {
            this.conversationDetailEl.style.display = 'none';
        }
        if (this.conversationsListEl) {
            this.conversationsListEl.style.display = 'block';
        }
        
        this.loadConversations();
        // Start periodic timestamp updates while modal is open
        this.startTimestampUpdates();
    }

    closePreviousConversations() {
        if (!this.prevModal) return;
        this.prevModal.style.display = 'none';
        this.prevModal.classList.remove('active');
        document.body.style.overflow = '';
        if (this.conversationDetailEl) this.conversationDetailEl.style.display = 'none';
        // Stop periodic updates when closing
        if (this.timestampUpdateInterval) {
            clearInterval(this.timestampUpdateInterval);
            this.timestampUpdateInterval = null;
        }
    }

    async loadConversations() {
        if (!this.conversationsListEl) return;
        this.conversationsListEl.innerHTML = '<div class="conv-loading"><div class="loading-circle"></div> Loading conversations...</div>';
        try {
            const res = await fetch('/Capstone2/api/user_support_conversations.php');
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to load');
            const conversations = data.data.conversations || [];
            if (conversations.length === 0) {
                this.conversationsListEl.innerHTML = '<div class="conv-empty">No previous conversations yet.</div>';
                return;
            }
            this.conversationsListEl.innerHTML = conversations.map(c => this.renderConversationCard(c)).join('');
            // attach click handlers
            this.conversationsListEl.querySelectorAll('[data-conv-id]').forEach(card => {
                card.addEventListener('click', () => {
                    const id = card.getAttribute('data-conv-id');
                    this.viewConversation(id);
                });
            });
            // Update timestamps immediately after render
            this.updateTimestamps();
        } catch (e) {
            console.error(e);
            this.conversationsListEl.innerHTML = '<div class="conv-error">Failed to load conversations. Please try again.</div>';
        }
    }

    renderConversationCard(c) {
        const preview = (c.last_message || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        const subject = (c.subject || 'General').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        const status = c.conversation_status || 'open';
        const statusClass = status !== 'open' ? `conversation-${status}` : '';
        const statusBadge = status !== 'open' ? `<span class="conv-status-badge status-${status}">${status.toUpperCase()}</span>` : '';
        
        return `
        <div class="conversation-card ${statusClass}" data-conv-id="${c.conversation_id}" data-status="${status}">
            <div class="conv-meta">
                <span class="conv-time" data-timestamp="${c.last_updated}">Last updated ${this.timeAgo(c.last_updated)}</span>
                ${statusBadge}
            </div>
            <div class="conv-subject">${subject}</div>
            <div class="conv-preview">${preview}</div>
            ${c.unread ? `<span class=\"conv-unread\" title=\"Unread admin replies\">${c.unread}</span>` : ''}
        </div>`;
    }

    async viewConversation(conversationId) {
        if (!this.conversationDetailEl) return;
        
        // Store current conversation ID for real-time updates
        this.currentConversationId = conversationId;
        console.log('Setting current conversation ID to:', conversationId);
        
        // Get conversation status from the card
        const conversationCard = document.querySelector(`[data-conv-id="${conversationId}"]`);
        const conversationStatus = conversationCard ? conversationCard.getAttribute('data-status') : 'open';
        
        // Hide the conversations list and show the detail view
        if (this.conversationsListEl) {
            this.conversationsListEl.style.display = 'none';
        }
        this.conversationDetailEl.style.display = 'block';
        this.conversationDetailEl.innerHTML = '<div class="conv-loading"><div class="loading-circle"></div> Loading messages...</div>';
        try {
            const url = `/Capstone2/api/customer_support.php?conversation_id=${encodeURIComponent(conversationId)}&last_message_id=0`;
            const res = await fetch(url);
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to load messages');
            const messages = data.data.messages || [];
            const list = messages.map(m => `
                <div class="message-row ${m.is_admin ? 'from-admin' : 'from-user'}">
                    <div class="message-header">
                        <span class="sender">${(m.sender_name||'').replace(/</g,'&lt;')}</span>
                        <span class="time" data-timestamp="${m.created_at}">${this.timeAgo(m.created_at)}</span>
                    </div>
                    <div class="message-body">${(m.message||'').replace(/</g,'&lt;').replace(/\n/g,'<br>')}</div>
                </div>
            `).join('');
            
            // Create status indicator for header
            const statusBadge = conversationStatus !== 'open' ? 
                `<span class="conv-detail-status status-${conversationStatus}">${conversationStatus.toUpperCase()}</span>` : '';
            
            // Create reply section or status message
            let replySection = '';
            if (conversationStatus === 'solved' || conversationStatus === 'closed') {
                replySection = `
                    <div class="conversation-status-message status-${conversationStatus}">
                        <div class="status-message-content">
                            <i class="fas fa-${conversationStatus === 'solved' ? 'check-circle' : 'lock'}"></i>
                            <span>This conversation has been marked as <strong>${conversationStatus}</strong> by our support team.</span>
                            <small>No new messages can be sent to this conversation.</small>
                        </div>
                    </div>
                `;
            } else {
                replySection = `
                    <div class="conversation-reply">
                        <textarea id="replyTextarea" class="reply-textarea" rows="3" placeholder="Write a reply..."></textarea>
                        <div class="reply-actions">
                            <button class="support-send-btn" id="sendReplyBtn"><i class="fas fa-paper-plane"></i> Send reply</button>
                        </div>
                    </div>
                `;
            }
            
            this.conversationDetailEl.innerHTML = `
                <div class="conversation-detail-header">
                    <button class="btn-back" id="backToList"><i class="fas fa-arrow-left"></i></button>
                    <div class="title">Conversation ${conversationId}</div>
                    ${statusBadge}
                </div>
                <div class="conversation-messages">${list || '<div class="conv-empty">No messages</div>'}</div>
                ${replySection}
            `;
            // Refresh timestamps immediately for the newly rendered view
            this.updateTimestamps();
            // Ensure we show the latest messages at the bottom
            const messagesEl = this.conversationDetailEl.querySelector('.conversation-messages');
            if (messagesEl) this.scrollToBottom(messagesEl);
            const backBtn = this.conversationDetailEl.querySelector('#backToList');
            if (backBtn) backBtn.addEventListener('click', () => {
                this.conversationDetailEl.style.display = 'none';
                // Show the conversations list again
                if (this.conversationsListEl) {
                    this.conversationsListEl.style.display = 'block';
                }
            });

            // Only bind reply events for open conversations
            if (conversationStatus === 'open') {
                const sendBtn = this.conversationDetailEl.querySelector('#sendReplyBtn');
                const replyTA = this.conversationDetailEl.querySelector('#replyTextarea');
                if (sendBtn && replyTA) {
                    sendBtn.addEventListener('click', async () => {
                        const text = replyTA.value.trim();
                        if (!text) return;
                        sendBtn.disabled = true;
                        sendBtn.innerHTML = '<div class="loading-circle" style="width: 16px; height: 16px; margin-right: 8px; display: inline-block;"></div> Sending...';
                        try {
                            await this.sendReply(conversationId, text);
                            replyTA.value = '';
                            // refresh messages
                            this.viewConversation(conversationId);
                        } catch (err) {
                            console.error(err);
                            sendBtn.innerHTML = '<i class="fas fa-triangle-exclamation"></i> Failed. Retry';
                            setTimeout(()=>{ sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send reply'; }, 1500);
                        } finally {
                            sendBtn.disabled = false;
                        }
                    });
                    // Ctrl+Enter to send
                    replyTA.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                            e.preventDefault();
                            sendBtn.click();
                        }
                    });
                }
            }
        } catch (e) {
            console.error(e);
            this.conversationDetailEl.innerHTML = '<div class="conv-error">Failed to load messages.</div>';
        }
    }

    async sendReply(conversationId, message) {
        const csrfToken = await this.getCSRFToken();
        const payload = {
            conversation_id: conversationId,
            subject: '',
            message,
            csrf_token: csrfToken
        };
        const res = await fetch('/Capstone2/api/customer_support.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Failed to send');
        return data;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('supportModal')) {
        window.supportMessaging = new SupportMessaging();
    }
});

// SupportMessaging class is now globally available

// --- Time handling helpers ---
SupportMessaging.prototype.timeAgo = function(datetime) {
    const now = new Date();
    const past = new Date(datetime);
    const diffInSeconds = Math.floor((now - past) / 1000);
    if (diffInSeconds < 60) return `${diffInSeconds}s ago`;
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
    if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`;
    if (diffInSeconds < 31536000) return `${Math.floor(diffInSeconds / 2592000)}mo ago`;
    return `${Math.floor(diffInSeconds / 31536000)}y ago`;
};

SupportMessaging.prototype.startTimestampUpdates = function() {
    if (this.timestampUpdateInterval) return;
    this.timestampUpdateInterval = setInterval(() => this.updateTimestamps(), 30000);
};

SupportMessaging.prototype.updateTimestamps = function() {
    // Update conversation cards
    document.querySelectorAll('.conv-time[data-timestamp]').forEach(el => {
        const ts = el.getAttribute('data-timestamp');
        if (ts) {
            el.textContent = `Last updated ${this.timeAgo(ts)}`;
        }
    });
    // Update message rows
    document.querySelectorAll('.message-row .time[data-timestamp]').forEach(el => {
        const ts = el.getAttribute('data-timestamp');
        if (ts) {
            el.textContent = this.timeAgo(ts);
        }
    });
};

// --- Scrolling helper ---
SupportMessaging.prototype.scrollToBottom = function(container) {
    if (!container) return;
    // Use rAF so the layout is settled
    requestAnimationFrame(() => {
        container.scrollTop = container.scrollHeight;
    });
};
