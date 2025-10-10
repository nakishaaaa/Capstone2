// Super Admin Support Module - Conversation System (matches Admin Support)
import { SSEClient } from '../core/sse-client.js';

export class SupportModule {
    constructor(dashboard) {
        this.dashboard = dashboard;
        this.currentConversations = [];
        this.currentMessages = [];
        this.selectedConversationId = null;
        this.timestampUpdateInterval = null;
        this.sseClient = null;
        this.currentFilters = {
            archive: 'active' // 'active', 'archived', 'all'
        };
        
        // Make functions globally available
        window.superAdminSupportModule = this;
        
        // Initialize badge count on dashboard load
        this.initializeBadge();
        
        // Initialize SSE for real-time updates
        this.initializeSSE();
    }

    async initializeBadge() {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php?action=get_support_conversations');
            const result = await response.json();
            
            if (result.success && result.data && result.data.stats) {
                this.updateSupportBadge(result.data.stats.unread || 0);
            }
        } catch (error) {
            console.error('Error initializing support badge:', error);
        }
    }

    initializeSSE() {
        try {
            // Initialize Pusher for real-time messaging
            this.pusher = new Pusher('5f2e092f1e11a34b880f', {
                cluster: 'ap1',
                encrypted: true
            });
            
            // Subscribe to support channel
            this.channel = this.pusher.subscribe('support-channel');
            
            // Handle new customer messages
            this.channel.bind('new-customer-message', (data) => {
                console.log('Support: New customer message received', data);
                this.handleRealTimeSupportUpdate({
                    messages: [data],
                    unread_count: 1
                });
            });
            
            // Handle connection events
            this.pusher.connection.bind('connected', () => {
                console.log('Support: Real-time connection established');
            });
            
            this.pusher.connection.bind('error', (err) => {
                console.error('Support: Connection error', err);
            });

            console.log('Support: Pusher initialized successfully');
        } catch (error) {
            console.error('Support: Failed to initialize Pusher:', error);
        }
    }

    handleRealTimeSupportUpdate(data) {
        console.log('Support: Real-time update received:', data);
        console.log('Support: Currently viewing support?', this.isCurrentlyViewingSupport());
        console.log('Support: Selected conversation ID:', this.selectedConversationId);
        console.log('Support: Message conversation ID:', data.messages?.[0]?.conversation_id);
        
        // Update badge count
        if (data.unread_count !== undefined) {
            this.updateSupportBadge(data.unread_count);
        } 

        // Show browser notification for new messages
        if (data.messages && data.messages.length > 0) {
            data.messages.forEach(msg => {
                this.showNotification('New Support Message', {
                    body: `${msg.user_name}: ${msg.message}`,
                    icon: '/favicon.ico',
                    tag: 'support-' + msg.conversation_id
                });
            });
        }

        // If viewing a specific conversation, refresh its messages immediately
        if (this.selectedConversationId) {
            console.log('Support: Refreshing conversation messages for:', this.selectedConversationId);
            this.loadConversationMessages(this.selectedConversationId);
        }
        
        // Also refresh conversations list if currently viewing support section
        if (this.isCurrentlyViewingSupport()) {
            console.log('Support: Refreshing conversations list');
            this.loadConversations(true);
        }
    }
    
    // Method to refresh messages (called by real-time updates)
    refreshMessages() {
        console.log('Support: Refreshing messages due to real-time update');
        
        // If viewing a specific conversation, refresh its messages
        if (this.selectedConversationId && this.isCurrentlyViewingSupport()) {
            console.log('Support: Refreshing conversation:', this.selectedConversationId);
            this.loadConversationMessages(this.selectedConversationId);
        }
        
        // Also refresh conversations list to update unread counts
        if (this.isCurrentlyViewingSupport()) {
            this.loadConversations(true);
        }
    }

    showNotification(title, options = {}) {
        if ('Notification' in window) {
            if (Notification.permission === 'granted') {
                new Notification(title, options);
            } else if (Notification.permission !== 'denied') {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        new Notification(title, options);
                    }
                });
            }
        }
    }

    isCurrentlyViewingSupport() {
        const activeSection = document.querySelector('.nav-link.active');
        return activeSection && activeSection.getAttribute('data-section') === 'customer-support';
    }

    loadCustomerSupport(container) {
        container.innerHTML = `
            <div class="admin-support-container">
                <div class="conversations-sidebar">
                    <div class="conversations-header">
                        <h3>Conversations</h3>
                        <select id="archiveFilter" class="filter-select" title="Filter conversations">
                            <option value="active">Active Conversations</option>
                            <option value="archived">Archived Conversations</option>
                            <option value="all">All Conversations</option>
                        </select>
                    </div>
                    <div class="search-container">
                        <input type="text" id="conversationSearch" placeholder="Search conversations..." class="search-input">
                    </div>
                    <div id="conversationsList" class="conversations-list">
                        <div class="loading">Loading conversations...</div>
                    </div>
                </div>

                <div class="chat-main">
                    <div id="chatHeader" class="chat-header" style="display: none;">
                        <div class="chat-user-info">
                            <div class="chat-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="chat-user-details">
                                <div id="chatUserName" class="chat-user-name">Select a conversation</div>
                                <div id="chatUserEmail" class="chat-user-email"></div>
                                <div id="chatSubject" class="chat-subject" style="font-size: 0.85rem; color: #333; margin-top: 2px; font-weight: 600; font-style: normal;">No subject</div>
                            </div>
                        </div>
                        <div class="chat-status-controls">
                            <label for="ticketStatus" class="status-label">Status:</label>
                            <select id="ticketStatus" class="status-dropdown">
                                <option value="open">Open</option>
                                <option value="pending">Pending</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                    </div>

                    <div id="chatMessages" class="chat-messages-container">
                        <div class="no-conversation-selected">
                            <i class="fas fa-comments"></i>
                            <p>Select a conversation to start chatting</p>
                        </div>
                    </div>

                    <div id="chatInputArea" class="chat-input-section" style="display: none;">
                        <div class="message-input-container">
                            <textarea id="adminReplyInput" placeholder="Type your reply..." rows="2"></textarea>
                            <button id="sendReplyBtn" class="send-message-btn">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadConversations();
        this.startTimestampUpdates();
    }

    bindEvents() {
        // Archive filter dropdown
        const archiveFilter = document.getElementById('archiveFilter');
        if (archiveFilter) {
            archiveFilter.addEventListener('change', (e) => {
                this.currentFilters.archive = e.target.value;
                this.loadConversations();
            });
        }
        
        // Conversation search
        const conversationSearch = document.getElementById('conversationSearch');
        if (conversationSearch) {
            conversationSearch.addEventListener('input', this.debounce(() => this.filterConversations(), 300));
        }

        // Chat input and send button
        const sendReplyBtn = document.getElementById('sendReplyBtn');
        const adminReplyInput = document.getElementById('adminReplyInput');
        
        if (sendReplyBtn) {
            sendReplyBtn.addEventListener('click', () => this.sendReply());
        }
        
        if (adminReplyInput) {
            adminReplyInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    this.sendReply();
                }
            });
        }
        
        // Status dropdown change event
        const ticketStatus = document.getElementById('ticketStatus');
        if (ticketStatus) {
            ticketStatus.addEventListener('change', (e) => this.updateTicketStatus(e.target.value));
        }
    }

    async loadConversations(preserveSearch = false) {
        try {
            const archiveFilter = this.currentFilters.archive || 'active';
            const response = await fetch(`api/superadmin_api/super_admin_actions.php?action=get_support_conversations&archive_filter=${archiveFilter}`);
            const result = await response.json();
            
            if (result.success) {
                this.currentConversations = result.data.conversations || [];
                this.updateConversationsList();
                this.updateSupportStats(result.data.stats);
                
                // Preserve search filter if requested
                if (preserveSearch) {
                    const searchInput = document.getElementById('conversationSearch');
                    if (searchInput && searchInput.value.trim()) {
                        this.filterConversations();
                    }
                }
            } else {
                console.error('Failed to load conversations:', result.message);
                this.showError('Failed to load conversations');
            }
        } catch (error) {
            console.error('Error loading conversations:', error);
            this.showError('Network error while loading conversations');
        }
    }

    updateConversationsList() {
        const conversationsList = document.getElementById('conversationsList');
        if (!conversationsList) return;
        
        if (this.currentConversations.length === 0) {
            conversationsList.innerHTML = '<div class="no-conversations">No conversations found</div>';
            return;
        }
        
        conversationsList.innerHTML = this.currentConversations.map(conv => {
            const isArchived = conv.archived == 1;
            return `
            <div class="conversation-item ${conv.unread_count > 0 ? 'unread' : ''} ${this.selectedConversationId === conv.ticket_id ? 'active' : ''} ${isArchived ? 'archived' : ''}" 
                 data-conversation-id="${conv.ticket_id}" 
                 onclick="superAdminSupportModule.selectConversation('${conv.ticket_id}')">
                <div class="conversation-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="conversation-info">
                    <div class="conversation-header">
                        <div class="conversation-name">${this.escapeHtml(conv.username)}</div>
                        <div class="conversation-actions">
                            <button class="archive-btn" 
                                    onclick="event.stopPropagation(); superAdminSupportModule.archiveConversation('${conv.ticket_id}', '${isArchived ? 'unarchive' : 'archive'}')" 
                                    title="${isArchived ? 'Unarchive' : 'Archive'} conversation">
                                <i class="fas fa-${isArchived ? 'box-open' : 'archive'}"></i>
                            </button>
                            <div class="conversation-ticket-id">#${conv.ticket_id}</div>
                        </div>
                    </div>
                    <div class="conversation-subject">${this.escapeHtml(conv.subject)}</div>
                    <div class="conversation-preview">
                        ${conv.last_message_by === 'admin' ? 'You: ' : ''}${this.truncateText(conv.last_message || conv.message, 50)}
                    </div>
                </div>
                <div class="conversation-time">${this.formatTimeAgo(conv.last_message_at || conv.created_at)}</div>
            </div>
            `;
        }).join('');
    }

    async selectConversation(conversationId) {
        this.selectedConversationId = conversationId;
        
        // Update UI to show selected conversation
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.classList.remove('active');
        });
        
        const selectedItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
        if (selectedItem) {
            selectedItem.classList.add('active');
        }
        
        // Load messages for this conversation
        await this.loadConversationMessages(conversationId);
        
        // Automatically mark conversation as read
        await this.markConversationAsRead(conversationId);
    }

    async loadConversationMessages(conversationId) {
        try {
            const response = await fetch(`api/superadmin_api/super_admin_actions.php?action=get_conversation_messages&ticket_id=${encodeURIComponent(conversationId)}`);
            const result = await response.json();
            
            if (result.success) {
                this.currentMessages = result.data.messages || [];
                this.updateChatMessages();
                this.showChatInterface(result.data.ticket);
            } else {
                console.error('Failed to load conversation messages:', result.message);
                this.showError('Failed to load conversation messages');
            }
        } catch (error) {
            console.error('Error loading conversation messages:', error);
            this.showError('Network error while loading messages');
        }
    }

    updateChatMessages() {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        if (this.currentMessages.length === 0) {
            chatMessages.innerHTML = '<div class="no-messages">No messages in this conversation</div>';
            return;
        }
        
        chatMessages.innerHTML = this.currentMessages.map(msg => {
            const isAdmin = msg.sender_type === 'admin';
            return `
                <div class="message-group ${isAdmin ? 'admin-group' : 'customer-group'}">
                    ${!isAdmin ? `<div class="customer-avatar">
                        <i class="fas fa-user"></i>
                    </div>` : ''}
                    <div class="message-bubble ${isAdmin ? 'admin-bubble' : 'customer-bubble'}">
                        <div class="bubble-header">
                            ${isAdmin ? 
                                `<span class="bubble-time">${this.formatTimeAgo(msg.created_at)}</span>
                                 <span class="bubble-sender">${this.escapeHtml(msg.sender_name)}</span>` :
                                `<span class="bubble-sender">${this.escapeHtml(msg.sender_name)}</span>
                                 <span class="bubble-time">${this.formatTimeAgo(msg.created_at)}</span>`
                            }
                        </div>
                        <div class="message-text">${this.escapeHtml(msg.message).replace(/\n/g, '<br>')}</div>
                        ${msg.attachment_path ? this.renderAttachment(msg.attachment_path) : ''}
                    </div>
                    ${isAdmin ? `<div class="admin-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>` : ''}
                </div>
            `;
        }).join('');
        
        // Scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    showChatInterface(ticket) {
        const chatHeader = document.getElementById('chatHeader');
        const chatInputArea = document.getElementById('chatInputArea');
        const chatUserName = document.getElementById('chatUserName');
        const chatUserEmail = document.getElementById('chatUserEmail');
        const chatSubject = document.getElementById('chatSubject');
        const ticketStatus = document.getElementById('ticketStatus');
        
        if (ticket) {
            if (chatUserName) chatUserName.textContent = ticket.username;
            if (chatUserEmail) chatUserEmail.textContent = ticket.customer_email || 'No email';
            
            // Extract and display subject (remove ticket ID prefix if present)
            if (ticket.subject) {
                let subject = ticket.subject;
                // Remove ticket ID prefix like [ANON-2025-5065] from subject
                subject = subject.replace(/^\[ANON-\d{4}-\d+\]\s*/, '');
                // Remove "Re: " prefix if present
                subject = subject.replace(/^Re:\s*/, '');
                // Clean up any extra whitespace
                subject = subject.trim();
                
                if (subject && chatSubject) {
                    chatSubject.textContent = `Subject: ${subject}`;
                    chatSubject.style.display = 'block';
                } else if (chatSubject) {
                    chatSubject.style.display = 'none';
                }
            } else if (chatSubject) {
                chatSubject.style.display = 'none';
            }
            
            // Set the status dropdown value
            if (ticketStatus) {
                ticketStatus.value = ticket.status || 'open';
            }
            
            // Show/hide input area based on status
            const conversationStatus = ticket.status || 'open';
            if (conversationStatus === 'resolved' || conversationStatus === 'closed') {
                if (chatInputArea) chatInputArea.style.display = 'none';
                this.showStatusMessage(conversationStatus);
            } else {
                if (chatInputArea) chatInputArea.style.display = 'block';
                this.hideStatusMessage();
            }
        }
        
        if (chatHeader) chatHeader.style.display = 'flex';
    }

    async sendReply() {
        const replyText = document.getElementById('adminReplyInput').value.trim();
        if (!replyText) {
            this.showError('Please enter a reply message');
            return;
        }
        
        if (!this.selectedConversationId) {
            this.showError('No conversation selected');
            return;
        }
        
        try {
            const csrfToken = await this.getCSRFToken();
            
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'send_conversation_reply',
                    ticket_id: this.selectedConversationId,
                    message: replyText,
                    csrf_token: csrfToken
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('adminReplyInput').value = '';
                this.loadConversationMessages(this.selectedConversationId); // Refresh messages
                this.loadConversations(true); // Refresh conversation list, preserving search
                this.showSuccess('Reply sent successfully');
            } else {
                this.showError(result.message || 'Failed to send reply');
            }
        } catch (error) {
            console.error('Error sending reply:', error);
            this.showError('Network error while sending reply');
        }
    }

    filterConversations() {
        const searchTerm = document.getElementById('conversationSearch').value.toLowerCase();
        const conversationItems = document.querySelectorAll('.conversation-item');
        
        conversationItems.forEach(item => {
            const name = item.querySelector('.conversation-name').textContent.toLowerCase();
            const subject = item.querySelector('.conversation-subject').textContent.toLowerCase();
            const message = item.querySelector('.conversation-last-message').textContent.toLowerCase();
            
            if (name.includes(searchTerm) || subject.includes(searchTerm) || message.includes(searchTerm)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    async markConversationAsRead(conversationId) {
        if (!conversationId) return;
        
        try {
            const csrfToken = await this.getCSRFToken();
            
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'mark_conversation_read',
                    ticket_id: conversationId,
                    csrf_token: csrfToken
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Update the conversation item UI to remove unread indicator
                const conversationItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
                if (conversationItem) {
                    conversationItem.classList.remove('unread');
                    const unreadBadge = conversationItem.querySelector('.conversation-unread-badge');
                    if (unreadBadge) {
                        unreadBadge.remove();
                    }
                }
                
                // Refresh conversation list to update counts, then restore active state
                await this.loadConversations(true);
                this.restoreActiveConversation();
            }
        } catch (error) {
            console.error('Error marking conversation as read:', error);
        }
    }

    restoreActiveConversation() {
        if (this.selectedConversationId) {
            const selectedItem = document.querySelector(`[data-conversation-id="${this.selectedConversationId}"]`);
            if (selectedItem) {
                selectedItem.classList.add('active');
            }
        }
    }

    updateSupportStats(stats) {
        if (!stats) return;
        
        const elements = {
            'total-messages': stats.total || 0,
            'unread-messages': stats.unread || 0,
            'replied-messages': stats.replied || 0,
            'active-conversations': stats.active || 0
        };
        
        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });

        // Update sidebar badge for unread support messages
        this.updateSupportBadge(stats.unread || 0);
    }

    startTimestampUpdates() {
        // Update timestamps every 30 seconds
        this.timestampUpdateInterval = setInterval(() => {
            this.updateTimestamps();
        }, 30000);
    }

    updateTimestamps() {
        // Update conversation timestamps
        document.querySelectorAll('.conversation-time').forEach(element => {
            const conversationId = element.closest('.conversation-item')?.dataset.conversationId;
            if (conversationId) {
                const conversation = this.currentConversations.find(c => c.ticket_id === conversationId);
                if (conversation && (conversation.last_message_at || conversation.created_at)) {
                    element.textContent = this.formatTimeAgo(conversation.last_message_at || conversation.created_at);
                }
            }
        });

        // Update message timestamps
        document.querySelectorAll('.message-time').forEach(element => {
            const messageElement = element.closest('.chat-message');
            if (messageElement) {
                const messageIndex = Array.from(messageElement.parentNode.children).indexOf(messageElement);
                if (this.currentMessages[messageIndex] && this.currentMessages[messageIndex].created_at) {
                    element.textContent = this.formatTimeAgo(this.currentMessages[messageIndex].created_at);
                }
            }
        });
    }

    renderAttachment(attachmentPath) {
        if (!attachmentPath) return '';
        
        const fileName = attachmentPath.split('/').pop();
        const fullPath = `/${attachmentPath}`;
        
        return `
            <div class="message-attachment">
                <i class="fas fa-paperclip"></i>
                <a href="${fullPath}" target="_blank">${this.escapeHtml(fileName)}</a>
            </div>
        `;
    }

    showStatusMessage(status) {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        // Remove existing status message if any
        const existingMessage = chatMessages.querySelector('.conversation-status-message');
        if (existingMessage) {
            existingMessage.remove();
        }
        
        const statusMessage = document.createElement('div');
        statusMessage.className = 'conversation-status-message';
        statusMessage.style.cssText = `
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin: 16px;
            text-align: center;
            color: #6b7280;
        `;
        
        statusMessage.innerHTML = `
            <i class="fas fa-${status === 'resolved' ? 'check-circle' : 'lock'}" style="font-size: 24px; margin-bottom: 8px; display: block;"></i>
            <strong>Conversation ${status === 'resolved' ? 'Resolved' : 'Closed'}</strong><br>
            <small>${status === 'resolved' ? 'This conversation has been marked as resolved.' : 'This conversation has been closed.'}</small><br>
            <small>Change status to "Open" to reopen and continue the conversation.</small>
        `;
        
        chatMessages.appendChild(statusMessage);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    hideStatusMessage() {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        const existingMessage = chatMessages.querySelector('.conversation-status-message');
        if (existingMessage) {
            existingMessage.remove();
        }
    }

    formatTimeAgo(dateString) {
        const now = new Date();
        const date = new Date(dateString);
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) return `${diffInSeconds}s ago`;
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
        if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`;
        if (diffInSeconds < 31536000) return `${Math.floor(diffInSeconds / 2592000)}mo ago`;
        return `${Math.floor(diffInSeconds / 31536000)}y ago`;
    }

    truncateText(text, maxLength) {
        if (!text || text.length <= maxLength) return text || '';
        return text.substring(0, maxLength) + '...';
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    async getCSRFToken() {
        try {
            const response = await fetch('api/csrf_token.php');
            const data = await response.json();
            return data.token;
        } catch (error) {
            console.error('Error getting CSRF token:', error);
            throw new Error('Failed to get CSRF token');
        }
    }

    showError(message) {
        console.error('Support Error:', message);
        if (this.dashboard && this.dashboard.showNotification) {
            this.dashboard.showNotification(message, 'error');
        } else {
            alert('Error: ' + message);
        }
    }

    showSuccess(message) {
        console.log('Support Success:', message);
        if (this.dashboard && this.dashboard.showNotification) {
            this.dashboard.showNotification(message, 'success');
        }
    }

    updateSupportBadge(count) {
        try {
            const badge = document.getElementById('supportBadge');
            if (!badge) return;
            const value = Number(count) || 0;
            if (value > 0) {
                badge.textContent = value > 99 ? '99+' : String(value);
                badge.style.display = 'inline-block';
            } else {
                badge.textContent = '0';
                badge.style.display = 'none';
            }
        } catch (e) {
            console.warn('Failed to update support badge', e);
        }
    }

    async updateTicketStatus(newStatus) {
        if (!this.selectedConversationId) {
            this.showError('No conversation selected');
            return;
        }

        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'update_ticket_status',
                    conversation_id: this.selectedConversationId,
                    status: newStatus
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showSuccess(`Ticket status updated to: ${newStatus.replace('_', ' ')}`);
                // Update the conversation in the list
                const conversation = this.currentConversations.find(c => c.ticket_id === this.selectedConversationId);
                if (conversation) {
                    conversation.status = newStatus;
                }
                this.updateConversationsList();
                
                // Update UI based on new status
                const chatInputArea = document.getElementById('chatInputArea');
                if (newStatus === 'resolved' || newStatus === 'closed') {
                    if (chatInputArea) chatInputArea.style.display = 'none';
                    this.showStatusMessage(newStatus);
                } else {
                    if (chatInputArea) chatInputArea.style.display = 'block';
                    this.hideStatusMessage();
                }
            } else {
                this.showError(result.message || 'Failed to update ticket status');
                // Reset dropdown to previous value
                this.resetStatusDropdown();
            }
        } catch (error) {
            console.error('Error updating ticket status:', error);
            this.showError('Network error while updating status');
            this.resetStatusDropdown();
        }
    }

    resetStatusDropdown() {
        const ticketStatus = document.getElementById('ticketStatus');
        const conversation = this.currentConversations.find(c => c.ticket_id === this.selectedConversationId);
        if (ticketStatus && conversation) {
            ticketStatus.value = conversation.status || 'open';
        }
    }

    destroy() {
        // Clear timestamp update interval
        if (this.timestampUpdateInterval) {
            clearInterval(this.timestampUpdateInterval);
            this.timestampUpdateInterval = null;
        }
        
        console.log('Super Admin Support module destroyed');
    }

    // Archive/Unarchive conversation methods
    async archiveConversation(conversationId, action = 'archive') {
        try {
            // Get CSRF token from meta tag or csrfService
            let csrfToken = null;
            
            // Try to get from csrfService first
            if (window.csrfService && typeof window.csrfService.getToken === 'function') {
                csrfToken = window.csrfService.getToken();
            }
            
            // Fallback: get from meta tag
            if (!csrfToken) {
                const metaTag = document.querySelector('meta[name="csrf-token"]');
                if (metaTag) {
                    csrfToken = metaTag.getAttribute('content');
                }
            }
            
            if (!csrfToken) {
                throw new Error('CSRF token not available. Please refresh the page.');
            }

            const response = await fetch('/Capstone2/api/archive_conversation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    conversation_id: conversationId,
                    action: action,
                    csrf_token: csrfToken
                })
            });

            const data = await response.json();
            
            if (data.success) {
                const actionText = action === 'archive' ? 'archived' : 'unarchived';
                console.log(`Conversation ${actionText} successfully`);
                
                // Refresh conversations list
                this.loadConversations();
                
                // Clear selected conversation if it was archived
                if (action === 'archive' && this.selectedConversationId === conversationId) {
                    this.selectedConversationId = null;
                }
                
                return true;
            } else {
                throw new Error(data.message || 'Archive operation failed');
            }
        } catch (error) {
            console.error('Archive conversation error:', error);
            return false;
        }
    }
}

// Make it globally accessible
window.superAdminSupportModule = null;

export default SupportModule;
