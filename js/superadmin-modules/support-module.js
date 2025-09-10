// Support Module for Super Admin Dashboard
export class SupportModule {
    constructor(dashboard) {
        this.dashboard = dashboard;
    }

    loadCustomerSupport(container) {
        container.innerHTML = `
            <section id="customer-support" class="content-section active">
                <div class="support-header">
                    <div class="support-stats">
                        <div class="stat-item">
                            <span class="stat-number" id="openTickets">0</span>
                            <span class="stat-label">Open Tickets</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" id="pendingTickets">0</span>
                            <span class="stat-label">Pending</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" id="resolvedTickets">0</span>
                            <span class="stat-label">Resolved Today</span>
                        </div>
                    </div>
                </div>

                <div class="support-filters">
                    <select id="ticketStatusFilter">
                        <option value="">All Status</option>
                        <option value="open">Open</option>
                        <option value="pending">Pending</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                    <select id="ticketPriorityFilter">
                        <option value="">All Priority</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                    <input type="text" id="ticketSearch" placeholder="Search tickets...">
                </div>

                <div class="support-tickets-container">
                    <table class="support-tickets-table">
                        <thead>
                            <tr>
                                <th>Ticket ID</th>
                                <th>Customer</th>
                                <th>Subject</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="supportTicketsBody">
                            <tr>
                                <td colspan="7" class="loading">Loading support tickets...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- View Ticket Modal -->
                <div id="viewTicketModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4>Ticket Details</h4>
                            <span class="close" onclick="closeViewTicketModal()">&times;</span>
                        </div>
                        <div class="modal-body">
                            <div class="ticket-details">
                                <div class="detail-row">
                                    <label>Ticket ID:</label>
                                    <span id="viewTicketId"></span>
                                </div>
                                <div class="detail-row">
                                    <label>Customer:</label>
                                    <span id="viewCustomerName"></span>
                                </div>
                                <div class="detail-row">
                                    <label>Email:</label>
                                    <span id="viewCustomerEmail"></span>
                                </div>
                                <div class="detail-row">
                                    <label>Subject:</label>
                                    <span id="viewTicketSubject"></span>
                                </div>
                                <div class="detail-row">
                                    <label>Priority:</label>
                                    <span id="viewTicketPriority" class="priority-badge"></span>
                                </div>
                                <div class="detail-row">
                                    <label>Status:</label>
                                    <span id="viewTicketStatus" class="status-badge"></span>
                                </div>
                                <div class="detail-row">
                                    <label>Created:</label>
                                    <span id="viewTicketDate"></span>
                                </div>
                                <div class="detail-row full-width">
                                    <label>Message:</label>
                                    <div id="viewTicketMessage" class="ticket-message"></div>
                                </div>
                                <div class="detail-row full-width" id="attachmentRow" style="display: none;">
                                    <label>Attachment:</label>
                                    <div id="viewTicketAttachment" class="ticket-attachment"></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="reply-btn" onclick="replyToTicketFromView()">
                                <i class="fas fa-reply"></i> Reply
                            </button>
                            <button type="button" class="cancel-btn" onclick="closeViewTicketModal()">Close</button>
                        </div>
                    </div>
                </div>

                <!-- Reply Modal -->
                <div id="replyModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4>Reply to Ticket</h4>
                            <span class="close" onclick="closeReplyModal()">&times;</span>
                        </div>
                        <form id="replyForm" onsubmit="sendReply(event)">
                            <input type="hidden" id="ticketId" name="ticket_id">
                            <div class="form-group">
                                <label for="replyMessage">Reply Message</label>
                                <textarea id="replyMessage" name="message" rows="5" required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="ticketStatus">Update Status</label>
                                <select id="ticketStatus" name="status">
                                    <option value="">Keep current status</option>
                                    <option value="open">Open</option>
                                    <option value="pending">Pending</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>
                            <div class="modal-actions">
                                <button type="button" class="cancel-btn" onclick="closeReplyModal()">Cancel</button>
                                <button type="submit" class="save-btn">Send Reply</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        `;
        this.loadSupportTickets();
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Status filter
        const statusFilter = document.getElementById('ticketStatusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', () => this.loadSupportTickets());
        }

