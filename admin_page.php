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

// Allow both admin and cashier roles to access this dashboard
$isStaffLoggedIn = false;
$adminName = '';
$adminEmail = '';
$role = $_SESSION['role'] ?? null;

if ($role === 'admin' || $role === 'cashier') {
    $isStaffLoggedIn = true;
    // Prefer standard session variables set during login
    $adminName = $_SESSION['username'] ?? ($_SESSION['admin_name'] ?? '');
    $adminEmail = $_SESSION['email'] ?? ($_SESSION['admin_email'] ?? '');
}

if (!$isStaffLoggedIn) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script>
        window.userRole = '<?php echo $role; ?>';
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/admin_page.css">
    
    
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar Navigation -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-store"></i> IMS/POS</h2>
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
                <?php if ($role === 'admin'): ?>
                <li><a href="#sales-management" class="nav-link" data-section="sales-management">
                    <i class="fas fa-cogs"></i> Product Management
                </a></li>
                <li><a href="#sales-report" class="nav-link" data-section="sales-report">
                    <i class="fas fa-chart-line"></i> Sales Report
                </a></li>
                <?php endif; ?>
                <li><a href="#notifications" class="nav-link" data-section="notifications">
                    <i class="fas fa-bell"></i> Notifications
                    <span class="nav-badge" id="notificationsBadge" aria-label="Unread notifications" title="Unread notifications" style="display:none">0</span>
                </a></li>
                <?php if ($role === 'admin'): ?>
                <li><a href="#requests" class="nav-link" data-section="requests">
                    <i class="fas fa-inbox"></i> Requests
                    <span class="nav-badge" id="requestsBadge" aria-label="Pending requests" title="Pending requests" style="display:none">0</span>
                </a></li>
                <li><a href="#customersupport" class="nav-link" data-section="customersupport">
                    <i class="fas fa-headset"></i> Customer Support
                    <span class="nav-badge" id="supportBadge" aria-label="Unread support messages" title="Unread support messages" style="display:none">0</span>
                </a></li>
                <li><a href="#user-management" class="nav-link" data-section="user-management">
                    <i class="fas fa-user"></i> User Management
                </a></li>
                <?php endif; ?>
                <li><a href="#" class="nav-link logout" onclick="handleLogout('admin')">
                    <i class="fas fa-right-from-bracket"></i> Logout
                </a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Dashboard Section -->
            <section id="dashboard" class="content-section active">
                <div class="section-header">
                    <h1>Dashboard</h1>
                    <div class="header-right">
                        <div class="logged-in-user" title="Logged in account">
                            <i class="fas <?php echo $role === 'admin' ? 'fa-user-shield' : 'fa-cash-register'; ?>"></i>
                            <span>
                                <?php echo htmlspecialchars($adminName ?: $adminEmail); ?>
                                (<?php echo htmlspecialchars(ucfirst($role)); ?>)
                            </span>
                        </div>
                        <div class="date-time" id="current-datetime"></div>
                    </div>
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
                            <i class="fas fa-inbox"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="total-requests">0</h3>
                            <p>Requests</p>
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

                <div class="charts-container">
                    <!-- Period Selector -->
                    <div class="chart-period-selector" id="chart-period-selector">
                        <div class="period-buttons">
                            <button class="period-btn active" data-period="daily">Daily</button>
                            <button class="period-btn" data-period="weekly">Weekly</button>
                            <button class="period-btn" data-period="monthly">Monthly</button>
                            <button class="period-btn" data-period="annually">Annually</button>
                        </div>
                    </div>
                    
                    <!-- Dashboard Charts -->
                    <div class="dashboard-charts">
                        <div class="chart-container">
                            <h3>Sales Analytics</h3>
                            <canvas id="salesOverviewChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <h3>Top Products</h3>
                            <canvas id="topProductsChart"></canvas>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Inventory Management Section -->
            <section id="inventory" class="content-section">
                <div class="section-header">
                    <h1>Inventory Management</h1>
                    <div class="section-description">
                        <p style="color: #666; font-size: 0.9rem; margin: 0;">Manage stock levels and restock products</p>
                    </div>
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
                        <button class="btn btn-primary" onclick="clearTransaction()">
                            <i class="fas fa-trash"></i> Clear
                        </button>
                    </div>
                </div>

                <div class="pos-container">
                    <div class="pos-left">
                        <div class="product-search">
                            <input type="text" id="productSearch" placeholder="Search products..." onkeyup="searchProducts()" autocomplete="off">
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

                                </div>
                                <div class="summary-row total">
                                    <span>Total:</span>
                                    <span id="cartTotal">₱0.00</span>
                                </div>
                            </div>
                            <div class="payment-section">
                                <select id="posPaymentMethod" autocomplete="off">
                                    <option value="cash">Cash</option>
                                    <option value="gcash">GCash</option>
                                </select>
                                <input type="number" id="amountReceived" placeholder="Amount Received" step="0.01" autocomplete="off">
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

            <!-- Sales Management Section (Admin Only) -->
            <?php if ($role === 'admin'): ?>
            <section id="sales-management" class="content-section">
                <div class="section-header">
                    <h1>Product Management</h1>
                    <div class="section-description">
                        <p style="color: #666; font-size: 0.9rem; margin: 0;">Add, edit, and delete products</p>
                    </div>
                    <button class="btn btn-primary" onclick="openAddProductModal()">
                        <i class="fas fa-plus"></i> Add Product
                    </button>
                </div>

                <div class="inventory-table-container">
                    <table class="inventory-table" id="productsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
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
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem;">Loading products...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Sales Report Section (Admin Only) -->
            <section id="sales-report" class="content-section">
                <div class="section-header">
                    <h1>Sales Report</h1>
                    <div class="report-actions">
                        <button class="btn btn-primary" onclick="generateReport()">
                            <i class="fas fa-chart-bar"></i> Generate Report
                        </button>
                        <button class="btn btn-secondary" onclick="exportReport()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>

                <!-- Report Filters -->
                <div class="report-filters-container">
                    <div class="report-filters">
                        <div class="filter-group">
                            <label for="reportDateRange">Date Range:</label>
                            <select id="reportDateRange" onchange="updateDateInputs()">
                                <option value="today">Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="this_week">This Week</option>
                                <option value="last_week">Last Week</option>
                                <option value="this_month">This Month</option>
                                <option value="last_month">Last Month</option>
                                <option value="this_year">This Year</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        
                        <div class="filter-group" id="customDateInputs" style="display: none;">
                            <label for="startDate">From:</label>
                            <input type="date" id="startDate" autocomplete="off">
                            <label for="endDate">To:</label>
                            <input type="date" id="endDate" autocomplete="off">
                        </div>
                        
                        <div class="filter-group">
                            <label for="reportCategory">Category:</label>
                            <select id="reportCategory">
                                <option value="all">All Categories</option>
                                <option value="electronics">Electronics</option>
                                <option value="clothing">Clothing</option>
                                <option value="food">Food & Beverages</option>
                                <option value="books">Books</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="reportPaymentMethod">Payment Method:</label>
                            <select id="reportPaymentMethod" autocomplete="off">
                                <option value="all">All Methods</option>
                                <option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Report Summary Cards -->
                <div class="report-summary">
                    <div class="summary-card">
                        <div class="summary-icon">
                            <i class="fas fa-peso-sign"></i>
                        </div>
                        <div class="summary-content">
                            <div class="summary-value" id="reportTotalSales">₱0.00</div>
                            <div class="summary-label">Total Sales</div>
                            <div class="summary-change" id="salesChange">+0%</div>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="summary-content">
                            <div class="summary-value" id="reportTotalTransactions">0</div>
                            <div class="summary-label">Total Transactions</div>
                            <div class="summary-change" id="transactionsChange">+0%</div>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="summary-content">
                            <div class="summary-value" id="reportAvgTransaction">₱0.00</div>
                            <div class="summary-label">Avg Transaction</div>
                            <div class="summary-change" id="avgChange">+0%</div>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="summary-content">
                            <div class="summary-value" id="reportTopProduct">-</div>
                            <div class="summary-label">Top Product</div>
                            <div class="summary-change" id="topProductSales">0 sold</div>
                        </div>
                    </div>
                </div>

                <!-- Report Charts -->
                <div class="report-charts-container">
                    <div class="chart-row">
                        <div class="chart-container large">
                            <h3>Sales Trend</h3>
                            <canvas id="salesTrendChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-row">
                        <div class="chart-container medium">
                            <h3>Sales by Category</h3>
                            <canvas id="categoryChart"></canvas>
                        </div>
                        <div class="chart-container medium">
                            <h3>Payment Methods</h3>
                            <canvas id="paymentMethodChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-row">
                        <div class="chart-container medium">
                            <h3>Hourly Sales Distribution</h3>
                            <canvas id="hourlySalesChart"></canvas>
                        </div>
                        <div class="chart-container medium">
                            <h3>Top 10 Products</h3>
                            <canvas id="topProductsReportChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Detailed Report Table -->
                <div class="report-table-container">
                    <div class="table-header">
                        <h3>Detailed Sales Data</h3>
                        <div class="table-controls">
                            <input type="text" id="reportTableSearch" placeholder="Search transactions..." onkeyup="filterReportTable()" autocomplete="off">
                            <select id="reportTableSort" onchange="sortReportTable()">
                                <option value="date_desc">Date (Newest)</option>
                                <option value="date_asc">Date (Oldest)</option>
                                <option value="amount_desc">Amount (High to Low)</option>
                                <option value="amount_asc">Amount (Low to High)</option>
                            </select>
                        </div>
                    </div>
                    
                    <table class="report-table" id="reportTable">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Date & Time</th>
                                <th>Items</th>
                                <th>Payment Method</th>
                                <th>Amount</th>
                                <th>Cashier</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="reportTableBody">
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem;">Select date range and click "Generate Report" to view data</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>

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

            <!-- Reports Section (Admin Only) -->
            <?php if ($role === 'admin'): ?>
            <section id="requests" class="content-section">
                <div class="section-header">
                    <h1>Customer Requests</h1>
                    <div class="section-description">
                        <p style="color: #666; font-size: 0.9rem; margin: 0;">Manage customer printing and service requests</p>
                    </div>

                    <div class="requests-filters">
                        <select id="requestStatusFilter" onchange="filterRequests()">
                            <option value="all">All Requests</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                        <button class="btn btn-primary" onclick="refreshRequests()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                </div>
                
                <div class="requests-actions" style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                    <form method="POST" action="api/clear_requests.php" onsubmit="return confirm('Are you sure you want to clear all requests?');" style="margin: 0;">
                        <button type="submit" class="btn btn-danger">
                            Clear All Requests
                        </button>
                    </form>
                        
                    <button class="btn btn-secondary" onclick="viewRequestHistory()">
                        View History
                    </button>
                </div>

                <!-- Request Statistics -->
                <div class="requests-stats">
                    <div class="stat-card small">
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="pending-requests">0</h3>
                            <p>Pending</p>
                        </div>
                    </div>
                    <div class="stat-card small">
                        <div class="stat-icon approved">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="approved-requests">0</h3>
                            <p>Approved</p>
                        </div>
                    </div>
                    <div class="stat-card small">
                        <div class="stat-icon rejected">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="rejected-requests">0</h3>
                            <p>Rejected</p>
                        </div>
                    </div>
                </div>

                <!-- Requests Table -->
                <div class="requests-table-container">
                    <table class="inventory-table" id="requestsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Details</th>
                                <th>Quantity</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="requestsTableBody">
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 2rem;">Loading requests...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>

            <!-- User Management Section (Admin Only) -->
            <?php if ($role === 'admin'): ?>
            <section id="user-management" class="content-section">
                <div class="section-header">
                    <h1>User Management</h1>
                    <div style="display: flex; gap: 0.5rem;">
                        <button class="btn btn-primary" onclick="openAddUserModal()">
                            <i class="fas fa-user-plus"></i> Add User
                        </button>
                        <button class="btn btn-secondary" onclick="userManagement.showRolePermissions()" style="
                            padding: 0.5rem 1rem;
                            border: 1px solid #d1d5db;
                            border-radius: 4px;
                            background: #ffffff;
                            color: #374151;
                            cursor: pointer;
                            transition: all 0.2s;
                        ">
                            <i class="fas fa-key"></i>
                        </button>
                    </div>
                </div>

                <!-- User Stats -->
                <div class="stats-grid">
                    <div class="stat-card small">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number" id="total-users">0</div>
                            <div class="stat-label">Total Staff</div>
                        </div>
                    </div>
                    <div class="stat-card small">
                        <div class="stat-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number" id="admin-users">0</div>
                            <div class="stat-label">Admins</div>
                        </div>
                    </div>
                    <div class="stat-card small">
                        <div class="stat-icon">
                            <i class="fas fa-cash-register"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number" id="cashier-users">0</div>
                            <div class="stat-label">Cashiers</div>
                        </div>
                    </div>
                </div>

                <!-- User Management Controls -->
                <div class="user-management-controls" style="
                    background: #ffffff;
                    border: 1px solid #e5e5e5;
                    border-radius: 8px;
                    padding: 1rem;
                    margin-bottom: 1rem;
                ">
                    <div class="search-filter-container" style="
                        display: flex;
                        gap: 1rem;
                        align-items: center;
                        flex-wrap: wrap;
                    ">
                        <input type="text" id="userSearch" placeholder="Search users..." class="search-input" autocomplete="off" style="
                            flex: 1;
                            min-width: 200px;
                            padding: 0.5rem;
                            border: 1px solid #d1d5db;
                            border-radius: 4px;
                            font-size: 0.875rem;
                            background: #ffffff;
                            color: #374151;
                        ">
                        <select id="roleFilter" onchange="filterUsers()" style="
                            padding: 0.5rem;
                            border: 1px solid #d1d5db;
                            border-radius: 4px;
                            font-size: 0.875rem;
                            background: #ffffff;
                            color: #374151;
                            min-width: 120px;
                        ">
                            <option value="all">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="cashier">Cashier</option>
                            <option value="user">User</option>
                        </select>
                        <select id="statusFilter" onchange="filterUsers()" style="
                            padding: 0.5rem;
                            border: 1px solid #d1d5db;
                            border-radius: 4px;
                            font-size: 0.875rem;
                            background: #ffffff;
                            color: #374151;
                            min-width: 120px;
                        ">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="users-table-container" style="
                    background: #ffffff;
                    border: 1px solid #e5e5e5;
                    border-radius: 8px;
                    overflow: hidden;
                ">
                    <table class="inventory-table" id="usersTable" style="
                        width: 100%;
                        border-collapse: collapse;
                        background: #ffffff;
                    ">
                        <thead style="background: #f8f9fa; border-bottom: 2px solid #e5e5e5;">
                            <tr>
                                <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e5e5;">ID</th>
                                <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e5e5;">Name</th>
                                <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e5e5;">Email</th>
                                <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e5e5;">Role</th>
                                <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e5e5;">Status</th>
                                <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e5e5;">Created</th>
                                <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e5e5;">Last Login</th>
                                <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e5e5;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem; color: #6b7280;">Loading users...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>

            <!-- Customer Support Section (Admin Only) -->
            <?php if ($role === 'admin'): ?>
            <section id="customersupport" class="content-section">
                <div class="section-header">
                    <h1>Customer Support</h1>
                    <div class="section-actions">
                        <button id="refreshSupportBtn" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>

                <!-- Support Stats -->
                <div class="stats-grid">
                    <div class="stat-card small">
                        <div class="stat-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number" id="total-messages">0</div>
                            <div class="stat-label">Total Messages</div>
                        </div>
                    </div>
                    <div class="stat-card small">
                        <div class="stat-icon">
                            <i class="fas fa-envelope-open"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number" id="unread-messages">0</div>
                            <div class="stat-label">Unread</div>
                        </div>
                    </div>
                    <div class="stat-card small">
                        <div class="stat-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number" id="active-conversations">0</div>
                            <div class="stat-label">Active Conversations</div>
                        </div>
                    </div>
                </div>

                <!-- Messenger-style Support Interface -->
                <div class="support-messenger-container">
                    <!-- Conversations List -->
                    <div class="support-conversations-panel">
                        <div class="conversations-header">
                            <h3>Conversations</h3>
                            <div class="conversations-search">
                                <input type="text" id="conversationSearch" placeholder="Search conversations..." class="search-input-small" autocomplete="off">
                            </div>
                        </div>
                        <div class="conversations-list" id="conversationsList">
                            <div class="loading-conversations">
                                <i class="fas fa-spinner fa-spin"></i> Loading conversations...
                            </div>
                        </div>
                    </div>

                    <!-- Chat Area -->
                    <div class="support-chat-panel">
                        <div class="chat-header" id="chatHeader" style="display: none;">
                            <div class="chat-user-info">
                                <div class="chat-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="chat-user-details">
                                    <div class="chat-user-name" id="chatUserName"></div>
                                    <div class="chat-user-email" id="chatUserEmail"></div>
                                </div>
                            </div>
                            <div class="chat-actions">
                                <button class="btn-chat-action" id="markAllReadBtn" title="Mark all as read">
                                    <i class="fas fa-check-double"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="chat-messages" id="chatMessages">
                            <div class="no-conversation-selected">
                                <i class="fas fa-comments"></i>
                                <h3>Select a conversation</h3>
                                <p>Choose a conversation from the list to start messaging</p>
                            </div>
                        </div>
                        
                        <div class="chat-input-area" id="chatInputArea" style="display: none;">
                            <div class="chat-input-container">
                                <textarea id="adminReplyInput" placeholder="Type your reply..." rows="2"></textarea>
                                <button id="sendReplyBtn" class="btn-send-reply">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        </main>
    </div>

    <!-- Support Message Modal -->
    <div id="supportMessageModal" class="support-message-modal">
        <div class="support-message-modal-content">
            <div class="support-message-modal-header">
                <h3>Support Message</h3>
                <button class="support-message-close" onclick="closeSupportMessageModal()">×</button>
            </div>
            <div class="support-message-modal-body">
                <div class="support-message-info">
                    <div class="support-message-meta">
                        <p><strong>From:</strong> <span id="modalCustomerName"></span></p>
                        <p><strong>Email:</strong> <span id="modalCustomerEmail"></span></p>
                    </div>
                    <div class="support-message-meta">
                        <p><strong>Date:</strong> <span id="modalMessageDate"></span></p>
                        <p><strong>Status:</strong> <span id="modalMessageStatus" class="support-message-status"></span></p>
                    </div>
                </div>
                
                <div class="support-message-content">
                    <h4>Subject:</h4>
                    <div id="modalSubject" class="support-message-text"></div>
                </div>
                
                <div class="support-message-content">
                    <h4>Message:</h4>
                    <div id="modalMessage" class="support-message-text"></div>
                </div>
                
                <div class="support-response-section">
                    <h4>Admin Response</h4>
                    <textarea id="adminResponse" class="support-response-textarea" placeholder="Type your response to the customer..." rows="6"></textarea>
                    <div class="support-modal-actions">
                        <button id="sendResponseBtn" class="support-btn support-btn-primary">
                            📤 Send Response
                        </button>
                        <button id="markAsReadBtn" class="support-btn support-btn-secondary">
                            ✓ Mark as Read
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Management Modals -->
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <button class="modal-close" onclick="closeAddUserModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <div class="form-group">
                        <label for="userName">Username</label>
                        <input type="text" id="userName" name="username" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label for="userEmail">Email</label>
                        <input type="email" id="userEmail" name="email" required autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="userPassword">Password</label>
                        <input type="password" id="userPassword" name="password" required autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="addUserRole">Role</label>
                        <select id="addUserRole" name="role" required>
                            <option value="">Select Role</option>
                            <option value="admin">Admin - Full access to all features</option>
                            <option value="cashier">Cashier - POS and inventory view only</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="userStatus">Status</label>
                        <select id="userStatus" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button class="modal-close" onclick="closeEditUserModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    <input type="hidden" id="editUserId" name="user_id">
                    <div class="form-group">
                        <label for="editUserName">Username</label>
                        <input type="text" id="editUserName" name="username" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label for="editUserEmail">Email</label>
                        <input type="email" id="editUserEmail" name="email" required autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="editUserRole">Role</label>
                        <select id="editUserRole" name="role" required>
                            <option value="admin">Admin - Full access to all features</option>
                            <option value="cashier">Cashier - POS and inventory view only</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="resetPassword" name="reset_password"> Reset Password
                        </label>
                        <div class="input-container" style="position: relative; display: none;" id="passwordContainer">
                            <input type="password" id="newPassword" name="new_password" placeholder="New password (if resetting)" autocomplete="new-password" style="padding-right: 50px;">
                            <button type="button" id="toggle-reset-password" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;outline:none;cursor:pointer;padding:5px;z-index:10;display:flex;align-items:center;justify-content:center;">
                                <img id="reset-eye-icon" src="images/svg/eye-slash-black.svg" alt="Show Password" width="20" height="20">
                            </button>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Role Permissions Modal -->
    <div id="rolePermissionsModal" class="modal-overlay" style="display: none;">
        <div class="modal-content large">
            <div class="modal-header">
                <h3>Role Permissions</h3>
                <button class="modal-close" onclick="closeRolePermissionsModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="permissions-grid">
                    <div class="role-section">
                        <h4><i class="fas fa-user-shield"></i> Admin</h4>
                        <ul class="permissions-list">
                            <li><i class="fas fa-check text-success"></i> Full dashboard access</li>
                            <li><i class="fas fa-check text-success"></i> User management</li>
                            <li><i class="fas fa-check text-success"></i> Product management</li>
                            <li><i class="fas fa-check text-success"></i> Inventory management</li>
                            <li><i class="fas fa-check text-success"></i> POS operations</li>
                            <li><i class="fas fa-check text-success"></i> Customer support</li>
                            <li><i class="fas fa-check text-success"></i> Reports and analytics</li>
                        </ul>
                    </div>
                    <div class="role-section">
                        <h4><i class="fas fa-cash-register"></i> Cashier</h4>
                        <ul class="permissions-list">
                            <li><i class="fas fa-check text-success"></i> POS operations</li>
                            <li><i class="fas fa-check text-success"></i> View inventory</li>
                            <li><i class="fas fa-check text-success"></i> Process transactions</li>
                            <li><i class="fas fa-check text-success"></i> View customer requests</li>
                            <li><i class="fas fa-times text-danger"></i> User management</li>
                            <li><i class="fas fa-times text-danger"></i> Product management</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="modalOverlay" class="modal-overlay">
        <div id="modalContent" class="modal-content">
        </div>
    </div>

    <script>
        // Set CSRF token for API requests
        <?php
        require_once 'includes/csrf.php';
        $csrfToken = getCSRFToken();
        ?>
        window.csrfToken = '<?php echo $csrfToken; ?>';
        window.currentAdminId = <?php echo $_SESSION['admin_user_id'] ?? 'null'; ?>;
    </script>
    <script type="module" src="js/admin-dashboard.js"></script>
</body>
</html>
