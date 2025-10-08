/**
 * User Support Tickets Module
 * Handles viewing support tickets and admin replies for customers
 */

export default class UserSupportTicketsModule {
    constructor(sseClient = null) {
        this.tickets = [];
        this.currentTicket = null;
        this.sseClient = sseClient;
        
        // Register global functions immediately
        window.openSupportTicketsModal = () => this.openModal();
        window.closeSupportTicketsModal = () => this.closeModal();
        window.viewTicketDetails = (ticketId) => this.viewTicketDetails(ticketId);
        window.closeTicketDetailsModal = () => this.closeTicketDetailsModal();
        
        // Make instance available globally for back button
        window.userSupportTickets = this;
    }

    // Method to refresh tickets (called by real-time updates)
    refreshTickets() {
        console.log('Support Tickets: Refreshing tickets due to real-time update');
        
        // If tickets modal is open, refresh the tickets list
        const modal = document.getElementById('supportTicketsModal');
        if (modal && modal.style.display !== 'none') {
            this.loadTickets();
        }
        
        // If viewing a specific ticket, refresh its details
        if (this.currentTicket) {
            this.viewTicketDetails(this.currentTicket.conversation_id, false); // false = don't show modal again
        }
    }

    init() {
        this.createTicketsModal();
        this.bindEvents();
        this.initializeRealtimeUpdates();
    }

