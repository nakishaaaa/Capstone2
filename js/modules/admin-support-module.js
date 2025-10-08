export class AdminSupportManager {
    constructor(toast, sseClient = null) {
        this.toast = toast;
        this.currentConversations = [];
        this.currentMessages = [];
        this.selectedConversationId = null;
        this.currentPage = 1;
        this.itemsPerPage = 20;
        this.currentFilters = {
            status: '',
            search: '',
            archive: 'active' // 'active', 'archived', 'all'
        };
        this.sseClient = sseClient;
        this.timestampUpdateInterval = null;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadConversations();
        this.initializeRealTimeUpdates();
        
        // Make functions globally available
        window.openSupportMessageModal = (messageId) => this.openMessageModal(messageId);
        window.closeSupportMessageModal = () => this.closeMessageModal();
        window.replyToMessage = (messageId) => this.replyToMessage(messageId);
        window.markAsRead = (messageId) => this.markAsRead(messageId);
    }
    
    bindEvents() {
        // Refresh button
        const refreshBtn = document.getElementById('refreshSupportBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.loadConversations());
        }
        
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
        
        // Mark all as read button removed
    }
    
    async loadConversations(preserveSearch = false) {
        try {
            const archiveFilter = this.currentFilters.archive || 'active';
            const response = await fetch(`/Capstone2/api/admin_support_messages.php?action=conversations&archive_filter=${archiveFilter}`);
            const result = await response.json();
            
            if (result.success) {
                this.currentConversations = result.data.conversations;
                this.updateConversationsList();
                this.updateSupportStats(result.data.stats);
                
                // Preserve search filter if requested
                if (preserveSearch) {
                    const searchInput = document.getElementById('conversationSearch');
                    if (searchInput && searchInput.value.trim()) {
                        this.filterConversations();
                    }
                }
                
                // Check for unread messages on initial load
                this.checkForUnreadMessages(result.data.stats);
            } else {
                console.error('Failed to load conversations:', result.message);
                this.showError('Failed to load conversations');
            }
        } catch (error) {
            console.error('Error loading conversations:', error);
            this.showError('Network error while loading conversations');
        }
    }

    async loadConversationMessages(conversationId) {
        try {
            const response = await fetch(`/Capstone2/api/admin_support_messages.php?action=conversation_messages&conversation_id=${encodeURIComponent(conversationId)}`);
            const result = await response.json();
            
            if (result.success) {
                this.currentMessages = result.data.messages;
                this.selectedConversationId = conversationId;
                this.updateChatMessages();
                this.showChatInterface();
            } else {
                console.error('Failed to load conversation messages:', result.message);
                this.showError('Failed to load conversation messages');
            }
        } catch (error) {
            console.error('Error loading conversation messages:', error);
            this.showError('Network error while loading messages');
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
            <div class="conversation-item ${conv.unread_count > 0 ? 'unread' : ''} ${conv.conversation_status !== 'open' ? 'conversation-' + conv.conversation_status : ''} ${isArchived ? 'archived' : ''}" 
                 data-conversation-id="${conv.conversation_id}" 
                 onclick="adminSupportModule.selectConversation('${conv.conversation_id}')">
                <div class="conversation-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="conversation-content">
                    <div class="conversation-header">
                        <div class="conversation-name">${this.escapeHtml(conv.user_name)}</div>
                        <div class="conversation-actions">
                            <button class="archive-btn" 
                                    onclick="event.stopPropagation(); adminSupportModule.archiveConversation('${conv.conversation_id}', '${isArchived ? 'unarchive' : 'archive'}')" 
                                    title="${isArchived ? 'Unarchive' : 'Archive'} conversation">
                                <i class="fas fa-${isArchived ? 'box-open' : 'archive'}"></i>
                            </button>
                            <div class="conversation-time">${this.timeAgo(conv.last_updated)}</div>
                        </div>
                    </div>
                    <div class="conversation-preview">
                        <div class="conversation-subject">${this.escapeHtml(conv.subject)}</div>
                        <div class="conversation-last-message">
                            ${conv.last_message_is_admin ? 'You: ' : ''}${this.truncateText(conv.last_message, 60)}
                        </div>
                    </div>
                    ${conv.conversation_status !== 'open' ? `<div class="conversation-status-badge status-${conv.conversation_status}">${conv.conversation_status.toUpperCase()}</div>` : ''}
                </div>
                ${conv.unread_count > 0 ? `<div class="conversation-unread-badge">${conv.unread_count}</div>` : ''}
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

    updateChatMessages() {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        if (this.currentMessages.length === 0) {
            chatMessages.innerHTML = '<div class="no-messages">No messages in this conversation</div>';
            return;
        }
        
        chatMessages.innerHTML = this.currentMessages.map(msg => `
            <div class="chat-message ${msg.is_admin ? 'admin-message' : 'user-message'}">
                <div class="message-avatar">
                    <i class="fas fa-${msg.is_admin ? 'user-shield' : 'user'}"></i>
                </div>
                <div class="message-content">
                    <div class="message-header">
                        ${msg.is_admin ? 
                            `<span class="message-time">${this.timeAgo(msg.created_at)}</span>
                             <span class="message-sender">${this.escapeHtml(msg.sender_name)}</span>` :
                            `<span class="message-sender">${this.escapeHtml(msg.sender_name)}</span>
                             <span class="message-time">${this.timeAgo(msg.created_at)}</span>`
                        }
                    </div>
                    <div class="message-text">${this.escapeHtml(msg.message).replace(/\n/g, '<br>')}</div>
                    ${msg.attachment_paths ? this.renderAttachments(msg.attachment_paths) : ''}
                </div>
            </div>
        `).join('');
        
        // Scroll to bottom
        this.scrollToBottom();
    }

    showChatInterface() {
        const chatHeader = document.getElementById('chatHeader');
        const chatInputArea = document.getElementById('chatInputArea');
        const chatUserName = document.getElementById('chatUserName');
        const chatUserEmail = document.getElementById('chatUserEmail');
        const chatSubject = document.getElementById('chatSubject');
        
        if (this.currentMessages.length > 0) {
            const firstMessage = this.currentMessages[0];
            chatUserName.textContent = firstMessage.sender_name;
            chatUserEmail.textContent = firstMessage.sender_email;
            
            // Extract and display subject (remove ticket ID prefix if present)
            if (firstMessage.subject) {
                let subject = firstMessage.subject;
                // Remove ticket ID prefix like [ANON-2025-5065] from subject
                subject = subject.replace(/^\[ANON-\d{4}-\d+\]\s*/, '');
                // Remove "Re: " prefix if present
                subject = subject.replace(/^Re:\s*/, '');
                // Clean up any extra whitespace
                subject = subject.trim();
                
                if (subject) {
                    chatSubject.textContent = `Subject: ${subject}`;
                    chatSubject.style.display = 'block';
                } else {
                    chatSubject.style.display = 'none';
                }
            } else {
                chatSubject.style.display = 'none';
            }
        }
        
        // Get current conversation status
        const currentConversation = this.currentConversations.find(c => c.conversation_id === this.selectedConversationId);
        const conversationStatus = currentConversation?.conversation_status || 'open';
        
        // Add status controls to chat header
        this.addStatusControls(conversationStatus);
        
        // Show/hide input area based on status
        if (conversationStatus === 'solved' || conversationStatus === 'closed') {
            chatInputArea.style.display = 'none';
            this.showStatusMessage(conversationStatus);
        } else {
            chatInputArea.style.display = 'block';
            this.hideStatusMessage();
        }
        
        chatHeader.style.display = 'flex';
    }
    
    updateSupportStats(stats) {
        if (!stats) return;
        
        const elements = {
            'total-messages': stats.total || 0,
            'unread-messages': stats.unread || 0,
            'replied-messages': stats.replied || 0,
            'active-conversations': stats.active_conversations || 0
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
    
    async openMessageModal(messageId) {
        const message = this.currentMessages.find(m => m.id == messageId);
        if (!message) return;
        
        // Populate modal with message details
        document.getElementById('modalCustomerName').textContent = message.user_name;
        document.getElementById('modalCustomerEmail').textContent = message.user_email;
        document.getElementById('modalMessageDate').textContent = this.formatDate(message.created_at);
        document.getElementById('modalSubject').textContent = this.truncateText(message.subject || 'No Subject', 100);
        document.getElementById('modalMessage').textContent = this.formatMessageText(message.message);
        
        const statusBadge = document.getElementById('modalMessageStatus');
        statusBadge.textContent = message.is_read ? 'Read' : 'Unread';
        statusBadge.className = `support-message-status ${message.is_read ? 'read' : 'unread'}`;
        
        // Store current message ID for response actions
        this.currentMessageId = messageId;
        
        // Show modal
        const modal = document.getElementById('supportMessageModal');
        if (modal) {
            modal.style.display = 'block';
            
            // Mark as read when opened
            if (!message.is_read) {
                this.markAsRead(messageId, false);
            }
        }
    }
    
    closeMessageModal() {
        const modal = document.getElementById('supportMessageModal');
        if (modal) {
            modal.style.display = 'none';
            document.getElementById('adminResponse').value = '';
            this.currentMessageId = null;
        }
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
            
            const response = await fetch('/Capstone2/api/admin_support_messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'reply_to_conversation',
                    conversation_id: this.selectedConversationId,
                    response: replyText,
                    csrf_token: csrfToken
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('adminReplyInput').value = '';
                this.loadConversationMessages(this.selectedConversationId); // Refresh messages
                this.loadConversations(true); // Refresh conversation list, preserving search
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
            
            const response = await fetch('/Capstone2/api/admin_support_messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'mark_conversation_read',
                    conversation_id: conversationId,
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
    
    async markAsRead(messageId, showNotification = true) {
        try {
            const csrfToken = await this.getCSRFToken();
            
            const response = await fetch('/Capstone2/api/admin_support_messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    message_id: messageId,
                    csrf_token: csrfToken
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (showNotification) {
                    this.showSuccess('Message marked as read');
                }
                this.loadSupportMessages(); // Refresh the list
            } else {
                this.showError(result.message || 'Failed to mark as read');
            }
        } catch (error) {
            console.error('Error marking as read:', error);
            this.showError('Network error');
        }
    }
    
    markCurrentAsRead() {
        if (this.currentMessageId) {
            this.markAsRead(this.currentMessageId);
        }
    }
    
    replyToMessage(messageId) {
        this.openMessageModal(messageId);
        // Focus on response textarea
        setTimeout(() => {
            const textarea = document.getElementById('adminResponse');
            if (textarea) {
                textarea.focus();
            }
        }, 300);
    }
    
    applyFilters() {
        this.currentFilters.status = document.getElementById('supportStatusFilter')?.value || '';
        this.currentFilters.search = document.getElementById('supportSearchInput')?.value || '';
        this.currentPage = 1; // Reset to first page
        this.loadSupportMessages();
    }
    
    clearFilters() {
        document.getElementById('supportStatusFilter').value = '';
        document.getElementById('supportSearchInput').value = '';
        this.currentFilters = { status: '', search: '' };
        this.currentPage = 1;
        this.loadSupportMessages();
    }

    initializeRealTimeUpdates() {
        try {
            // Initialize Pusher for real-time messaging
            this.pusher = new Pusher('5f2e092f1e11a34b880f', {
                cluster: 'ap1',
                encrypted: true
            });
            
            // Subscribe to support channel
            this.channel = this.pusher.subscribe('support-channel');
            
            // Handle new customer support messages (customer → admin)
            this.channel.bind('new-customer-support-message', (data) => {
                console.log('Admin Support: New customer message received', data);
                console.log('Admin Support: Message user_name:', data.user_name);
                console.log('Admin Support: Message conversation_id:', data.conversation_id);
                
                // Show browser notification
                this.showNotification('New Support Message', {
                    body: `${data.user_name}: ${data.message}`,
                    icon: '/favicon.ico',
                    tag: 'support-' + data.conversation_id
                });
                
                // Update badge count
                this.updateSupportBadge(1);
                
                // Force refresh conversations list to show updated conversation
                console.log('Admin Support: Refreshing conversations list for customer message');
                this.loadConversations();
                
                // If viewing the specific conversation, refresh its messages
                if (this.selectedConversationId === data.conversation_id) {
                    console.log('Admin Support: Refreshing current conversation messages');
                    this.loadConversationMessages(this.selectedConversationId).then(() => {
                        // Auto-scroll to bottom for new unread messages
                        this.scrollToBottom(true);
                    });
                }
            });
            
            // Handle new anonymous tickets
            this.channel.bind('new-anonymous-ticket', (data) => {
                console.log('Admin Support: New anonymous ticket received', data);
                
                // Show browser notification
                this.showNotification('New Anonymous Ticket', {
                    body: `${data.ticket_id}: ${data.message}`,
                    icon: '/favicon.ico',
                    tag: 'ticket-' + data.ticket_id
                });
                
                // Update badge count
                this.updateSupportBadge(1);
                
                // Force refresh conversations list to show new ticket immediately
                console.log('Admin Support: Refreshing conversations list for new ticket');
                this.loadConversations();
            });
            
            // Handle connection events
            this.pusher.connection.bind('connected', () => {
                console.log('Admin Support: Real-time connection established');
            });
            
            this.pusher.connection.bind('error', (err) => {
                console.error('Admin Support: Connection error', err);
                console.error('Admin Support: Error details:', JSON.stringify(err, null, 2));
                
                // Check for specific error codes
                if (err.data && err.data.code) {
                    console.error('Admin Support: Pusher error code:', err.data.code);
                    console.error('Admin Support: Pusher error message:', err.data.message);
                }
            });
            
            console.log('Admin Support: Pusher initialized successfully');
        } catch (error) {
            console.error('Admin Support: Failed to initialize Pusher:', error);
        }
        
        // Fallback: Use existing SSE client if available
        if (this.sseClient) {
            console.log('Admin Support: SSE client available as fallback');
            
            // Listen for heartbeat to update timestamps
            this.sseClient.on('heartbeat', (data) => {
                this.updateTimestamps();
            });
            
            // Listen for connection status
            this.sseClient.on('connection', (data) => {
                if (data.status === 'connected') {
                    console.log('Admin Support: SSE connection established');
                } else if (data.status === 'error') {
                    console.warn('Admin Support: Real-time connection error, using manual refresh');
                }
            });
        } else {
            console.warn('Admin Support: No SSE client available, using manual refresh only');
        }
        
        // Start timestamp update interval as fallback
        this.startTimestampUpdates();
    }

    startTimestampUpdates() {
        // Update timestamps every 30 seconds
        this.timestampUpdateInterval = setInterval(() => {
            this.updateTimestamps();
        }, 30000);
    }

    handleSupportMessagesUpdate(data) {
        console.log('Admin Support: Real-time support messages update:', data);
        console.log('Admin Support: Current selected conversation:', this.selectedConversationId);
        
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
            console.log('Admin Support: Refreshing conversation messages for:', this.selectedConversationId);
            this.loadConversationMessages(this.selectedConversationId);
        }
        
        // Also refresh conversations list if currently viewing support
        if (this.isCurrentlyViewingSupport()) {
            console.log('Admin Support: Refreshing conversations list');
            this.loadConversations();
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
        return activeSection && activeSection.getAttribute('data-section') === 'customersupport';
    }

    updateTimestamps() {
        // Update conversation timestamps
        document.querySelectorAll('.conversation-time').forEach(element => {
            const conversationId = element.closest('.conversation-item')?.dataset.conversationId;
            if (conversationId) {
                const conversation = this.currentConversations.find(c => c.conversation_id === conversationId);
                if (conversation && conversation.last_updated) {
                    element.textContent = this.timeAgo(conversation.last_updated);
                }
            }
        });

        // Update message timestamps
        document.querySelectorAll('.message-time').forEach(element => {
            const messageElement = element.closest('.chat-message');
            if (messageElement) {
                const messageIndex = Array.from(messageElement.parentNode.children).indexOf(messageElement);
                if (this.currentMessages[messageIndex] && this.currentMessages[messageIndex].created_at) {
                    element.textContent = this.timeAgo(this.currentMessages[messageIndex].created_at);
                }
            }
        });
    }
    
    checkForUnreadMessages(stats) {
        if (stats && stats.unread > 0 && this.toast) {
            const unreadCount = stats.unread;
            const message = unreadCount === 1 
                ? 'You have 1 unread message' 
                : `You have ${unreadCount} unread messages`;
            this.toast.info(message);
        }
    }

    handleActivityUpdate(activities) {
        // Check if there are new support messages
        const supportActivities = activities.filter(activity => 
            activity.type === 'support_message'
        );
        
        if (supportActivities.length > 0) {
            console.log('Admin Support: New support activity detected');
            // Refresh conversations to update unread counts and ordering, preserving search
            this.loadConversations(true);

            // If the current conversation received a new user message, refresh its messages view
            const hasCurrentConvUpdate = supportActivities.some(a => 
                a.type === 'support_message' && a.conversation_id && a.conversation_id === this.selectedConversationId
            );
            if (hasCurrentConvUpdate && this.selectedConversationId) {
                this.loadConversationMessages(this.selectedConversationId);
            }
        }
    }

    timeAgo(datetime) {
        const now = new Date();
        const past = new Date(datetime);
        const diffInSeconds = Math.floor((now - past) / 1000);
        
        if (diffInSeconds < 60) return `${diffInSeconds}s ago`;
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
        if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`;
        if (diffInSeconds < 31536000) return `${Math.floor(diffInSeconds / 2592000)}mo ago`;
        return `${Math.floor(diffInSeconds / 31536000)}y ago`;
    }

    addStatusControls(currentStatus) {
        const chatHeader = document.getElementById('chatHeader');
        if (!chatHeader) return;
        
        // Remove existing status controls
        const existingControls = chatHeader.querySelector('.status-controls');
        if (existingControls) {
            existingControls.remove();
        }
        
        // Create status controls
        const statusControls = document.createElement('div');
        statusControls.className = 'status-controls';
        statusControls.innerHTML = `
            <div class="status-dropdown">
                <label for="conversationStatus">Status:</label>
                <select id="conversationStatus" onchange="adminSupportModule.updateConversationStatus(this.value)">
                    <option value="open" ${currentStatus === 'open' ? 'selected' : ''}>Open</option>
                    <option value="solved" ${currentStatus === 'solved' ? 'selected' : ''}>Solved</option>
                    <option value="closed" ${currentStatus === 'closed' ? 'selected' : ''}>Closed</option>
                </select>
            </div>
        `;
        
        chatHeader.appendChild(statusControls);
    }
    
    showStatusMessage(status) {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        // Remove existing status message
        const existingMessage = chatMessages.querySelector('.status-message');
        if (existingMessage) {
            existingMessage.remove();
        }
        
        // Add status message
        const statusMessage = document.createElement('div');
        statusMessage.className = `status-message status-${status}`;
        statusMessage.innerHTML = `
            <div class="status-message-content">
                <i class="fas fa-${status === 'solved' ? 'check-circle' : 'lock'}"></i>
                <span>This conversation has been marked as <strong>${status}</strong>. No new messages can be sent.</span>
                <button onclick="adminSupportModule.updateConversationStatus('open')" class="reopen-btn">
                    <i class="fas fa-unlock"></i> Reopen Conversation
                </button>
            </div>
        `;
        
        chatMessages.appendChild(statusMessage);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    hideStatusMessage() {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        const existingMessage = chatMessages.querySelector('.status-message');
        if (existingMessage) {
            existingMessage.remove();
        }
    }
    
    async updateConversationStatus(newStatus) {
        if (!this.selectedConversationId) {
            this.showError('No conversation selected');
            return;
        }
        
        try {
            const csrfToken = await this.getCSRFToken();
            
            const response = await fetch('/Capstone2/api/admin_support_messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'update_conversation_status',
                    conversation_id: this.selectedConversationId,
                    status: newStatus,
                    csrf_token: csrfToken
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess(`Conversation status updated to ${newStatus}`);
                
                // Refresh conversations and current chat
                await this.loadConversations(true);
                await this.loadConversationMessages(this.selectedConversationId);
                
                // Update UI based on new status
                if (newStatus === 'solved' || newStatus === 'closed') {
                    document.getElementById('chatInputArea').style.display = 'none';
                    this.showStatusMessage(newStatus);
                } else {
                    document.getElementById('chatInputArea').style.display = 'block';
                    this.hideStatusMessage();
                }
                
            } else {
                this.showError(result.message || 'Failed to update conversation status');
            }
        } catch (error) {
            console.error('Error updating conversation status:', error);
            this.showError('Network error while updating status');
        }
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
            
            // Fallback: get from hidden input
            if (!csrfToken) {
                const hiddenInput = document.querySelector('input[name="csrf_token"]');
                if (hiddenInput) {
                    csrfToken = hiddenInput.value;
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

    destroy() {
        // Clean up SSE event listeners (don't close the shared client)
        if (this.sseClient) {
            this.sseClient.off('heartbeat');
            this.sseClient.off('activity_update');
            this.sseClient.off('connection');
        }
        
        // Clear timestamp update interval
        if (this.timestampUpdateInterval) {
            clearInterval(this.timestampUpdateInterval);
            this.timestampUpdateInterval = null;
        }
        
        console.log('Admin Support module destroyed');
    }
    
    // Utility functions
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }
    
    truncateText(text, maxLength) {
        if (!text || text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    }
    
    formatMessageText(text) {
        if (!text) return 'No message content';
        
        // Limit to 500 characters for display
        const maxLength = 500;
        let formattedText = text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
        
        // Replace multiple spaces/tabs with single space
        formattedText = formattedText.replace(/\s+/g, ' ');
        
        // Ensure proper line breaks for readability
        formattedText = formattedText.replace(/(.{80})/g, '$1\n');
        
        return formattedText.trim();
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    renderAttachments(attachmentPaths) {
        if (!attachmentPaths) return '';
        
        let paths;
        try {
            // Try to parse as JSON array first
            paths = JSON.parse(attachmentPaths);
            if (!Array.isArray(paths)) {
                // If it's not an array, treat as single path (backward compatibility)
                paths = [attachmentPaths];
            }
        } catch (e) {
            // If parsing fails, treat as single path (backward compatibility)
            paths = [attachmentPaths];
        }
        
        if (paths.length === 0) return '';
        
        const attachmentHtml = paths.map(path => this.renderSingleAttachment(path)).join('');
        
        return `
            <div class="message-attachments">
                <div class="attachments-header">
                    <i class="fas fa-images"></i>
                    <span class="attachments-count">${paths.length} image${paths.length > 1 ? 's' : ''}</span>
                </div>
                <div class="attachments-grid">
                    ${attachmentHtml}
                </div>
            </div>
        `;
    }
    
    renderSingleAttachment(attachmentPath) {
        if (!attachmentPath) return '';
        
        const fileName = attachmentPath.split('/').pop();
        const fileExtension = fileName.split('.').pop().toLowerCase();
        const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExtension);
        const fullPath = `/Capstone2/${attachmentPath}`;
        
        if (isImage) {
            return `
                <div class="attachment-item">
                    <div class="attachment-preview">
                        <img src="${fullPath}" alt="${this.escapeHtml(fileName)}" 
                             onclick="window.open('${fullPath}', '_blank')" 
                             title="${this.escapeHtml(fileName)}">
                    </div>
                </div>
            `;
        } else {
            // For non-image files (backward compatibility)
            const icon = this.getFileIcon(fileExtension);
            return `
                <div class="attachment-item">
                    <div class="attachment-download">
                        <a href="${fullPath}" target="_blank" class="attachment-link">
                            <i class="fas ${icon}"></i>
                            <span>${this.escapeHtml(fileName)}</span>
                        </a>
                    </div>
                </div>
            `;
        }
    }
    
    getFileIcon(extension) {
        const iconMap = {
            'pdf': 'fa-file-pdf',
            'doc': 'fa-file-word',
            'docx': 'fa-file-word',
            'txt': 'fa-file-text',
            'zip': 'fa-file-archive',
            'rar': 'fa-file-archive'
        };
        return iconMap[extension] || 'fa-file';
    }
    
    scrollToBottom(smooth = false) {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        if (smooth) {
            // Smooth scroll for new messages
            chatMessages.scrollTo({
                top: chatMessages.scrollHeight,
                behavior: 'smooth'
            });
        } else {
            // Instant scroll for initial load
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
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
            const response = await fetch('/Capstone2/api/csrf_token.php');
            const data = await response.json();
            return data.token;
        } catch (error) {
            console.error('Error getting CSRF token:', error);
            throw new Error('Failed to get CSRF token');
        }
    }
    
    showError(message) {
        // You can integrate with your existing toast system
        console.error('Support Error:', message);
        alert('Error: ' + message); // Temporary - replace with proper notification
    }
    
    showSuccess(message) {
        // You can integrate with your existing toast system
        console.log('Support Success:', message);
        // Temporary success indication - replace with proper notification
    }
}

export default AdminSupportManager;

// Helper to update the sidebar support badge
AdminSupportManager.prototype.updateSupportBadge = function(count) {
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
