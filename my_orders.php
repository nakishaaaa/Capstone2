<?php
session_start();

// Require only user-specific session variables
$isUserLoggedIn = false;
$userName = '';
$userEmail = '';
$userId = '';

if (isset($_SESSION['user_name']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'user') {
    $isUserLoggedIn = true;
    $userName = $_SESSION['user_name'];
    $userEmail = $_SESSION['user_email'] ?? '';
    $userId = $_SESSION['user_id'] ?? '';
}

if (!$isUserLoggedIn) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>My Orders - 053 PRINTS</title>
    <link rel="stylesheet" href="css/user_page.css">
    <link rel="stylesheet" href="css/my_orders.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <button class="back-btn" onclick="window.location.href='user_page.php'">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back</span>
                </button>
            </div>
            <div class="brand">
                <span>053</span>
            </div>
            <div class="user-info">
                <div class="user-dropdown">
                    <button class="user-dropdown-btn" id="userDropdownBtn">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </button>
                    <div class="user-dropdown-menu" id="userDropdownMenu">
                        <div class="dropdown-header">
                            <div class="user-profile">
                                <div class="profile-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="profile-info">
                                    <div class="profile-name"><?php echo htmlspecialchars($userName); ?></div>
                                    <div class="profile-email"><?php echo htmlspecialchars($userEmail); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-links">
                            <a href="user_page.php" class="dropdown-link">
                                <i class="fas fa-home"></i>
                                Dashboard
                            </a>
                            <a href="#" class="dropdown-link active">
                                <i class="fas fa-shopping-cart"></i>
                                My Orders
                            </a>
                        </div>
                        <div class="dropdown-divider"></div>
                        <div class="dropdown-actions">
                            <a href="#" class="dropdown-action logout-action" onclick="handleLogout('user')">
                                <i class="fas fa-right-from-bracket" style="color: #ff4757;"></i>
                                Sign out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <div class="orders-container">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="page-title">
                        <h1><i class="fas fa-shopping-cart"></i> My Orders</h1>
                        <p>Track and manage your printing orders</p>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-primary" onclick="window.location.href='user_page.php'">
                            <i class="fas fa-plus"></i>
                            New Order
                        </button>
                    </div>
                </div>

                <!-- Order Filters -->
                <div class="order-filters">
                    <div class="filter-tabs">
                        <button class="filter-tab active" data-status="all">
                            <span>All Orders</span>
                            <span class="tab-count" id="allCount">0</span>
                        </button>
                        <button class="filter-tab" data-status="pending">
                            <span>Pending</span>
                            <span class="tab-count" id="pendingCount">0</span>
                        </button>
                        <button class="filter-tab" data-status="approved">
                            <span>Approved</span>
                            <span class="tab-count" id="approvedCount">0</span>
                        </button>
                        <button class="filter-tab" data-status="rejected">
                            <span>Rejected</span>
                            <span class="tab-count" id="rejectedCount">0</span>
                        </button>
                    </div>
                    <div class="filter-actions">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="orderSearch" placeholder="Search orders...">
                        </div>
                        <select id="sortBy" class="sort-select">
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="status">By Status</option>
                        </select>
                    </div>
                </div>

                <!-- Orders List -->
                <div class="orders-list" id="ordersList">
                    <!-- Loading State -->
                    <div class="loading-state" id="loadingState">
                        <div class="loading-spinner"></div>
                        <p>Loading your orders...</p>
                    </div>

                    <!-- Empty State -->
                    <div class="empty-state" id="emptyState" style="display: none;">
                        <div class="empty-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h3>No orders found</h3>
                        <p>You haven't placed any orders yet. Start by creating your first order!</p>
                        <button class="btn btn-primary" onclick="window.location.href='user_page.php'">
                            <i class="fas fa-plus"></i>
                            Place Your First Order
                        </button>
                    </div>

                    <!-- Orders will be dynamically loaded here -->
                </div>

                <!-- Pagination -->
                <div class="pagination-container" id="paginationContainer" style="display: none;">
                    <div class="pagination-info">
                        <span id="paginationInfo">Showing 1-10 of 25 orders</span>
                    </div>
                    <div class="pagination-controls">
                        <button class="pagination-btn" id="prevBtn" disabled>
                            <i class="fas fa-chevron-left"></i>
                            Previous
                        </button>
                        <div class="pagination-numbers" id="paginationNumbers">
                            <!-- Page numbers will be generated here -->
                        </div>
                        <button class="pagination-btn" id="nextBtn">
                            Next
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Order Detail Modal -->
    <div id="orderDetailModal" class="modal order-modal" style="display: none;">
        <div class="modal-content order-modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-receipt"></i> Order Details</h2>
                <button class="modal-close" id="closeOrderModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="orderDetailContent">
                <!-- Order details will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div id="toastContainer" class="toast-container"></div>

    <script>
        // Pass user data to JavaScript
        window.userData = {
            userId: '<?php echo $userId; ?>',
            userName: '<?php echo htmlspecialchars($userName); ?>',
            userEmail: '<?php echo htmlspecialchars($userEmail); ?>'
        };

        // Global logout function
        window.handleLogout = function(role) {
            fetch('api/logout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin'
            }).then(() => {
                window.location.href = 'index.php';
            }).catch(() => {
                window.location.href = 'index.php';
            });
        };
    </script>
    <script type="module" src="js/modules/my-orders-module.js"></script>
</body>
</html>
