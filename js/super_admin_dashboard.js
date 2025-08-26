// Super Admin Dashboard JavaScript

class SuperAdminDashboard {
    constructor() {
        this.currentSection = 'dashboard';
        this.consoleErrors = [];
        this.init();
        this.setupErrorLogging();
    }

    setupErrorLogging() {
        // Capture console errors
        const originalError = console.error;
        console.error = (...args) => {
            this.logError('Console Error', args.join(' '));
            originalError.apply(console, args);
        };

        // Capture unhandled promise rejections
        window.addEventListener('unhandledrejection', (event) => {
            this.logError('Unhandled Promise Rejection', event.reason);
        });

        // Capture JavaScript errors
        window.addEventListener('error', (event) => {
            this.logError('JavaScript Error', `${event.message} at ${event.filename}:${event.lineno}`);
        });
    }

    logError(type, message) {
        const error = {
            id: Date.now(),
            type: type,
            message: message,
            timestamp: new Date().toISOString(),
            url: window.location.href
        };
        
        this.consoleErrors.unshift(error);
        
        // Keep only last 100 errors
        if (this.consoleErrors.length > 100) {
            this.consoleErrors = this.consoleErrors.slice(0, 100);
        }

        // Update error count in UI if console errors section is active
        this.updateErrorCount();
    }

