/**
 * Order Management Module for Admin Dashboard
 * Handles production tracking and status updates for approved orders
 */

export class OrderManagementModule {
    constructor(toast, modal) {
        this.toast = toast
        this.modal = modal
        this.orders = []
        this.filteredOrders = []
        this.selectedOrders = new Set()
        this.currentFilter = 'all'
        this.isActive = false
    }

    // Utility function to parse JSON responses that might contain PHP warnings
    parseJsonResponse(responseText) {
        try {
            return JSON.parse(responseText)
        } catch (jsonError) {
            // Try to extract JSON from response that might contain PHP warnings
            const jsonMatch = responseText.match(/(\{.*\})$/)
            if (jsonMatch) {
                try {
                    const data = JSON.parse(jsonMatch[1])
                    console.warn('Response contained warnings but JSON was extracted successfully')
                    return data
                } catch (secondError) {
                    console.error('Invalid JSON response:', responseText)
                    throw new Error('Server returned invalid response. Please check server logs.')
                }
            } else {
                console.error('Invalid JSON response:', responseText)
                throw new Error('Server returned invalid response. Please check server logs.')
            }
        }
    }

    init() {
        console.log('Initializing Order Management Module...')
        this.setupEventListeners()
        this.setupAutoRefresh()
    }

    setupAutoRefresh() {
        // Auto-refresh every 30 seconds when active
        this.autoRefreshInterval = setInterval(() => {
            if (this.isActive) {
                this.loadOrders(true) // Silent refresh
                this.showAutoRefreshIndicator()
            }
        }, 30000)
    }

    showAutoRefreshIndicator() {
        const refreshBtn = document.getElementById('refreshOrdersBtn')
        if (refreshBtn) {
            const originalText = refreshBtn.innerHTML
            refreshBtn.innerHTML = '<i class="fas fa-sync fa-spin"></i> Auto-refreshed'
            refreshBtn.style.background = '#10b981'
            
            setTimeout(() => {
                refreshBtn.innerHTML = originalText
                refreshBtn.style.background = ''
            }, 2000)
        }
    }

    activate() {
        this.isActive = true
        this.loadOrders()
        
        // Set up form event listener when section is activated
        setTimeout(() => {
            const statusForm = document.getElementById('statusUpdateForm')
            if (statusForm) {
                // Remove any existing listeners to avoid duplicates
                statusForm.removeEventListener('submit', this.handleStatusUpdate.bind(this))
                // Add the event listener
                statusForm.addEventListener('submit', this.handleStatusUpdate.bind(this))
                console.log('Status form event listener attached')
            } else {
                console.warn('Status form not found during activation')
            }
        }, 100)
    }

    deactivate() {
        this.isActive = false
    }

    setupEventListeners() {
        // Make functions globally accessible
        window.refreshOrders = () => this.loadOrders()
        window.filterByStatus = (status) => this.filterByStatus(status)
        window.updateOrderStatus = (orderId) => this.showStatusModal(orderId)
        window.viewOrderDetails = (orderId) => this.showOrderDetails(orderId)
        window.bulkUpdateStatus = (status) => this.bulkUpdateStatus(status)
        window.clearSelection = () => this.clearSelection()
        window.closeOrderModal = () => this.closeOrderModal()
        window.closeStatusModal = () => this.closeStatusModal()

        // Status update form will be set up in activate() method
    }

    async loadOrders(silent = false) {
        if (!this.isActive) return
        
        try {
            const response = await fetch('api/order_management.php?action=get_orders', {
                credentials: 'include'
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const responseText = await response.text()
            const data = this.parseJsonResponse(responseText)
            
            if (data.success) {
                this.orders = data.orders || []
                this.updateStatusCounts()
                this.displayOrders()
                console.log('Orders loaded:', this.orders.length)
            } else {
                throw new Error(data.message || 'Failed to load orders')
            }
        } catch (error) {
            console.error('Error loading orders:', error)
            this.showError('Failed to load orders: ' + error.message)
        }
    }

    updateStatusCounts() {
        const counts = {
            all: this.orders.length,
            approved: this.orders.filter(o => o.status === 'approved' && (o.payment_status === 'partial_paid' || o.payment_status === 'fully_paid')).length,
            printing: this.orders.filter(o => o.status === 'printing').length,
            ready_for_pickup: this.orders.filter(o => o.status === 'ready_for_pickup').length,
            on_the_way: this.orders.filter(o => o.status === 'on_the_way').length,
            completed: this.orders.filter(o => o.status === 'completed').length
        }

        // Update count displays
        Object.keys(counts).forEach(status => {
            const element = document.getElementById(`count-${status === 'ready_for_pickup' ? 'ready' : status === 'on_the_way' ? 'delivery' : status}`)
            if (element) {
                element.textContent = counts[status]
            }
        })

        // Update production badge
        const productionBadge = document.getElementById('productionBadge')
        if (productionBadge) {
            const inProduction = counts.approved + counts.printing + counts.ready_for_pickup + counts.on_the_way
            if (inProduction > 0) {
                productionBadge.textContent = inProduction
                productionBadge.style.display = 'inline'
            } else {
                productionBadge.style.display = 'none'
            }
        }
    }

    filterByStatus(status) {
        this.currentFilter = status
        
        // Update active tab
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active')
        })
        const activeTab = document.querySelector(`[data-status="${status}"]`)
        if (activeTab) {
            activeTab.classList.add('active')
        }
        
        this.displayOrders()
    }

