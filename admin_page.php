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
                <li><a href="#sales-management" class="nav-link" data-section="sales-management">
                    <i class="fas fa-cogs"></i> Product Management
                </a></li>
                <li><a href="#notifications" class="nav-link" data-section="notifications">
                    <i class="fas fa-bell"></i> Notifications
                </a></li>
                <li><a href="#requests" class="nav-link" data-section="requests">
                    <i class="fas fa-inbox"></i> Requests
                </a></li>
                <li><a href="#customersupport" class="nav-link" data-section="customersupport">
                    <i class="fas fa-headset"></i> Customer Support
                </a></li>
                <li><a href="#user-management" class="nav-link" data-section="user-management">
                    <i class="fas fa-user"></i> User Management
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
                    <h1>Dashboard</h1>
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
                            <h3>Sales Overview</h3>
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

                                </div>
                                <div class="summary-row total">
                                    <span>Total:</span>
                                    <span id="cartTotal">₱0.00</span>
                                </div>
                            </div>
                            <div class="payment-section">
                                <select id="paymentMethod">
                                    <option value="cash">Cash</option>
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

            <!-- Requests Section -->
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
                        
                    <a href="request_history.php" class="btn btn-secondary">
                        View History
                    </a>
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
        </main>
    </div>
    
    <div id="modalOverlay" class="modal-overlay">
        <div id="modalContent" class="modal-content">
        </div>
    </div>

    <script type="module" src="js/admin-dashboard.js"></script>
</body>
</html>
