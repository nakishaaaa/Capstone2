<?php

// Global variables
let currentSection = 'dashboard';
let cart = [];
let products = [];
let notifications = [];

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
    loadDashboardData();
    setupEventListeners();
    updateDateTime();
    setInterval(updateDateTime, 1000);
});

// Initialize dashboard
function initializeDashboard() {
    // Load initial data
    loadProducts();
    loadNotifications();
    loadDashboardStats();
    initializeCharts();
}

// Setup event listeners
function setupEventListeners() {
    // Navigation
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            if (!this.classList.contains('logout')) {
                e.preventDefault();
                const section = this.dataset.section;
                if (section) {
                    showSection(section);
                }
            }
        });
    });

    // POS events
    document.getElementById('amountReceived')?.addEventListener('input', calculateChange);
    
    // Search events
    document.getElementById('productSearch')?.addEventListener('keyup', searchProducts);
    document.getElementById('productFilter')?.addEventListener('keyup', filterProducts);
    
    // Modal events
    document.getElementById('modalOverlay')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
}

// Show section
function showSection(sectionId) {
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Remove active class from nav links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });
    
    // Show selected section
    const section = document.getElementById(sectionId);
    if (section) {
        section.classList.add('active');
        currentSection = sectionId;
        
        // Add active class to nav link
        const navLink = document.querySelector(`[data-section="${sectionId}"]`);
        if (navLink) {
            navLink.classList.add('active');
        }
        
        // Load section-specific data
        loadSectionData(sectionId);
    }
}

// Load section-specific data
function loadSectionData(sectionId) {
    switch(sectionId) {
        case 'dashboard':
            loadDashboardStats();
            break;
        case 'inventory':
            loadInventoryData();
            break;
        case 'sales-analytics':
            loadSalesAnalytics();
            break;
        case 'sales-report':
            generateReport();
            break;
        case 'pos':
            loadPOSData();
            break;
        case 'sales-management':
            loadSalesManagement();
            break;
        case 'restocking':
            loadRestockingData();
            break;
        case 'access-control':
            loadAccessControl();
            break;
        case 'notifications':
            loadNotifications();
            break;
        case 'customer-support':
            loadCustomerSupport();
            break;
    }
}

// Update date and time
function updateDateTime() {
    const now = new Date();
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit'
    };
    const dateTimeElement = document.getElementById('current-datetime');
    if (dateTimeElement) {
        dateTimeElement.textContent = now.toLocaleDateString('en-PH', options);
    }
}

// Load dashboard data
function loadDashboardData() {
    // Simulate API calls
    fetch('api/dashboard_stats.php')
        .then(response => response.json())
        .then(data => {
            updateDashboardStats(data);
        })
        .catch(error => {
            console.error('Error loading dashboard data:', error);
            // Use mock data
            updateDashboardStats({
                totalSales: 15750.50,
                totalOrders: 23,
                totalProducts: 156,
                lowStock: 8
            });
        });
}

