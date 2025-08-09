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
      } else {
        console.error("Error loading notifications:", result.error)
        this.toast.error("Error loading notifications: " + result.error)
        this.notifications = []
      }
    } catch (error) {
      console.error("Error loading notifications:", error)
      this.toast.error("Error connecting to database for notifications")
      this.notifications = []
    }
  }

  async displayNotifications() {
    await this.loadNotifications()

    const container = document.getElementById("notificationsContainer")
    if (!container) return

    if (this.notifications.length === 0) {
      container.innerHTML = '<div style="text-align: center; padding: 2rem;">No notifications available</div>'
      return
    }

    container.innerHTML = this.notifications
      .map(
        (notification) => `
      <div class="notification-item ${!notification.is_read ? "unread" : ""}">
        <div class="notification-icon">
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

  // Real-time SSE update handler for notification badge count
  updateBadgeCount(unreadCount) {
    console.log("Updating notification badge count from SSE:", unreadCount)
    
    // Update notification badge in navigation/header
    const badgeElements = document.querySelectorAll('.notification-badge, .notifications-badge')
    badgeElements.forEach(badge => {
      if (unreadCount > 0) {
        badge.textContent = unreadCount
        badge.style.display = 'inline-block'
        badge.style.backgroundColor = '#dc3545'
        badge.style.color = 'white'
      } else {
        badge.style.display = 'none'
      }
    })
    
    // Update any notification counters in the UI
    const notificationCounters = document.querySelectorAll('.notification-count')
    notificationCounters.forEach(counter => {
      counter.textContent = unreadCount
    })
  }
}
