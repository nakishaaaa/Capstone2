// Notifications functionality
import { ApiClient } from "../core/api-client.js"
import { Utils } from "../utils/helpers.js"

export class NotificationsModule {
  constructor(toastManager) {
    this.api = new ApiClient()
    this.toast = toastManager
    this.notifications = []
  }

  async loadNotifications() {
    try {
      console.log("Loading notifications from database...")
      const result = await this.api.getAllNotifications()

      if (result.success) {
        this.notifications = result.data
        console.log("Notifications loaded successfully:", this.notifications.length, "items")
        // Update sidebar badge on load as well (initial page load/background refresh)
        const unreadCount = Array.isArray(this.notifications)
          ? this.notifications.filter(n => !n.is_read).length
          : 0
        this.updateNotificationsBadge(unreadCount)
      } else {
        console.error("Error loading notifications:", result.error)
        this.toast.error("Error loading notifications: " + result.error)
        this.notifications = []
        this.updateNotificationsBadge(0)
      }
    } catch (error) {
      console.error("Error loading notifications:", error)
      this.toast.error("Error connecting to database for notifications")
      this.notifications = []
      this.updateNotificationsBadge(0)
    }
  }

  async displayNotifications() {
    await this.loadNotifications()

    const container = document.getElementById("notificationsContainer")
    if (!container) return

    if (this.notifications.length === 0) {
      container.innerHTML = '<div style="text-align: center; padding: 2rem;">No notifications available</div>'
      // Update sidebar badge: hide when zero
      this.updateNotificationsBadge(0)
      return
    }

    container.innerHTML = this.notifications
      .map(
        (notification) => `
      <div class="notification-item ${!notification.is_read ? "unread" : ""}">
        <div class="notification-icon ${notification.type}">
          <i class="fas fa-${Utils.getNotificationIcon(notification.type)}"></i>
        </div>
        <div class="notification-content">
          <div class="notification-title">${Utils.escapeHtml(notification.title)}</div>
          <div class="notification-message">${Utils.escapeHtml(notification.message)}</div>
          <div class="notification-time">${Utils.formatTime(notification.created_at)}</div>
        </div>
        <div class="notification-actions">
          ${
            !notification.is_read
              ? `
            <button class="btn btn-sm btn-primary" onclick="window.notificationsModule.markAsRead(${notification.id})">
              Mark as Read
            </button>
          `
              : ""
          }
          <button class="btn btn-sm btn-danger" onclick="window.notificationsModule.deleteNotification(${notification.id})">
            Delete
          </button>
        </div>
      </div>
    `,
      )
      .join("")

    // Update sidebar badge with unread count
    const unreadCount = this.notifications.filter(n => !n.is_read).length
    this.updateNotificationsBadge(unreadCount)
  }

  async markAsRead(id) {
    try {
      const result = await this.api.markNotificationRead(id)

      if (result.success) {
        await this.displayNotifications()
      } else {
        this.toast.error("Error marking notification as read")
      }
    } catch (error) {
      console.error("Error marking notification as read:", error)
      this.toast.error("Error marking notification as read")
    }
  }

  async markAllRead() {
    try {
      const result = await this.api.markAllNotificationsRead()

      if (result.success) {
        this.toast.success("All notifications marked as read")
        await this.displayNotifications()
      } else {
        this.toast.error("Error marking notifications as read")
      }
    } catch (error) {
      console.error("Error marking notifications as read:", error)
      this.toast.error("Error marking notifications as read")
    }
  }

  async deleteNotification(id) {
    try {
      const result = await this.api.deleteNotification(id)

      if (result.success) {
        this.toast.success("Notification dismissed")
        await this.displayNotifications()
      } else {
        this.toast.error("Error dismissing notification")
      }
    } catch (error) {
      console.error("Error dismissing notification:", error)
      this.toast.error("Error dismissing notification")
    }
  }

  updateNotificationsBadge(count) {
    try {
      const badge = document.getElementById('notificationsBadge')
      if (!badge) return
      const value = Number(count) || 0
      if (value > 0) {
        badge.textContent = value > 99 ? '99+' : String(value)
        badge.style.display = 'inline-block'
      } else {
        badge.textContent = '0'
        badge.style.display = 'none'
      }
    } catch (e) {
      console.warn('Failed to update notifications badge', e)
    }
  }
}