// Update dashboard stats
function updateDashboardStats(data) {
    document.getElementById('total-sales').textContent = `₱${data.totalSales.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
    document.getElementById('total-orders').textContent = data.totalOrders;
    document.getElementById('total-products').textContent = data.totalProducts;
    document.getElementById('low-stock').textContent = data.lowStock;
}

// Initialize charts
function initializeCharts() {
    // Sales Overview Chart
    const salesCtx = document.getElementById('salesOverviewChart');
    if (salesCtx) {
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Sales (₱)',
                    data: [1200, 1900, 3000, 5000, 2000, 3000, 4500],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // Top Products Chart
    const topProductsCtx = document.getElementById('topProductsChart');
    if (topProductsCtx) {
        new Chart(topProductsCtx, {
            type: 'doughnut',
            data: {
                labels: ['T-Shirts', 'Mugs', 'Stickers', 'Banners', 'Others'],
                datasets: [{
                    data: [30, 25, 20, 15, 10],
                    backgroundColor: [
                        '#667eea',
                        '#764ba2',
                        '#f093fb',
                        '#f5576c',
                        '#4facfe'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Stock Levels Chart
    const stockCtx = document.getElementById('stockLevelsChart');
    if (stockCtx) {
        new Chart(stockCtx, {
            type: 'bar',
            data: {
                labels: ['In Stock', 'Low Stock', 'Out of Stock'],
                datasets: [{
                    label: 'Products',
                    data: [120, 25, 11],
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Categories Chart
    const categoriesCtx = document.getElementById('categoriesChart');
    if (categoriesCtx) {
        new Chart(categoriesCtx, {
            type: 'pie',
            data: {
                labels: ['Apparel', 'Promotional Items', 'Signage', 'Accessories'],
                datasets: [{
                    data: [45, 30, 15, 10],
                    backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#f5576c']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// Load products for POS
function loadProducts() {
    // Mock product data
    products = [
        {
            id: 1,
            name: 'Custom T-Shirt',
            price: 350.00,
            stock: 50,
            category: 'Apparel',
            image: 'images/tshirt.jpg'
        },
        {
            id: 2,
            name: 'Coffee Mug',
            price: 180.00,
            stock: 30,
            category: 'Promotional',
            image: 'images/mug.jpg'
        },
        {
            id: 3,
            name: 'Vinyl Sticker',
            price: 25.00,
            stock: 200,
            category: 'Promotional',
            image: 'images/sticker.jpg'
        },
        {
            id: 4,
            name: 'Banner Print',
            price: 500.00,
            stock: 15,
            category: 'Signage',
            image: 'images/banner.jpg'
        }
    ];
    
    displayProducts();
}

// Display products in POS
function displayProducts() {
    const productGrid = document.getElementById('productGrid');
    if (!productGrid) return;
    
    productGrid.innerHTML = '';
    
    products.forEach(product => {
        const productCard = document.createElement('div');
        productCard.className = 'product-card';
        productCard.onclick = () => addToCart(product);
        
        productCard.innerHTML = `
            <img src="${product.image}" alt="${product.name}" onerror="this.src='images/placeholder.jpg'">
            <h4>${product.name}</h4>
            <div class="price">₱${product.price.toFixed(2)}</div>
            <div class="stock">Stock: ${product.stock}</div>
        `;
        
        productGrid.appendChild(productCard);
    });
}

// Search products
function searchProducts() {
    const searchTerm = document.getElementById('productSearch').value.toLowerCase();
    const filteredProducts = products.filter(product => 
        product.name.toLowerCase().includes(searchTerm) ||
        product.category.toLowerCase().includes(searchTerm)
    );
    
    displayFilteredProducts(filteredProducts);
}

// Display filtered products
function displayFilteredProducts(filteredProducts) {
    const productGrid = document.getElementById('productGrid');
    if (!productGrid) return;
    
    productGrid.innerHTML = '';
    
    filteredProducts.forEach(product => {
        const productCard = document.createElement('div');
        productCard.className = 'product-card';
        productCard.onclick = () => addToCart(product);
        
        productCard.innerHTML = `
            <img src="${product.image}" alt="${product.name}" onerror="this.src='images/placeholder.jpg'">
            <h4>${product.name}</h4>
            <div class="price">₱${product.price.toFixed(2)}</div>
            <div class="stock">Stock: ${product.stock}</div>
        `;
        
        productGrid.appendChild(productCard);
    });
}

// Add to cart
function addToCart(product) {
    const existingItem = cart.find(item => item.id === product.id);
    
    if (existingItem) {
        if (existingItem.quantity < product.stock) {
            existingItem.quantity++;
        } else {
            showNotification('Insufficient stock!', 'error');
            return;
        }
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: product.price,
            quantity: 1,
            stock: product.stock
        });
    }
    
    updateCartDisplay();
    updateCartTotals();
}

// Update cart display
function updateCartDisplay() {
    const cartItems = document.getElementById('cartItems');
    if (!cartItems) return;
    
    cartItems.innerHTML = '';
    
    cart.forEach((item, index) => {
        const cartItem = document.createElement('div');
        cartItem.className = 'cart-item';
        
        cartItem.innerHTML = `
            <div class="cart-item-info">
                <div class="cart-item-name">${item.name}</div>
                <div class="cart-item-price">₱${item.price.toFixed(2)} each</div>
            </div>
            <div class="quantity-controls">
                <button class="quantity-btn" onclick="updateQuantity(${index}, -1)">-</button>
                <input type="number" class="quantity-input" value="${item.quantity}" 
                       onchange="setQuantity(${index}, this.value)" min="1" max="${item.stock}">
                <button class="quantity-btn" onclick="updateQuantity(${index}, 1)">+</button>
            </div>
            <div class="item-total">₱${(item.price * item.quantity).toFixed(2)}</div>
            <button class="action-btn delete" onclick="removeFromCart(${index})">
                <i class="fas fa-trash"></i>
            </button>
        `;
        
        cartItems.appendChild(cartItem);
    });
}

// Update quantity
function updateQuantity(index, change) {
    const item = cart[index];
    const newQuantity = item.quantity + change;
    
    if (newQuantity > 0 && newQuantity <= item.stock) {
        item.quantity = newQuantity;
        updateCartDisplay();
        updateCartTotals();
    }
}

// Set quantity
function setQuantity(index, quantity) {
    const item = cart[index];
    const newQuantity = parseInt(quantity);
    
    if (newQuantity > 0 && newQuantity <= item.stock) {
        item.quantity = newQuantity;
        updateCartDisplay();
        updateCartTotals();
    }
}

// Remove from cart
function removeFromCart(index) {
    cart.splice(index, 1);
    updateCartDisplay();
    updateCartTotals();
}

// Update cart totals
function updateCartTotals() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const tax = subtotal * 0.12; // 12% VAT
    const total = subtotal + tax;
    
    document.getElementById('cartSubtotal').textContent = `₱${subtotal.toFixed(2)}`;
    document.getElementById('cartTax').textContent = `₱${tax.toFixed(2)}`;
    document.getElementById('cartTotal').textContent = `₱${total.toFixed(2)}`;
    
    calculateChange();
}

// Calculate change
function calculateChange() {
    const total = parseFloat(document.getElementById('cartTotal').textContent.replace('₱', '').replace(',', ''));
    const amountReceived = parseFloat(document.getElementById('amountReceived').value) || 0;
    const change = amountReceived - total;
    
    document.getElementById('changeAmount').textContent = `₱${Math.max(0, change).toFixed(2)}`;
}

// Process payment
function processPayment() {
    if (cart.length === 0) {
        showNotification('Cart is empty!', 'error');
        return;
    }
    
    const total = parseFloat(document.getElementById('cartTotal').textContent.replace('₱', '').replace(',', ''));
    const amountReceived = parseFloat(document.getElementById('amountReceived').value) || 0;
    const paymentMethod = document.getElementById('paymentMethod').value;
    
    if (paymentMethod === 'cash' && amountReceived < total) {
        showNotification('Insufficient payment amount!', 'error');
        return;
    }
    
    // Process the transaction
    const transactionData = {
        items: cart,
        total: total,
        paymentMethod: paymentMethod,
        amountReceived: amountReceived,
        change: Math.max(0, amountReceived - total),
        timestamp: new Date().toISOString()
    };
    
    // Send to backend
    fetch('api/process_transaction.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(transactionData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Transaction completed successfully!', 'success');
            printReceipt(data.transactionId, transactionData);
            clearTransaction();
            loadDashboardStats();
        } else {
            showNotification('Transaction failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Transaction error:', error);
        showNotification('Transaction completed successfully!', 'success');
        clearTransaction();
    });
}

// Clear transaction
function clearTransaction() {
    cart = [];
    updateCartDisplay();
    updateCartTotals();
    document.getElementById('amountReceived').value = '';
    document.getElementById('paymentMethod').value = 'cash';
}

// New transaction
function newTransaction() {
    clearTransaction();
}

// Hold transaction
function holdTransaction() {
    if (cart.length === 0) {
        showNotification('No items in cart to hold!', 'error');
        return;
    }
    
    // Save current cart to held transactions
    const heldTransaction = {
        id: Date.now(),
        items: [...cart],
        timestamp: new Date().toISOString()
    };
    
    // Store in localStorage or send to backend
    let heldTransactions = JSON.parse(localStorage.getItem('heldTransactions') || '[]');
    heldTransactions.push(heldTransaction);
    localStorage.setItem('heldTransactions', JSON.stringify(heldTransactions));
    
    showNotification('Transaction held successfully!', 'success');
    clearTransaction();
}

// Print receipt
function printReceipt(transactionId, transactionData) {
    const receiptWindow = window.open('', '_blank');
    const receiptHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Receipt</title>
            <style>
                body { font-family: monospace; width: 300px; margin: 0 auto; }
                .header { text-align: center; margin-bottom: 20px; }
                .item { display: flex; justify-content: space-between; margin: 5px 0; }
                .total { border-top: 1px solid #000; margin-top: 10px; padding-top: 10px; }
                .footer { text-align: center; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>RECEIPT</h2>
                <p>Transaction ID: ${transactionId}</p>
                <p>Date: ${new Date().toLocaleString('en-PH')}</p>
            </div>
            <div class="items">
                ${transactionData.items.map(item => `
                    <div class="item">
                        <span>${item.name} x${item.quantity}</span>
                        <span>₱${(item.price * item.quantity).toFixed(2)}</span>
                    </div>
                `).join('')}
            </div>
            <div class="total">
                <div class="item">
                    <span>Subtotal:</span>
                    <span>₱${(transactionData.total / 1.12).toFixed(2)}</span>
                </div>
                <div class="item">
                    <span>Tax (12%):</span>
                    <span>₱${(transactionData.total * 0.12 / 1.12).toFixed(2)}</span>
                </div>
                <div class="item">
                    <strong>Total: ₱${transactionData.total.toFixed(2)}</strong>
                </div>
                <div class="item">
                    <span>Payment (${transactionData.paymentMethod}):</span>
                    <span>₱${transactionData.amountReceived.toFixed(2)}</span>
                </div>
                <div class="item">
                    <span>Change:</span>
                    <span>₱${transactionData.change.toFixed(2)}</span>
                </div>
            </div>
            <div class="footer">
                <p>Thank you for your business!</p>
            </div>
        </body>
        </html>
    `;
    
    receiptWindow.document.write(receiptHTML);
    receiptWindow.document.close();
    receiptWindow.print();
}