    createTicketsModal() {
        const modalHTML = `
            <!-- Support Tickets Modal -->
            <div id="supportTicketsModal" class="modal" style="display: none;">
                <div class="modal-content support-tickets-modal" style="max-height: 80vh; display: flex; flex-direction: column;">
                    <div class="modal-header">
                        <h3>My Support Tickets</h3>
                        <span class="close" onclick="closeSupportTicketsModal()">&times;</span>
                    </div>
                    <div class="modal-body" style="padding: 20px; flex: 1; overflow: hidden;">
                        <div id="ticketsLoading" class="loading-spinner">
                            <div class="loading-circle"></div> Loading tickets...
                        </div>
                        <div id="ticketsContent" style="display: none; height: 100%;">
                            <div id="ticketsList" style="height: 100%; overflow-y: auto; padding-right: 10px;"></div>
                        </div>
                        <div id="noTickets" style="display: none;" class="no-tickets">
                            <i class="fas fa-ticket-alt"></i>
                            <h4>No Support Tickets</h4>
                            <p>You haven't submitted any support tickets yet.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ticket Details Modal -->
            <div id="ticketDetailsModal" class="modal" style="display: none;">
                <div class="modal-content ticket-details-modal">
                    <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; background: #f8f9fa; border-bottom: 1px solid #e9ecef;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <button onclick="window.userSupportTickets.goBackToTicketsList()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #666; padding: 5px;">
                                <i class="fas fa-arrow-left"></i>
                            </button>
                            <h3 style="margin: 0; color: #333;">Ticket Details</h3>
                        </div>
                        <span class="close" onclick="closeTicketDetailsModal()" style="cursor: pointer; font-size: 24px; color: #666; padding: 5px;">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div id="ticketDetailsContent"></div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    bindEvents() {
        // Close modal when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target.id === 'supportTicketsModal') {
                this.closeModal();
            }
            if (e.target.id === 'ticketDetailsModal') {
                this.closeTicketDetailsModal();
            }
        });

        // Escape key handler
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (document.getElementById('ticketDetailsModal').style.display === 'flex') {
                    this.closeTicketDetailsModal();
                } else if (document.getElementById('supportTicketsModal').style.display === 'flex') {
                    this.closeModal();
                }
            }
        });
    }

    async openModal() {
        const modal = document.getElementById('supportTicketsModal');
        modal.style.display = 'flex';
        
        await this.loadTickets();
    }

    closeModal() {
        const modal = document.getElementById('supportTicketsModal');
        modal.style.display = 'none';
    }

    closeTicketDetailsModal() {
        const modal = document.getElementById('ticketDetailsModal');
        modal.style.display = 'none';
    }

    goBackToTicketsList() {
        // Close details modal and reopen tickets list modal
        this.closeTicketDetailsModal();
        this.openModal();
    }

    async loadTickets() {
        const loadingEl = document.getElementById('ticketsLoading');
        const contentEl = document.getElementById('ticketsContent');
        const noTicketsEl = document.getElementById('noTickets');
        
        loadingEl.style.display = 'block';
        contentEl.style.display = 'none';
        noTicketsEl.style.display = 'none';

        try {
            const response = await fetch('api/user_support_tickets.php?action=list');
            const data = await response.json();

            if (data.success) {
                this.tickets = data.tickets;
                this.renderTickets();
                
                if (this.tickets.length === 0) {
                    noTicketsEl.style.display = 'block';
                } else {
                    contentEl.style.display = 'block';
                }
            } else {
                this.showError('Failed to load tickets: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading tickets:', error);
            this.showError('Error loading tickets');
        } finally {
            loadingEl.style.display = 'none';
        }
    }

    renderTickets() {
        const listEl = document.getElementById('ticketsList');
        
        if (this.tickets.length === 0) {
            listEl.innerHTML = '';
            return;
        }

        const ticketsHTML = this.tickets.map(ticket => `
            <div class="ticket-card" onclick="viewTicketDetails('${ticket.id}')">
                <div class="ticket-header">
                    <div class="ticket-id">#${ticket.id}</div>
                    <div class="ticket-status">
                        <span class="status-badge ${ticket.status}">${this.formatStatus(ticket.status)}</span>
                    </div>
                </div>
                <div class="ticket-subject">${ticket.subject}</div>
                <div class="ticket-meta">
                    <div class="ticket-priority">
                        <span class="priority-badge ${ticket.priority}">${ticket.priority.toUpperCase()}</span>
                    </div>
                    <div class="ticket-date">${new Date(ticket.created_at).toLocaleDateString()}</div>
                    <div class="ticket-replies">
                        <i class="fas fa-reply"></i> ${ticket.reply_count || 0} ${ticket.reply_count === 1 ? 'reply' : 'replies'}
                    </div>
                </div>
            </div>
        `).join('');

        listEl.innerHTML = ticketsHTML;
    }

    async viewTicketDetails(ticketId) {
        try {
            const response = await fetch(`api/user_support_tickets.php?action=details&ticket_id=${ticketId}`);
            const data = await response.json();

            if (data.success) {
                this.currentTicket = data.ticket;
                this.currentMessages = data.messages || [];
                this.hasConversation = data.has_conversation || false;
                this.renderTicketDetails();
                
                // Close tickets list modal and open details modal
                this.closeModal();
                document.getElementById('ticketDetailsModal').style.display = 'flex';
            } else {
                this.showError('Failed to load ticket details: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading ticket details:', error);
            this.showError('Error loading ticket details');
        }
    }

    renderTicketDetails() {
        const contentEl = document.getElementById('ticketDetailsContent');
        const ticket = this.currentTicket;

        // Render conversation or fallback to old format
        const conversationHTML = this.hasConversation && this.currentMessages.length > 0 
            ? this.renderConversation() 
            : this.renderLegacyResponse();

        contentEl.innerHTML = `
            <div class="ticket-details">
                <div class="ticket-info">
                    <div class="info-row">
                        <label>Ticket ID:</label>
                        <span>#${ticket.id}</span>
                    </div>
                    <div class="info-row">
                        <label>Subject:</label>
                        <span>${ticket.subject}</span>
                    </div>
                    <div class="info-row">
                        <label>Priority:</label>
                        <span class="priority-badge ${ticket.priority}">${ticket.priority.toUpperCase()}</span>
                    </div>
                    <div class="info-row">
                        <label>Status:</label>
                        <span class="status-badge ${ticket.status}">${this.formatStatus(ticket.status)}</span>
                    </div>
                    <div class="info-row">
                        <label>Created:</label>
                        <span>${new Date(ticket.created_at).toLocaleString()}</span>
                    </div>
                    ${ticket.updated_at !== ticket.created_at ? `
                        <div class="info-row">
                            <label>Last Updated:</label>
                            <span>${new Date(ticket.updated_at).toLocaleString()}</span>
                        </div>
                    ` : ''}
                </div>
                
                ${this.hasConversation ? '' : `
                    <div class="ticket-message-section">
                        <h4>Your Message</h4>
                        <div class="original-message">${this.getOriginalMessage()}</div>
                        ${ticket.attachment_path ? `
                            <div class="attachment-section" style="text-align: left; margin-top: 10px;">
                                <a href="${this.getAttachmentUrl(ticket.attachment_path)}" target="_blank" style="color: #007bff; text-decoration: underline;">
                                    ${ticket.original_filename || ticket.attachment_path.split('/').pop()}
                                </a>
                            </div>
                        ` : ''}
                    </div>
                `}
                
                ${conversationHTML}
            </div>
        `;
        
        // Scroll to bottom of chat messages after rendering
        setTimeout(() => {
            const chatArea = document.getElementById('chatMessagesArea');
            if (chatArea) {
                chatArea.scrollTop = chatArea.scrollHeight;
            }
        }, 100);
    }

    renderConversation() {
        if (!this.currentMessages || this.currentMessages.length === 0) {
            return `
                <div class="chat-container">
                    <div class="chat-messages-area">
                        <div class="no-messages">
                            <i class="fas fa-comments"></i>
                            <p>No messages in this conversation yet.</p>
                        </div>
                    </div>
                    <div class="chat-input-area">
                        <div class="chat-input-container">
                            <textarea id="customerReplyInput" placeholder="Write a reply..." rows="3"></textarea>
                            <button id="sendCustomerReplyBtn" class="send-btn" onclick="userSupportTickets.sendReply()">
                                <i class="fas fa-paper-plane"></i> Send reply
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        const messagesHTML = this.currentMessages.map(message => {
            const isAdmin = message.sender_type === 'admin';
            const messageClass = isAdmin ? 'message-left' : 'message-right';
            const bubbleClass = isAdmin ? 'bubble-support' : 'bubble-customer';
            
            return `
                <div class="chat-message ${messageClass}">
                    <div class="message-bubble ${bubbleClass}">
                        <div class="message-text">${message.message.replace(/\n/g, '<br>')}</div>
                        ${message.attachment_path ? `
                            <div class="message-attachment">
                                <i class="fas fa-paperclip"></i>
                                <a href="${this.getAttachmentUrl(message.attachment_path)}" target="_blank">View Attachment</a>
                            </div>
                        ` : ''}
                        <div class="message-time">${message.time_ago}</div>
                    </div>
                    <div class="message-sender">${isAdmin ? '🛡️ Support Team' : '👤 You'}</div>
                </div>
            `;
        }).join('');

        // Check if ticket is closed
        const isTicketClosed = this.currentTicket && this.currentTicket.status === 'closed';
        
        return `
            <div class="chat-container">
                <div class="chat-messages-area" id="chatMessagesArea">
                    ${messagesHTML}
                </div>
                ${isTicketClosed ? `
                    <div class="chat-input-area closed-ticket">
                        <div class="closed-ticket-message">
                            <span>This ticket has been closed. You can view the conversation history but cannot send new replies.</span>
                        </div>
                    </div>
                ` : `
                    <div class="chat-input-area">
                        <div class="chat-input-container">
                            <textarea id="customerReplyInput" placeholder="Write a reply..." rows="3"></textarea>
                            <button id="sendCustomerReplyBtn" class="send-btn" onclick="userSupportTickets.sendReply()">
                                <i class="fas fa-paper-plane"></i> Send reply
                            </button>
                        </div>
                    </div>
                `}
            </div>
        `;
    }