    // Load audit trails data
    async loadAuditTrails() {
        const dateRange = document.getElementById('auditDateRange').value;
        const action = document.getElementById('auditAction').value;
        const user = document.getElementById('auditUser').value;
        
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'get_audit_logs',
                    date_range: dateRange,
                    filter_action: action,
                    filter_user: user
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                this.displayAuditLogs(data.logs);
            } else {
                throw new Error(data.message || 'Failed to load audit logs');
            }
        } catch (error) {
            console.error('Error loading audit trails:', error);
            document.getElementById('auditTableBody').innerHTML = `
                <tr>
                    <td colspan="6" class="error-row">
                        <i class="fas fa-exclamation-triangle"></i>
                        Error loading audit logs: ${error.message}
                    </td>
                </tr>
            `;
        }
    }

    displayAuditLogs(logs) {
        const tbody = document.getElementById('auditTableBody');
        
        if (!logs || logs.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="no-data-row">
                        <i class="fas fa-info-circle"></i>
                        No audit logs found for the selected criteria
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = logs.map(log => {
            const actionIcon = this.getActionIcon(log.action);
            const actionClass = this.getActionClass(log.action);
            
            return `
                <tr>
                    <td>${new Date(log.created_at).toLocaleString()}</td>
                    <td>
                        <div class="user-info">
                            <i class="fas fa-user"></i>
                            ${log.username || `User ID: ${log.user_id}`}
                        </div>
                    </td>
                    <td>
                        <span class="action-badge ${actionClass}">
                            <i class="${actionIcon}"></i>
                            ${log.action.replace('_', ' ').toUpperCase()}
                        </span>
                    </td>
                    <td class="description">${log.description}</td>
                    <td class="ip-address">${log.ip_address || 'N/A'}</td>
                    <td class="user-agent" title="${log.user_agent || 'N/A'}">
                        ${this.truncateUserAgent(log.user_agent)}
                    </td>
                </tr>
            `;
        }).join('');
    }

    getActionIcon(action) {
        const icons = {
            'login': 'fas fa-sign-in-alt',
            'logout': 'fas fa-sign-out-alt',
            'login_failed': 'fas fa-times-circle',
            'default': 'fas fa-info-circle'
        };
        return icons[action] || icons.default;
    }

    getActionClass(action) {
        const classes = {
            'login': 'success',
            'logout': 'info',
            'login_failed': 'danger',
            'default': 'secondary'
        };
        return classes[action] || classes.default;
    }

    truncateUserAgent(userAgent) {
        if (!userAgent) return 'N/A';
        if (userAgent.length <= 50) return userAgent;
        return userAgent.substring(0, 47) + '...';
    }

    updateErrorCount() {
        const errorCountElement = document.getElementById('errorCount');
        if (errorCountElement) {
            errorCountElement.textContent = this.consoleErrors.length;
        }
    }

    init() {
        this.setupNavigation();
        this.setupEventListeners();
        this.loadInitialData();
    }

    setupNavigation() {
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const section = link.getAttribute('data-section');
                this.switchSection(section);
            });
        });
    }

    setupEventListeners() {
        // Setup various event listeners for dashboard functionality
        document.addEventListener('click', (e) => {
            if (e.target.matches('.action-btn')) {
                this.handleQuickAction(e.target);
            }
        });
    }

    switchSection(sectionName) {
        // Hide all sections
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });
        
        // Remove active class from all nav links
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
        });
        
        // Show selected section
        const targetSection = document.getElementById(sectionName);
        if (targetSection) {
            targetSection.classList.add('active');
        }
        
        // Add active class to clicked nav link
        const activeLink = document.querySelector(`[data-section="${sectionName}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
        }
        
        // Load section-specific data
        if (sectionName === 'audit-trails') {
            this.loadAuditTrails();
        }
        
        this.currentSection = sectionName;
    }

    updatePageTitle(section) {
        const titles = {
            'dashboard': 'Developer Dashboard',
            'system-settings': 'System Settings',
            'account-management': 'Account Management',
            'audit-trails': 'Audit Trails',
            'customer-support': 'Customer Support',
            'analytics': 'System Analytics',
            'notifications': 'Developer Notifications',
            'backup': 'Data Backup & Restore',
            'console-errors': 'Console Errors'
        };

        const subtitles = {
            'dashboard': 'System Administration & Technical Management',
            'system-settings': 'Configure system-wide settings and maintenance',
            'account-management': 'Manage user accounts and permissions',
            'audit-trails': 'View system activity logs and security events',
            'customer-support': 'Handle customer inquiries and support tickets',
            'analytics': 'Monitor system performance and usage statistics',
            'notifications': 'Developer alerts and system notifications',
            'backup': 'Database backup and restoration tools',
            'console-errors': 'View console errors and system warnings'
        };

        document.getElementById('page-title').textContent = titles[section] || 'Developer Dashboard';
        document.getElementById('page-subtitle').textContent = subtitles[section] || 'System Administration';
    }

    loadDynamicSection(sectionName) {
        const dynamicContent = document.getElementById('dynamic-content');
        
        switch (sectionName) {
            case 'system-settings':
                this.loadSystemSettings(dynamicContent);
                break;
            case 'account-management':
                this.loadAccountManagement(dynamicContent);
                break;
            case 'audit-trails':
                this.loadAuditTrails(dynamicContent);
                break;
            case 'customer-support':
                this.loadCustomerSupport(dynamicContent);
                break;
            case 'analytics':
                this.loadAnalytics(dynamicContent);
                break;
            case 'notifications':
                this.loadNotifications(dynamicContent);
                break;
            case 'backup':
                this.loadBackup(dynamicContent);
                break;
            case 'console-errors':
                this.loadConsoleErrors(dynamicContent);
                break;
        }
    }

    loadSystemSettings(container) {
        container.innerHTML = `
            <section id="system-settings" class="content-section active">
                <div class="settings-grid">
                    <div class="settings-card">
                        <h3>Maintenance Mode</h3>
                        <div class="setting-item">
                            <label>Enable Maintenance Mode</label>
                            <label class="toggle-switch">
                                <input type="checkbox" id="maintenanceToggle">
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="setting-item">
                            <label for="maintenanceMessage">Maintenance Message</label>
                            <textarea id="maintenanceMessage" rows="3" placeholder="Enter maintenance message..."></textarea>
                        </div>
                        <button class="save-btn" onclick="saveMaintenanceSettings()">Save Changes</button>
                    </div>

                    <div class="settings-card">
                        <h3>Security Settings</h3>
                        <div class="setting-item">
                            <label for="maxLoginAttempts">Max Login Attempts</label>
                            <input type="number" id="maxLoginAttempts" min="1" max="10" value="5">
                        </div>
                        <div class="setting-item">
                            <label for="sessionTimeout">Session Timeout (minutes)</label>
                            <input type="number" id="sessionTimeout" min="5" max="480" value="60">
                        </div>
                        <div class="setting-item">
                            <label>Enable Audit Logging</label>
                            <label class="toggle-switch">
                                <input type="checkbox" id="auditLogging" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <button class="save-btn" onclick="saveSecuritySettings()">Save Changes</button>
                    </div>

                    <div class="settings-card">
                        <h3>Backup Settings</h3>
                        <div class="setting-item">
                            <label for="backupFrequency">Backup Frequency</label>
                            <select id="backupFrequency">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div class="setting-item">
                            <label for="notificationEmail">Notification Email</label>
                            <input type="email" id="notificationEmail" placeholder="admin@053prints.com">
                        </div>
                        <button class="save-btn" onclick="saveBackupSettings()">Save Changes</button>
                    </div>
                </div>
            </section>
        `;
        this.loadCurrentSettings();
    }

    loadAccountManagement(container) {
        container.innerHTML = `
            <section id="account-management" class="content-section active">
                <div class="account-header">
                    <h3>User Account Management</h3>
                    <button class="create-btn" onclick="showCreateUserModal()">
                        <i class="fas fa-plus"></i>
                        Create New Account
                    </button>
                </div>

                <div class="account-filters">
                    <input type="text" id="userSearch" placeholder="Search users..." class="search-input">
                    <select id="roleFilter" class="filter-select">
                        <option value="">All Roles</option>
                        <option value="admin">Admin</option>
                        <option value="cashier">Cashier</option>
                        <option value="user">User</option>
                    </select>
                    <select id="statusFilter" class="filter-select">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <button class="filter-btn" onclick="filterUsers()">Filter</button>
                </div>

                <div class="users-table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr>
                                <td colspan="7" class="loading">Loading users...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Create/Edit User Modal -->
                <div id="userModal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 id="modalTitle">Create New Account</h4>
                            <span class="close" onclick="closeUserModal()">&times;</span>
                        </div>
                        <form id="userForm">
                            <input type="hidden" id="userId" name="user_id">
                            <div class="form-group">
                                <label for="username">Username:</label>
                                <input type="text" id="username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email:</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="form-group" id="passwordGroup">
                                <label for="password">Password:</label>
                                <input type="password" id="password" name="password" required>
                                <small>Minimum 6 characters</small>
                            </div>
                            <div class="form-group">
                                <label for="role">Role:</label>
                                <select id="role" name="role" required>
                                    <option value="admin">Admin</option>
                                    <option value="cashier">Cashier</option>
                                    <option value="user">User</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">Status:</label>
                                <select id="status" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="modal-actions">
                                <button type="button" onclick="closeUserModal()" class="cancel-btn">Cancel</button>
                                <button type="submit" class="save-btn">Save Account</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        `;
        this.loadUsers();
        this.setupUserFormHandlers();
        
        // Ensure modal is properly hidden when section loads
        const modal = document.getElementById('userModal');
        if (modal) {
            modal.style.display = 'none';
            modal.style.visibility = 'hidden';
            modal.style.opacity = '0';
            modal.classList.remove('show');
        }
    }

    setupUserFormHandlers() {
        const form = document.getElementById('userForm');
        const searchInput = document.getElementById('userSearch');
        const closeBtn = document.querySelector('.close');
        const modal = document.getElementById('userModal');
        
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveUser();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.loadUsers();
                }, 300);
            });
        }

        // Setup close button handlers
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                this.closeUserModal();
            });
        }

        // Close modal when clicking outside
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeUserModal();
                }
            });
        }
    }

    async saveUser() {
        const form = document.getElementById('userForm');
        const formData = new FormData(form);
        const userId = formData.get('user_id');
        
        const userData = {
            username: formData.get('username'),
            email: formData.get('email'),
            role: formData.get('role'),
            status: formData.get('status')
        };

        if (userId) {
            userData.user_id = userId;
        } else {
            userData.password = formData.get('password');
        }

        console.log('Sending user data:', userData); // Debug log

        try {
            const action = userId ? 'update_user' : 'create_admin';
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, ...userData })
            });

            const responseText = await response.text();
            console.log('Raw response:', responseText); // Debug log
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                this.showNotification('Server returned invalid response', 'error');
                return;
            }
            
            if (data.success) {
                this.showNotification(data.message, 'success');
                this.closeUserModal();
                this.loadUsers();
            } else {
                console.error('Server error:', data.message); // Debug log
                this.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error saving user:', error);
            this.showNotification('Error saving user account', 'error');
        }
    }

    async deleteUser(userId) {
        if (!confirm('Are you sure you want to delete this user account? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_user', user_id: userId })
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification(data.message, 'success');
                this.loadUsers();
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error deleting user:', error);
            this.showNotification('Error deleting user account', 'error');
        }
    }

    showCreateUserModal() {
        const modal = document.getElementById('userModal');
        const form = document.getElementById('userForm');
        const title = document.getElementById('modalTitle');
        const passwordGroup = document.getElementById('passwordGroup');
        
        title.textContent = 'Create New Account';
        form.reset();
        document.getElementById('userId').value = '';
        passwordGroup.style.display = 'block';
        document.getElementById('password').required = true;
        
        modal.style.display = 'block';
        modal.style.visibility = 'visible';
        modal.style.opacity = '1';
    }

    async showEditUserModal(userId) {
        try {
            const response = await fetch(`api/superadmin_api/super_admin_actions.php?action=get_user&user_id=${userId}`);
            const data = await response.json();
            
            if (data.success && data.user) {
                const user = data.user;
                const modal = document.getElementById('userModal');
                const title = document.getElementById('modalTitle');
                const passwordGroup = document.getElementById('passwordGroup');
                
                title.textContent = 'Edit Account';
                document.getElementById('userId').value = user.id;
                document.getElementById('username').value = user.username;
                document.getElementById('email').value = user.email;
                document.getElementById('role').value = user.role;
                document.getElementById('status').value = user.status || 'active';
                
                passwordGroup.style.display = 'none';
                document.getElementById('password').required = false;
                
                modal.style.display = 'block';
                modal.style.visibility = 'visible';
                modal.style.opacity = '1';
            } else {
                this.showNotification('Failed to load user data', 'error');
            }
        } catch (error) {
            console.error('Error loading user:', error);
            this.showNotification('Error loading user data', 'error');
        }
    }

    closeUserModal() {
        const modal = document.getElementById('userModal');
        if (modal) {
            modal.style.display = 'none';
            modal.style.visibility = 'hidden';
            modal.style.opacity = '0';
        }
    }


    loadNotifications(container) {
        container.innerHTML = `
            <section id="notifications" class="content-section active">
                <div class="notifications-header">
                    <h3>Developer Notifications</h3>
                    <button class="clear-btn" onclick="clearAllNotifications()">
                        <i class="fas fa-check-double"></i>
                        Mark All as Read
                    </button>
                </div>

                <div class="notification-filters">
                    <button class="filter-tab active" data-filter="all">All</button>
                    <button class="filter-tab" data-filter="error">Errors</button>
                    <button class="filter-tab" data-filter="warning">Warnings</button>
                    <button class="filter-tab" data-filter="info">Info</button>
                    <button class="filter-tab" data-filter="critical">Critical</button>
                </div>

                <div class="notifications-list" id="notificationsList">
                    <div class="loading">Loading notifications...</div>
                </div>
            </section>
        `;
        this.loadNotificationsList();
    }

    loadAnalytics(container) {
        container.innerHTML = `
            <section id="analytics" class="content-section active">
                <div class="analytics-header">
                    <h3>System Analytics</h3>
                    <div class="analytics-controls">
                        <select id="analyticsTimeframe" class="timeframe-select">
                            <option value="24h">Last 24 Hours</option>
                            <option value="7d">Last 7 Days</option>
                            <option value="30d">Last 30 Days</option>
                            <option value="90d">Last 90 Days</option>
                        </select>
                        <button class="refresh-btn" onclick="refreshAnalytics()">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                    </div>
                </div>

                <div class="analytics-grid">
                    <div class="analytics-card">
                        <h4>User Activity</h4>
                        <div class="chart-container">
                            <canvas id="userActivityChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="analytics-card">
                        <h4>System Performance</h4>
                        <div class="chart-container">
                            <canvas id="performanceChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="analytics-card">
                        <h4>Error Rates</h4>
                        <div class="chart-container">
                            <canvas id="errorChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="analytics-card">
                        <h4>Resource Usage</h4>
                        <div class="chart-container">
                            <canvas id="resourceChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="analytics-summary">
                    <div class="summary-item">
                        <span class="summary-label">Total Users</span>
                        <span class="summary-value" id="totalUsers">-</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Active Sessions</span>
                        <span class="summary-value" id="activeSessions">-</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">System Uptime</span>
                        <span class="summary-value" id="systemUptime">-</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Database Size</span>
                        <span class="summary-value" id="databaseSize">-</span>
                    </div>
                </div>
            </section>
        `;
        this.loadAnalyticsData();
    }

    loadBackup(container) {
        container.innerHTML = `
            <section id="backup" class="content-section active">
                <div class="backup-grid">
                    <div class="backup-card">
                        <h3>Create Backup</h3>
                        <div class="backup-options">
                            <label class="backup-option">
                                <input type="checkbox" checked> Database
                            </label>
                            <label class="backup-option">
                                <input type="checkbox" checked> User Files
                            </label>
                            <label class="backup-option">
                                <input type="checkbox"> System Logs
                            </label>
                        </div>
                        <button class="backup-btn" onclick="createManualBackup()">
                            <i class="fas fa-download"></i>
                            Create Backup Now
                        </button>
                    </div>

                    <div class="backup-card">
                        <h3>Restore from Backup</h3>
                        <div class="restore-section">
                            <input type="file" id="backupFile" accept=".sql,.zip" class="file-input">
                            <button class="restore-btn" onclick="restoreFromBackup()">
                                <i class="fas fa-upload"></i>
                                Restore Backup
                            </button>
                        </div>
                        <div class="restore-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Warning: This will overwrite current data
                        </div>
                    </div>
                </div>

                <div class="backup-history">
                    <h3>Backup History</h3>
                    <div class="backup-table-container">
                        <table class="backup-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="backupHistoryBody">
                                <tr>
                                    <td colspan="5" class="loading">Loading backup history...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        `;
        this.loadBackupHistory();
    }

    loadConsoleErrors(container) {
        container.innerHTML = `
            <section id="console-errors" class="content-section active">
                <div class="console-errors-header">
                    <h3>Console Errors</h3>
                </div>

                <div class="console-errors-table-container">
                    <table class="console-errors-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Error Type</th>
                                <th>Error Message</th>
                            </tr>
                        </thead>
                        <tbody id="consoleErrorsTableBody">
                            <tr>
                                <td colspan="3" class="loading">Loading console errors...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        `;
        this.loadConsoleErrorsList();
    }

    // API Methods
    async loadCurrentSettings() {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php?action=get_settings');
            const data = await response.json();
            
            if (data.success) {
                // Populate form fields with current settings
                Object.keys(data.settings).forEach(key => {
                    const element = document.getElementById(key);
                    if (element) {
                        if (element.type === 'checkbox') {
                            element.checked = data.settings[key] === 'true';
                        } else {
                            element.value = data.settings[key];
                        }
                    }
                });
            }
        } catch (error) {
            console.error('Error loading settings:', error);
        }
    }

    async loadUsers() {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php?action=get_users');
            const data = await response.json();
            
            const tbody = document.getElementById('usersTableBody');
            if (data.success && data.users) {
                tbody.innerHTML = data.users.map(user => `
                    <tr>
                        <td>${user.username}</td>
                        <td>${user.email}</td>
                        <td><span class="role-badge ${user.role}">${user.role}</span></td>
                        <td><span class="status-badge ${user.status || 'active'}">${user.status || 'active'}</span></td>
                        <td>${user.last_login ? new Date(user.last_login).toLocaleString() : 'Never'}</td>
                        <td>${new Date(user.created_at).toLocaleString()}</td>
                        <td>
                            <div class="action-buttons">
                                <button class="edit-btn" onclick="showEditUserModal(${user.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="delete-btn" onclick="deleteUser(${user.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7">No users found</td></tr>';
            }
        } catch (error) {
            console.error('Error loading users:', error);
        }
    }


    async loadNotificationsList() {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php?action=get_notifications');
            const data = await response.json();
            
            const container = document.getElementById('notificationsList');
            if (data.success && data.notifications) {
                container.innerHTML = data.notifications.map(notification => `
                    <div class="notification-item ${notification.type} ${notification.is_read ? 'read' : 'unread'}">
                        <div class="notification-icon">
                            <i class="fas fa-${this.getNotificationIcon(notification.type)}"></i>
                        </div>
                        <div class="notification-content">
                            <h4>${notification.title}</h4>
                            <p>${notification.message}</p>
                            <span class="notification-time">${new Date(notification.created_at).toLocaleString()}</span>
                        </div>
                        <div class="notification-actions">
                            ${!notification.is_read ? `
                                <button onclick="markAsRead(${notification.id})"><i class="fas fa-check"></i></button>
                            ` : ''}
                            <button onclick="deleteNotification(${notification.id})"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<div class="no-notifications">No notifications found</div>';
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }

    async loadAnalyticsData() {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php?action=get_analytics_data');
            const data = await response.json();
            
            if (data.success) {
                // Update analytics data
                console.log('Analytics data updated:', data);
            }
        } catch (error) {
            console.error('Error loading analytics data:', error);
        }
    }

    async loadRecentActivityData() {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php?action=recent_activity');
            const data = await response.json();
            
            if (data.success && data.activities) {
                const activityList = document.getElementById('recentActivityList');
                if (activityList) {
                    activityList.innerHTML = data.activities.map(activity => `
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-${this.getActivityIcon(activity.action)}"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-description">${activity.description}</div>
                                <div class="activity-meta">
                                    <span class="activity-user">${activity.username || 'System'}</span>
                                    <span class="activity-time">${new Date(activity.created_at).toLocaleString()}</span>
                                </div>
                            </div>
                        </div>
                    `).join('');
                }
            }
        } catch (error) {
            console.error('Error loading recent activity:', error);
            this.showNotification('Failed to load recent activity', 'error');
        }
    }

    async loadConsoleErrorsList() {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php?action=get_console_errors');
            const data = await response.json();
            
            const tbody = document.getElementById('consoleErrorsTableBody');
            if (data.success && data.errors) {
                tbody.innerHTML = data.errors.map(error => `
                    <tr>
                        <td>${new Date(error.timestamp).toLocaleString()}</td>
                        <td>${error.type}</td>
                        <td>${error.message}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="3">No console errors found</td></tr>';
            }
        } catch (error) {
            console.error('Error loading console errors:', error);
        }
    }

    getActivityIcon(action) {
        const iconMap = {
            'login': 'sign-in-alt',
            'logout': 'sign-out-alt',
            'backup': 'database',
            'maintenance': 'tools',
            'user_created': 'user-plus',
            'user_deleted': 'user-minus',
            'settings_changed': 'cog',
            'cache_cleared': 'broom'
        };
        return iconMap[action] || 'info-circle';
    }

    async loadBackupHistory() {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php?action=get_backup_history');
            const data = await response.json();
            
            const tbody = document.getElementById('backupHistoryBody');
            if (data.success && data.backups) {
                tbody.innerHTML = data.backups.map(backup => `
                    <tr>
                        <td>${new Date(backup.created_at).toLocaleString()}</td>
                        <td><span class="type-badge ${backup.backup_type}">${backup.backup_type}</span></td>
                        <td>${this.formatFileSize(backup.file_size)}</td>
                        <td><span class="status-badge ${backup.status}">${backup.status}</span></td>
                        <td>
                            <div class="action-buttons">
                                ${backup.status === 'completed' ? `
                                    <button class="download-btn" onclick="downloadBackup('${backup.file_name}')">
                                        <i class="fas fa-download"></i>
                                    </button>
                                ` : ''}
                                <button class="delete-btn" onclick="deleteBackup(${backup.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="5">No backup history found</td></tr>';
            }
        } catch (error) {
            console.error('Error loading backup history:', error);
        }
    }

    loadInitialData() {
        // Load any initial data needed for the dashboard
        this.refreshDashboardStats();
    }

    async refreshDashboardStats() {
        // Refresh dashboard statistics
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php?action=get_dashboard_stats');
            const data = await response.json();
            
            if (data.success) {
                // Update dashboard stats if needed
                console.log('Dashboard stats updated:', data);
            }
        } catch (error) {
            console.error('Error refreshing dashboard stats:', error);
        }
    }

    // Utility methods
    getNotificationIcon(type) {
        const icons = {
            'error': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle',
            'critical': 'exclamation'
        };
        return icons[type] || 'bell';
    }

    formatFileSize(bytes) {
        if (!bytes) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    handleQuickAction(button) {
        const action = button.textContent.trim().toLowerCase();
        
        switch (action) {
            case 'create backup':
                this.switchSection('backup');
                break;
            case 'clear cache':
                this.clearSystemCache();
                break;
            case 'view logs':
                this.switchSection('audit-trails');
                break;
            case 'system check':
                this.runSystemCheck();
                break;
        }
    }

    async clearSystemCache() {
        if (confirm('Clear system cache? This may temporarily slow down the system.')) {
            try {
                const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'clear_cache' })
                });
                
                const data = await response.json();
                if (data.success) {
                    this.showNotification('Cache cleared successfully', 'success');
                } else {
                    this.showNotification('Failed to clear cache', 'error');
                }
            } catch (error) {
                console.error('Error clearing cache:', error);
                this.showNotification('Error clearing cache', 'error');
            }
        }
    }

    async runSystemCheck() {
        this.showNotification('Running system check...', 'info');
        
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'system_check' })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showNotification('System check completed successfully', 'success');
            } else {
                this.showNotification('System check found issues', 'warning');
            }
        } catch (error) {
            console.error('Error running system check:', error);
            this.showNotification('Error running system check', 'error');
        }
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
}

// Initialize dashboard instance
const dashboard = new SuperAdminDashboard();

// Make loadAuditTrails globally accessible
window.loadAuditTrails = function() {
    dashboard.loadAuditTrails();
};

// Global functions for inline event handlers
function saveMaintenanceSettings() {
    // Implementation for saving maintenance settings
    window.superAdminDashboard.showNotification('Maintenance settings saved', 'success');
}

function saveSecuritySettings() {
    // Implementation for saving security settings
    window.superAdminDashboard.showNotification('Security settings saved', 'success');
}

function saveBackupSettings() {
    // Implementation for saving backup settings
    window.superAdminDashboard.showNotification('Backup settings saved', 'success');
}

function editUser(userId) {
    // Implementation for editing user
    console.log('Edit user:', userId);
}

function deleteUser(userId) {
    if (window.superAdminDashboard) {
        window.superAdminDashboard.deleteUser(userId);
    }
}

function markAsRead(notificationId) {
    // Implementation for marking notification as read
    console.log('Mark as read:', notificationId);
}

function deleteNotification(notificationId) {
    // Implementation for deleting notification
    console.log('Delete notification:', notificationId);
}

function createManualBackup() {
    if (confirm('Create a manual backup? This may take several minutes.')) {
        window.superAdminDashboard.showNotification('Backup started...', 'info');
        // Implementation for creating backup
    }
}

function restoreFromBackup() {
    const fileInput = document.getElementById('backupFile');
    if (!fileInput.files[0]) {
        window.superAdminDashboard.showNotification('Please select a backup file', 'error');
        return;
    }
    
    if (confirm('Are you sure you want to restore from this backup? This will overwrite current data.')) {
        window.superAdminDashboard.showNotification('Restore started...', 'info');
        // Implementation for restoring backup
    }
}

function downloadBackup(fileName) {
    // Implementation for downloading backup
    window.open(`api/superadmin_api/download_backup.php?file=${fileName}`, '_blank');
}

function deleteBackup(backupId) {
    if (confirm('Are you sure you want to delete this backup?')) {
        // Implementation for deleting backup
        console.log('Delete backup:', backupId);
    }
}

function showCreateUserModal() {
    if (window.superAdminDashboard) {
        window.superAdminDashboard.showCreateUserModal();
    }
}

function showEditUserModal(userId) {
    if (window.superAdminDashboard) {
        window.superAdminDashboard.showEditUserModal(userId);
    }
}

function closeUserModal() {
    if (window.superAdminDashboard) {
        window.superAdminDashboard.closeUserModal();
    }
}

function filterUsers() {
    if (window.superAdminDashboard) {
        window.superAdminDashboard.loadUsers();
    }
}

function clearCache() {
    if (confirm('Are you sure you want to clear the system cache? This may temporarily slow down the application.')) {
        window.superAdminDashboard.clearSystemCache();
    }
}

function viewLogs() {
    window.superAdminDashboard.switchSection('audit-trails');
}

function systemCheck() {
    window.superAdminDashboard.runSystemCheck();
}

function loadRecentActivity() {
    window.superAdminDashboard.loadRecentActivityData();
}

function refreshAnalytics() {
    if (window.superAdminDashboard) {
        window.superAdminDashboard.loadAnalyticsData();
        window.superAdminDashboard.showNotification('Analytics refreshed', 'success');
    }
}

function clearAllNotifications() {
    if (confirm('Mark all notifications as read?')) {
        window.superAdminDashboard.markAllNotificationsRead();
    }
}

function filterAuditLogs() {
    if (window.superAdminDashboard) {
        window.superAdminDashboard.loadAuditLogs();
    }
}
