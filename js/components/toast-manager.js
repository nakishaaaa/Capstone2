// Toast notification system
import { CONFIG, NOTIFICATION_TYPES } from "../core/config.js"
import { Utils } from "../utils/helpers.js"

export class ToastManager {
  constructor() {
    this.container = this.createContainer()
  }

  createContainer() {
    let container = document.getElementById("toast-container")
    if (!container) {
      container = document.createElement("div")
      container.id = "toast-container"
      container.className = "toast-container"
      container.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        display: flex;
        flex-direction: column;
        gap: 10px;
      `
      document.body.appendChild(container)
    }
    return container
  }

  show(message, type = NOTIFICATION_TYPES.INFO) {
    // Remove existing toasts of same type
    this.container.querySelectorAll(`.toast-${type}`).forEach((toast) => {
      toast.remove()
    })

    const toast = document.createElement("div")
    toast.className = `toast toast-${type}`
    toast.style.cssText = `
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 16px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      min-width: 300px;
      max-width: 500px;
      animation: slideIn 0.3s ease-out;
      background: white;
      border-left: 4px solid ${this.getTypeColor(type)};
    `

    toast.innerHTML = `
      <div style="display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-${Utils.getNotificationIcon(type)}" style="color: ${this.getTypeColor(type)};"></i>
        <span>${Utils.escapeHtml(message)}</span>
      </div>
      <button onclick="this.parentElement.remove()" 
              style="background: none; border: none; cursor: pointer; padding: 4px; border-radius: 4px; opacity: 0.8;">
        <i class="fas fa-times"></i>
      </button>
    `

    this.container.appendChild(toast)

    // Auto remove after duration
    setTimeout(() => {
      if (toast.parentElement) {
        toast.style.animation = "slideOut 0.3s ease-in"
        setTimeout(() => toast.remove(), 300)
      }
    }, CONFIG.TOAST_DURATION)

    return toast
  }

  getTypeColor(type) {
    switch (type) {
      case NOTIFICATION_TYPES.SUCCESS:
        return CONFIG.CHART_COLORS.success
      case NOTIFICATION_TYPES.ERROR:
        return CONFIG.CHART_COLORS.danger
      case NOTIFICATION_TYPES.WARNING:
        return CONFIG.CHART_COLORS.warning
      case NOTIFICATION_TYPES.INFO:
        return CONFIG.CHART_COLORS.info
      default:
        return CONFIG.CHART_COLORS.info
    }
  }

  success(message) {
    return this.show(message, NOTIFICATION_TYPES.SUCCESS)
  }

  error(message) {
    return this.show(message, NOTIFICATION_TYPES.ERROR)
  }

  warning(message) {
    return this.show(message, NOTIFICATION_TYPES.WARNING)
  }

  info(message) {
    return this.show(message, NOTIFICATION_TYPES.INFO)
  }
}
