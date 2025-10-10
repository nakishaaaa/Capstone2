<?php
session_start();
require_once 'includes/csrf.php';

// Check if user is logged in as developer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'developer') {
    header('Location: devlog.php');
    exit();
}

require_once 'includes/config.php';
require_once 'includes/pusher_config.php';

// Generate CSRF token for this session
$csrfToken = generateCSRFToken();

// Simple automatic audit cleanup (once per day)
require_once 'includes/simple_audit_cleanup.php';

// Get system statistics
function getSystemStats($conn) {
    $stats = [];
    
    // Total users
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $result->fetch_assoc()['count'];
    
    // Total admins
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role IN ('admin', 'developer')");
    $stats['total_admins'] = $result->fetch_assoc()['count'];
    
    
    // Open customer support tickets (exclude archived)
    $result = $conn->query("SELECT COUNT(DISTINCT conversation_id) as count FROM support_tickets_messages WHERE archived = 0 OR archived IS NULL");
    $stats['open_support'] = $result->fetch_assoc()['count'];
    
    // Recent activities (placeholder)
    $stats['recent_activities'] = 0;
    
    return $stats;
}

$stats = getSystemStats($conn);

// Get maintenance mode status
$maintenanceResult = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
$maintenanceMode = $maintenanceResult ? $maintenanceResult->fetch_assoc()['setting_value'] === 'true' : false;