    renderLegacyResponse() {
        const ticket = this.currentTicket;
        
        // Show original message first
        let html = `
            <div class="ticket-message-section">
                <h4>Your Message</h4>
                <div class="original-message">${ticket.message}</div>
                ${ticket.attachment_path ? `
                    <div class="attachment-section" style="text-align: left; margin-top: 10px;">
                        <a href="${this.getAttachmentUrl(ticket.attachment_path)}" target="_blank" style="color: #007bff; text-decoration: underline;">
                            ${ticket.original_filename || ticket.attachment_path.split('/').pop()}
                        </a>
                    </div>
                ` : ''}
            </div>
        `;

        // Show admin response if exists
        if (ticket.admin_response) {
            html += `
                <div class="admin-response-section">
                    <h4>Support Team Response</h4>
                    <div class="admin-response">
                        <div class="response-header">
                            <strong>${ticket.admin_username || 'Support Team'}</strong>
                            <span class="response-date">${new Date(ticket.updated_at).toLocaleString()}</span>
                        </div>
                        <div class="response-message">${ticket.admin_response}</div>
                    </div>
                </div>
            `;
        } else {
            html += '<div class="no-response">No response from support team yet.</div>';
        }

        return html;
    }

    getOriginalMessage() {
        // Get the first customer message from the conversation
        if (this.currentMessages && this.currentMessages.length > 0) {
            const firstCustomerMessage = this.currentMessages.find(msg => msg.sender_type === 'customer');
            return firstCustomerMessage ? firstCustomerMessage.message : 'No message found';
        }
        return this.currentTicket?.message || 'No message found';
    }