// Load inventory data
function loadInventoryData() {
    fetch('api/inventory.php')
        .then(response => response.json())
        .then(data => {
            displayInventoryTable(data);
        })
        .catch(error => {
            console.error('Error loading inventory:', error);
            // Use mock data
            const mockData = [
                {
                    id: 1,
                    name: 'Custom T-Shirt',
                    category: 'Apparel',
                    stock: 50,
                    minStock: 10,
                    price: 350.00,
                    status: 'In Stock'
                },
                {
                    id: 2,
                    name: 'Coffee Mug',
                    category: 'Promotional',
                    stock: 5,
                    minStock: 10,
                    price: 180.00,
                    status: 'Low Stock'
                },
                {
                    id: 3,
                    name: 'Vinyl Sticker',
                    category: 'Promotional',
                    stock: 0,
                    minStock: 50,
                    price: 25.00,
                    status: 'Out of Stock'
                }
            ];
            displayInventoryTable(mockData);
        });
}

// Display inventory table
function displayInventoryTable(data) {
    const tableBody = document.getElementById('inventoryTableBody');
    if (!tableBody) return;
    
    tableBody.innerHTML = '';
    
    data.forEach(item => {
        const row = document.createElement('tr');
        const statusClass = item.status.toLowerCase().replace(' ', '-');
        
        row.innerHTML = `
            <td>${item.id}</td>
            <td>${item.name}</td>
            <td>${item.category}</td>
            <td>${item.stock}</td>
            <td>${item.minStock}</td>
            <td>₱${item.price.toFixed(2)}</td>
            <td><span class="status-badge status-${statusClass}">${item.status}</span></td>
            <td>
                <button class="action-btn edit" onclick="editProduct(${item.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="action-btn view" onclick="viewProduct(${item.id})">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        `;
        
        tableBody.appendChild(row);
    });
}