        // Priority filter
        const priorityFilter = document.getElementById('ticketPriorityFilter');
        if (priorityFilter) {
            priorityFilter.addEventListener('change', () => this.loadSupportTickets());
        }

        // Search input with debounce
        const searchInput = document.getElementById('ticketSearch');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => this.loadSupportTickets(), 300);
            });
        }
    }

    async loadSupportTickets() {
        try {
            // Get filter values
            const status = document.getElementById('ticketStatusFilter')?.value || '';
            const priority = document.getElementById('ticketPriorityFilter')?.value || '';
            const search = document.getElementById('ticketSearch')?.value || '';

            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'get_support_tickets',
                    status: status,
                    priority: priority,
                    search: search
                })
            });

            console.log('Response status:', response.status);
            const data = await response.json();
            console.log('Support tickets API response:', data);
            const tbody = document.getElementById('supportTicketsBody');

            if (data.success && data.tickets) {
                tbody.innerHTML = data.tickets.map(ticket => `
                    <tr>
                        <td>#${ticket.id}</td>
                        <td>
                            <div class="customer-info">
                                <strong>${ticket.username || 'Unknown User'}</strong>
                            </div>
                        </td>
                        <td>${ticket.subject}</td>
                        <td><span class="priority-badge ${ticket.priority}">${ticket.priority}</span></td>
                        <td><span class="status-badge ${ticket.status}">${ticket.status}</span></td>
                        <td>${new Date(ticket.created_at).toLocaleDateString()}</td>
                        <td>
                            <button onclick="viewTicket(${ticket.id})" class="view-btn" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="replyToTicket(${ticket.id})" class="reply-btn" title="Reply">
                                <i class="fas fa-reply"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');

                // Update stats
                this.updateSupportStats(data.stats);
                
                // Update support badge in sidebar
                this.updateSupportBadge(data.stats);
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="no-data">No support tickets found</td></tr>';
            }
        } catch (error) {
            console.error('Error loading support tickets:', error);
            document.getElementById('supportTicketsBody').innerHTML = '<tr><td colspan="7" class="error-row">Error loading support tickets</td></tr>';
        }
    }

    updateSupportStats(stats) {
        if (stats) {
            document.getElementById('openTickets').textContent = stats.open_tickets || 0;
            document.getElementById('pendingTickets').textContent = stats.pending_tickets || 0;
            document.getElementById('resolvedTickets').textContent = stats.resolved_today || 0;
        }
    }

    updateSupportBadge(stats) {
        if (stats) {
            const openCount = stats.open_tickets || 0;
            const badge = document.getElementById('supportBadge');
            
            if (badge) {
                if (openCount > 0) {
                    badge.textContent = openCount;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
        }
    }

    async viewTicket(ticketId) {
        try {
            const response = await fetch(`api/superadmin_api/super_admin_actions.php?action=get_ticket&ticket_id=${ticketId}`);
            const data = await response.json();

            if (data.success && data.ticket) {
                const ticket = data.ticket;
                
                // Populate modal with ticket details
                document.getElementById('viewTicketId').textContent = `#${ticket.id}`;
                document.getElementById('viewCustomerName').textContent = ticket.customer_name || 'Unknown User';
                document.getElementById('viewCustomerEmail').textContent = ticket.customer_email || 'No email';
                document.getElementById('viewTicketSubject').textContent = ticket.subject;
                document.getElementById('viewTicketMessage').textContent = ticket.message;
                document.getElementById('viewTicketDate').textContent = new Date(ticket.created_at).toLocaleString();
                
                // Handle attachment if present
                const attachmentRow = document.getElementById('attachmentRow');
                const attachmentDiv = document.getElementById('viewTicketAttachment');
                
                if (ticket.attachment_path && ticket.attachment_path.trim() !== '') {
                    // Use original filename if available, otherwise fall back to generated filename
                    const displayFileName = ticket.original_filename || ticket.attachment_path.split('/').pop();
                    const fileExtension = displayFileName.split('.').pop().toLowerCase();
                    
                    // Convert server path to web-accessible URL
                    let webPath = ticket.attachment_path;
                    if (webPath.startsWith('../uploads/')) {
                        webPath = webPath.replace('../uploads/', 'uploads/');
                    } else if (webPath.startsWith('uploads/')) {
                        // Already correct format
                    } else if (webPath.includes('uploads/')) {
                        // Extract from any path containing uploads
                        webPath = webPath.substring(webPath.indexOf('uploads/'));
                    }
                    
                    // Check if it's an image
                    const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                    
                    if (imageExtensions.includes(fileExtension)) {
                        // For images, show as downloadable link with preview attempt
                        attachmentDiv.innerHTML = `
                            <div class="attachment-file">
                                <i class="fas fa-image"></i>
                                <a href="${webPath}" target="_blank" class="attachment-link">${displayFileName}</a>
                                <span class="attachment-type">(Image)</span>
                            </div>
                        `;
                    } else {
                        attachmentDiv.innerHTML = `
                            <div class="attachment-file">
                                <i class="fas fa-file-alt"></i>
                                <a href="${webPath}" target="_blank" class="attachment-link">${displayFileName}</a>
                                <span class="attachment-type">(File)</span>
                            </div>
                        `;
                    }
                    attachmentRow.style.display = 'block';
                } else {
                    attachmentRow.style.display = 'none';
                }
                
                // Set priority and status with proper classes
                const priorityElement = document.getElementById('viewTicketPriority');
                priorityElement.textContent = ticket.priority;
                priorityElement.className = `priority-badge ${ticket.priority}`;
                
                const statusElement = document.getElementById('viewTicketStatus');
                statusElement.textContent = ticket.status;
                statusElement.className = `status-badge ${ticket.status}`;
                
                // Store ticket ID for potential reply
                this.currentViewingTicketId = ticketId;
                
                // Show the modal
                this.showViewTicketModal();
            } else {
                this.dashboard.showNotification('Error loading ticket details', 'error');
            }
        } catch (error) {
            console.error('Error viewing ticket:', error);
            this.dashboard.showNotification('Error loading ticket details', 'error');
        }
    }

    replyToTicket(ticketId) {
        const modal = document.getElementById('replyModal');
        document.getElementById('ticketId').value = ticketId;
        document.getElementById('replyMessage').value = '';
        document.getElementById('ticketStatus').value = '';
        
        modal.style.display = 'flex';
        modal.style.visibility = 'visible';
        modal.style.opacity = '1';
    }

    closeReplyModal() {
        const modal = document.getElementById('replyModal');
        modal.style.display = 'none';
        modal.style.visibility = 'hidden';
        modal.style.opacity = '0';
    }

    showViewTicketModal() {
        const modal = document.getElementById('viewTicketModal');
        modal.style.display = 'flex';
        modal.style.visibility = 'visible';
        modal.style.opacity = '1';
    }

    closeViewTicketModal() {
        const modal = document.getElementById('viewTicketModal');
        modal.style.display = 'none';
        modal.style.visibility = 'hidden';
        modal.style.opacity = '0';
        
        // Clear attachment display
        const attachmentRow = document.getElementById('attachmentRow');
        if (attachmentRow) {
            attachmentRow.style.display = 'none';
        }
    }

    replyToTicketFromView() {
        if (this.currentViewingTicketId) {
            this.closeViewTicketModal();
            this.replyToTicket(this.currentViewingTicketId);
        }
    }

    async sendReply(event) {
        event.preventDefault();
        
        const form = document.getElementById('replyForm');
        const formData = new FormData(form);
        
        const replyData = {
            action: 'reply_to_ticket',
            ticket_id: formData.get('ticket_id'),
            reply_message: formData.get('message'),
            status: formData.get('status') || null
        };

        // Debug logging
        console.log('Form data collected:', {
            ticket_id: formData.get('ticket_id'),
            message: formData.get('message'),
            status: formData.get('status')
        });
        console.log('Reply data being sent:', replyData);

        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(replyData)
            });

            const data = await response.json();
            
            if (data.success) {
                this.dashboard.showNotification('Reply sent successfully', 'success');
                this.closeReplyModal();
                this.loadSupportTickets();
            } else {
                this.dashboard.showNotification(data.message || 'Failed to send reply', 'error');
            }
        } catch (error) {
            console.error('Error sending reply:', error);
            this.dashboard.showNotification('Error sending reply', 'error');
        }
    }
}