// Get unread notification count - let JavaScript handle the badge count dynamically
$unreadCount = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <title>Developer Dashboard - 053 Prints</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/super_admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="js/core/error-tracker.js"></script>
    <script>
        // Initialize Pusher for real-time updates
        const pusher = new Pusher('<?php echo PUSHER_KEY; ?>', {
            cluster: '<?php echo PUSHER_CLUSTER; ?>',
            encrypted: true
        });
        
        // Subscribe to developer channel
        const developerChannel = pusher.subscribe('developer-channel');
        
        // Listen for system events
        developerChannel.bind('system-alert', function(data) {
            showNotification(data.message, data.type || 'info');
        });
        
        developerChannel.bind('user-activity', function(data) {
            updateActivityFeed(data);
        });
        
        developerChannel.bind('support-ticket', function(data) {
            updateSupportBadge();
            if (data.message) {
                showNotification(data.message, 'info');
            }
        });
        
        console.log('Pusher initialized for developer dashboard');
    </script>
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
                            <span class="badge support-badge" id="supportBadge" style="display: none;"></span>
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
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="sidebar-footer">
                <div class="sidebar-user-info">
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
                            <i class="fas fa-headset"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= number_format($stats['open_support']) ?></h3>
                            <p>Open Customer Support</p>
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
                            <button class="quick-action-btn" onclick="navigateToSection('system-settings')">
                                <i class="fas fa-download"></i>
                                Create Backup
                            </button>
                            <button class="quick-action-btn" onclick="clearCache()">
                                <i class="fas fa-broom"></i>
                                Clear Cache
                            </button>
                            <button class="quick-action-btn" onclick="viewLogs()">
                                <i class="fas fa-file-alt"></i>
                                View Logs
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Audit Trails Section -->
            <section id="audit-trails" class="content-section">
                <div class="audit-trails-container">

                    
                    <div class="audit-filters">
                        <div class="audit-filter-group">
                            <label class="audit-filter-label" for="auditUser">User:</label>
                            <input type="text" id="auditUser" class="audit-filter-input" placeholder="Search by username...">
                        </div>
                        <div class="audit-filter-group">
                            <label class="audit-filter-label" for="auditDateRange">Date Range:</label>
                            <select id="auditDateRange" class="audit-filter-select">
                                <option value="today">Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="week" selected>This Week</option>
                                <option value="month">This Month</option>
                                <option value="all">All Time</option>
                            </select>
                        </div>
                        <div class="audit-filter-group">
                            <label class="audit-filter-label" for="auditAction">Action:</label>
                            <select id="auditAction" class="audit-filter-select">
                                <option value="all">All Actions</option>
                                <option value="login">Login</option>
                                <option value="logout">Logout</option>
                                <option value="user_create">User Create</option>
                                <option value="user_update">User Update</option>
                                <option value="maintenance_toggle">Maintenance Toggle</option>
                                <option value="system_check">System Check</option>
                            </select>
                        </div>
                        <button class="audit-filter-btn" onclick="loadAuditTrails()">
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
                                    <th>User Agent</th>
                                </tr>
                            </thead>
                            <tbody id="auditTableBody">
                                <tr>
                                    <td colspan="5" class="loading-row">
                                        <i class="fas fa-spinner fa-spin"></i>
                                        Loading audit logs...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Other sections will be loaded dynamically -->
            <div id="dynamic-content"></div>
        </main>
    </div>

    <script type="module" src="js/super_admin_dashboard.js"></script>
    <script>
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            updateCurrentTime();
            setInterval(updateCurrentTime, 1000);
            
            // Wait for module to load before calling functions
            setTimeout(() => {
                // Auto-load audit trails if on that section
                if (window.location.hash === '#audit-trails') {
                    if (window.loadAuditTrails) {
                        window.loadAuditTrails();
                    }
                }
                
                if (window.loadRecentActivity) {
                    window.loadRecentActivity();
                }
                
                // Setup maintenance mode toggle
                const maintenanceToggle = document.getElementById('maintenanceMode');
                if (maintenanceToggle) {
                    maintenanceToggle.addEventListener('change', function() {
                        if (window.toggleMaintenanceMode) {
                            window.toggleMaintenanceMode(this.checked);
                        }
                    });
                }
            }, 500);
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
        async function clearCache() {
            // Use the settings module's confirm modal if available
            if (window.superAdminDashboard && window.superAdminDashboard.settingsModule) {
                const confirmed = await window.superAdminDashboard.settingsModule.showConfirmModal(
                    'Clear System Cache?',
                    'This may temporarily slow down the system while the cache is being rebuilt.',
                    'Clear Cache',
                    'Cancel'
                );
                
                if (!confirmed) return;
            } else if (!confirm('Clear system cache?')) {
                return;
            }
            
            try {
                showNotification('Clearing cache...', 'info');
                
                const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'clear_cache' })
                });
                
                const data = await response.json();
                if (data.success) {
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.message || 'Failed to clear cache', 'error');
                }
            } catch (error) {
                console.error('Error clearing cache:', error);
                showNotification('Error clearing cache', 'error');
            }
        }

        function viewLogs() {
            // Switch to audit trails section
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            document.getElementById('audit-trails').classList.add('active');
            document.querySelector('[data-section="audit-trails"]').closest('.nav-item').classList.add('active');
            
            // Load audit trails data
            loadAuditTrails();
        }

        // Make CSRF token available globally for soft delete functions
        window.csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        
        // Real-time update functions
        function updateActivityFeed(data) {
            const activityList = document.getElementById('recentActivityList');
            if (!activityList) return;
            
            const activityItem = document.createElement('div');
            activityItem.className = 'activity-item new-activity';
            activityItem.innerHTML = `
                <i class="fas ${getActivityIcon(data.type)}"></i>
                <div class="activity-content">
                    <span class="activity-text">${data.message}</span>
                    <span class="activity-time">${formatTime(new Date())}</span>
                </div>
            `;
            
            // Add to top of list
            activityList.insertBefore(activityItem, activityList.firstChild);
            
            // Remove old items if more than 10
            const items = activityList.querySelectorAll('.activity-item');
            if (items.length > 10) {
                items[items.length - 1].remove();
            }
            
            // Highlight new item
            setTimeout(() => {
                activityItem.classList.remove('new-activity');
            }, 3000);
        }
        
        function updateSupportBadge() {
            // Update support ticket badge count
            fetch('api/superadmin_api/super_admin_actions.php?action=get_support_count')
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('supportBadge');
                    if (badge && data.success) {
                        if (data.count > 0) {
                            badge.textContent = data.count;
                            badge.style.display = 'inline';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                })
                .catch(error => console.error('Error updating support badge:', error));
        }
        
        function getActivityIcon(type) {
            const icons = {
                'user_login': 'fa-sign-in-alt',
                'user_logout': 'fa-sign-out-alt',
                'user_create': 'fa-user-plus',
                'user_update': 'fa-user-edit',
                'system_backup': 'fa-download',
                'maintenance': 'fa-tools',
                'support_ticket': 'fa-headset',
                'order_update': 'fa-shopping-cart',
                'default': 'fa-info-circle'
            };
            return icons[type] || icons.default;
        }
        
        function formatTime(date) {
            return date.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Enhanced notification function with better styling
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.pusher-notification');
            existingNotifications.forEach(n => n.remove());
            
            const notification = document.createElement('div');
            notification.className = `pusher-notification ${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas ${getNotificationIcon(type)}"></i>
                    <span>${message}</span>
                    <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            // Add styles if not already added
            if (!document.getElementById('pusher-notification-styles')) {
                const styles = document.createElement('style');
                styles.id = 'pusher-notification-styles';
                styles.textContent = `
                    .pusher-notification {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        z-index: 10000;
                        min-width: 300px;
                        max-width: 500px;
                        padding: 15px;
                        border-radius: 8px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                        animation: slideIn 0.3s ease-out;
                    }
                    .pusher-notification.info { background: #e3f2fd; border-left: 4px solid #2196f3; color: #1565c0; }
                    .pusher-notification.success { background: #e8f5e8; border-left: 4px solid #4caf50; color: #2e7d32; }
                    .pusher-notification.warning { background: #fff3e0; border-left: 4px solid #ff9800; color: #ef6c00; }
                    .pusher-notification.error { background: #ffebee; border-left: 4px solid #f44336; color: #c62828; }
                    .notification-content { display: flex; align-items: center; gap: 10px; }
                    .notification-close { background: none; border: none; cursor: pointer; opacity: 0.7; }
                    .notification-close:hover { opacity: 1; }
                    @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
                `;
                document.head.appendChild(styles);
            }
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }
        
        function getNotificationIcon(type) {
            const icons = {
                'info': 'fa-info-circle',
                'success': 'fa-check-circle',
                'warning': 'fa-exclamation-triangle',
                'error': 'fa-times-circle'
            };
            return icons[type] || icons.info;
        }
        
        // Initialize real-time updates on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSupportBadge();
            
            // Update support badge every 30 seconds
            setInterval(updateSupportBadge, 30000);
            
            console.log('Developer dashboard real-time features initialized');
        });
        
        // Toggle description cell function for backup history
        window.toggleDescriptionCell = function(backupId, fullDescription, truncatedDescription) {
            const cell = document.getElementById(`desc-cell-${backupId}`);
            const truncatedDiv = cell.querySelector('.description-truncated');
            const fullDiv = cell.querySelector('.description-full');
            
            if (cell.dataset.expanded === 'false') {
                // Show full description below
                truncatedDiv.style.display = 'none';
                fullDiv.style.display = 'block';
                cell.dataset.expanded = 'true';
                cell.title = 'Click to collapse';
            } else {
                // Show truncated description
                truncatedDiv.style.display = 'block';
                fullDiv.style.display = 'none';
                cell.dataset.expanded = 'false';
                cell.title = 'Click to expand/collapse';
            }
        };

    </script>
    
    <!-- Soft Delete Functions -->
    <script src="js/soft-delete-functions.js"></script>
</body>
</html>
