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
                        `<button class="btn btn-primary">
                            <i class="fas fa-phone"></i>
                            Contact Us
                        </button>` : ''
                    }
                </div>
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
                ${order.status === 'approved' ? `
                    <button class="btn btn-primary">
                        <i class="fas fa-phone"></i>
                        Contact for Pickup
                    </button>
                ` : ''}
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
}

// Initialize the module
const myOrdersModule = new MyOrdersModule();

// Make it globally available
window.myOrdersModule = myOrdersModule;
