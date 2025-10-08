// Notifications Module for Super Admin Dashboard
export class NotificationsModule {
    constructor(dashboard) {
        this.dashboard = dashboard;
    }

    loadNotifications(container) {
        container.innerHTML = `
            <section class="notifications-section">

                <div class="notifications-filters">
                    <div class="filter-tabs">
                        <button class="filter-tab active" data-filter="all">All</button>
                        <button class="filter-tab" data-filter="security">Security</button>
                        <button class="filter-tab" data-filter="system">System</button>
                        <button class="filter-tab" data-filter="customer_support">Customer Support</button>
                    </div>
                    <div class="filter-actions">
                        <button class="btn btn-success" id="markAllReadBtn">
                            Mark All as Read
                        </button>
                    </div>
                </div>


                <div class="notifications-list" id="notificationsList">
                    <div class="loading">Loading notifications...</div>
                </div>
            </section>
        `;
        
        // Load notifications immediately after DOM is ready
        setTimeout(() => {
            this.loadNotificationsList();
            this.updateNotificationBadge();
            // Removed updateSupportBadge() - causes conflict with support-module.js
            this.setupFilterTabs();
            this.setupMarkAllReadButton();
        }, 100);
    }

    async init() {
        await this.loadNotificationsList();
        await this.updateNotificationBadge();
        // Removed updateSupportBadge() - causes conflict with support-module.js
    }

