<?php
session_start();

// Allow both admin and cashier roles to access this dashboard
$isStaffLoggedIn = false;
$adminName = '';
$adminEmail = '';
$role = $_SESSION['role'] ?? null;

if ($role === 'admin' || $role === 'cashier') {
    $isStaffLoggedIn = true;
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
    <title>Order Management - Production Tracking</title>
    <script>
        window.userRole = '<?php echo $role; ?>';
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin_page.css">
    <link rel="stylesheet" href="css/order_management.css">
</head>
<body>
    <div class="order-management-container">
        <!-- Header -->
        <header class="order-header">
            <div class="header-left">
                <button class="back-btn" onclick="window.location.href='admin_page.php'">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </button>
                <h1><i class="fas fa-cogs"></i> Order Production Management</h1>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <i class="fas <?php echo $role === 'admin' ? 'fa-user-shield' : 'fa-cash-register'; ?>"></i>
                    <span><?php echo htmlspecialchars($adminName ?: $adminEmail); ?></span>
                </div>
                <button class="refresh-btn" onclick="refreshOrders()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
        </header>

        <!-- Status Filter Tabs -->
        <div class="status-tabs">
            <button class="tab-btn active" data-status="all" onclick="filterByStatus('all')">
                <i class="fas fa-list"></i> All Orders (<span id="count-all">0</span>)
            </button>
            <button class="tab-btn" data-status="approved" onclick="filterByStatus('approved')">
                <i class="fas fa-check-circle"></i> Awaiting Production (<span id="count-approved">0</span>)
            </button>
            <button class="tab-btn" data-status="printing" onclick="filterByStatus('printing')">
                <i class="fas fa-print"></i> Printing (<span id="count-printing">0</span>)
            </button>
            <button class="tab-btn" data-status="ready_for_pickup" onclick="filterByStatus('ready_for_pickup')">
                <i class="fas fa-box"></i> Ready for Pickup (<span id="count-ready">0</span>)
            </button>
            <button class="tab-btn" data-status="on_the_way" onclick="filterByStatus('on_the_way')">
                <i class="fas fa-truck"></i> On the Way (<span id="count-delivery">0</span>)
            </button>
            <button class="tab-btn" data-status="completed" onclick="filterByStatus('completed')">
                <i class="fas fa-check-double"></i> Completed (<span id="count-completed">0</span>)
            </button>
        </div>

        <!-- Orders Grid -->
        <div class="orders-grid" id="ordersGrid">
            <div class="loading-state">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading orders...</p>
            </div>
        </div>

        <!-- Quick Actions Panel -->
        <div class="quick-actions" id="quickActions" style="display: none;">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            <div class="action-buttons">
                <button class="quick-btn" onclick="bulkUpdateStatus('printing')">
                    <i class="fas fa-print"></i> Move Selected to Printing
                </button>
                <button class="quick-btn" onclick="bulkUpdateStatus('ready_for_pickup')">
                    <i class="fas fa-box"></i> Mark as Ready for Pickup
                </button>
                <button class="quick-btn" onclick="bulkUpdateStatus('on_the_way')">
                    <i class="fas fa-truck"></i> Mark as On the Way
                </button>
                <button class="quick-btn" onclick="bulkUpdateStatus('completed')">
                    <i class="fas fa-check-double"></i> Mark as Completed
                </button>
            </div>
            <button class="clear-selection" onclick="clearSelection()">
                <i class="fas fa-times"></i> Clear Selection
            </button>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderModal" class="modal-overlay" style="display: none;">
        <div class="modal-content order-modal">
            <div class="modal-header">
                <h3 id="modalTitle">Order Details</h3>
                <button class="modal-close" onclick="closeOrderModal()">×</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Order details will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal-overlay" style="display: none;">
        <div class="modal-content status-modal">
            <div class="modal-header">
                <h3>Update Order Status</h3>
                <button class="modal-close" onclick="closeStatusModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="statusUpdateForm">
                    <input type="hidden" id="orderId" name="order_id">
                    
                    <div class="form-group">
                        <label for="newStatus">New Status</label>
                        <select id="newStatus" name="status" required>
                            <option value="">Select status...</option>
                            <option value="printing">🖨️ Printing</option>
                            <option value="ready_for_pickup">📦 Ready for Pickup</option>
                            <option value="on_the_way">🚚 On the Way</option>
                            <option value="completed">✅ Completed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="statusNote">Update Note</label>
                        <textarea id="statusNote" name="note" 
                                placeholder="Add a note about this status change (will be sent to customer)..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="sendEmail" name="send_email" checked>
                            <span class="checkmark"></span>
                            Send email notification to customer
                        </label>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Update & Notify
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Set CSRF token for API requests
        <?php
        require_once 'includes/csrf.php';
        $csrfToken = CSRFToken::getToken();
        ?>
        window.csrfToken = '<?php echo $csrfToken; ?>';
    </script>
    <script type="module" src="js/order-management.js"></script>
</body>
</html>
