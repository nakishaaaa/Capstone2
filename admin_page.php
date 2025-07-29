<?php
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
    <link rel="stylesheet" href="css/admin_page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>
<body>
    <div class="admin-container">
        <!-- Sidebar Navigation -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-store"></i> Admin Panel</h2>
                <p>Welcome</p>
            </div>
            <ul class="nav-menu">
                <li><a href="#dashboard" class="nav-link active" data-section="dashboard">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a></li>
                <li><a href="#inventory" class="nav-link" data-section="inventory">
                    <i class="fas fa-boxes"></i> Inventory Analytics
                </a></li>
                <li><a href="#sales-analytics" class="nav-link" data-section="sales-analytics">
                    <i class="fas fa-chart-line"></i> Sales Analytics
                </a></li>
                <li><a href="#sales-report" class="nav-link" data-section="sales-report">
                    <i class="fas fa-file-alt"></i> Sales Report
                </a></li>
                <li><a href="#pos" class="nav-link" data-section="pos">
                    <i class="fas fa-cash-register"></i> Point of Sale
                </a></li>
                <li><a href="#sales-management" class="nav-link" data-section="sales-management">
                    <i class="fas fa-cogs"></i> Sales Management
                </a></li>
                <li><a href="#restocking" class="nav-link" data-section="restocking">
                    <i class="fas fa-truck"></i> Restocking
                </a></li>
                <li><a href="#access-control" class="nav-link" data-section="access-control">
                    <i class="fas fa-users-cog"></i> Access Control
                </a></li>
                <li><a href="#notifications" class="nav-link" data-section="notifications">
                    <i class="fas fa-bell"></i> Notifications
                </a></li>
                <li><a href="#customer-support" class="nav-link" data-section="customer-support">
                    <i class="fas fa-headset"></i> Customer Support
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

            <!-- Inventory Analytics Section -->
            <section id="inventory" class="content-section">
                <div class="section-header">
                    <h1>Inventory Analytics</h1>
                    <button class="btn btn-primary" onclick="refreshInventory()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
                
                <div class="inventory-grid">
                    <div class="inventory-card">
                        <h3>Stock Levels</h3>
                        <canvas id="stockLevelsChart"></canvas>
                    </div>
                    <div class="inventory-card">
                        <h3>Product Categories</h3>
                        <canvas id="categoriesChart"></canvas>
                    </div>
                </div>

                <div class="inventory-table-container">
                    <h3>Current Inventory Status</h3>
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
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Sales Analytics Section -->
            <section id="sales-analytics" class="content-section">
                <div class="section-header">
                    <h1>Sales Analytics</h1>
                    <div class="date-filters">
                        <select id="analyticsFilter">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                </div>

                <div class="analytics-grid">
                    <div class="analytics-card">
                        <h3>Sales Trend</h3>
                        <canvas id="salesTrendChart"></canvas>
                    </div>
                    <div class="analytics-card">
                        <h3>Revenue Analysis</h3>
                        <canvas id="revenueChart"></canvas>
                    </div>
                    <div class="analytics-card">
                        <h3>Product Performance</h3>
                        <canvas id="productPerformanceChart"></canvas>
                    </div>
                    <div class="analytics-card">
                        <h3>Customer Analytics</h3>
                        <canvas id="customerChart"></canvas>
                    </div>
                </div>
            </section>

            <!-- Sales Report Section -->
            <section id="sales-report" class="content-section">
                <div class="section-header">
                    <h1>Sales Report</h1>
                    <div class="report-controls">
                        <input type="date" id="reportStartDate">
                        <input type="date" id="reportEndDate">
                        <button class="btn btn-primary" onclick="generateReport()">
                            <i class="fas fa-chart-bar"></i> Generate Report
                        </button>
                        <button class="btn btn-secondary" onclick="exportReport()">
                            <i class="fas fa-download"></i> Export PDF
                        </button>
                    </div>
                </div>

                <div class="report-summary" id="reportSummary">
                    <div class="summary-card">
                        <h4>Total Revenue</h4>
                        <p id="reportRevenue">₱0.00</p>
                    </div>
                    <div class="summary-card">
                        <h4>Total Orders</h4>
                        <p id="reportOrders">0</p>
                    </div>
                    <div class="summary-card">
                        <h4>Average Order Value</h4>
                        <p id="reportAOV">₱0.00</p>
                    </div>
                    <div class="summary-card">
                        <h4>Top Product</h4>
                        <p id="reportTopProduct">-</p>
                    </div>
                </div>

                <div class="report-table-container">
                    <table class="report-table" id="reportTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Products</th>
                                <th>Quantity</th>
                                <th>Total (₱)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="reportTableBody">
                            <!-- Dynamic content -->
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
                        <button class="btn btn-warning" onclick="holdTransaction()">
                            <i class="fas fa-pause"></i> Hold
                        </button>
                        <button class="btn btn-danger" onclick="clearTransaction()">
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
                            <!-- Dynamic product cards -->
                        </div>
                    </div>

                    <div class="pos-right">
                        <div class="cart-container">
                            <h3>Current Transaction</h3>
                            <div class="cart-items" id="cartItems">
                                <!-- Dynamic cart items -->
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

            <!-- Sales Management Section -->
            <section id="sales-management" class="content-section">
                <div class="section-header">
                    <h1>Sales Management</h1>
                    <button class="btn btn-primary" onclick="openAddProductModal()">
                        <i class="fas fa-plus"></i> Add Product
                    </button>
                </div>

                <div class="management-filters">
                    <input type="text" id="productFilter" placeholder="Filter products..." onkeyup="filterProducts()">
                    <select id="categoryFilter" onchange="filterProducts()">
                        <option value="">All Categories</option>
                        <!-- Dynamic categories -->
                    </select>
                </div>

                <div class="products-table-container">
                    <table class="products-table" id="productsTable">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price (₱)</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="productsTableBody">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Restocking Section -->
            <section id="restocking" class="content-section">
                <div class="section-header">
                    <h1>Restocking Management</h1>
                    <button class="btn btn-primary" onclick="openRestockModal()">
                        <i class="fas fa-truck"></i> New Restock Entry
                    </button>
                </div>

                <div class="restock-summary">
                    <div class="restock-card">
                        <h4>Pending Restocks</h4>
                        <p id="pendingRestocks">0</p>
                    </div>
                    <div class="restock-card">
                        <h4>This Month</h4>
                        <p id="monthlyRestocks">0</p>
                    </div>
                    <div class="restock-card">
                        <h4>Total Value</h4>
                        <p id="restockValue">₱0.00</p>
                    </div>
                </div>

                <div class="restock-table-container">
                    <table class="restock-table" id="restockTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Supplier</th>
                                <th>Quantity</th>
                                <th>Cost per Unit (₱)</th>
                                <th>Total Cost (₱)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="restockTableBody">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Access Control Section -->
            <section id="access-control" class="content-section">
                <div class="section-header">
                    <h1>Role-Based Access Control</h1>
                    <button class="btn btn-primary" onclick="openAddUserModal()">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                </div>

                <div class="users-table-container">
                    <table class="users-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Notifications Section -->
            <section id="notifications" class="content-section">
                <div class="section-header">
                    <h1>Notifications & Alerts</h1>
                    <button class="btn btn-secondary" onclick="markAllRead()">
                        <i class="fas fa-check-double"></i> Mark All Read
                    </button>
                </div>

                <div class="notifications-container" id="notificationsContainer">
                    <!-- Dynamic notifications -->
                </div>
            </section>

            <!-- Customer Support Section -->
            <section id="customer-support" class="content-section">
                <div class="section-header">
                    <h1>Customer Support</h1>
                    <div class="support-stats">
                        <span class="stat">Open Tickets: <strong id="openTickets">0</strong></span>
                        <span class="stat">Pending: <strong id="pendingTickets">0</strong></span>
                        <span class="stat">Resolved Today: <strong id="resolvedToday">0</strong></span>
                    </div>
                </div>

                <div class="support-table-container">
                    <table class="support-table" id="supportTable">
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
                        <tbody id="supportTableBody">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
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

    <script src="js/admin_dashboard.js"></script>
</body>
</html>