    displayOrders() {
        const grid = document.getElementById('ordersGrid')
        if (!grid) return

        // Filter orders based on current filter
        if (this.currentFilter === 'all') {
            this.filteredOrders = this.orders.filter(order => 
                order.status === 'approved' && (order.payment_status === 'partial_paid' || order.payment_status === 'fully_paid') ||
                ['printing', 'ready_for_pickup', 'on_the_way', 'completed'].includes(order.status)
            )
        } else if (this.currentFilter === 'approved') {
            this.filteredOrders = this.orders.filter(order => 
                order.status === 'approved' && (order.payment_status === 'partial_paid' || order.payment_status === 'fully_paid')
            )
        } else {
            this.filteredOrders = this.orders.filter(order => order.status === this.currentFilter)
        }

        if (this.filteredOrders.length === 0) {
            grid.innerHTML = `
                <div class="loading-state">
                    <i class="fas fa-inbox"></i>
                    <p>No orders found for ${this.getStatusLabel(this.currentFilter)}</p>
                </div>
            `
            return
        }

        grid.innerHTML = this.filteredOrders.map(order => this.renderOrderCard(order)).join('')
    }

    renderOrderCard(order) {
        const isSelected = this.selectedOrders.has(order.id)
        const statusDisplay = order.status === 'approved' ? 'awaiting production' : order.status
        
        return `
            <div class="order-card ${isSelected ? 'selected' : ''}" onclick="event.stopPropagation(); window.orderManagement.toggleSelection(${order.id})">
                <input type="checkbox" class="select-checkbox" ${isSelected ? 'checked' : ''} 
                       onchange="event.stopPropagation(); window.orderManagement.toggleSelection(${order.id})">
                
                <div class="order-header-info">
                    <div class="order-id">#${order.id}</div>
                    <div class="order-date">${this.formatDate(order.created_at)}</div>
                </div>

                <div class="order-status-badge ${order.status === 'approved' ? 'approved' : order.status}">
                    ${this.getStatusLabel(statusDisplay)}
                </div>

                <div class="customer-info">
                    <div class="customer-name">${this.escapeHtml(order.customer_name || order.name)}</div>
                    <div class="customer-contact">${this.escapeHtml(order.contact_number)}</div>
                    ${order.customer_email ? `<div class="customer-contact">${this.escapeHtml(order.customer_email)}</div>` : ''}
                </div>

                <div class="order-details">
                    <div class="order-service">${this.formatCategory(order.category)}</div>
                    <div class="order-specs">
                        Size: ${this.escapeHtml(order.size)} | Qty: ${order.quantity}
                        ${order.custom_size ? `<br>Custom: ${this.escapeHtml(order.custom_size)}` : ''}
                    </div>
                </div>

                <div class="payment-info">
                    <div class="payment-amount">₱${parseFloat(order.total_price || 0).toLocaleString()}</div>
                    <div class="payment-status ${order.payment_status}">
                        ${this.formatPaymentStatus(order.payment_status)}
                    </div>
                </div>

                <div class="order-actions">
                    <button class="action-btn primary" onclick="event.stopPropagation(); updateOrderStatus(${order.id})">
                        <i class="fas fa-edit"></i> Update Status
                    </button>
                    <button class="action-btn secondary" onclick="event.stopPropagation(); viewOrderDetails(${order.id})">
                        <i class="fas fa-eye"></i> Details
                    </button>
                </div>

                ${order.production_started_at ? `
                    <div style="margin-top: 0.5rem; font-size: 0.75rem; color: #6b7280;">
                        Production started: ${this.formatDate(order.production_started_at)}
                    </div>
                ` : ''}
            </div>
        `
    }

    toggleSelection(orderId) {
        if (this.selectedOrders.has(orderId)) {
            this.selectedOrders.delete(orderId)
        } else {
            this.selectedOrders.add(orderId)
        }
        
        this.updateQuickActions()
        this.displayOrders()
    }

    updateQuickActions() {
        const quickActions = document.getElementById('quickActions')
        if (quickActions) {
            if (this.selectedOrders.size > 0) {
                quickActions.style.display = 'block'
            } else {
                quickActions.style.display = 'none'
            }
        }
    }

