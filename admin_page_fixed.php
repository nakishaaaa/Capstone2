<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// For production
/*
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
*/

session_start();

if (!isset($_SESSION['name']) || !isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Inventory Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/admin_page.css">
    
    
</head>
<body>
    <!-- Database Status -->

    <div class="admin-container">
        <!-- Sidebar Navigation -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-store"></i> Admin Panel</h2>
                <p>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></p>
            </div>
            <ul class="nav-menu">
                <li><a href="#dashboard" class="nav-link active" data-section="dashboard">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a></li>
                <li><a href="#inventory" class="nav-link" data-section="inventory">
                    <i class="fas fa-boxes"></i> Inventory Management
                </a></li>
                <li><a href="#pos" class="nav-link" data-section="pos">
                    <i class="fas fa-cash-register"></i> Point of Sale
                </a></li>
                <li><a href="#sales-management" class="nav-link" data-section="sales-management">
                    <i class="fas fa-cogs"></i> Product Management
                </a></li>
                <li><a href="#notifications" class="nav-link" data-section="notifications">
                    <i class="fas fa-bell"></i> Notifications
                </a></li>
                <li><a href="index.php" class="nav-link logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Dashboard Section -->
            <section id="dashboard" class="content-section active">
                <div class="section-header">
                    <h1>Dashboard Overview</h1>
                    <div class="date-time" id="current-datetime"></div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-peso-sign"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="total-sales">₱0.00</h3>
                            <p>Total Sales Today</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="total-orders">0</h3>
                            <p>Orders Today</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="total-products">0</h3>
                            <p>Total Products</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="low-stock">0</h3>
                            <p>Low Stock Items</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-charts">
                    <div class="chart-container">
                        <h3>Sales Overview</h3>
                        <canvas id="salesOverviewChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h3>Top Products</h3>
                        <canvas id="topProductsChart"></canvas>
                    </div>
                </div>
            </section>

            <!-- Inventory Management Section -->
            <section id="inventory" class="content-section">
                <div class="section-header">
                    <h1>Inventory Management</h1>
                    <button class="btn btn-primary" onclick="refreshInventory()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>

                <div class="inventory-table-container">
                    <h3 style="padding: 1rem;">Current Inventory Status</h3>
                    <table class="inventory-table" id="inventoryTable">
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Minimum Stock</th>
                                <th>Price (₱)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryTableBody">
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem;">Loading inventory data...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Point of Sale Section -->
            <section id="pos" class="content-section">
                <div class="section-header">
                    <h1>Point of Sale</h1>
                    <div class="pos-controls">
                        <button class="btn btn-success" onclick="newTransaction()">
                            <i class="fas fa-plus"></i> New Transaction
                        </button>
                        <button class="btn btn-primary" onclick="clearTransaction()">
                            <i class="fas fa-trash"></i> Clear
                        </button>
                    </div>
                </div>

                <div class="pos-container">
                    <div class="pos-left">
                        <div class="product-search">
                            <input type="text" id="productSearch" placeholder="Search products..." onkeyup="searchProducts()">
                        </div>
                        <div class="product-grid" id="productGrid">
                            <div style="grid-column: 1/-1; text-align: center; padding: 2rem;">
                                Loading products...
                            </div>
                        </div>
                    </div>

                    <div class="pos-right">
                        <div class="cart-container">
                            <h3>Current Transaction</h3>
                            <div class="cart-items" id="cartItems">
                                <div style="text-align: center; padding: 2rem; color: #666;">
                                    No items in cart
                                </div>
                            </div>
                            <div class="cart-summary">
                                <div class="summary-row">
                                    <span>Subtotal:</span>
                                    <span id="cartSubtotal">₱0.00</span>
                                </div>
                                <div class="summary-row">
                                    <span>Tax (12%):</span>
                                    <span id="cartTax">₱0.00</span>
                                </div>
                                <div class="summary-row total">
                                    <span>Total:</span>
                                    <span id="cartTotal">₱0.00</span>
                                </div>
                            </div>
                            <div class="payment-section">
                                <select id="paymentMethod">
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="gcash">GCash</option>
                                </select>
                                <input type="number" id="amountReceived" placeholder="Amount Received" step="0.01">
                                <div class="change-display">
                                    <span>Change: </span>
                                    <span id="changeAmount">₱0.00</span>
                                </div>
                                <button class="btn btn-success btn-large" onclick="processPayment()">
                                    <i class="fas fa-credit-card"></i> Process Payment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Product Management Section -->
            <section id="sales-management" class="content-section">
                <div class="section-header">
                    <h1>Product Management</h1>
                    <button class="btn btn-primary" onclick="openAddProductModal()">
                        <i class="fas fa-plus"></i> Add Product
                    </button>
                </div>

                <div class="inventory-table-container">
                    <table class="inventory-table" id="productsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price (₱)</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="productsTableBody">
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem;">Loading products...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Notifications Section -->
            <section id="notifications" class="content-section">
                <div class="section-header">
                    <h1>Notifications & Alerts</h1>
                    <button class="btn btn-primary" onclick="markAllRead()">
                        <i class="fas fa-check-double"></i> Mark All Read
                    </button>
                </div>

                <div class="notifications-container" id="notificationsContainer">
                    <div style="text-align: center; padding: 2rem;">
                        Loading notifications...
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Modals -->
    <div id="modalOverlay" class="modal-overlay">
        <div id="modalContent" class="modal-content">
            <!-- Dynamic modal content -->
        </div>
    </div>

    <!-- JavaScript with database connection -->
    <script>
        console.log('Admin dashboard with database connection loading...');
        
        // Global variables
        let currentSection = 'dashboard';
        let cart = [];
        let products = [];
        let notifications = [];

        // API Base URL
        const API_BASE = 'api/';

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing dashboard with database...');
            initializeDashboard();
            setupEventListeners();
            updateDateTime();
            setInterval(updateDateTime, 1000);
            
            // Handle initial hash or show dashboard
            handleInitialHash();
            
            // Listen for hash changes
            window.addEventListener('hashchange', handleHashChange);
        });

        // Include all the JavaScript functions from the previous file
        // (Same as in admin_page_fixed.php but with better error handling)

        // Handle initial hash
        function handleInitialHash() {
            const hash = window.location.hash.substring(1);
            if (hash && document.getElementById(hash)) {
                showSection(hash);
            } else {
                showSection('dashboard');
                updateURL('dashboard');
            }
        }

        // Handle hash changes
        function handleHashChange() {
            const hash = window.location.hash.substring(1);
            if (hash && document.getElementById(hash)) {
                showSection(hash, false);
            }
        }

        // Update URL hash
        function updateURL(sectionId) {
            if (window.location.hash !== '#' + sectionId) {
                window.history.pushState(null, null, '#' + sectionId);
            }
        }

        // Initialize dashboard
        async function initializeDashboard() {
            console.log('Initializing dashboard with database connection...');
            showNotification('Connecting to database...', 'info');
            
            try {
                await loadProducts();
                await loadNotifications();
                await loadDashboardStats();
                initializeCharts();
                showNotification('Dashboard loaded successfully!', 'success');
            } catch (error) {
                console.error('Error initializing dashboard:', error);
                showNotification('Error loading dashboard data', 'error');
            }
        }

        // Setup event listeners
        function setupEventListeners() {
            // Navigation
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!this.classList.contains('logout')) {
                        e.preventDefault();
                        const section = this.getAttribute('data-section');
                        if (section) {
                            showSection(section, true);
                        }
                    }
                });
            });

            // POS events
            const amountReceived = document.getElementById('amountReceived');
            if (amountReceived) {
                amountReceived.addEventListener('input', calculateChange);
            }

            // Search events
            const productSearch = document.getElementById('productSearch');
            if (productSearch) {
                productSearch.addEventListener('keyup', searchProducts);
            }

            // Modal events
            const modalOverlay = document.getElementById('modalOverlay');
            if (modalOverlay) {
                modalOverlay.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal();
                    }
                });
            }
        }

        // Show section
        function showSection(sectionId, updateURL = true) {
            console.log('Showing section:', sectionId);
            
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
                
                // Update URL hash if requested
                if (updateURL) {
                    window.history.pushState(null, null, '#' + sectionId);
                }
                
                // Load section-specific data
                loadSectionData(sectionId);
            }
        }

        // Load section-specific data
        async function loadSectionData(sectionId) {
            switch(sectionId) {
                case 'dashboard':
                    await loadDashboardStats();
                    break;
                case 'inventory':
                    await loadInventoryData();
                    break;
                case 'pos':
                    await loadPOSData();
                    break;
                case 'sales-management':
                    await loadSalesManagement();
                    break;
                case 'notifications':
                    await displayNotifications();
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

        // Load dashboard stats from API
        async function loadDashboardStats() {
            try {
                console.log('Loading dashboard stats from database...');
                const response = await fetch(`${API_BASE}sales.php?stats=1`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();

                if (result.success) {
                    updateDashboardStats(result.data);
                    console.log('Dashboard stats loaded successfully:', result.data);
                } else {
                    console.error('Error loading dashboard stats:', result.error);
                    showNotification('Error loading dashboard stats: ' + result.error, 'error');
                }
            } catch (error) {
                console.error('Error loading dashboard stats:', error);
                showNotification('Error connecting to database for stats', 'error');
                // Use fallback data
                updateDashboardStats({
                    total_sales: 0,
                    total_orders: 0,
                    total_products: 0,
                    low_stock: 0
                });
            }
        }

        // Update dashboard stats
        function updateDashboardStats(data) {
            const totalSalesEl = document.getElementById('total-sales');
            const totalOrdersEl = document.getElementById('total-orders');
            const totalProductsEl = document.getElementById('total-products');
            const lowStockEl = document.getElementById('low-stock');

            if (totalSalesEl) totalSalesEl.textContent = `₱${data.total_sales.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
            if (totalOrdersEl) totalOrdersEl.textContent = data.total_orders;
            if (totalProductsEl) totalProductsEl.textContent = data.total_products;
            if (lowStockEl) lowStockEl.textContent = data.low_stock;
        }

        // Load products from API
        async function loadProducts() {
            try {
                console.log('Loading products from database...');
                const response = await fetch(`${API_BASE}inventory.php`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();

                if (result.success) {
                    products = result.data;
                    displayProducts();
                    console.log('Products loaded successfully:', products.length, 'items');
                } else {
                    console.error('Error loading products:', result.error);
                    showNotification('Error loading products: ' + result.error, 'error');
                }
            } catch (error) {
                console.error('Error loading products:', error);
                showNotification('Error connecting to database for products', 'error');
                products = []; // Empty array as fallback
                displayProducts();
            }
        }

        // Display products in POS
        function displayProducts() {
            const productGrid = document.getElementById('productGrid');
            if (!productGrid) return;

            if (products.length === 0) {
                productGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: #666;">No products available. Please add products to inventory.</div>';
                return;
            }

            productGrid.innerHTML = '';

            products.forEach(product => {
                const productCard = document.createElement('div');
                productCard.className = 'product-card';
                
                // Disable card if out of stock
                if (product.stock <= 0) {
                    productCard.classList.add('out-of-stock');
                    productCard.style.opacity = '0.5';
                    productCard.style.cursor = 'not-allowed';
                    productCard.onclick = () => showNotification('Product is out of stock!', 'error');
                } else {
                    productCard.onclick = () => addToCart(product);
                }

                const stockStatus = product.stock <= 0 ? 'OUT OF STOCK' : `Stock: ${product.stock}`;
                const stockColor = product.stock <= 0 ? 'color: #dc3545; font-weight: bold;' : 
                          product.stock <= (product.min_stock || 10) ? 'color: #ffc107; font-weight: bold;' : 
                          'color: #28a745;';

                productCard.innerHTML = `
                    <img src="${product.image_url || 'images/placeholder.jpg'}" alt="${product.name}" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0zNSA0MEg2NVY2MEgzNVY0MFoiIGZpbGw9IiM5Q0EzQUYiLz4KPC9zdmc+'">
                    <h4>${product.name}</h4>
                    <div class="price">₱${parseFloat(product.price).toFixed(2)}</div>
                    <div class="stock" style="${stockColor}">${stockStatus}</div>
                `;

                productGrid.appendChild(productCard);
            });
        }

        // Add to cart
        function addToCart(product) {
            if (product.stock <= 0) {
                showNotification('Product is out of stock!', 'error');
                return;
            }

            const existingItem = cart.find(item => item.id === product.id);

            if (existingItem) {
                // Calculate total quantity that would be in cart
                const totalQuantityInCart = existingItem.quantity + 1;
                
                if (totalQuantityInCart > product.stock) {
                    showNotification(`Cannot add more! Only ${product.stock} items available in stock.`, 'error');
                    return;
                }
                existingItem.quantity++;
            } else {
                cart.push({
                    id: product.id,
                    name: product.name,
                    price: parseFloat(product.price),
                    quantity: 1,
                    stock: product.stock
                });
            }

            updateCartDisplay();
            updateCartTotals();
            showNotification(`${product.name} added to cart`, 'success');
        }

        // Update cart display
        function updateCartDisplay() {
            const cartItems = document.getElementById('cartItems');
            if (!cartItems) return;

            if (cart.length === 0) {
                cartItems.innerHTML = '<div style="text-align: center; padding: 2rem; color: #666;">No items in cart</div>';
                return;
            }

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
                    <button class="action-btn" onclick="removeFromCart(${index})" style="background: #dc3545; color: white;">
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

            if (newQuantity <= 0) {
                // Remove item if quantity becomes 0 or less
                removeFromCart(index);
                return;
            }

            if (newQuantity > item.stock) {
                showNotification(`Cannot add more! Only ${item.stock} items available in stock.`, 'error');
                return;
            }

            item.quantity = newQuantity;
            updateCartDisplay();
            updateCartTotals();
        }

        // Set quantity
        function setQuantity(index, quantity) {
            const item = cart[index];
            const newQuantity = parseInt(quantity);

            if (newQuantity <= 0) {
                showNotification('Quantity must be greater than 0', 'error');
                // Reset to previous value
                updateCartDisplay();
                return;
            }

            if (newQuantity > item.stock) {
                showNotification(`Cannot set quantity to ${newQuantity}! Only ${item.stock} items available in stock.`, 'error');
                // Reset to previous value
                updateCartDisplay();
                return;
            }

            item.quantity = newQuantity;
            updateCartDisplay();
            updateCartTotals();
        }

        // Remove from cart
        function removeFromCart(index) {
            const item = cart[index];
            cart.splice(index, 1);
            updateCartDisplay();
            updateCartTotals();
            showNotification(`${item.name} removed from cart`, 'info');
        }

        // Update cart totals
        function updateCartTotals() {
            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const tax = subtotal * 0.12; // 12% VAT
            const total = subtotal + tax;

            const cartSubtotal = document.getElementById('cartSubtotal');
            const cartTax = document.getElementById('cartTax');
            const cartTotal = document.getElementById('cartTotal');

            if (cartSubtotal) cartSubtotal.textContent = `₱${subtotal.toFixed(2)}`;
            if (cartTax) cartTax.textContent = `₱${tax.toFixed(2)}`;
            if (cartTotal) cartTotal.textContent = `₱${total.toFixed(2)}`;

            calculateChange();
        }

        // Calculate change
        function calculateChange() {
            const cartTotalEl = document.getElementById('cartTotal');
            const amountReceivedEl = document.getElementById('amountReceived');
            const changeAmountEl = document.getElementById('changeAmount');

            if (!cartTotalEl || !amountReceivedEl || !changeAmountEl) return;

            const total = parseFloat(cartTotalEl.textContent.replace('₱', '').replace(',', ''));
            const amountReceived = parseFloat(amountReceivedEl.value) || 0;
            const change = amountReceived - total;

            changeAmountEl.textContent = `₱${Math.max(0, change).toFixed(2)}`;
        }

        // Process payment
        async function processPayment() {
            if (cart.length === 0) {
                showNotification('Cart is empty!', 'error');
                return;
            }

            // Final stock validation before processing
            for (const cartItem of cart) {
                const currentProduct = products.find(p => p.id === cartItem.id);
                if (!currentProduct) {
                    showNotification(`Product ${cartItem.name} no longer exists!`, 'error');
                    return;
                }
                
                if (currentProduct.stock < cartItem.quantity) {
                    showNotification(`Insufficient stock for ${cartItem.name}! Available: ${currentProduct.stock}, Requested: ${cartItem.quantity}`, 'error');
                    // Refresh products to get latest stock
                    await loadProducts();
                    return;
                }
            }

            const total = parseFloat(document.getElementById('cartTotal').textContent.replace('₱', '').replace(',', ''));
            const amountReceived = parseFloat(document.getElementById('amountReceived').value) || 0;
            const paymentMethod = document.getElementById('paymentMethod').value;

            if (paymentMethod === 'cash' && amountReceived < total) {
                showNotification('Insufficient payment amount!', 'error');
                return;
            }

            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const tax = subtotal * 0.12;

            const transactionData = {
                transaction_id: 'TXN-' + Date.now(),
                total_amount: total,
                tax_amount: tax,
                payment_method: paymentMethod,
                amount_received: amountReceived,
                change_amount: Math.max(0, amountReceived - total),
                items: cart
            };

            try {
                showNotification('Processing payment...', 'info');
                
                const response = await fetch(`${API_BASE}sales.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(transactionData)
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                if (result.success) {
                    showNotification('Transaction completed successfully!', 'success');
                    printReceipt(transactionData.transaction_id, transactionData);
                    clearTransaction();
                    await loadDashboardStats();
                    await loadProducts(); // Refresh products to update stock
                } else {
                    showNotification('Error processing transaction: ' + result.error, 'error');
                    // Refresh products in case stock changed
                    await loadProducts();
                }
            } catch (error) {
                console.error('Error processing payment:', error);
                showNotification('Error processing payment: ' + error.message, 'error');
                // Refresh products in case of error
                await loadProducts();
            }
        }

        // Clear transaction
        function clearTransaction() {
            cart = [];
            updateCartDisplay();
            updateCartTotals();
            const amountReceivedEl = document.getElementById('amountReceived');
            const paymentMethodEl = document.getElementById('paymentMethod');

            if (amountReceivedEl) amountReceivedEl.value = '';
            if (paymentMethodEl) paymentMethodEl.value = 'cash';
        }

        // New transaction
        function newTransaction() {
            clearTransaction();
            showNotification('New transaction started', 'info');
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
                            <span>₱${(transactionData.total_amount - transactionData.tax_amount).toFixed(2)}</span>
                        </div>
                        <div class="item">
                            <span>Tax (12%):</span>
                            <span>₱${transactionData.tax_amount.toFixed(2)}</span>
                        </div>
                        <div class="item">
                            <strong>Total: ₱${transactionData.total_amount.toFixed(2)}</strong>
                        </div>
                        <div class="item">
                            <span>Payment (${transactionData.payment_method}):</span>
                            <span>₱${transactionData.amount_received.toFixed(2)}</span>
                        </div>
                        <div class="item">
                            <span>Change:</span>
                            <span>₱${transactionData.change_amount.toFixed(2)}</span>
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
        async function loadInventoryData() {
            try {
                console.log('Loading inventory data from database...');
                const response = await fetch(`${API_BASE}inventory.php`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();

                if (result.success) {
                    displayInventoryTable(result.data);
                    console.log('Inventory data loaded successfully:', result.data.length, 'items');
                } else {
                    console.error('Error loading inventory:', result.error);
                    showNotification('Error loading inventory: ' + result.error, 'error');
                    displayInventoryTable([]);
                }
            } catch (error) {
                console.error('Error loading inventory:', error);
                showNotification('Error connecting to database for inventory', 'error');
                displayInventoryTable([]);
            }
        }

        // Display inventory table
        function displayInventoryTable(data) {
            const tableBody = document.getElementById('inventoryTableBody');
            if (!tableBody) return;

            if (!data || data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem;">No inventory data available. Please add products to inventory.</td></tr>';
                return;
            }

            tableBody.innerHTML = '';

            data.forEach(item => {
                const row = document.createElement('tr');
                const statusClass = (item.stock_status || 'In Stock').toLowerCase().replace(' ', '-');

                row.innerHTML = `
                    <td>${item.id}</td>
                    <td>${item.name}</td>
                    <td>${item.category}</td>
                    <td>${item.stock}</td>
                    <td>${item.min_stock || 10}</td>
                    <td>₱${parseFloat(item.price).toFixed(2)}</td>
                    <td><span class="status-badge status-${statusClass}">${item.stock_status || 'In Stock'}</span></td>
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

        // Load notifications
        async function loadNotifications() {
            try {
                console.log('Loading notifications from database...');
                const response = await fetch(`${API_BASE}notifications.php`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();

                if (result.success) {
                    notifications = result.data;
                    console.log('Notifications loaded successfully:', notifications.length, 'items');
                } else {
                    console.error('Error loading notifications:', result.error);
                    showNotification('Error loading notifications: ' + result.error, 'error');
                    notifications = [];
                }
            } catch (error) {
                console.error('Error loading notifications:', error);
                showNotification('Error connecting to database for notifications', 'error');
                notifications = [];
            }
        }

        // Display notifications
        async function displayNotifications() {
            await loadNotifications();

            const container = document.getElementById('notificationsContainer');
            if (!container) return;

            if (notifications.length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 2rem;">No notifications available</div>';
                return;
            }

            container.innerHTML = '';

            notifications.forEach(notification => {
                const notificationItem = document.createElement('div');
                notificationItem.className = `notification-item ${!notification.is_read ? 'unread' : ''}`;

                notificationItem.innerHTML = `
                    <div class="notification-icon">
                        <i class="fas fa-${getNotificationIcon(notification.type)}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${notification.title}</div>
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-time">${formatTime(notification.created_at)}</div>
                    </div>
                    <button class="action-btn" onclick="dismissNotification(${notification.id})" style="background: #dc3545; color: white;">
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

        // Show notification toast
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            document.querySelectorAll('.notification-toast').forEach(toast => {
                toast.remove();
            });

            const notification = document.createElement('div');
            notification.className = `notification-toast notification-${type}`;
            notification.style.cssText = `
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
                border-left: 4px solid ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#17a2b8'};
            `;
            
            notification.innerHTML = `
                <i class="fas fa-${getNotificationIcon(type)}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; cursor: pointer; margin-left: auto;">
                    <i class="fas fa-times"></i>
                </button>
            `;

            document.body.appendChild(notification);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        // Initialize charts
        function initializeCharts() {
            // Sales Overview Chart
            const salesCtx = document.getElementById('salesOverviewChart');
            if (salesCtx && typeof Chart !== 'undefined') {
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
            if (topProductsCtx && typeof Chart !== 'undefined') {
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

            if (filteredProducts.length === 0) {
                productGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: #666;">No products found matching your search.</div>';
                return;
            }

            productGrid.innerHTML = '';

            filteredProducts.forEach(product => {
                const productCard = document.createElement('div');
                productCard.className = 'product-card';
                productCard.onclick = () => addToCart(product);

                productCard.innerHTML = `
                    <img src="${product.image_url || 'images/placeholder.jpg'}" alt="${product.name}" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0zNSA0MEg2NVY2MEgzNVY0MFoiIGZpbGw9IiM5Q0EzQUYiLz4KPC9zdmc+'">
                    <h4>${product.name}</h4>
                    <div class="price">₱${parseFloat(product.price).toFixed(2)}</div>
                    <div class="stock">Stock: ${product.stock}</div>
                `;

                productGrid.appendChild(productCard);
            });
        }

        // Placeholder functions
        function refreshInventory() {
            loadInventoryData();
            showNotification('Inventory refreshed', 'success');
        }

        function loadPOSData() {
            displayProducts();
        }

        function loadSalesManagement() {
            loadInventoryData();
        }

        function editProduct(id) {
            showNotification(`Edit product ${id} functionality coming soon`, 'info');
        }

        function viewProduct(id) {
            showNotification(`View product ${id} functionality coming soon`, 'info');
        }

        async function dismissNotification(id) {
            try {
                const response = await fetch(`${API_BASE}notifications.php?id=${id}`, {
                    method: 'DELETE'
                });

                const result = await response.json();

                if (result.success) {
                    showNotification('Notification dismissed', 'success');
                    displayNotifications();
                } else {
                    showNotification('Error dismissing notification', 'error');
                }
            } catch (error) {
                console.error('Error dismissing notification:', error);
                showNotification('Error dismissing notification', 'error');
            }
        }

        async function markAllRead() {
            try {
                const response = await fetch(`${API_BASE}notifications.php?mark_all_read=1`, {
                    method: 'PUT'
                });

                const result = await response.json();

                if (result.success) {
                    showNotification('All notifications marked as read', 'success');
                    displayNotifications();
                } else {
                    showNotification('Error marking notifications as read', 'error');
                }
            } catch (error) {
                console.error('Error marking notifications as read:', error);
                showNotification('Error marking notifications as read', 'error');
            }
        }

        function openAddProductModal() {
            showNotification('Add product modal functionality coming soon', 'info');
        }

        // Modal functions
        function openModal(title, content) {
            const modal = document.getElementById('modalOverlay');
            const modalContent = document.getElementById('modalContent');

            if (modal && modalContent) {
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
        }

        function closeModal() {
            const modal = document.getElementById('modalOverlay');
            if (modal) {
                modal.classList.remove('active');
            }
        }

        console.log('Admin dashboard with database connection loaded successfully');
    </script>
</body>
</html>