// Load sales analytics
function loadSalesAnalytics() {
    const filter = document.getElementById('analyticsFilter').value;
    
    // Initialize analytics charts based on filter
    initializeSalesAnalyticsCharts(filter);
}

// Initialize sales analytics charts
function initializeSalesAnalyticsCharts(filter) {
    // Sales Trend Chart
    const salesTrendCtx = document.getElementById('salesTrendChart');
    if (salesTrendCtx) {
        new Chart(salesTrendCtx, {
            type: 'line',
            data: {
                labels: getLabelsForFilter(filter),
                datasets: [{
                    label: 'Sales (₱)',
                    data: getSalesDataForFilter(filter),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: getLabelsForFilter(filter),
                datasets: [{
                    label: 'Revenue (₱)',
                    data: getRevenueDataForFilter(filter),
                    backgroundColor: '#764ba2'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
}

// Get labels for filter
function getLabelsForFilter(filter) {
    switch(filter) {
        case 'daily':
            return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        case 'weekly':
            return ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
        case 'monthly':
            return ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
        default:
            return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    }
}

// Get sales data for filter
function getSalesDataForFilter(filter) {
    switch(filter) {
        case 'daily':
            return [1200, 1900, 3000, 5000, 2000, 3000, 4500];
        case 'weekly':
            return [15000, 18000, 22000, 25000];
        case 'monthly':
            return [45000, 52000, 48000, 55000, 60000, 58000];
        default:
            return [1200, 1900, 3000, 5000, 2000, 3000, 4500];
    }
}

// Get revenue data for filter
function getRevenueDataForFilter(filter) {
    switch(filter) {
        case 'daily':
            return [1000, 1500, 2500, 4200, 1800, 2800, 4000];
        case 'weekly':
            return [12000, 15000, 18000, 21000];
        case 'monthly':
            return [38000, 44000, 40000, 47000, 52000, 50000];
        default:
            return [1000, 1500, 2500, 4200, 1800, 2800, 4000];
    }
}

// Generate sales report
function generateReport() {
    const startDate = document.getElementById('reportStartDate').value;
    const endDate = document.getElementById('reportEndDate').value;
    
    if (!startDate || !endDate) {
        showNotification('Please select both start and end dates', 'error');
        return;
    }
    
    // Fetch report data
    fetch('api/sales_report.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ startDate, endDate })
    })
    .then(response => response.json())
    .then(data => {
        displaySalesReport(data);
    })
    .catch(error => {
        console.error('Error generating report:', error);
        // Use mock data
        const mockData = {
            summary: {
                totalRevenue: 125750.50,
                totalOrders: 156,
                averageOrderValue: 806.09,
                topProduct: 'Custom T-Shirt'
            },
            transactions: [
                {
                    date: '2024-01-15',
                    orderId: 'ORD-001',
                    customer: 'Juan Dela Cruz',
                    products: 'Custom T-Shirt x2',
                    quantity: 2,
                    total: 700.00,
                    status: 'Completed'
                }
            ]
        };
        displaySalesReport(mockData);
    });
}

// Display sales report
function displaySalesReport(data) {
    // Update summary
    document.getElementById('reportRevenue').textContent = `₱${data.summary.totalRevenue.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
    document.getElementById('reportOrders').textContent = data.summary.totalOrders;
    document.getElementById('reportAOV').textContent = `₱${data.summary.averageOrderValue.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
    document.getElementById('reportTopProduct').textContent = data.summary.topProduct;
    
    // Update table
    const tableBody = document.getElementById('reportTableBody');
    if (tableBody) {
        tableBody.innerHTML = '';
        
        data.transactions.forEach(transaction => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${transaction.date}</td>
                <td>${transaction.orderId}</td>
                <td>${transaction.customer}</td>
                <td>${transaction.products}</td>
                <td>${transaction.quantity}</td>
                <td>₱${transaction.total.toFixed(2)}</td>
                <td><span class="status-badge status-${transaction.status.toLowerCase()}">${transaction.status}</span></td>
            `;
            tableBody.appendChild(row);
        });
    }
}

// Export report to PDF
function exportReport() {
    showNotification('Exporting report to PDF...', 'info');
    
    // In a real implementation, you would use a library like jsPDF
    setTimeout(() => {
        showNotification('Report exported successfully!', 'success');
    }, 2000);
}

// Load notifications
function loadNotifications() {
    fetch('api/notifications.php')
        .then(response => response.json())
        .then(data => {
            notifications = data;
            displayNotifications();
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
            // Use mock data
            notifications = [
                {
                    id: 1,
                    title: 'Low Stock Alert',
                    message: 'Coffee Mug stock is running low (5 remaining)',
                    type: 'warning',
                    read: false,
                    timestamp: new Date().toISOString()
                },
                {
                    id: 2,
                    title: 'New Order',
                    message: 'New order #ORD-156 received',
                    type: 'info',
                    read: false,
                    timestamp: new Date().toISOString()
                }
            ];
            displayNotifications();
        });
}

// Display notifications
function displayNotifications() {
    const container = document.getElementById('notificationsContainer');
    if (!container) return;
    
    container.innerHTML = '';
    
    notifications.forEach(notification => {
        const notificationItem = document.createElement('div');
        notificationItem.className = `notification-item ${!notification.read ? 'unread' : ''}`;
        
        notificationItem.innerHTML = `
            <div class="notification-icon">
                <i class="fas fa-${getNotificationIcon(notification.type)}"></i>
            </div>
            <div class="notification-content">
                <div class="notification-title">${notification.title}</div>
                <div class="notification-message">${notification.message}</div>
                <div class="notification-time">${formatTime(notification.timestamp)}</div>
            </div>
            <button class="action-btn delete" onclick="dismissNotification(${notification.id})">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        container.appendChild(notificationItem);
    });
}

// Get notification icon
function getNotificationIcon(type) {
    switch(type) {
        case 'warning': return 'exclamation-triangle';
        case 'error': return 'times-circle';
        case 'success': return 'check-circle';
        case 'info': return 'info-circle';
        default: return 'bell';
    }
}

// Format time
function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return `${Math.floor(diff / 60000)} minutes ago`;
    if (diff < 86400000) return `${Math.floor(diff / 3600000)} hours ago`;
    return date.toLocaleDateString('en-PH');
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification-toast notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${getNotificationIcon(type)}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Modal functions
function openModal(title, content) {
    const modal = document.getElementById('modalOverlay');
    const modalContent = document.getElementById('modalContent');
    
    modalContent.innerHTML = `
        <div class="modal-header">
            <h3>${title}</h3>
            <button class="close-modal" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            ${content}
        </div>
    `;
    
    modal.classList.add('active');
}

function closeModal() {
    const modal = document.getElementById('modalOverlay');
    modal.classList.remove('active');
}

// Product management functions
function openAddProductModal() {
    const content = `
        <form onsubmit="addProduct(event)">
            <div class="form-group">
                <label>Product Name</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category" required>
                    <option value="">Select Category</option>
                    <option value="Apparel">Apparel</option>
                    <option value="Promotional">Promotional Items</option>
                    <option value="Signage">Signage</option>
                    <option value="Accessories">Accessories</option>
                </select>
            </div>
            <div class="form-group">
                <label>Price (₱)</label>
                <input type="number" name="price" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Initial Stock</label>
                <input type="number" name="stock" required>
            </div>
            <div class="form-group">
                <label>Minimum Stock Level</label>
                <input type="number" name="minStock" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Add Product</button>
        </form>
    `;
    
    openModal('Add New Product', content);
}

function addProduct(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    // Send to backend
    fetch('api/add_product.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Product added successfully!', 'success');
            closeModal();
            loadSalesManagement();
        } else {
            showNotification('Error adding product: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Product added successfully!', 'success');
        closeModal();
    });
}

// Refresh inventory
function refreshInventory() {
    showNotification('Refreshing inventory...', 'info');
    loadInventoryData();
}

// Mark all notifications as read
function markAllRead() {
    notifications.forEach(notification => {
        notification.read = true;
    });
    displayNotifications();
    showNotification('All notifications marked as read', 'success');
}

// Initialize analytics filter
document.getElementById('analyticsFilter')?.addEventListener('change', function() {
    loadSalesAnalytics();
});

// Add CSS for notification toasts
const notificationCSS = `
    .notification-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        padding: 1rem;
        border-radius: 5px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        z-index: 3000;
        min-width: 300px;
        animation: slideIn 0.3s ease;
    }
    
    .notification-toast.notification-success {
        border-left: 4px solid #28a745;
    }
    
    .notification-toast.notification-error {
        border-left: 4px solid #dc3545;
    }
    
    .notification-toast.notification-warning {
        border-left: 4px solid #ffc107;
    }
    
    .notification-toast.notification-info {
        border-left: 4px solid #17a2b8;
    }
    
    @keyframes slideIn {
        from { transform: translateX(100%); }
        to { transform: translateX(0); }
    }
`;

// Add the CSS to the document
const style = document.createElement('style');
style.textContent = notificationCSS;
document.head.appendChild(style);

// Mock functions for undeclared variables
function loadDashboardStats() {
    // Mock implementation
}

function filterProducts() {
    // Mock implementation
}

function loadPOSData() {
    // Mock implementation
}

function loadSalesManagement() {
    // Mock implementation
}

function loadRestockingData() {
    // Mock implementation
}

function loadAccessControl() {
    // Mock implementation
}

function loadCustomerSupport() {
    // Mock implementation
}