    clearSelection() {
        this.selectedOrders.clear()
        this.updateQuickActions()
        this.displayOrders()
    }

    showStatusModal(orderId) {
        const order = this.orders.find(o => o.id === orderId)
        if (!order) return

        console.log('Opening status modal for order:', orderId, 'Current status:', order.status)

        document.getElementById('orderId').value = orderId
        document.getElementById('statusModal').style.display = 'flex'
        
        // Set appropriate status options based on current status
        const statusSelect = document.getElementById('newStatus')
        const currentStatus = order.status === 'approved' ? 'approved' : order.status
        
        console.log('Mapped status for options:', currentStatus)
        const optionsHtml = this.getStatusOptions(currentStatus)
        console.log('Generated options HTML:', optionsHtml)
        
        statusSelect.innerHTML = optionsHtml
        console.log('Dropdown after setting innerHTML:', statusSelect.innerHTML)
        console.log('Number of options in dropdown:', statusSelect.options.length)
    }

    getStatusOptions(currentStatus) {
        const options = ['<option value="">Select status...</option>']
        
        console.log('Getting status options for:', currentStatus)
        
        switch (currentStatus) {
            case 'approved':
            case 'awaiting_production':
            case 'pending_production':
                options.push('<option value="printing">🖨️ Start Printing</option>')
                break
            case 'printing':
                options.push('<option value="ready_for_pickup">📦 Ready for Pickup</option>')
                options.push('<option value="on_the_way">🚚 Out for Delivery</option>')
                break
            case 'ready_for_pickup':
                options.push('<option value="on_the_way">🚚 Out for Delivery</option>')
                options.push('<option value="completed">✅ Mark as Completed</option>')
                break
            case 'on_the_way':
                options.push('<option value="completed">✅ Mark as Completed</option>')
                break
            case 'completed':
                options.push('<option value="ready_for_pickup">📦 Back to Ready</option>')
                break
            default:
                console.warn('Unknown status:', currentStatus, 'defaulting to approved options')
                options.push('<option value="printing">🖨️ Start Printing</option>')
                break
        }
        
        console.log('Generated options:', options)
        return options.join('')
    }

    closeStatusModal() {
        document.getElementById('statusModal').style.display = 'none'
        document.getElementById('statusUpdateForm').reset()
    }

