// Super Admin Dashboard JavaScript - Modular Version
import { SettingsModule } from './superadmin-modules/settings-module.js';
import { UserManagementModule } from './superadmin-modules/user-management-module.js';
import { AnalyticsModule } from './superadmin-modules/analytics-module.js';
import { NotificationsModule } from './superadmin-modules/notifications-module.js';
import { BackupModule } from './superadmin-modules/backup-module.js';
import { SupportModule } from './superadmin-modules/support-module.js';
import { AuditModule } from './superadmin-modules/audit-module.js';
import { ConsoleErrorsModule } from './superadmin-modules/console-errors-module.js';

class SuperAdminDashboard {
    constructor() {
        this.currentSection = 'dashboard';
        this.charts = {};
        this.init();
        this.initializeModules();
    }

    initializeModules() {
        this.settingsModule = new SettingsModule(this);
        this.userManagementModule = new UserManagementModule(this);
        this.analyticsModule = new AnalyticsModule(this);
        this.notificationsModule = new NotificationsModule(this);
        this.backupModule = new BackupModule(this);
        this.supportModule = new SupportModule(this);
        this.auditModule = new AuditModule(this);
        this.consoleErrorsModule = new ConsoleErrorsModule(this);
        
        // Make analytics module globally accessible for refresh button
        window.currentAnalyticsModule = this.analyticsModule;
        
        // Setup error logging
        this.consoleErrorsModule.setupErrorLogging();
    }

    init() {
        this.setupNavigation();
        this.setupEventListeners();
        this.loadInitialData();
        this.handleInitialSection();
        this.startAutoRefresh();
    }

    startAutoRefresh() {
        // Auto-refresh dashboard stats every 30 seconds
        this.refreshInterval = setInterval(() => {
            this.refreshDashboardStats();
        }, 30000); // 30 seconds
        
        console.log('Dashboard auto-refresh started (30 seconds interval)');
    }

    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
            console.log('Dashboard auto-refresh stopped');
        }
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
        document.addEventListener('click', (e) => {
            if (e.target.matches('.action-btn')) {
                this.handleQuickAction(e.target);
            }
        });
    }

    handleInitialSection() {
        const activeSection = document.querySelector('.content-section.active');
        if (activeSection) {
            const sectionId = activeSection.id;
            
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            const targetNavItem = document.querySelector(`[data-section="${sectionId}"]`)?.closest('.nav-item');
            if (targetNavItem) {
                targetNavItem.classList.add('active');
            }
            
            this.currentSection = sectionId;
            this.updatePageTitle(sectionId);
        }
    }

    switchSection(sectionName) {
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });
        
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        
        const dynamicContent = document.getElementById('dynamic-content');
        if (dynamicContent) {
            dynamicContent.innerHTML = '';
        }
        
        const targetSection = document.getElementById(sectionName);
        if (targetSection) {
            targetSection.classList.add('active');
        } else {
            this.loadDynamicSection(sectionName);
        }
        
        const activeNavItem = document.querySelector(`[data-section="${sectionName}"]`)?.closest('.nav-item');
        if (activeNavItem) {
            activeNavItem.classList.add('active');
        }
        
        if (sectionName === 'audit-trails' && targetSection) {
            this.auditModule.loadAuditTrails();
        }
        
        this.updatePageTitle(sectionName);
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
        
        // Only update subtitle if element exists
        const subtitleElement = document.getElementById('page-subtitle');
        if (subtitleElement) {
            subtitleElement.textContent = subtitles[section] || 'System Administration';
        }
    }

    loadDynamicSection(sectionName) {
        const dynamicContent = document.getElementById('dynamic-content');
        
        switch (sectionName) {
            case 'system-settings':
                this.settingsModule.loadSystemSettings(dynamicContent);
                break;
            case 'account-management':
                this.userManagementModule.loadAccountManagement(dynamicContent);
                break;
            case 'audit-trails':
                this.auditModule.loadAuditTrails(dynamicContent);
                break;
            case 'customer-support':
                this.supportModule.loadCustomerSupport(dynamicContent);
                break;
            case 'analytics':
                this.analyticsModule.loadAnalytics(dynamicContent);
                break;
            case 'notifications':
                this.notificationsModule.loadNotifications(dynamicContent);
                this.notificationsModule.init();
                break;
            case 'backup':
                this.backupModule.loadBackup(dynamicContent);
                break;
            case 'console-errors':
                this.consoleErrorsModule.loadConsoleErrors(dynamicContent);
                break;
        }
    }

    // Delegation methods for module functionality
    // deleteUser method removed - now handled by soft delete functions

    showCreateUserModal() {
        return this.userManagementModule.showAddUserModal();
    }

    async showEditUserModal(userId) {
        return this.userManagementModule.showEditUserModal(userId);
    }

    closeUserModal() {
        return this.userManagementModule.closeUserModal();
    }

    async saveUser(event) {
        if (event) event.preventDefault();
        return this.userManagementModule.saveUser();
    }

    // Utility methods
    async loadInitialData() {
        try {
            await this.refreshDashboardStats();
            await this.auditModule.loadRecentActivityData();
            await this.notificationsModule.updateNotificationBadge();
            // Support badge is now handled by support-module.js to avoid conflicts
        } catch (error) {
            console.error('Error loading initial data:', error);
        }
    }

    // Delegation method for recent activity data
    async loadRecentActivityData() {
        return this.auditModule.loadRecentActivityData();
    }

    async refreshDashboardStats() {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php?action=get_dashboard_stats');
            const data = await response.json();
            
            if (data.success) {
                this.updateDashboardStats(data.stats);
            }
        } catch (error) {
            console.error('Error loading dashboard stats:', error);
        }
    }

    updateDashboardStats(stats) {
        if (stats) {
            // Update stat cards by finding them by their text content
            const statCards = document.querySelectorAll('.stat-card');
            
            statCards.forEach(card => {
                const content = card.querySelector('.stat-content');
                const label = content.querySelector('p').textContent.trim();
                const valueElement = content.querySelector('h3');
                
                switch (label) {
                    case 'Total Users':
                        if (stats.total_users !== undefined) {
                            valueElement.textContent = Number(stats.total_users).toLocaleString();
                        }
                        break;
                    case 'Admin Accounts':
                        if (stats.total_admins !== undefined) {
                            valueElement.textContent = Number(stats.total_admins).toLocaleString();
                        }
                        break;
                    case 'Open Customer Support':
                        if (stats.open_support !== undefined) {
                            valueElement.textContent = Number(stats.open_support).toLocaleString();
                        }
                        break;
                }
            });
        }
    }

    handleQuickAction(button) {
        const action = button.dataset.action;
        
        switch (action) {
            case 'refresh-stats':
                this.refreshDashboardStats();
                break;
            case 'clear-cache':
                this.settingsModule.clearSystemCache();
                break;
            case 'system-check':
                this.settingsModule.runSystemCheck();
                break;
        }
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 3000);
    }
}

