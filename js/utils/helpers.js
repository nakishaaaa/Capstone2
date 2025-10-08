// Utility functions
import { CONFIG } from "../core/config.js"

export class Utils {
  static formatCurrency(amount) {
    return `${CONFIG.CURRENCY}${Number.parseFloat(amount).toLocaleString(CONFIG.LOCALE, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`
  }

  static formatTime(timestamp) {
    const date = new Date(timestamp)
    const now = new Date()
    const diff = now - date
    const seconds = Math.floor(diff / 1000)
    const minutes = Math.floor(diff / 60000)
    const hours = Math.floor(diff / 3600000)

    if (seconds < 30) return "Just now"
    if (seconds < 60) return `${seconds}s ago`
    if (minutes === 1) return "1m ago"
    if (minutes < 60) return `${minutes}m ago`
    if (hours === 1) return "1h ago"
    if (hours < 24) return `${hours}h ago`
    return date.toLocaleDateString(CONFIG.LOCALE)
  }

  static escapeHtml(text) {
    const div = document.createElement("div")
    div.textContent = text
    return div.innerHTML
  }

  static generateTransactionId() {
    return `TXN-${Date.now()}`
  }

  static validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return emailRegex.test(email)
  }

  static debounce(func, wait) {
    let timeout
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout)
        func(...args)
      }
      clearTimeout(timeout)
      timeout = setTimeout(later, wait)
    }
  }

  static getStockStatusClass(status) {
    switch (status) {
      case "In Stock":
        return "status-success"
      case "Low Stock":
        return "status-warning"
      case "Out of Stock":
        return "status-danger"
      default:
        return "status-info"
    }
  }

  static getNotificationIcon(type) {
    switch (type) {
      case "warning":
        return "exclamation-triangle"
      case "error":
        return "times-circle"
      case "success":
        return "check-circle"
      case "info":
        return "info-circle"
      default:
        return "bell"
    }
  }
}
