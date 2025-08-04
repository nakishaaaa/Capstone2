// Centralized API client for all backend communications
import { CONFIG } from "./config.js"

export class ApiClient {
  constructor() {
    this.baseUrl = CONFIG.API_BASE
  }

  async request(endpoint, options = {}) {
    const url = `${this.baseUrl}${endpoint}`

    const defaultOptions = {
      headers: {
        "Content-Type": "application/json",
      },
    }

    const config = { ...defaultOptions, ...options }

    try {
      const response = await fetch(url, config)

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data = await response.json()

      if (!data.success && data.error) {
        throw new Error(data.error)
      }

      return data
    } catch (error) {
      console.error(`API request failed: ${endpoint}`, error)
      throw error
    }
  }

  // Sales API methods
  async getSalesStats() {
    return this.request("sales.php?stats=1")
  }

  async processSale(saleData) {
    return this.request("sales.php", {
      method: "POST",
      body: JSON.stringify(saleData),
    })
  }

  async getSalesReport(startDate, endDate) {
    const params = new URLSearchParams()
    if (startDate) params.append("start_date", startDate)
    if (endDate) params.append("end_date", endDate)
    return this.request(`sales.php?report=1&${params.toString()}`)
  }

  // Inventory API methods
  async getAllProducts() {
    return this.request("inventory.php")
  }

  async getProduct(id) {
    return this.request(`inventory.php?id=${id}`)
  }

  async createProduct(productData) {
    return this.request("inventory.php", {
      method: "POST",
      body: JSON.stringify(productData),
    })
  }

  async updateProduct(id, productData) {
    return this.request(`inventory.php?id=${id}`, {
      method: "PUT",
      body: JSON.stringify(productData),
    })
  }

  async deleteProduct(id) {
    return this.request(`inventory.php?id=${id}`, {
      method: "DELETE",
    })
  }

  // Notifications API methods
  async getAllNotifications() {
    return this.request("notifications.php")
  }

  async markNotificationRead(id) {
    return this.request(`notifications.php?id=${id}`, {
      method: "PUT",
    })
  }

  async markAllNotificationsRead() {
    return this.request("notifications.php?mark_all_read=1", {
      method: "PUT",
    })
  }

  async deleteNotification(id) {
    return this.request(`notifications.php?id=${id}`, {
      method: "DELETE",
    })
  }

  // Image upload
  async uploadImage(imageFile) {
    const formData = new FormData()
    formData.append("image", imageFile)

    return this.request("upload_image.php", {
      method: "POST",
      body: formData,
      headers: {}, // Remove Content-Type to let browser set it for FormData
    })
  }
}
