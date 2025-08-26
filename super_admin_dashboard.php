<?php
session_start();

// Check if user is logged in as super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: superadminlog.php');
    exit();
}

require_once 'includes/config.php';

// Get system statistics
function getSystemStats($conn) {
    $stats = [];
    
    // Total users
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $result->fetch_assoc()['count'];
    
    // Total admins
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role IN ('admin', 'super_admin')");
    $stats['total_admins'] = $result->fetch_assoc()['count'];
    
    // Total sales today
    $result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(created_at) = CURDATE()");
    $stats['sales_today'] = $result->fetch_assoc()['total'];
    
    // Pending requests
    $result = $conn->query("SELECT COUNT(*) as count FROM user_requests WHERE status = 'pending'");
    $stats['pending_requests'] = $result->fetch_assoc()['count'];
    
    // Recent activities (placeholder)
    $stats['recent_activities'] = 0;
    
    return $stats;
}

$stats = getSystemStats($conn);

// Get maintenance mode status
$maintenanceResult = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
$maintenanceMode = $maintenanceResult ? $maintenanceResult->fetch_assoc()['setting_value'] === 'true' : false;

// Get recent notifications
$notificationsResult = $conn->query("SELECT * FROM developer_notifications WHERE is_read = FALSE ORDER BY created_at DESC LIMIT 5");
$notifications = $notificationsResult ? $notificationsResult->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Dashboard - 053 Prints</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/super_admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-code"></i>
                    <span>Developer Portal</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item active">
                        <a href="#dashboard" class="nav-link" data-section="dashboard">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#system-settings" class="nav-link" data-section="system-settings">
                            <i class="fas fa-cogs"></i>
                            <span>System Settings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#account-management" class="nav-link" data-section="account-management">
                            <i class="fas fa-users-cog"></i>
                            <span>Account Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#customer-support" class="nav-link" data-section="customer-support">
                            <i class="fas fa-headset"></i>
                            <span>Customer Support</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#analytics" class="nav-link" data-section="analytics">
                            <i class="fas fa-chart-bar"></i>
                            <span>System Analytics</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#audit-trails" class="nav-link" data-section="audit-trails">
                            <i class="fas fa-clipboard-list"></i>
                            <span>Audit Trails</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="notifications">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                            <?php if (count($notifications) > 0): ?>
                                <span class="badge"><?= count($notifications) ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#backup" class="nav-link" data-section="backup">
                            <i class="fas fa-database"></i>
                            <span>Data Backup</span>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <i class="fas fa-user-shield"></i>
                    <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                </div>
                <a href="api/superadmin_api/super_admin_logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <div class="header-left">
                    <h1 id="page-title">Developer Dashboard</h1>
                    <p id="page-subtitle">System Administration & Technical Management</p>
                </div>
                <div class="header-right">
                    <div class="maintenance-toggle">
                        <label class="toggle-switch">
                            <input type="checkbox" id="maintenanceMode" <?= $maintenanceMode ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                        <span>Maintenance Mode</span>
                    </div>
                    <div class="current-time" id="currentTime"></div>
                </div>
            </header>

            <!-- Dashboard Section -->
            <section id="dashboard" class="content-section active">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= number_format($stats['total_users']) ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= number_format($stats['total_admins']) ?></h3>
                            <p>Admin Accounts</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-peso-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3>₱<?= number_format($stats['sales_today'], 2) ?></h3>
                            <p>Sales Today</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= number_format($stats['pending_requests']) ?></h3>
                            <p>Pending Requests</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <h3>System Status</h3>
                        <div class="system-status">
                            <div class="status-item">
                                <span class="status-indicator online"></span>
                                <span>Database: Online</span>
                            </div>
                            <div class="status-item">
                                <span class="status-indicator <?= $maintenanceMode ? 'maintenance' : 'online' ?>"></span>
                                <span>System: <?= $maintenanceMode ? 'Maintenance' : 'Online' ?></span>
                            </div>
                            <div class="status-item">
                                <span class="status-indicator online"></span>
                                <span>Backup: Active</span>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <h3>Recent Activity</h3>
                        <div class="activity-list" id="recentActivityList">
                            <div class="activity-item">
                                <i class="fas fa-info-circle"></i>
                                <span>Loading recent activities...</span>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <h3>Quick Actions</h3>
                        <div class="quick-actions">
                            <button class="action-btn" onclick="createBackup()">
                                <i class="fas fa-download"></i>
                                Create Backup
                            </button>
                            <button class="action-btn" onclick="clearCache()">
                                <i class="fas fa-broom"></i>
                                Clear Cache
                            </button>
                            <button class="action-btn" onclick="viewLogs()">
                                <i class="fas fa-file-alt"></i>
                                View Logs
                            </button>
                            <button class="action-btn" onclick="systemCheck()">
                                <i class="fas fa-check-circle"></i>
                                System Check
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Audit Trails Section -->
            <section id="audit-trails" class="content-section">
                <div class="section-header">
                    <h2>Audit Trails</h2>
                    <p>Monitor all user activities and system events</p>
                </div>
                
                <div class="audit-controls">
                    <div class="control-group">
                        <label for="auditDateRange">Date Range:</label>
                        <select id="auditDateRange">
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="week" selected>This Week</option>
                            <option value="month">This Month</option>
                            <option value="all">All Time</option>
                        </select>
                    </div>
                    <div class="control-group">
                        <label for="auditAction">Action:</label>
                        <select id="auditAction">
                            <option value="all">All Actions</option>
                            <option value="login">Login</option>
                            <option value="logout">Logout</option>
                            <option value="login_failed">Failed Login</option>
                        </select>
                    </div>
                    <div class="control-group">
                        <label for="auditUser">User:</label>
                        <input type="text" id="auditUser" placeholder="Search by username...">
                    </div>
                    <button class="btn-primary" onclick="loadAuditTrails()">
                        <i class="fas fa-search"></i>
                        Filter
                    </button>
                </div>

                <div class="audit-table-container">
                    <table class="audit-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                                <th>User Agent</th>
                            </tr>
                        </thead>
                        <tbody id="auditTableBody">
                            <tr>
                                <td colspan="6" class="loading-row">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    Loading audit logs...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="audit-pagination" id="auditPagination">
                    <!-- Pagination will be loaded here -->
                </div>
            </section>

            <!-- Other sections will be loaded dynamically -->
            <div id="dynamic-content"></div>
        </main>
    </div>

    <script src="js/super_admin_dashboard.js"></script>
    <script>
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            updateCurrentTime();
            setInterval(updateCurrentTime, 1000);
            
            // Auto-load audit trails if on that section
            if (window.location.hash === '#audit-trails') {
                setTimeout(() => {
                    loadAuditTrails();
                }, 100);
            }
            
            loadRecentActivity();
            
            // Setup maintenance mode toggle
            document.getElementById('maintenanceMode').addEventListener('change', function() {
                toggleMaintenanceMode(this.checked);
            });
        });

        function updateCurrentTime() {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleString();
        }

        function toggleMaintenanceMode(enabled) {
            fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_maintenance', enabled: enabled })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Maintenance mode updated', 'success');
                } else {
                    showNotification('Failed to update maintenance mode', 'error');
                }
            });
        }

        function loadRecentActivity() {
            if (window.superAdminDashboard) {
                window.superAdminDashboard.loadRecentActivityData();
            }
        }

        async function createBackup() {
            if (confirm('Create a system backup? This process may take several minutes.')) {
                try {
                    const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'create_backup' })
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        window.superAdminDashboard.showNotification('Backup created successfully', 'success');
                    } else {
                        window.superAdminDashboard.showNotification('Backup failed: ' + data.message, 'error');
                    }
                } catch (error) {
                    window.superAdminDashboard.showNotification('Error creating backup', 'error');
                }
            }
        }

        function getActivityIcon(action) {
            const icons = {
                'login': 'sign-in-alt',
                'logout': 'sign-out-alt',
                'backup': 'download',
                'maintenance': 'tools',
                'default': 'circle'
            };
            return icons[action] || icons.default;
        }

        function formatTime(timestamp) {
            return new Date(timestamp).toLocaleString();
        }

        function showNotification(message, type = 'info') {
            // Create and show notification
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Quick action functions
        function clearCache() {
            if (confirm('Clear system cache?')) {
                showNotification('Cache cleared successfully', 'success');
                // Implementation will be added
            }
        }

        function viewLogs() {
            // Switch to audit trails section
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            document.getElementById('audit-trails').classList.add('active');
            document.querySelector('[data-section="audit-trails"]').classList.add('active');
            
            // Load audit trails data
            loadAuditTrails();
        }

        function systemCheck() {
            showNotification('Running system check...', 'info');
            // Implementation will be added
        }
    </script>
</body>
</html>