    async handleStatusUpdate(e) {
        e.preventDefault()
        
        const formData = new FormData(e.target)
        const orderId = formData.get('order_id')
        const newStatus = formData.get('status')
        const note = formData.get('note')
        const sendEmail = formData.get('send_email') === 'on'

        if (!newStatus) {
            this.showError('Please select a status')
            return
        }

        try {
            const response = await fetch('api/order_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'update_status',
                    order_id: orderId,
                    status: newStatus,
                    note: note,
                    send_email: sendEmail,
                    csrf_token: window.csrfToken
                })
            })

            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const responseText = await response.text()
            const data = this.parseJsonResponse(responseText)
            
            if (data.success) {
                this.showSuccess(`Order status updated to ${this.getStatusLabel(newStatus)}!`)
                this.closeStatusModal()
                this.loadOrders()
            } else {
                throw new Error(data.message || 'Failed to update status')
            }
        } catch (error) {
            console.error('Error updating status:', error)
            this.showError('Failed to update status: ' + error.message)
        }
    }

    async bulkUpdateStatus(status) {
        if (this.selectedOrders.size === 0) {
            this.showError('Please select orders to update')
            return
        }

        if (!confirm(`Update ${this.selectedOrders.size} orders to ${this.getStatusLabel(status)}?`)) {
            return
        }

        try {
            const response = await fetch('api/order_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'bulk_update',
                    order_ids: Array.from(this.selectedOrders),
                    status: status,
                    send_email: true,
                    csrf_token: window.csrfToken
                })
            })

            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const responseText = await response.text()
            const data = this.parseJsonResponse(responseText)
            
            if (data.success) {
                this.showSuccess(`${this.selectedOrders.size} orders updated successfully!`)
                this.clearSelection()
                this.loadOrders()
            } else {
                throw new Error(data.message || 'Failed to update orders')
            }
        } catch (error) {
            console.error('Error bulk updating:', error)
            this.showError('Failed to update orders: ' + error.message)
        }
    }

    showOrderDetails(orderId) {
        const order = this.orders.find(o => o.id === orderId)
        if (!order) return

        const modalBody = document.getElementById('modalBody')
        modalBody.innerHTML = `
            <div style="
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                color: #374151;
                line-height: 1.5;
                padding: 0;
            ">
                <!-- Customer & Contact Info -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 1.5rem;">
                    <div>
                        <h4 style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.875rem; font-weight: 500;">Customer</h4>
                        <p style="margin: 0; font-size: 1rem; font-weight: 600; color: #111827;">${this.escapeHtml(order.customer_name || order.name)}</p>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.875rem; font-weight: 500;">Contact</h4>
                        <p style="margin: 0; font-size: 1rem; color: #374151;">${this.escapeHtml(order.contact_number)}</p>
                        ${order.customer_email ? `<p style="margin: 0.25rem 0 0 0; font-size: 0.875rem; color: #6b7280;">${this.escapeHtml(order.customer_email)}</p>` : ''}
                    </div>
                </div>

                <!-- Service & Type/Size Info -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 1.5rem;">
                    <div>
                        <h4 style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.875rem; font-weight: 500;">Service</h4>
                        <p style="margin: 0; font-size: 1rem; font-weight: 600; color: #111827;">${this.formatCategory(order.category)}</p>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.875rem; font-weight: 500;">Type/Size</h4>
                        <p style="margin: 0; font-size: 1rem; color: #374151;">${this.escapeHtml(order.size)}</p>
                        ${order.custom_size ? `<p style="margin: 0.25rem 0 0 0; font-size: 0.875rem; color: #6b7280;">Custom: ${this.escapeHtml(order.custom_size)}</p>` : ''}
                    </div>
                </div>

                <!-- Quantity & Status -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 1.5rem;">
                    <div>
                        <h4 style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.875rem; font-weight: 500;">Quantity</h4>
                        <p style="margin: 0; font-size: 1.5rem; font-weight: 700; color: #111827;">${order.quantity}</p>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.875rem; font-weight: 500;">Status</h4>
                        <span style="
                            display: inline-block;
                            padding: 0.25rem 0.75rem;
                            background: ${this.getSimpleStatusBg(order.status)};
                            color: ${this.getSimpleStatusText(order.status)};
                            border-radius: 4px;
                            font-size: 0.875rem;
                            font-weight: 500;
                            text-transform: uppercase;
                        ">
                            ${this.getStatusLabel(order.status)}
                        </span>
                    </div>
                </div>

                ${order.category === 't-shirt-print' && order.size_breakdown ? `
                    <!-- Size Breakdown -->
                    <div style="margin-bottom: 1.5rem;">
                        <h4 style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.875rem; font-weight: 500;">Size Breakdown</h4>
                        <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.75rem;">
                            ${this.renderSizeBreakdown(order.size_breakdown)}
                        </div>
                    </div>
                ` : ''}

                <!-- Total Price -->
                <div style="margin-bottom: 1.5rem;">
                    <h4 style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.875rem; font-weight: 500;">Total Price</h4>
                    <p style="margin: 0; font-size: 1.5rem; font-weight: 700; color: #111827;">₱${parseFloat(order.total_price || 0).toLocaleString()}</p>
                </div>

                ${this.renderOrderImages(order)}

                ${order.notes ? `
                    <!-- Customer Notes -->
                    <div style="margin-bottom: 1.5rem;">
                        <h4 style="margin: 0 0 0.75rem 0; color: #6b7280; font-size: 0.875rem; font-weight: 500;">Notes</h4>
                        <div style="
                            background: #f9fafb;
                            border: 1px solid #e5e7eb;
                            border-radius: 6px;
                            padding: 1rem;
                        ">
                            <p id="note-${order.id}" style="
                                margin: 0; 
                                color: #374151; 
                                line-height: 1.5; 
                                white-space: pre-wrap;
                                word-wrap: break-word;
                                word-break: break-word;
                                max-width: 100%;
                                width: 100%;
                                box-sizing: border-box;
                                max-height: ${order.notes.length > 150 ? '4.5em' : 'none'};
                                overflow: hidden;
                            ">${this.escapeHtml(order.notes)}</p>
                            ${order.notes.length > 150 ? `
                                <button id="toggle-note-${order.id}" onclick="toggleNote(${order.id})" style="
                                    background: none;
                                    border: none;
                                    color: #3b82f6;
                                    cursor: pointer;
                                    font-size: 0.875rem;
                                    margin-top: 0.5rem;
                                    padding: 0;
                                    text-decoration: none;
                                    display: flex;
                                    align-items: center;
                                    gap: 0.25rem;
                                ">
                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor">
                                        <path d="M6 8.5L2.5 5h7L6 8.5z"/>
                                    </svg>
                                    Show more
                                </button>
                            ` : ''}
                        </div>
                    </div>
                ` : ''}

                <!-- Status Timeline -->
                <div>
                    <h4 style="margin: 0 0 0.75rem 0; color: #6b7280; font-size: 0.875rem; font-weight: 500;">Order Timeline</h4>
                    <div style="space-y: 0.75rem;">
                        <div style="padding-bottom: 0.75rem; border-bottom: 1px solid #f3f4f6;">
                            <p style="margin: 0; font-weight: 600; color: #111827;">Order Placed:</p>
                            <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">${this.formatDate(order.created_at)}</p>
                        </div>
                        
                        ${order.pricing_set_at ? `
                            <div style="padding-bottom: 0.75rem; border-bottom: 1px solid #f3f4f6;">
                                <p style="margin: 0; font-weight: 600; color: #111827;">Approved & Priced:</p>
                                <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">${this.formatDate(order.pricing_set_at)}</p>
                            </div>
                        ` : ''}
                        
                        ${order.production_started_at ? `
                            <div style="padding-bottom: 0.75rem; border-bottom: 1px solid #f3f4f6;">
                                <p style="margin: 0; font-weight: 600; color: #111827;">Production Started:</p>
                                <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">${this.formatDate(order.production_started_at)}</p>
                            </div>
                        ` : ''}
                        
                        ${order.ready_at ? `
                            <div style="padding-bottom: 0.75rem; border-bottom: 1px solid #f3f4f6;">
                                <p style="margin: 0; font-weight: 600; color: #111827;">Ready for Pickup:</p>
                                <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">${this.formatDate(order.ready_at)}</p>
                            </div>
                        ` : ''}
                        
                        ${order.completed_at ? `
                            <div>
                                <p style="margin: 0; font-weight: 600; color: #111827;">Completed:</p>
                                <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">${this.formatDate(order.completed_at)}</p>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `

        document.getElementById('modalTitle').textContent = `Order #${order.id} Details`
        document.getElementById('orderModal').style.display = 'flex'
        
        // Set up toggle note functionality after modal is rendered
        setTimeout(() => {
            const noteElement = document.getElementById(`note-${order.id}`)
            const toggleButton = document.getElementById(`toggle-note-${order.id}`)
            
            if (noteElement && toggleButton && order.notes && order.notes.length > 150) {
                let isExpanded = false
                
                // Get the natural height of the content
                const originalMaxHeight = noteElement.style.maxHeight
                noteElement.style.maxHeight = 'none'
                const fullHeight = noteElement.scrollHeight + 'px'
                noteElement.style.maxHeight = originalMaxHeight
                
                window.toggleNote = (orderId) => {
                    if (orderId !== order.id) return
                    
                    isExpanded = !isExpanded
                    
                    if (isExpanded) {
                        noteElement.style.maxHeight = fullHeight
                        noteElement.style.overflow = 'visible'
                        toggleButton.innerHTML = `
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor">
                                <path d="M6 3.5L9.5 7h-7L6 3.5z"/>
                            </svg>
                            Show less
                        `
                    } else {
                        noteElement.style.maxHeight = '4.5em'
                        noteElement.style.overflow = 'hidden'
                        toggleButton.innerHTML = `
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor">
                                <path d="M6 8.5L2.5 5h7L6 8.5z"/>
                            </svg>
                            Show more
                        `
                    }
                }
            }
        }, 100)
    }

    closeOrderModal() {
        document.getElementById('orderModal').style.display = 'none'
    }

    // Utility functions
    getStatusIcon(status) {
        const icons = {
            'awaiting production': '⏳',
            'approved': '✅',
            'printing': '🖨️',
            'ready_for_pickup': '📦',
            'on_the_way': '🚚',
            'completed': '✅'
        }
        return icons[status] || '📋'
    }

    getStatusLabel(status) {
        const labels = {
            'all': 'All Orders',
            'approved': 'Awaiting Production',
            'awaiting production': 'Awaiting Production',
            'printing': 'Printing',
            'ready_for_pickup': 'Ready for Pickup',
            'on_the_way': 'On the Way',
            'completed': 'Completed'
        }
        return labels[status] || status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
    }

    getStatusBgColor(status) {
        const colors = {
            'approved': '#e6fffa',
            'printing': '#ebf8ff',
            'ready_for_pickup': '#fffaf0',
            'on_the_way': '#f0f9ff',
            'completed': '#f0fff4'
        }
        return colors[status] || '#f7fafc'
    }

    getStatusTextColor(status) {
        const colors = {
            'approved': '#234e52',
            'printing': '#2a4365',
            'ready_for_pickup': '#744210',
            'on_the_way': '#1e3a8a',
            'completed': '#22543d'
        }
        return colors[status] || '#4a5568'
    }

    getStatusBorderColor(status) {
        const colors = {
            'approved': '#81e6d9',
            'printing': '#90cdf4',
            'ready_for_pickup': '#f6e05e',
            'on_the_way': '#93c5fd',
            'completed': '#9ae6b4'
        }
        return colors[status] || '#cbd5e0'
    }

    getSimpleStatusBg(status) {
        const colors = {
            'approved': '#dbeafe',
            'printing': '#dbeafe',
            'ready_for_pickup': '#fef3c7',
            'on_the_way': '#e0e7ff',
            'completed': '#d1fae5'
        }
        return colors[status] || '#f3f4f6'
    }

    getSimpleStatusText(status) {
        const colors = {
            'approved': '#1e40af',
            'printing': '#1e40af',
            'ready_for_pickup': '#92400e',
            'on_the_way': '#3730a3',
            'completed': '#065f46'
        }
        return colors[status] || '#374151'
    }

    formatPaymentStatus(status) {
        const labels = {
            'partial_paid': 'Partially Paid',
            'fully_paid': 'Fully Paid',
            'awaiting_payment': 'Awaiting Payment'
        }
        return labels[status] || status
    }

    formatCategory(category) {
        if (!category) return 'N/A'
        return category.split('-').map(word => 
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ')
    }

    formatDate(dateString) {
        if (!dateString) return 'N/A'
        const date = new Date(dateString)
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 
                       'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
        const day = date.getDate()
        const month = months[date.getMonth()]
        const year = date.getFullYear()
        const time = date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})
        return `${day} ${month} ${year} ${time}`
    }

    renderOrderImages(order) {
        // Check if order has any images to display
        if (!this.hasOrderImages(order)) {
            return ''
        }
        
        let imagesHtml = ''
        
        // Regular image upload (for non-T-shirt/non-card orders or T-shirt orders with regular uploads)
        if (order.image_path && 
            (order.category !== 't-shirt-print' || order.design_option !== 'customize') &&
            !(order.category === 'card-print' && (order.size === 'calling' || order.size === 'business'))) {
            imagesHtml += this.renderAttachedImages(order.image_path)
        }
        
        // T-shirt customization images
        if (order.category === 't-shirt-print' && order.design_option === 'customize') {
            imagesHtml += `
                <div style="margin-bottom: 1.5rem;">
                    <h4 style="margin: 0 0 0.75rem 0; color: #6b7280; font-size: 0.875rem; font-weight: 500;">T-shirt Customization Details</h4>
                    <div style="
                        background: #f8fafc;
                        border: 2px dashed #e2e8f0;
                        border-radius: 12px;
                        padding: 1rem;
                    ">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            ${order.front_image_path ? `
                                <div>
                                    <div style="font-size: 0.75rem; color: #718096; margin-bottom: 0.25rem; font-weight: 600;">Front Design</div>
                                    <div style="
                                        background: #ffffff;
                                        border: 1px solid #e2e8f0;
                                        border-radius: 8px;
                                        padding: 8px;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        min-height: 100px;
                                        cursor: pointer;
                                    " onclick="window.open('${this.escapeHtml(order.front_image_path)}', '_blank')">
                                        <img src="${this.escapeHtml(order.front_image_path)}" alt="Front Design" style="
                                            max-width: 100%;
                                            max-height: 150px;
                                            border-radius: 6px;
                                            object-fit: contain;
                                        ">
                                    </div>
                                    <div style="margin-top: 0.4rem;">
                                        <a href="${this.escapeHtml(order.front_image_path)}" download target="_blank" rel="noopener" style="
                                            display: inline-flex;
                                            align-items: center;
                                            gap: 0.4rem;
                                            padding: 0.4rem 0.75rem;
                                            border-radius: 6px;
                                            background: #f1f5f9;
                                            color: #1f2937;
                                            border: 1px solid #cbd5e1;
                                            text-decoration: none;
                                            font-size: 0.8rem;
                                        ">
                                            <i class="fa-solid fa-download"></i>
                                            Download Front
                                        </a>
                                    </div>
                                </div>
                            ` : ''}
                            
                            ${order.back_image_path ? `
                                <div>
                                    <div style="font-size: 0.75rem; color: #718096; margin-bottom: 0.25rem; font-weight: 600;">Back Design</div>
                                    <div style="
                                        background: #ffffff;
                                        border: 1px solid #e2e8f0;
                                        border-radius: 8px;
                                        padding: 8px;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        min-height: 100px;
                                        cursor: pointer;
                                    " onclick="window.open('${this.escapeHtml(order.back_image_path)}', '_blank')">
                                        <img src="${this.escapeHtml(order.back_image_path)}" alt="Back Design" style="
                                            max-width: 100%;
                                            max-height: 150px;
                                            border-radius: 6px;
                                            object-fit: contain;
                                        ">
                                    </div>
                                    <div style="margin-top: 0.4rem;">
                                        <a href="${this.escapeHtml(order.back_image_path)}" download target="_blank" rel="noopener" style="
                                            display: inline-flex;
                                            align-items: center;
                                            gap: 0.4rem;
                                            padding: 0.4rem 0.75rem;
                                            border-radius: 6px;
                                            background: #f1f5f9;
                                            color: #1f2937;
                                            border: 1px solid #cbd5e1;
                                            text-decoration: none;
                                            font-size: 0.8rem;
                                        ">
                                            <i class="fa-solid fa-download"></i>
                                            Download Back
                                        </a>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                        
                        ${order.tag_image_path ? `
                            <div style="margin-top: 1rem;">
                                <div style="font-size: 0.75rem; color: #718096; margin-bottom: 0.25rem; font-weight: 600;">
                                    Tag Design ${order.tag_location ? `(${this.escapeHtml(order.tag_location)})` : ''}
                                </div>
                                <div style="display: flex; gap: 1rem; align-items: flex-start;">
                                    <div style="
                                        background: #ffffff;
                                        border: 1px solid #e2e8f0;
                                        border-radius: 8px;
                                        padding: 8px;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        min-height: 80px;
                                        flex: 0 0 120px;
                                        cursor: pointer;
                                    " onclick="window.open('${this.escapeHtml(order.tag_image_path)}', '_blank')">
                                        <img src="${this.escapeHtml(order.tag_image_path)}" alt="Tag Design" style="
                                            max-width: 100%;
                                            max-height: 100px;
                                            border-radius: 6px;
                                            object-fit: contain;
                                        ">
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="margin-bottom: 0.5rem;">
                                            <a href="${this.escapeHtml(order.tag_image_path)}" download target="_blank" rel="noopener" style="
                                                display: inline-flex;
                                                align-items: center;
                                                gap: 0.4rem;
                                                padding: 0.4rem 0.75rem;
                                                border-radius: 6px;
                                                background: #f1f5f9;
                                                color: #1f2937;
                                                border: 1px solid #cbd5e1;
                                                text-decoration: none;
                                                font-size: 0.8rem;
                                            ">
                                                <i class="fa-solid fa-download"></i>
                                                Download Tag
                                            </a>
                                        </div>
                                        ${order.tag_location ? `
                                            <div style="font-size: 0.75rem; color: #4a5568;">
                                                <strong>Location:</strong> ${this.escapeHtml(order.tag_location)}
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `
        }
        
        // Card customization images (for calling and business cards)
        if (order.category === 'card-print' && (order.size === 'calling' || order.size === 'business')) {
            imagesHtml += `
                <div style="margin-bottom: 1.5rem;">
                    <h4 style="margin: 0 0 0.75rem 0; color: #6b7280; font-size: 0.875rem; font-weight: 500;">Card Design Details</h4>
                    <div style="
                        background: #f8fafc;
                        border: 2px dashed #e2e8f0;
                        border-radius: 12px;
                        padding: 1rem;
                    ">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            ${order.front_image_path ? `
                                <div>
                                    <div style="font-size: 0.75rem; color: #718096; margin-bottom: 0.25rem; font-weight: 600;">Front Design</div>
                                    <div style="
                                        background: white;
                                        border: 1px solid #e2e8f0;
                                        border-radius: 8px;
                                        padding: 0.5rem;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        min-height: 100px;
                                        cursor: pointer;
                                    " onclick="window.open('${this.escapeHtml(order.front_image_path)}', '_blank')">
                                        <img src="${this.escapeHtml(order.front_image_path)}" alt="Card Front Design" style="
                                            max-width: 100%;
                                            max-height: 150px;
                                            border-radius: 6px;
                                            object-fit: contain;
                                        ">
                                    </div>
                                    <div style="margin-top: 0.4rem;">
                                        <a href="${this.escapeHtml(order.front_image_path)}" download target="_blank" rel="noopener" style="
                                            display: inline-flex;
                                            align-items: center;
                                            gap: 0.4rem;
                                            color: #3b82f6;
                                            text-decoration: none;
                                            font-size: 0.8rem;
                                            padding: 0.25rem 0.5rem;
                                            border-radius: 4px;
                                            border: 1px solid #cbd5e1;
                                            background: white;
                                            transition: all 0.2s;
                                        " onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                                            <i class="fa-solid fa-download"></i>
                                            Download Front
                                        </a>
                                    </div>
                                </div>
                            ` : ''}
                            
                            ${order.back_image_path ? `
                                <div>
                                    <div style="font-size: 0.75rem; color: #718096; margin-bottom: 0.25rem; font-weight: 600;">Back Design</div>
                                    <div style="
                                        background: white;
                                        border: 1px solid #e2e8f0;
                                        border-radius: 8px;
                                        padding: 0.5rem;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        min-height: 100px;
                                        cursor: pointer;
                                    " onclick="window.open('${this.escapeHtml(order.back_image_path)}', '_blank')">
                                        <img src="${this.escapeHtml(order.back_image_path)}" alt="Card Back Design" style="
                                            max-width: 100%;
                                            max-height: 150px;
                                            border-radius: 6px;
                                            object-fit: contain;
                                        ">
                                    </div>
                                    <div style="margin-top: 0.4rem;">
                                        <a href="${this.escapeHtml(order.back_image_path)}" download target="_blank" rel="noopener" style="
                                            display: inline-flex;
                                            align-items: center;
                                            gap: 0.4rem;
                                            color: #3b82f6;
                                            text-decoration: none;
                                            font-size: 0.8rem;
                                            padding: 0.25rem 0.5rem;
                                            border-radius: 4px;
                                            border: 1px solid #cbd5e1;
                                            background: white;
                                            transition: all 0.2s;
                                        " onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                                            <i class="fa-solid fa-download"></i>
                                            Download Back
                                        </a>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `
        }
        
        return imagesHtml
    }

    renderAttachedImages(imagePath) {
        if (!imagePath) return ''
        
        let images = []
        
        // Check if it's a JSON array of multiple images
        try {
            const parsed = JSON.parse(imagePath)
            if (Array.isArray(parsed)) {
                images = parsed
            } else {
                images = [imagePath]
            }
        } catch (e) {
            // Single image path
            images = [imagePath]
        }
        
        if (images.length === 0) return ''
        
        return `
            <div style="margin-bottom: 1.5rem;">
                <h4 style="margin: 0 0 0.75rem 0; color: #6b7280; font-size: 0.875rem; font-weight: 500;">
                    Attached Image${images.length > 1 ? 's' : ''}
                </h4>
                <div style="
                    background: #f8fafc;
                    border: 2px dashed #e2e8f0;
                    border-radius: 12px;
                    padding: 1rem;
                ">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                        ${images.map((img, index) => `
                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                <div style="
                                    background: #ffffff;
                                    border: 1px solid #e2e8f0;
                                    border-radius: 8px;
                                    padding: 8px;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    min-height: 120px;
                                    cursor: pointer;
                                " onclick="window.open('${this.escapeHtml(img)}', '_blank')">
                                    <img src="${this.escapeHtml(img)}" alt="Order Image ${index + 1}" style="
                                        max-width: 100%;
                                        max-height: 150px;
                                        border-radius: 6px;
                                        object-fit: contain;
                                    ">
                                </div>
                                <a href="${this.escapeHtml(img)}" download target="_blank" rel="noopener" style="
                                    display: inline-flex;
                                    align-items: center;
                                    justify-content: center;
                                    gap: 0.4rem;
                                    padding: 0.4rem 0.75rem;
                                    border-radius: 6px;
                                    background: #f1f5f9;
                                    color: #1f2937;
                                    border: 1px solid #cbd5e1;
                                    text-decoration: none;
                                    font-size: 0.8rem;
                                ">
                                    <i class="fa-solid fa-download"></i>
                                    Download ${images.length > 1 ? `#${index + 1}` : 'Image'}
                                </a>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        `
    }

    hasOrderImages(order) {
        // Check for regular image upload
        if (order.image_path) {
            return true
        }
        
        // Check for T-shirt customization images
        if (order.category === 't-shirt-print' && order.design_option === 'customize') {
            return order.front_image_path || order.back_image_path || order.tag_image_path
        }
        
        // Check for card customization images
        if (order.category === 'card-print' && (order.size === 'calling' || order.size === 'business')) {
            return order.front_image_path || order.back_image_path
        }
        
        return false
    }

    escapeHtml(text) {
        if (!text) return ''
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }

    showSuccess(message) {
        if (this.toast) {
            this.toast.success(message)
        } else {
            // Fallback toast
            this.createToast(message, 'success')
        }
    }

    showError(message) {
        if (this.toast) {
            this.toast.error(message)
        } else {
            // Fallback toast
            this.createToast(message, 'error')
        }
    }

    createToast(message, type) {
        const toast = document.createElement('div')
        toast.className = `toast toast-${type}`
        toast.textContent = message
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#10b981' : '#ef4444'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `
        document.body.appendChild(toast)
        setTimeout(() => toast.remove(), type === 'success' ? 3000 : 5000)
    }

    renderSizeBreakdown(sizeBreakdownJson) {
        try {
            const sizeBreakdown = JSON.parse(sizeBreakdownJson);
            
            if (!Array.isArray(sizeBreakdown) || sizeBreakdown.length === 0) {
                return '<span style="color: #6b7280; font-style: italic;">No size breakdown available</span>';
            }
            
            return `
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
                    ${sizeBreakdown.map(item => `
                        <div style="
                            display: flex; 
                            align-items: center; 
                            background: #ffffff; 
                            border: 1px solid #d1d5db; 
                            border-radius: 6px; 
                            padding: 0.25rem 0.5rem;
                            font-size: 0.8rem;
                        ">
                            <span style="font-weight: 600; color: #374151; margin-right: 0.25rem;">${this.escapeHtml(item.size)}</span>
                            <span style="color: #6b7280;">×${item.quantity}</span>
                        </div>
                    `).join('')}
                </div>
            `;
        } catch (error) {
            console.error('Error parsing size breakdown:', error);
            return '<span style="color: #ef4444; font-style: italic;">Error displaying size breakdown</span>';
        }
    }
}

// Make the class globally accessible
window.OrderManagementModule = OrderManagementModule
