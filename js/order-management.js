/**
 * Order Management System
 * Handles production tracking and status updates for approved orders
 */

class OrderManagement {
    constructor() {
        this.orders = []
        this.filteredOrders = []
        this.selectedOrders = new Set()
        this.currentFilter = 'all'
        this.init()
    }

    init() {
        console.log('Initializing Order Management System...')
        this.setupEventListeners()
        this.initializeTabs()
        this.loadOrders()
        
        // Auto-refresh every 30 seconds
        setInterval(() => this.loadOrders(), 30000)
    }
    
    initializeTabs() {
        // Make sure "All Orders" tab is active by default
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active')
        })
        const allOrdersTab = document.querySelector('[data-status="all"]')
        if (allOrdersTab) {
            allOrdersTab.classList.add('active')
        }
        this.currentFilter = 'all'
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

        // Status update form
        const statusForm = document.getElementById('statusUpdateForm')
        if (statusForm) {
            statusForm.addEventListener('submit', (e) => this.handleStatusUpdate(e))
        }
    }

    async loadOrders() {
        try {
            const response = await fetch('api/order_management.php?action=get_orders', {
                credentials: 'include'
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const data = await response.json()
            
            if (data.success) {
                this.orders = data.orders || []
                console.log('Orders loaded:', this.orders.length)
                console.log('Order statuses:', this.orders.map(o => `${o.id}: ${o.status}`))
                this.updateStatusCounts()
                this.displayOrders()
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
            approved: this.orders.filter(o => o.status === 'pending' && (o.payment_status === 'partial_paid' || o.payment_status === 'fully_paid')).length,
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
    }

    filterByStatus(status) {
        this.currentFilter = status
        
        // Update active tab
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active')
        })
        document.querySelector(`[data-status="${status}"]`).classList.add('active')
        
        this.displayOrders()
    }

    displayOrders() {
        const grid = document.getElementById('ordersGrid')
        if (!grid) return

        // Filter orders based on current filter
        if (this.currentFilter === 'all') {
            this.filteredOrders = this.orders
        } else if (this.currentFilter === 'approved') {
            this.filteredOrders = this.orders.filter(order => 
                order.status === 'pending' && (order.payment_status === 'partial_paid' || order.payment_status === 'fully_paid')
            )
        } else {
            this.filteredOrders = this.orders.filter(order => order.status === this.currentFilter)
        }
        
        console.log(`Filter: ${this.currentFilter}, Showing: ${this.filteredOrders.length}/${this.orders.length} orders`)

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
            <div class="order-card ${isSelected ? 'selected' : ''}" onclick="this.toggleSelection(${order.id})">
                <input type="checkbox" class="select-checkbox" ${isSelected ? 'checked' : ''} 
                       onchange="event.stopPropagation(); window.orderManager.toggleSelection(${order.id})">
                
                <div class="order-header-info">
                    <div class="order-id">#${order.id}</div>
                    <div class="order-date">${this.formatDate(order.created_at)}</div>
                </div>

                <div class="order-status-badge ${order.status === 'approved' ? 'approved' : order.status}">
                    ${this.getStatusIcon(statusDisplay)} ${this.getStatusLabel(statusDisplay)}
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
        if (this.selectedOrders.size > 0) {
            quickActions.style.display = 'block'
        } else {
            quickActions.style.display = 'none'
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

        document.getElementById('orderId').value = orderId
        document.getElementById('statusModal').style.display = 'flex'
        
        // Set appropriate status options based on current status
        const statusSelect = document.getElementById('newStatus')
        const currentStatus = order.status === 'approved' ? 'approved' : order.status
        
        statusSelect.innerHTML = this.getStatusOptions(currentStatus)
    }

    getStatusOptions(currentStatus) {
        const options = ['<option value="">Select status...</option>']
        
        switch (currentStatus) {
            case 'approved':
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
        }
        
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

            const data = await response.json()
            
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

            const data = await response.json()
            
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
            <div class="order-detail-content">
                <div class="detail-section">
                    <h4>Customer Information</h4>
                    <p><strong>Name:</strong> ${this.escapeHtml(order.customer_name || order.name)}</p>
                    <p><strong>Contact:</strong> ${this.escapeHtml(order.contact_number)}</p>
                    ${order.customer_email ? `<p><strong>Email:</strong> ${this.escapeHtml(order.customer_email)}</p>` : ''}
                </div>

                <div class="detail-section">
                    <h4>Order Details</h4>
                    <p><strong>Service:</strong> ${this.formatCategory(order.category)}</p>
                    <p><strong>Size:</strong> ${this.escapeHtml(order.size)}</p>
                    ${order.custom_size ? `<p><strong>Custom Size:</strong> ${this.escapeHtml(order.custom_size)}</p>` : ''}
                    <p><strong>Quantity:</strong> ${order.quantity}</p>
                    <p><strong>Total Price:</strong> ₱${parseFloat(order.total_price || 0).toLocaleString()}</p>
                </div>

                ${order.notes ? `
                    <div class="detail-section">
                        <h4>Customer Notes</h4>
                        <p>${this.escapeHtml(order.notes)}</p>
                    </div>
                ` : ''}

                <div class="detail-section">
                    <h4>Status Timeline</h4>
                    <div class="timeline">
                        <div class="timeline-item">
                            <strong>Order Placed:</strong> ${this.formatDate(order.created_at)}
                        </div>
                        ${order.pricing_set_at ? `
                            <div class="timeline-item">
                                <strong>Approved & Priced:</strong> ${this.formatDate(order.pricing_set_at)}
                            </div>
                        ` : ''}
                        ${order.production_started_at ? `
                            <div class="timeline-item">
                                <strong>Production Started:</strong> ${this.formatDate(order.production_started_at)}
                            </div>
                        ` : ''}
                        ${order.ready_at ? `
                            <div class="timeline-item">
                                <strong>Ready for Pickup:</strong> ${this.formatDate(order.ready_at)}
                            </div>
                        ` : ''}
                        ${order.completed_at ? `
                            <div class="timeline-item">
                                <strong>Completed:</strong> ${this.formatDate(order.completed_at)}
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `

        document.getElementById('modalTitle').textContent = `Order #${order.id} Details`
        document.getElementById('orderModal').style.display = 'flex'
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
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})
    }

    escapeHtml(text) {
        if (!text) return ''
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }

    showSuccess(message) {
        // Simple toast notification
        const toast = document.createElement('div')
        toast.className = 'toast toast-success'
        toast.textContent = message
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `
        document.body.appendChild(toast)
        setTimeout(() => toast.remove(), 3000)
    }

    showError(message) {
        const toast = document.createElement('div')
        toast.className = 'toast toast-error'
        toast.textContent = message
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #ef4444;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `
        document.body.appendChild(toast)
        setTimeout(() => toast.remove(), 5000)
    }
}

// Initialize the order management system
window.orderManager = new OrderManagement()
