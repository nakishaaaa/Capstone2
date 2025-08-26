class MyOrdersModule {
    constructor() {
        this.currentPage = 1;
        this.currentStatus = 'all';
        this.currentSearch = '';
        this.currentSort = 'newest';
        this.orders = [];
        this.totalPages = 1;
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadOrderCounts();
        this.loadOrders();
        this.checkPaymentConfirmation();
    }

    bindEvents() {
        // Filter tabs
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const status = e.currentTarget.dataset.status;
                this.filterByStatus(status);
            });
        });

        // Search functionality
        const searchInput = document.getElementById('orderSearch');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.currentSearch = e.target.value;
                    this.currentPage = 1;
                    this.loadOrders();
                }, 500);
            });
        }

        // Sort functionality
        const sortSelect = document.getElementById('sortBy');
        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => {
                this.currentSort = e.target.value;
                this.currentPage = 1;
                this.loadOrders();
            });
        }

        // User dropdown
        const userDropdownBtn = document.getElementById('userDropdownBtn');
        const userDropdownMenu = document.getElementById('userDropdownMenu');
        
        if (userDropdownBtn && userDropdownMenu) {
            userDropdownBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdownMenu.classList.toggle('show');
            });

            document.addEventListener('click', () => {
                userDropdownMenu.classList.remove('show');
            });
        }

        // Modal close
        const closeOrderModal = document.getElementById('closeOrderModal');
        if (closeOrderModal) {
            closeOrderModal.addEventListener('click', () => {
                this.closeOrderModal();
            });
        }

        // Close modal on outside click
        const orderModal = document.getElementById('orderDetailModal');
        if (orderModal) {
            orderModal.addEventListener('click', (e) => {
                if (e.target === orderModal) {
                    this.closeOrderModal();
                }
            });
        }
    }

    async loadOrderCounts() {
        try {
            const response = await fetch('api/my_orders.php?action=get_order_counts');
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('allCount').textContent = data.counts.all;
                document.getElementById('pendingCount').textContent = data.counts.pending;
                document.getElementById('approvedCount').textContent = data.counts.approved;
                document.getElementById('rejectedCount').textContent = data.counts.rejected;
            }
        } catch (error) {
            console.error('Error loading order counts:', error);
        }
    }

    async loadOrders() {
        this.showLoadingState();
        
        try {
            const params = new URLSearchParams({
                action: 'get_orders',
                status: this.currentStatus,
                search: this.currentSearch,
                sort: this.currentSort,
                page: this.currentPage
            });

            const response = await fetch(`api/my_orders.php?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.orders = data.orders;
                this.totalPages = data.pagination.totalPages;
                this.renderOrders();
                this.renderPagination(data.pagination);
            } else {
                this.showError('Failed to load orders');
            }
        } catch (error) {
            console.error('Error loading orders:', error);
            this.showError('Failed to load orders');
        }
    }

    filterByStatus(status) {
        // Update active tab
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelector(`[data-status="${status}"]`).classList.add('active');
        
        this.currentStatus = status;
        this.currentPage = 1;
        this.loadOrders();
    }

    showLoadingState() {
        const ordersList = document.getElementById('ordersList');
        const loadingState = document.getElementById('loadingState');
        const emptyState = document.getElementById('emptyState');
        
        loadingState.style.display = 'flex';
        emptyState.style.display = 'none';
        
        // Hide existing orders
        const existingOrders = ordersList.querySelectorAll('.order-card');
        existingOrders.forEach(order => order.remove());
    }

    renderOrders() {
        const ordersList = document.getElementById('ordersList');
        const loadingState = document.getElementById('loadingState');
        const emptyState = document.getElementById('emptyState');
        
        loadingState.style.display = 'none';
        
        // Clear existing orders
        const existingOrders = ordersList.querySelectorAll('.order-card');
        existingOrders.forEach(order => order.remove());
        
        if (this.orders.length === 0) {
            emptyState.style.display = 'flex';
            return;
        }
        
        emptyState.style.display = 'none';
        
        this.orders.forEach(order => {
            const orderCard = this.createOrderCard(order);
            ordersList.appendChild(orderCard);
        });
    }

    createOrderCard(order) {
        const card = document.createElement('div');
        card.className = 'order-card';
        card.innerHTML = `
            <div class="order-header">
                <div class="order-info">
                    <div class="order-number">${order.orderNumber}</div>
                    <div class="order-date">${order.createdAtFormatted}</div>
                </div>
                <div class="order-status">
                    <span class="status-badge status-${order.status}">${order.statusDisplay}</span>
                </div>
            </div>
            
            <div class="order-content">
                <div class="order-details">
                    <div class="order-image">
                        ${order.image_url ? 
                            `<img src="${order.image_url}" alt="Order image" onerror="this.src='images/placeholder.png'">` :
                            `<div class="image-placeholder"><i class="fas fa-image"></i></div>`
                        }
                    </div>
                    <div class="order-description">
                        <h3>${order.categoryDisplay}</h3>
                        <div class="order-specs">
                            <span class="spec-item"><i class="fas fa-expand-arrows-alt"></i> ${order.size}</span>
                            <span class="spec-item"><i class="fas fa-hashtag"></i> ${order.quantity} pcs</span>
                        </div>
                        ${order.notes ? `<p class="order-notes">${order.notes.substring(0, 100)}${order.notes.length > 100 ? '...' : ''}</p>` : ''}
                        ${order.adminResponse ? `<div class="admin-response"><i class="fas fa-comment"></i> ${order.adminResponse}</div>` : ''}
                    </div>
                </div>
                
                <div class="order-actions">
                    <button class="btn btn-outline" onclick="myOrdersModule.viewOrderDetail(${order.id})">
                        <i class="fas fa-eye"></i>
                        View Details
                    </button>
                    ${order.status === 'approved' ? 
                        order.hasPayment && order.needsPayment ? 
                            `<button class="btn btn-primary" onclick="myOrdersModule.showPaymentOptions(${order.id})">
                                <i class="fas fa-credit-card"></i>
                                Make Payment
                            </button>` :
                            order.hasPayment && order.isPaid ?
                                `<div class="payment-status-indicator">
                                    <button class="btn btn-success" disabled>
                                        <i class="fas fa-check-circle"></i>
                                        Payment Received
                                    </button>
                                    <span class="payment-badge ${order.paymentStatus === 'partial_paid' ? 'partial' : 'full'}">
                                        ${order.paymentStatus === 'partial_paid' ? 'Downpayment Paid' : 'Fully Paid'}
                                    </span>
                                </div>` :
                                `<button class="btn btn-primary">
                                    <i class="fas fa-phone"></i>
                                    Contact Us
                                </button>`
                        : ''
                    }
                </div>
            
            <div class="order-footer">
                <div class="order-timeline-mini">
                    <div class="timeline-step ${order.status !== 'pending' ? 'completed' : ''}">
                        <i class="fas fa-plus-circle"></i>
                        <span>Placed</span>
                    </div>
                    <div class="timeline-step ${order.status === 'approved' || order.status === 'rejected' ? 'completed' : ''}">
                        <i class="fas fa-search"></i>
                        <span>Reviewed</span>
                    </div>
                    <div class="timeline-step ${order.status === 'approved' ? 'completed' : order.status === 'rejected' ? 'rejected' : ''}">
                        <i class="fas fa-${order.status === 'approved' ? 'check-circle' : order.status === 'rejected' ? 'times-circle' : 'clock'}"></i>
                        <span>${order.status === 'approved' ? 'Approved' : order.status === 'rejected' ? 'Rejected' : 'Pending'}</span>
                    </div>
                </div>
            </div>
        `;
        
        return card;
    }

    async viewOrderDetail(orderId) {
        try {
            const response = await fetch(`api/my_orders.php?action=get_order_detail&order_id=${orderId}`);
            const data = await response.json();
            
            if (data.success) {
                this.showOrderModal(data.order);
            } else {
                this.showError('Failed to load order details');
            }
        } catch (error) {
            console.error('Error loading order detail:', error);
            this.showError('Failed to load order details');
        }
    }

    showOrderModal(order) {
        const modal = document.getElementById('orderDetailModal');
        const content = document.getElementById('orderDetailContent');
        
        content.innerHTML = `
            <div class="order-detail-header">
                <div class="order-detail-info">
                    <h3>${order.orderNumber}</h3>
                    <div class="order-detail-meta">
                        <span class="status-badge status-${order.status}">${order.statusDisplay}</span>
                        <span class="order-date">Placed on ${order.createdAtFormatted}</span>
                    </div>
                </div>
            </div>
            
            <div class="order-detail-content">
                <div class="detail-section">
                    <h4><i class="fas fa-info-circle"></i> Order Information</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Service Category</label>
                            <span>${order.categoryDisplay}</span>
                        </div>
                        <div class="detail-item">
                            <label>Size</label>
                            <span>${order.size}</span>
                        </div>
                        <div class="detail-item">
                            <label>Quantity</label>
                            <span>${order.quantity} pieces</span>
                        </div>
                        <div class="detail-item">
                            <label>Customer Name</label>
                            <span>${order.customerName}</span>
                        </div>
                        <div class="detail-item">
                            <label>Contact Number</label>
                            <span>+63${order.contactNumber}</span>
                        </div>
                    </div>
                    
                    ${order.notes ? `
                        <div class="detail-item full-width">
                            <label>Notes</label>
                            <div class="notes-content">${order.notes}</div>
                        </div>
                    ` : ''}
                    
                    ${order.image_url ? `
                        <div class="detail-item full-width">
                            <label>Attached Image</label>
                            <div class="image-preview">
                                <img src="${order.image_url}" alt="Order image" onclick="window.open('${order.image_url}', '_blank')">
                                <button class="btn btn-outline btn-sm" onclick="window.open('${order.image_url}', '_blank')">
                                    <i class="fas fa-external-link-alt"></i>
                                    View Full Size
                                </button>
                            </div>
                        </div>
                    ` : ''}
                </div>
                
                ${order.adminResponse ? `
                    <div class="detail-section">
                        <h4><i class="fas fa-comment"></i> Admin Response</h4>
                        <div class="admin-response-detail">
                            ${order.adminResponse}
                        </div>
                    </div>
                ` : ''}
                
                <div class="detail-section">
                    <h4><i class="fas fa-history"></i> Order Timeline</h4>
                    <div class="order-timeline">
                        ${order.timeline.map(step => `
                            <div class="timeline-item ${step.completed ? 'completed' : ''} ${step.status === 'rejected' ? 'rejected' : ''}">
                                <div class="timeline-icon">
                                    <i class="${step.icon}"></i>
                                </div>
                                <div class="timeline-content">
                                    <h5>${step.title}</h5>
                                    <p>${step.description}</p>
                                    <span class="timeline-date">${step.timestampFormatted}</span>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
            
            <div class="order-detail-actions">
                ${order.status === 'approved' ? 
                    order.hasPayment && order.needsPayment ? 
                        `<button class="btn btn-primary" onclick="myOrdersModule.showPaymentOptions(${order.id})">
                            <i class="fas fa-credit-card"></i>
                            Make Payment
                        </button>` :
                        order.hasPayment && !order.needsPayment ?
                            `<button class="btn btn-success" disabled>
                                <i class="fas fa-check"></i>
                                Payment Complete
                            </button>` :
                            `<button class="btn btn-primary">
                                <i class="fas fa-phone"></i>
                                Contact for Pickup
                            </button>`
                    : ''
                }
                <button class="btn btn-outline" onclick="myOrdersModule.closeOrderModal()">
                    <i class="fas fa-times"></i>
                    Close
                </button>
            </div>
        `;
        
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    closeOrderModal() {
        const modal = document.getElementById('orderDetailModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    renderPagination(pagination) {
        const paginationContainer = document.getElementById('paginationContainer');
        const paginationInfo = document.getElementById('paginationInfo');
        const paginationNumbers = document.getElementById('paginationNumbers');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        
        if (pagination.totalPages <= 1) {
            paginationContainer.style.display = 'none';
            return;
        }
        
        paginationContainer.style.display = 'flex';
        
        // Update pagination info
        const start = ((pagination.currentPage - 1) * pagination.ordersPerPage) + 1;
        const end = Math.min(pagination.currentPage * pagination.ordersPerPage, pagination.totalOrders);
        paginationInfo.textContent = `Showing ${start}-${end} of ${pagination.totalOrders} orders`;
        
        // Update navigation buttons
        prevBtn.disabled = !pagination.hasPrev;
        nextBtn.disabled = !pagination.hasNext;
        
        prevBtn.onclick = () => {
            if (pagination.hasPrev) {
                this.currentPage--;
                this.loadOrders();
            }
        };
        
        nextBtn.onclick = () => {
            if (pagination.hasNext) {
                this.currentPage++;
                this.loadOrders();
            }
        };
        
        // Generate page numbers
        paginationNumbers.innerHTML = '';
        const maxVisiblePages = 5;
        let startPage = Math.max(1, pagination.currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(pagination.totalPages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = `pagination-number ${i === pagination.currentPage ? 'active' : ''}`;
            pageBtn.textContent = i;
            pageBtn.onclick = () => {
                this.currentPage = i;
                this.loadOrders();
            };
            paginationNumbers.appendChild(pageBtn);
        }
    }

    showError(message) {
        const loadingState = document.getElementById('loadingState');
        loadingState.style.display = 'none';
        
        // Show toast notification
        this.showToast(message, 'error');
    }

    showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        toastContainer.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 5000);
    }

    async showPaymentOptions(orderId) {
        try {
            const response = await fetch(`api/my_orders.php?action=get_order_detail&order_id=${orderId}`);
            const data = await response.json();
            
            if (data.success) {
                // Generate payment options based on order data
                const order = data.order;
                const paymentOptions = this.generatePaymentOptions(order);
                this.showPaymentModal(order, paymentOptions);
            } else {
                this.showToast(data.error || 'Failed to load payment options', 'error');
            }
        } catch (error) {
            console.error('Error loading payment options:', error);
            this.showToast('Failed to load payment options', 'error');
        }
    }

    generatePaymentOptions(order) {
        const totalAmount = parseFloat(order.totalPrice);
        const paidAmount = parseFloat(order.paidAmount || 0);
        const remainingAmount = totalAmount - paidAmount;
        const PAYMONGO_MINIMUM = 100; // PayMongo minimum amount
        
        const options = [];
        
        if (paidAmount === 0) {
            // First payment options - both downpayment and full payment
            let downpaymentAmount = totalAmount * 0.70;
            
            // Ensure downpayment meets PayMongo minimum
            if (downpaymentAmount < PAYMONGO_MINIMUM) {
                downpaymentAmount = Math.min(PAYMONGO_MINIMUM, totalAmount);
            }
            
            // Only add downpayment option if it's less than total amount
            if (downpaymentAmount < totalAmount) {
                const remainingAfterDownpayment = totalAmount - downpaymentAmount;
                const percentage = Math.round((downpaymentAmount / totalAmount) * 100);
                
                options.push({
                    type: 'downpayment',
                    label: `Downpayment (${percentage}%)`,
                    description: `Pay ₱${downpaymentAmount.toFixed(2)} now, remaining ₱${remainingAfterDownpayment.toFixed(2)} on pickup`,
                    amount: downpaymentAmount
                });
            }
            
            // Always add full payment option
            options.push({
                type: 'full_payment',
                label: 'Full Payment (100%)',
                description: 'Pay the complete amount now',
                amount: totalAmount
            });
        } else if (remainingAmount > 0) {
            // Final payment - remaining amount
            options.push({
                type: 'final_payment',
                label: 'Final Payment',
                description: 'Complete your order payment',
                amount: remainingAmount
            });
        }
        
        return options;
    }

    showPaymentModal(order, paymentOptions) {
        // Create payment modal if it doesn't exist
        let modal = document.getElementById('paymentModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'paymentModal';
            modal.className = 'modal payment-modal';
            document.body.appendChild(modal);
        }

        modal.innerHTML = `
            <div class="modal-content payment-modal-content" style="
                max-width: 420px;
                margin: 3rem auto;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                overflow: hidden;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                border: 1px solid #e5e7eb;
            ">
                <div class="modal-header" style="
                    background: white;
                    color: #1f2937;
                    padding: 1rem 1.5rem;
                    border-radius: 8px 8px 0 0;
                    border-bottom: 1px solid #e5e7eb;
                    position: relative;
                ">
                    <h3 style="
                        margin: 0;
                        font-size: 1.125rem;
                        font-weight: 600;
                    ">Make Payment</h3>
                    <button onclick="myOrdersModule.closePaymentModal()" style="
                        position: absolute;
                        top: 0.75rem;
                        right: 1rem;
                        background: none;
                        border: none;
                        color: #6b7280;
                        width: 24px;
                        height: 24px;
                        border-radius: 4px;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 1.25rem;
                        transition: all 0.2s;
                    " onmouseover="this.style.backgroundColor='#f3f4f6'; this.style.color='#374151'" onmouseout="this.style.backgroundColor='transparent'; this.style.color='#6b7280'">&times;</button>
                </div>
                
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="payment-info" style="
                        background: #f9fafb;
                        padding: 1rem;
                        border-radius: 6px;
                        margin-bottom: 1.5rem;
                        border: 1px solid #e5e7eb;
                    ">
                        <div style="
                            font-size: 0.875rem;
                            color: #6b7280;
                            margin-bottom: 0.25rem;
                        ">${order.orderNumber}</div>
                        <div class="payment-summary">
                            <div style="
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                                padding: 0.5rem 0;
                                border-bottom: 1px solid #e5e7eb;
                            ">
                                <span style="color: #374151; font-weight: 500;">Total Amount:</span>
                                <span class="amount" style="
                                    color: #111827;
                                    font-weight: 600;
                                    font-size: 1.125rem;
                                ">₱${parseFloat(order.totalPrice).toFixed(2)}</span>
                            </div>
                            ${order.paidAmount > 0 ? `
                                <div class="summary-item" style="
                                    display: flex;
                                    justify-content: space-between;
                                    align-items: center;
                                    margin-bottom: 0.5rem;
                                    font-size: 0.95rem;
                                ">
                                    <span style="color: #4a5568;">Paid Amount:</span>
                                    <span class="amount paid" style="color: #38a169; font-weight: 600;">₱${parseFloat(order.paidAmount).toFixed(2)}</span>
                                </div>
                                <div class="summary-item" style="
                                    display: flex;
                                    justify-content: space-between;
                                    align-items: center;
                                    font-size: 1rem;
                                    padding-top: 0.5rem;
                                    border-top: 1px solid #e2e8f0;
                                ">
                                    <span style="color: #4a5568; font-weight: 500;">Remaining:</span>
                                    <span class="amount remaining" style="color: #e53e3e; font-weight: 700;">₱${(parseFloat(order.totalPrice) - parseFloat(order.paidAmount)).toFixed(2)}</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>

                    <div class="payment-options" style="margin-bottom: 1.5rem;">
                        <div style="
                            margin: 0 0 1rem 0;
                            color: #374151;
                            font-size: 0.875rem;
                            font-weight: 500;
                        ">Choose Payment Option:</div>
                        ${paymentOptions.map(option => `
                            <label class="payment-option" data-type="${option.type}" data-amount="${option.amount}" style="
                                display: block;
                                border: 1px solid #d1d5db;
                                border-radius: 6px;
                                padding: 0.875rem;
                                margin-bottom: 0.5rem;
                                cursor: pointer;
                                transition: all 0.15s;
                                background: white;
                            " onmouseover="this.style.borderColor='#9ca3af'" onmouseout="this.style.borderColor='#d1d5db'">
                                <div style="
                                    display: flex;
                                    align-items: center;
                                    justify-content: space-between;
                                ">
                                    <div style="display: flex; align-items: center;">
                                        <input type="radio" name="paymentOption" value="${option.type}" id="option-${option.type}" style="
                                            margin-right: 0.75rem;
                                            width: 16px;
                                            height: 16px;
                                            accent-color: #3b82f6;
                                        ">
                                        <span style="
                                            color: #111827;
                                            font-size: 0.875rem;
                                            font-weight: 500;
                                        ">${option.label}</span>
                                    </div>
                                    <span class="option-amount" style="
                                        background: #3b82f6;
                                        color: white;
                                        padding: 0.125rem 0.5rem;
                                        border-radius: 4px;
                                        font-weight: 500;
                                        font-size: 0.75rem;
                                    ">₱${parseFloat(option.amount).toFixed(2)}</span>
                                </div>
                                <div style="
                                    margin-top: 0.25rem;
                                    margin-left: 2rem;
                                    color: #6b7280;
                                    font-size: 0.75rem;
                                ">${option.description}</div>
                            </label>
                        `).join('')}
                    </div>

                    <div class="payment-method-section" style="display: none;">
                        <h4 style="
                            margin: 0 0 0.75rem 0;
                            color: #374151;
                            font-size: 1rem;
                            font-weight: 600;
                        ">Payment Details</h4>
                        <div class="payment-methods" style="
                            display: grid;
                            grid-template-columns: repeat(3, 1fr);
                            gap: 0.75rem;
                        ">
                            <label class="payment-method" style="cursor: pointer;">
                                <input type="radio" name="paymentMethod" value="cash" style="display: none;">
                                <div class="method-card" style="
                                    border: 2px solid #e2e8f0;
                                    border-radius: 12px;
                                    padding: 1rem;
                                    text-align: center;
                                    transition: all 0.2s;
                                    background: white;
                                " onmouseover="this.style.borderColor='#cbd5e1'; this.style.transform='translateY(-2px)'" onmouseout="this.style.borderColor='#e2e8f0'; this.style.transform='translateY(0)'">
                                    <i class="fas fa-money-bill-wave" style="
                                        font-size: 1.5rem;
                                        color: #38a169;
                                        margin-bottom: 0.5rem;
                                        display: block;
                                    "></i>
                                    <span style="
                                        color: #2d3748;
                                        font-weight: 500;
                                        font-size: 0.875rem;
                                    ">Cash</span>
                                </div>
                            </label>
                            <label class="payment-method" style="cursor: pointer;">
                                <input type="radio" name="paymentMethod" value="paymongo_link" style="display: none;">
                                <div class="method-card" style="
                                    border: 2px solid #e2e8f0;
                                    border-radius: 12px;
                                    padding: 1rem;
                                    text-align: center;
                                    transition: all 0.2s;
                                    background: white;
                                " onmouseover="this.style.borderColor='#cbd5e1'; this.style.transform='translateY(-2px)'" onmouseout="this.style.borderColor='#e2e8f0'; this.style.transform='translateY(0)'">
                                    <i class="fas fa-link" style="
                                        font-size: 1.5rem;
                                        color: #3182ce;
                                        margin-bottom: 0.5rem;
                                        display: block;
                                    "></i>
                                    <span style="
                                        color: #2d3748;
                                        font-weight: 500;
                                        font-size: 0.875rem;
                                    ">PayMongo Link</span>
                                </div>
                            </label>
                            <label class="payment-method" style="cursor: pointer;">
                                <input type="radio" name="paymentMethod" value="gcash" style="display: none;">
                                <div class="method-card" style="
                                    border: 2px solid #e2e8f0;
                                    border-radius: 12px;
                                    padding: 1rem;
                                    text-align: center;
                                    transition: all 0.2s;
                                    background: white;
                                " onmouseover="this.style.borderColor='#cbd5e1'; this.style.transform='translateY(-2px)'" onmouseout="this.style.borderColor='#e2e8f0'; this.style.transform='translateY(0)'">
                                    <i class="fas fa-mobile-alt" style="
                                        font-size: 1.5rem;
                                        color: #38a169;
                                        margin-bottom: 0.5rem;
                                        display: block;
                                    "></i>
                                    <span style="
                                        color: #2d3748;
                                        font-weight: 500;
                                        font-size: 0.875rem;
                                    ">GCash</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="
                    background: #f9fafb;
                    padding: 1rem 1.5rem;
                    display: flex;
                    gap: 0.75rem;
                    justify-content: flex-end;
                    border-top: 1px solid #e5e7eb;
                ">
                    <button class="btn btn-outline" onclick="myOrdersModule.closePaymentModal()" style="
                        padding: 0.5rem 1.25rem;
                        background: white;
                        border: 1px solid #d1d5db;
                        color: #6b7280;
                        border-radius: 6px;
                        cursor: pointer;
                        font-size: 0.875rem;
                        font-weight: 500;
                        transition: all 0.2s;
                    " onmouseover="this.style.borderColor='#9ca3af'; this.style.backgroundColor='#f9fafb'" onmouseout="this.style.borderColor='#d1d5db'; this.style.backgroundColor='white'">
                        Cancel
                    </button>
                    <button class="btn btn-primary" id="processPaymentBtn" onclick="myOrdersModule.processPayment(${order.id})" disabled style="
                        padding: 0.5rem 1.25rem;
                        background: #3b82f6;
                        color: white;
                        border: none;
                        border-radius: 6px;
                        cursor: pointer;
                        font-size: 0.875rem;
                        font-weight: 500;
                        transition: all 0.2s;
                        opacity: 0.5;
                    " onmouseover="if(!this.disabled) { this.style.backgroundColor='#2563eb'; this.style.transform='translateY(-1px)'; }" onmouseout="if(!this.disabled) { this.style.backgroundColor='#3b82f6'; this.style.transform='translateY(0)'; }">
                        Process Payment
                    </button>
                </div>
            </div>
        `;

        // Add event listeners for payment option selection
        modal.querySelectorAll('input[name="paymentOption"]').forEach(radio => {
            radio.addEventListener('change', () => {
                const methodSection = modal.querySelector('.payment-method-section');
                methodSection.style.display = 'block';
                this.updateProcessButton();
            });
        });

        modal.querySelectorAll('input[name="paymentMethod"]').forEach(radio => {
            radio.addEventListener('change', () => {
                this.updateProcessButton();
            });
        });

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    updateProcessButton() {
        const modal = document.getElementById('paymentModal');
        const processBtn = modal.querySelector('#processPaymentBtn');
        const selectedOption = modal.querySelector('input[name="paymentOption"]:checked');
        const selectedMethod = modal.querySelector('input[name="paymentMethod"]:checked');
        
        processBtn.disabled = !selectedOption || !selectedMethod;
    }

    async processPayment(orderId) {
        const modal = document.getElementById('paymentModal');
        const selectedOption = modal.querySelector('input[name="paymentOption"]:checked');
        const selectedMethod = modal.querySelector('input[name="paymentMethod"]:checked');
        
        if (!selectedOption || !selectedMethod) {
            this.showToast('Please select payment option and method', 'error');
            return;
        }

        const paymentData = {
            request_id: orderId,
            payment_type: selectedOption.value,
            payment_method: selectedMethod.value,
            amount: selectedOption.closest('.payment-option').dataset.amount,
            csrf_token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        };

        try {
            // Handle different payment methods
            if (paymentData.payment_method === 'cash') {
                // Process cash payment through existing API
                await this.processCashPayment(paymentData);
            } else if (paymentData.payment_method === 'paymongo_link') {
                // Process PayMongo Link payments
                await this.processPayMongoLink(paymentData);
            } else if (paymentData.payment_method === 'gcash') {
                // Process GCash through PayMongo Link
                await this.processPayMongoLink(paymentData);
            } else {
                throw new Error('Unsupported payment method');
            }
        } catch (error) {
            console.error('Error processing payment:', error);
            this.showToast('Payment processing failed', 'error');
        }
    }

    async processCashPayment(paymentData) {
        const formData = new FormData();
        Object.keys(paymentData).forEach(key => {
            formData.append(key, paymentData[key]);
        });

        const response = await fetch('api/payments.php?action=process_payment', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        
        if (data.success) {
            this.showToast('Cash payment recorded successfully!', 'success');
            this.closePaymentModal();
            this.loadOrders();
            this.loadOrderCounts();
        } else {
            this.showToast(data.error || 'Payment processing failed', 'error');
        }
    }

    async processPayMongoLink(paymentData) {
        // Show loading state
        const processBtn = document.getElementById('processPaymentBtn');
        const originalText = processBtn.innerHTML;
        processBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Payment Link...';
        processBtn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('action', 'create_payment_link');
            formData.append('request_id', paymentData.request_id);
            formData.append('payment_type', paymentData.payment_type);
            formData.append('csrf_token', paymentData.csrf_token);

            const response = await fetch('api/paymongo_payment.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success && data.checkout_url) {
                // Store payment info in sessionStorage for confirmation
                sessionStorage.setItem('paymongo_payment', JSON.stringify({
                    request_id: paymentData.request_id,
                    link_id: data.link_id,
                    amount: data.amount,
                    timestamp: Date.now()
                }));
                
                // Redirect to PayMongo checkout
                this.showToast('Redirecting to PayMongo checkout...', 'info');
                window.location.href = data.checkout_url;
            } else {
                throw new Error(data.message || 'Failed to create payment link');
            }
        } catch (error) {
            console.error('Error processing PayMongo payment:', error);
            this.showToast(error.message || 'Payment processing failed', 'error');
            
            // Restore button state
            processBtn.innerHTML = originalText;
            processBtn.disabled = false;
        }
    }

    // Check for payment confirmation on page load
    checkPaymentConfirmation() {
        const urlParams = new URLSearchParams(window.location.search);
        const paymentStatus = urlParams.get('payment');
        const requestId = urlParams.get('request_id');
        
        if (paymentStatus && requestId) {
            const paymentData = sessionStorage.getItem('paymongo_payment');
            
            if (paymentData) {
                const payment = JSON.parse(paymentData);
                
                if (payment.request_id == requestId) {
                    if (paymentStatus === 'success') {
                        this.confirmPayMongoPayment(payment.link_id, requestId);
                    } else if (paymentStatus === 'failed') {
                        this.showToast('Payment was cancelled or failed', 'error');
                    }
                    
                    // Clean up
                    sessionStorage.removeItem('paymongo_payment');
                    
                    // Clean URL
                    window.history.replaceState({}, document.title, window.location.pathname);
                }
            }
        }
    }

    async confirmPayMongoPayment(linkId, requestId) {
        try {
            const formData = new FormData();
            formData.append('action', 'confirm_link_payment');
            formData.append('link_id', linkId);
            formData.append('request_id', requestId);
            formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

            const response = await fetch('api/paymongo_payment.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                this.showToast('Payment confirmed successfully!', 'success');
                this.loadOrders();
                this.loadOrderCounts();
            } else {
                this.showToast(data.message || 'Payment confirmation failed', 'error');
            }
        } catch (error) {
            console.error('Error confirming payment:', error);
            this.showToast('Payment confirmation failed', 'error');
        }
    }

    closePaymentModal() {
        const modal = document.getElementById('paymentModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }
}

// Initialize the module
const myOrdersModule = new MyOrdersModule();

// Make it globally available
window.myOrdersModule = myOrdersModule;
