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

    if (diff < 60000) return "Just now"
    if (diff < 3600000) return `${Math.floor(diff / 60000)} minutes ago`
    if (diff < 86400000) return `${Math.floor(diff / 3600000)} hours ago`
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