    async loadNotificationsList(filter = 'all') {
        try {
            const response = await fetch(`api/superadmin_api/super_admin_actions.php?action=get_notifications&filter=${filter}`);
            const data = await response.json();
            
            const container = document.getElementById('notificationsList');
            if (data.success && data.notifications && data.notifications.length > 0) {
                container.innerHTML = data.notifications.map(notification => `
                    <div class="notification-item ${!notification.is_read ? 'unread' : 'read'}" data-notification-id="${notification.id}">
                        <div class="notification-icon">
                            <i class="fas fa-${this.getNotificationIcon(notification)}"></i>
                        </div>
                        <div class="notification-content" onclick="toggleNotificationText(${notification.id})">
                            <h4>${notification.title}</h4>
                            <p class="notification-text" data-full-text="${notification.message.replace(/"/g, '&quot;')}" data-truncated="true">
                                ${notification.message.length > 80 ? notification.message.substring(0, 80) + '...' : notification.message}
                                ${notification.message.length > 80 ? '<span class="expand-hint"> (click to expand)</span>' : ''}
                            </p>
                            <span class="notification-time">${new Date(notification.created_at).toLocaleString()}</span>
                            ${this.getNotificationBadge(notification)}
                        </div>
                        <div class="notification-actions">
                            ${!notification.is_read ? `
                                <button class="notification-btn read-btn" onclick="markAsRead(${notification.id}, '${notification.notification_source || 'system'}', '${notification.support_type || notification.system_type || 'ticket'}')">
                                    <i class="fas fa-check"></i>
                                </button>
                            ` : ''}
                            <button class="notification-btn delete-btn" onclick="deleteNotification(${notification.id}, '${notification.notification_source || 'system'}', '${notification.support_type || notification.system_type || 'ticket'}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
                
                // Add the toggle function to window for global access
                window.toggleNotificationText = this.toggleNotificationText;
            } else {
                container.innerHTML = '<div class="no-notifications">No notifications found</div>';
            }
            
            // Debug logging
            console.log('Notifications API response:', data);
            console.log('Container element:', container);
            console.log('Data success:', data.success);
            console.log('Notifications array:', data.notifications);
            console.log('Notifications length:', data.notifications ? data.notifications.length : 'undefined');
        } catch (error) {
            console.error('Error loading notifications:', error);
            const container = document.getElementById('notificationsList');
            container.innerHTML = '<div class="error-loading">Error loading notifications</div>';
        }
    }

    toggleNotificationText(notificationId) {
        const notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
        if (!notificationItem) return;
        
        const textElement = notificationItem.querySelector('.notification-text');
        const fullText = textElement.getAttribute('data-full-text');
        const isTruncated = textElement.getAttribute('data-truncated') === 'true';
        
        if (isTruncated) {
            // Expand to show full text
            textElement.innerHTML = fullText + '<span class="expand-hint"> (click to collapse)</span>';
            textElement.setAttribute('data-truncated', 'false');
            notificationItem.classList.add('expanded');
        } else {
            // Collapse to show truncated text
            const truncatedText = fullText.length > 80 ? fullText.substring(0, 80) + '...' : fullText;
            textElement.innerHTML = truncatedText + (fullText.length > 80 ? '<span class="expand-hint"> (click to expand)</span>' : '');
            textElement.setAttribute('data-truncated', 'true');
            notificationItem.classList.remove('expanded');
        }
    }

    getNotificationIcon(notification) {
        // Handle customer support notifications
        if (notification.notification_source === 'customer_support') {
            return 'headset';
        }
        
        // Handle security notifications
        if (notification.notification_source === 'security') {
            const securityIconMap = {
                'failed_login': 'shield-alt',
                'new_staff': 'user-plus'
            };
            return securityIconMap[notification.security_type] || 'shield-alt';
        }
        
        // Handle system notifications
        if (notification.notification_source === 'system') {
            const systemIconMap = {
                'high_value_order': 'dollar-sign',
                'system_error': 'exclamation-triangle',
                'low_inventory': 'boxes'
            };
            return systemIconMap[notification.system_type] || 'cog';
        }
        
        // Handle admin action notifications
        if (notification.notification_source === 'admin_actions') {
            const adminIconMap = {
                'user_role_change': 'user-cog',
                'user_delete': 'user-times',
                'maintenance_toggle': 'tools',
                'system_setting_change': 'cogs',
                'admin_login': 'sign-in-alt',
                'cashier_login': 'cash-register'
            };
            return adminIconMap[notification.action_type] || 'user-shield';
        }
        
        // Handle developer notifications by type
        const iconMap = {
            'error': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle',
            'success': 'check-circle',
            'critical': 'times-circle'
        };
        return iconMap[notification.type] || 'bell';
    }

    getNotificationBadge(notification) {
        const badgeMap = {
            'customer_support': {
                class: 'customer-support',
                icon: 'headset',
                text: 'Customer Support'
            },
            'security': {
                class: 'security',
                icon: 'shield-alt',
                text: 'Security Alert'
            },
            'system': {
                class: 'system',
                icon: 'cog',
                text: 'System'
            },
            'admin_actions': {
                class: 'admin-actions',
                icon: 'user-shield',
                text: 'Admin Action'
            }
        };

        const badge = badgeMap[notification.notification_source];
        if (badge) {
            return `
                <div class="notification-badge ${badge.class}">
                    <i class="fas fa-${badge.icon}"></i> ${badge.text}
                </div>
            `;
        }
        return '';
    }

    setupFilterTabs() {
        const filterTabs = document.querySelectorAll('.filter-tab');
        filterTabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                // Remove active class from all tabs
                filterTabs.forEach(t => t.classList.remove('active'));
                // Add active class to clicked tab
                e.target.classList.add('active');
                
                // Load notifications with selected filter
                const filter = e.target.getAttribute('data-filter');
                this.loadNotificationsList(filter);
            });
        });
    }

    setupMarkAllReadButton() {
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', () => {
                this.markAllNotificationsRead();
            });
        }
    }

    navigateToSupport(supportType, id) {
        // Navigate to customer support dashboard
        if (supportType === 'ticket') {
            // Navigate to support tickets view
            window.location.href = '#customer-support';
        } else if (supportType === 'message') {
            // Navigate to support messages view
            window.location.href = '#customer-support';
        }
    }

    async markAsRead(notificationId, notificationSource = 'system', supportType = 'ticket') {
        try {
            // Immediately update UI to show as read
            const notificationElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (notificationElement) {
                notificationElement.classList.remove('unread');
                notificationElement.classList.add('read');
                
                // Remove the "Mark as Read" button immediately for UI feedback
                const markReadBtn = notificationElement.querySelector('.read-btn');
                if (markReadBtn) {
                    markReadBtn.remove();
                }
            }

            const requestBody = {
                action: 'mark_notification_read',
                id: notificationId,
                source: notificationSource,
                support_type: supportType
            };

            // Add system_type for system notifications
            if (notificationSource === 'system') {
                requestBody.system_type = supportType;
            }

            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestBody)
            });

            const data = await response.json();
            if (data.success) {
                // Ensure the button is removed after successful API response
                const markReadBtn = notificationElement.querySelector('.read-btn');
                if (markReadBtn) {
                    markReadBtn.remove();
                }
                this.dashboard.showNotification('Notification marked as read', 'success');
                // Update notification badge count immediately
                this.updateNotificationBadge();
            } else {
                this.dashboard.showNotification('Failed to mark notification as read', 'error');
                // Revert UI changes on failure
                if (notificationElement) {
                    notificationElement.classList.remove('read');
                    notificationElement.classList.add('unread');
                    this.loadNotificationsList(); // Reload to restore original state
                }
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
            this.dashboard.showNotification('Error marking notification as read', 'error');
            // Reload notifications on error to restore correct state
            this.loadNotificationsList();
        }
    }

    async deleteNotification(notificationId, notificationSource = 'system', supportType = 'ticket') {

        try {
            const requestBody = {
                action: 'delete_notification',
                id: notificationId,
                source: notificationSource,
                support_type: supportType
            };

            // Add system_type for system notifications
            if (notificationSource === 'system') {
                requestBody.system_type = supportType;
            }

            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestBody)
            });

            const data = await response.json();
            if (data.success) {
                this.dashboard.showNotification('Notification deleted', 'success');
                this.loadNotificationsList();
                this.updateNotificationBadge();
            } else {
                this.dashboard.showNotification('Failed to delete notification', 'error');
            }
        } catch (error) {
            console.error('Error deleting notification:', error);
            this.dashboard.showNotification('Error deleting notification', 'error');
        }
    }

    async markAllNotificationsRead() {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'mark_all_notifications_read'
                })
            });

            const data = await response.json();
            if (data.success) {
                this.loadNotificationsList();
                this.updateNotificationBadge();
                this.dashboard.showNotification('All notifications marked as read', 'success');
            } else {
                this.dashboard.showNotification('Failed to mark all notifications as read', 'error');
            }
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
            this.dashboard.showNotification('Error marking all notifications as read', 'error');
        }
    }

    async updateNotificationBadge() {
        try {
            const response = await fetch(`api/superadmin_api/super_admin_actions.php?action=get_notifications`);
            const data = await response.json();
            
            if (data.success && data.notifications) {
                const unreadCount = data.notifications.filter(n => !n.is_read).length;
                // Target specifically the notifications badge, not the support badge
                const notificationLink = document.querySelector('[data-section="notifications"]');
                const badge = notificationLink ? notificationLink.querySelector('.badge:not(.support-badge)') : null;
                
                if (badge) {
                    if (unreadCount > 0) {
                        badge.textContent = unreadCount;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                } else if (unreadCount > 0 && notificationLink) {
                    // Create badge if it doesn't exist
                    const newBadge = document.createElement('span');
                    newBadge.className = 'badge';
                    newBadge.textContent = unreadCount;
                    notificationLink.appendChild(newBadge);
                }
            }
        } catch (error) {
            console.error('Error updating notification badge:', error);
        }
    }

    // updateSupportBadge() disabled - causes conflict with support-module.js
    // The support module handles the support badge to show unread message count
    // This function was showing open ticket count which is different and conflicting
}

// Global functions for inline onclick handlers
window.markAsRead = function(notificationId, notificationSource, supportType) {
    // Get the current notifications module instance from the dashboard
    if (window.superAdminDashboard && window.superAdminDashboard.notificationsModule) {
        window.superAdminDashboard.notificationsModule.markAsRead(notificationId, notificationSource, supportType);
    }
};

window.deleteNotification = function(notificationId, notificationSource, supportType) {
    // Get the current notifications module instance from the dashboard
    if (window.superAdminDashboard && window.superAdminDashboard.notificationsModule) {
        window.superAdminDashboard.notificationsModule.deleteNotification(notificationId, notificationSource, supportType);
    }
};