// Initialize dashboard when DOM is ready
let dashboard;

document.addEventListener('DOMContentLoaded', function() {
    dashboard = new SuperAdminDashboard();
    window.superAdminDashboard = dashboard;
    
    // Setup all global function handlers after dashboard is initialized
    setupGlobalHandlers();
});

function setupGlobalHandlers() {
    // Global function handlers for inline event handlers
    window.loadAuditTrails = function() {
        if (dashboard && dashboard.auditModule) {
            dashboard.auditModule.loadAuditTrails();
        }
    };

    window.saveMaintenanceSettings = function() {
        if (dashboard) dashboard.showNotification('Maintenance settings saved', 'success');
    };

    window.saveSecuritySettings = function() {
        if (dashboard) dashboard.showNotification('Security settings saved', 'success');
    };

    window.saveBackupSettings = function() {
        if (dashboard) dashboard.showNotification('Backup settings saved', 'success');
    };

    window.editUser = function(userId) {
        if (dashboard) dashboard.showEditUserModal(userId);
    };

    // window.deleteUser removed - now handled by soft delete functions (showSoftDeleteModal)

    window.showAddUserModal = function() {
        if (dashboard) dashboard.showCreateUserModal();
    };

    window.closeUserModal = function() {
        if (dashboard) dashboard.closeUserModal();
    };

    window.saveUser = function(event) {
        if (dashboard) dashboard.saveUser(event);
    };

    window.markAsRead = function(notificationId, notificationSource, supportType) {
        if (dashboard && dashboard.notificationsModule) {
            dashboard.notificationsModule.markAsRead(notificationId, notificationSource, supportType);
        }
    };

    window.deleteNotification = function(notificationId, notificationSource, supportType) {
        if (dashboard && dashboard.notificationsModule) {
            dashboard.notificationsModule.deleteNotification(notificationId, notificationSource, supportType);
        }
    };

    window.clearAllNotifications = function() {
        if (confirm('Mark all notifications as read?')) {
            if (dashboard && dashboard.notificationsModule) {
                dashboard.notificationsModule.markAllNotificationsRead();
            }
        }
    };

    window.refreshAnalytics = function() {
        if (dashboard && dashboard.analyticsModule) {
            dashboard.analyticsModule.refreshAnalytics();
        }
    };

    window.createManualBackup = function() {
        if (confirm('Create a manual backup? This may take several minutes.')) {
            if (dashboard && dashboard.backupModule) {
                dashboard.backupModule.createManualBackup();
            }
        }
    };

    window.restoreFromBackup = function() {
        if (dashboard && dashboard.backupModule) {
            dashboard.backupModule.restoreFromBackup();
        }
    };

    window.downloadBackup = function(backupId) {
        if (dashboard && dashboard.backupModule) {
            dashboard.backupModule.downloadBackup(backupId);
        }
    };

    window.deleteBackup = function(backupId) {
        if (dashboard && dashboard.backupModule) {
            dashboard.backupModule.deleteBackup(backupId);
        }
    };

    window.viewTicket = function(ticketId) {
        if (dashboard && dashboard.supportModule) {
            dashboard.supportModule.viewTicket(ticketId);
        }
    };

    window.replyToTicket = function(ticketId) {
        if (dashboard && dashboard.supportModule) {
            dashboard.supportModule.replyToTicket(ticketId);
        }
    };

    window.closeReplyModal = function() {
        if (dashboard && dashboard.supportModule) {
            dashboard.supportModule.closeReplyModal();
        }
    };

    window.sendReply = function(event) {
        if (dashboard && dashboard.supportModule) {
            dashboard.supportModule.sendReply(event);
        }
    };

    window.closeViewTicketModal = function() {
        if (dashboard && dashboard.supportModule) {
            dashboard.supportModule.closeViewTicketModal();
        }
    };

    window.replyToTicketFromView = function() {
        if (dashboard && dashboard.supportModule) {
            dashboard.supportModule.replyToTicketFromView();
        }
    };

    window.clearConsoleErrors = function() {
        if (dashboard && dashboard.consoleErrorsModule) {
            dashboard.consoleErrorsModule.clearConsoleErrors();
        }
    };

    window.exportConsoleErrors = function() {
        if (dashboard && dashboard.consoleErrorsModule) {
            dashboard.consoleErrorsModule.exportConsoleErrors();
        }
    };

    window.viewErrorDetails = function(errorId) {
        if (dashboard && dashboard.consoleErrorsModule) {
            dashboard.consoleErrorsModule.viewErrorDetails(errorId);
        }
    };

    window.deleteError = function(errorId) {
        if (dashboard && dashboard.consoleErrorsModule) {
            dashboard.consoleErrorsModule.deleteError(errorId);
        }
    };

    // Additional missing handlers
    window.toggleMaintenanceMode = function(enabled) {
        if (dashboard && dashboard.settingsModule) {
            dashboard.settingsModule.toggleMaintenanceMode(enabled);
        }
    };

    window.createBackup = function() {
        if (confirm('Create a system backup? This process may take several minutes.')) {
            if (dashboard && dashboard.backupModule) {
                dashboard.backupModule.createManualBackup();
            }
        }
    };

    window.loadRecentActivity = function() {
        if (dashboard && dashboard.auditModule) {
            dashboard.auditModule.loadRecentActivityData();
        }
    };

    // Add missing navigateToSection function
    window.navigateToSection = function(sectionName) {
        if (dashboard) {
            dashboard.switchSection(sectionName);
        }
    };
}

// Chart interaction handlers
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        if (e.target.matches('.chart-btn')) {
            const container = e.target.closest('.chart-container');
            const buttons = container.querySelectorAll('.chart-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            e.target.classList.add('active');
            
            const chartType = e.target.dataset.chart;
            if (chartType === 'sales-monthly' && window.superAdminDashboard) {
                console.log('Switching to monthly sales view');
            }
        }
    });
});

export default SuperAdminDashboard;