    getAttachmentUrl(attachmentPath) {
        // Convert server path to web-accessible URL
        if (attachmentPath.startsWith('../uploads/')) {
            return attachmentPath.replace('../uploads/', 'uploads/');
        } else if (attachmentPath.startsWith('uploads/')) {
            return attachmentPath;
        } else if (attachmentPath.includes('uploads/')) {
            return attachmentPath.substring(attachmentPath.indexOf('uploads/'));
        }
        return attachmentPath;
    }

    async sendReply() {
        const replyInput = document.getElementById('customerReplyInput');
        const message = replyInput.value.trim();
        
        if (!message) {
            this.showError('Please enter a message');
            return;
        }
        
        if (!this.currentTicket || !this.currentTicket.id) {
            this.showError('No ticket selected');
            return;
        }
        
        try {
            const response = await fetch('api/customer_reply.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    conversation_id: this.currentTicket.id,
                    message: message
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                replyInput.value = '';
                // Refresh the conversation
                await this.viewTicketDetails(this.currentTicket.id);
                // Scroll to bottom
                const messagesArea = document.getElementById('chatMessagesArea');
                if (messagesArea) {
                    messagesArea.scrollTop = messagesArea.scrollHeight;
                }
            } else {
                this.showError('Failed to send message: ' + data.message);
            }
        } catch (error) {
            console.error('Error sending reply:', error);
            this.showError('Error sending message');
        }
    }

    showError(message) {
        // You can implement a toast notification here
        console.error(message);
        alert(message); // Temporary fallback
    }

    showSuccess(message) {
        console.log(message);
        // You can implement a toast notification here
    }

    initializeRealtimeUpdates() {
        if (this.sseClient) {
            console.log('UserSupportTickets: Setting up real-time updates');
            
            // Listen for real-time notifications
            this.sseClient.on('realtime_notifications', (notifications) => {
                this.handleRealtimeNotifications(notifications);
            });
        } else {
            console.warn('UserSupportTickets: No SSE client available for real-time updates');
        }
    }

    handleRealtimeNotifications(notifications) {
        if (!Array.isArray(notifications)) return;
        
        // Get current user ID from session or other source
        const currentUserId = this.getCurrentUserId();
        if (!currentUserId) return;
        
        notifications.forEach(notification => {
            if (notification.data) {
                const data = notification.data;
                
                // Check if this notification is for the current user
                if (data.customer_id == currentUserId) {
                    if (data.type === 'support_status_change') {
                        this.handleStatusChangeNotification(data);
                    } else if (data.type === 'support_new_reply') {
                        this.handleNewReplyNotification(data);
                    }
                }
            }
        });
    }

    handleStatusChangeNotification(data) {
        console.log('Status change notification received:', data);
        
        // Show visual notification
        this.showStatusChangeToast(data);
        
        // Update ticket status in memory if tickets are loaded
        if (this.tickets && this.tickets.length > 0) {
            const ticket = this.tickets.find(t => t.id == data.conversation_id);
            if (ticket) {
                ticket.status = data.new_status;
                
                // If tickets modal is open, refresh the display
                if (document.getElementById('supportTicketsModal').style.display === 'flex') {
                    this.renderTickets();
                }
            }
        }
        
        // If currently viewing this ticket's details, refresh the view
        if (this.currentTicket && this.currentTicket.id == data.conversation_id) {
            this.currentTicket.status = data.new_status;
            
            // If details modal is open, refresh the display
            if (document.getElementById('ticketDetailsModal').style.display === 'flex') {
                this.renderTicketDetails();
            }
        }
    }

    handleNewReplyNotification(data) {
        console.log('New reply notification received:', data);
        
        // Visual notification disabled - popup removed
        // this.showNewReplyToast(data);
        
        // Trigger support messaging refresh for real-time conversation updates
        if (window.supportMessaging) {
            console.log('Triggering support messaging refresh from ticket notification');
            window.supportMessaging.refreshMessages();
        }
        
        // If currently viewing this ticket's details, refresh to show new message
        if (this.currentTicket && this.currentTicket.id == data.conversation_id) {
            // If details modal is open, refresh the conversation
            if (document.getElementById('ticketDetailsModal').style.display === 'flex') {
                this.viewTicketDetails(data.conversation_id);
            }
        }
        
        // If tickets list is open, refresh to update reply count
        if (document.getElementById('supportTicketsModal').style.display === 'flex') {
            this.loadTickets();
        }
    }

