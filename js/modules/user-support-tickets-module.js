/**
 * User Support Tickets Module
 * Handles viewing support tickets and admin replies for customers
 */

export default class UserSupportTicketsModule {
    constructor() {
        this.tickets = [];
        this.currentTicket = null;
        
        // Register global functions immediately
        window.openSupportTicketsModal = () => this.openModal();
        window.closeSupportTicketsModal = () => this.closeModal();
        window.viewTicketDetails = (ticketId) => this.viewTicketDetails(ticketId);
        window.closeTicketDetailsModal = () => this.closeTicketDetailsModal();
        
        // Make instance available globally for back button
        window.userSupportTickets = this;
    }

    init() {
        this.createTicketsModal();
        this.bindEvents();
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
                            <i class="fas fa-spinner fa-spin"></i> Loading tickets...
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
            <div class="ticket-card" onclick="viewTicketDetails('${ticket.id}')">"
                <div class="ticket-header">
                    <div class="ticket-id">#${ticket.id}</div>
                    <div class="ticket-status">
                        <span class="status-badge ${ticket.status}">${ticket.status}</span>
                    </div>
                </div>
                <div class="ticket-subject">${ticket.subject}</div>
                <div class="ticket-meta">
                    <div class="ticket-priority">
                        <span class="priority-badge ${ticket.priority}">${ticket.priority}</span>
                    </div>
                    <div class="ticket-date">${new Date(ticket.created_at).toLocaleDateString()}</div>
                    <div class="ticket-replies">
                        <i class="fas fa-reply"></i> ${ticket.admin_response ? '1 reply' : '0 replies'}
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
                        <span class="priority-badge ${ticket.priority}">${ticket.priority}</span>
                    </div>
                    <div class="info-row">
                        <label>Status:</label>
                        <span class="status-badge ${ticket.status}">${ticket.status}</span>
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

        return `
            <div class="chat-container">
                <div class="chat-messages-area" id="chatMessagesArea">
                    ${messagesHTML}
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
}
