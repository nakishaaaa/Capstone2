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
    }

    init() {
        this.createTicketsModal();
        this.bindEvents();
    }

    createTicketsModal() {
        const modalHTML = `
            <!-- Support Tickets Modal -->
            <div id="supportTicketsModal" class="modal" style="display: none;">
                <div class="modal-content support-tickets-modal">
                    <div class="modal-header">
                        <h3>My Support Tickets</h3>
                        <span class="close" onclick="closeSupportTicketsModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div id="ticketsLoading" class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i> Loading tickets...
                        </div>
                        <div id="ticketsContent" style="display: none;">
                            <div id="ticketsList"></div>
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
                    <div class="modal-header">
                        <h3>Ticket Details</h3>
                        <span class="close" onclick="closeTicketDetailsModal()">&times;</span>
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
            <div class="ticket-card" onclick="viewTicketDetails(${ticket.id})">
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

        const adminResponseHTML = ticket.admin_response ? `
            <div class="admin-response-section">
                <h4>Admin Response</h4>
                <div class="admin-response">
                    <div class="response-header">
                        <strong>${ticket.admin_username || 'Admin'}</strong>
                        <span class="response-date">${new Date(ticket.updated_at).toLocaleString()}</span>
                    </div>
                    <div class="response-message">${ticket.admin_response}</div>
                </div>
            </div>
        ` : '<div class="no-response">No admin response yet.</div>';

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
                
                <div class="ticket-message-section">
                    <h4>Your Message</h4>
                    <div class="original-message">${ticket.message}</div>
                </div>
                
                ${adminResponseHTML}
            </div>
        `;
    }

    showError(message) {
        // You can implement a toast notification here
        console.error(message);
        alert(message); // Temporary fallback
    }
}