    showStatusChangeToast(data) {
        // Create a toast notification
        const toast = document.createElement('div');
        toast.className = 'status-change-toast';
        toast.innerHTML = `
            <div class="toast-content">
                <div class="toast-icon">
                    <i class="fas fa-${this.getStatusIcon(data.new_status)}"></i>
                </div>
                <div class="toast-message">
                    <div class="toast-title">Support Ticket Updated</div>
                    <div class="toast-text">${data.message}</div>
                    <div class="toast-admin">Updated by: ${data.admin_name}</div>
                </div>
                <button class="toast-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        this.showToast(toast);
    }

    showNewReplyToast(data) {
        // Create a toast notification for new replies
        const toast = document.createElement('div');
        toast.className = 'status-change-toast new-reply-toast';
        toast.innerHTML = `
            <div class="toast-content">
                <div class="toast-icon">
                    <i class="fas fa-reply"></i>
                </div>
                <div class="toast-message">
                    <div class="toast-title">New Support Reply</div>
                    <div class="toast-text">${data.message}</div>
                    <div class="toast-preview">"${data.reply_preview}"</div>
                    <div class="toast-admin">From: ${data.admin_name}</div>
                </div>
                <button class="toast-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        this.showToast(toast);
    }

    showToast(toast) {
        // Add CSS if not already added
        this.addToastStyles();
        
        // Add to page
        document.body.appendChild(toast);
        
        // Auto-remove after 8 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 8000);
        
        // Animate in
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
    }

    getStatusIcon(status) {
        const icons = {
            'open': 'envelope-open',
            'solved': 'check-circle',
            'closed': 'lock'
        };
        return icons[status] || 'bell';
    }

    addToastStyles() {
        if (document.getElementById('status-toast-styles')) return;
        
        const styles = document.createElement('style');
        styles.id = 'status-toast-styles';
        styles.textContent = `
            .status-change-toast {
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-left: 4px solid #007bff;
                min-width: 300px;
                max-width: 400px;
                z-index: 10000;
                transform: translateX(100%);
                transition: transform 0.3s ease;
                margin-bottom: 10px;
            }
            
            .status-change-toast.new-reply-toast {
                border-left-color: #28a745;
            }
            
            .status-change-toast.new-reply-toast .toast-icon {
                background: #28a745;
            }
            
            .status-change-toast.show {
                transform: translateX(0);
            }
            
            .toast-content {
                display: flex;
                align-items: flex-start;
                padding: 16px;
                gap: 12px;
            }
            
            .toast-icon {
                background: #007bff;
                color: white;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            
            .toast-message {
                flex: 1;
            }
            
            .toast-title {
                font-weight: 600;
                color: #333;
                margin-bottom: 4px;
            }
            
            .toast-text {
                color: #666;
                font-size: 14px;
                margin-bottom: 4px;
            }
            
            .toast-preview {
                color: #555;
                font-size: 13px;
                font-style: italic;
                margin-bottom: 4px;
                background: #f8f9fa;
                padding: 4px 8px;
                border-radius: 4px;
            }
            
            .toast-admin {
                color: #888;
                font-size: 12px;
            }
            
            .toast-close {
                background: none;
                border: none;
                color: #999;
                cursor: pointer;
                padding: 4px;
                flex-shrink: 0;
            }
            
            .toast-close:hover {
                color: #666;
            }
        `;
        
        document.head.appendChild(styles);
    }

    getCurrentUserId() {
        // Try to get user ID from various sources
        // This might need to be adapted based on how user session is handled
        if (window.currentUser && window.currentUser.id) {
            return window.currentUser.id;
        }
        
        // Try to get from a global variable or session storage
        if (window.userId) {
            return window.userId;
        }
        
        // Try to get from session storage
        const userId = sessionStorage.getItem('user_id') || localStorage.getItem('user_id');
        if (userId) {
            return parseInt(userId);
        }
        
        return null;
    }

    formatStatus(status) {
        if (!status) return 'OPEN';
        
        // Convert status to uppercase and replace underscores with spaces
        return status.toUpperCase().replace(/_/g, ' ');
    }

    destroy() {
        // Clean up SSE event listeners
        if (this.sseClient) {
            this.sseClient.off('realtime_notifications');
        }
        
        console.log('UserSupportTickets module destroyed');
    }
}
