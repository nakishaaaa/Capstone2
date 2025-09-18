// Notifications Module for Super Admin Dashboard
export class NotificationsModule {
    constructor(dashboard) {
        this.dashboard = dashboard;
    }

    loadNotifications(container) {
        container.innerHTML = `
            <section class="notifications-section">

                <div class="notifications-filters">
                    <button class="btn btn-success" onclick="clearAllNotifications()">
                        Mark All as Read
                    </button>
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
            this.updateSupportBadge();
        }, 100);
    }

    async init() {
        await this.loadNotificationsList();
        await this.updateNotificationBadge();
        await this.updateSupportBadge();
    }

    async loadNotificationsList() {
        try {
            const response = await fetch(`api/superadmin_api/super_admin_actions.php?action=get_notifications`);
            const data = await response.json();
            
            const container = document.getElementById('notificationsList');
            if (data.success && data.notifications && data.notifications.length > 0) {
                container.innerHTML = data.notifications.map(notification => `
                    <div class="notification-item ${!notification.is_read ? 'unread' : 'read'}" data-notification-id="${notification.id}">
                        <div class="notification-icon">
                            <i class="fas fa-${this.getNotificationIcon(notification)}"></i>
                        </div>
                        <div class="notification-content">
                            <h4>${notification.title}</h4>
                            <p>${notification.message.length > 80 ? notification.message.substring(0, 80) + '...' : notification.message}</p>
                            <span class="notification-time">${new Date(notification.created_at).toLocaleString()}</span>
                            ${notification.notification_source === 'customer_support' ? `
                                <div class="notification-badge customer-support">
                                    <i class="fas fa-headset"></i> Customer Support
                                </div>
                            ` : ''}
                        </div>
                        <div class="notification-actions">
                            ${!notification.is_read ? `
                                <button class="notification-btn read-btn" onclick="markAsRead(${notification.id}, '${notification.notification_source || 'developer'}', '${notification.support_type || 'ticket'}')">
                                    <i class="fas fa-check"></i>
                                </button>
                            ` : ''}
                            <button class="notification-btn delete-btn" onclick="deleteNotification(${notification.id}, '${notification.notification_source || 'developer'}', '${notification.support_type || 'ticket'}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
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


    getNotificationIcon(notification) {
        // Handle customer support notifications
        if (notification.notification_source === 'customer_support') {
            return 'headset';
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

    async markAsRead(notificationId, notificationSource = 'developer', supportType = 'ticket') {
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

            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'mark_notification_read',
                    id: notificationId,
                    source: notificationSource,
                    support_type: supportType
                })
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

    async deleteNotification(notificationId, notificationSource = 'developer', supportType = 'ticket') {
        if (!confirm('Delete this notification?')) return;

        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_notification',
                    id: notificationId,
                    source: notificationSource,
                    support_type: supportType
                })
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

    async updateSupportBadge() {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'get_support_tickets',
                    status: 'open'
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.stats) {
                const openCount = data.stats.open_tickets || 0;
                const badge = document.getElementById('supportBadge');
                
                if (badge) {
                    if (openCount > 0) {
                        badge.textContent = openCount;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Error updating support badge:', error);
        }
    }
}
