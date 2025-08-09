import { ToastManager } from "./components/toast-manager.js"
import { ModalManager } from "./components/modal-manager.js"
import { DashboardModule } from "./modules/dashboard-module.js"
import { InventoryModule } from "./modules/inventory-module.js"
import { POSModule } from "./modules/pos-module.js"
import { ProductManagementModule } from "./modules/product-management-module.js"
import { NotificationsModule } from "./modules/notifications-module.js"
import { RequestsModule } from "./modules/requests-module.js"
import { NavigationModule } from "./modules/navigation-module.js"

class AdminDashboard {
  constructor() {
    this.modules = {}
    this.init()
  }

  async init() {
    console.log("Initializing refactored admin dashboard with database connection...")

    try {
      // Initialize core components
      this.toast = new ToastManager()
      this.modal = new ModalManager()
      this.navigation = new NavigationModule()

      // Initialize modules
      this.modules.dashboard = new DashboardModule(this.toast)
      this.modules.inventory = new InventoryModule(this.toast, this.modal)
      this.modules.pos = new POSModule(this.toast)
      this.modules.productManagement = new ProductManagementModule(this.toast, this.modal)
      this.modules.notifications = new NotificationsModule(this.toast)
      this.modules.requests = new RequestsModule(this.toast, this.modal)

      // Make modules globally accessible for onclick handlers
      window.modalManager = this.modal
      window.inventoryModule = this.modules.inventory
      window.posModule = this.modules.pos
      window.productManagementModule = this.modules.productManagement
      window.notificationsModule = this.modules.notifications
      window.requestsModule = this.modules.requests

      // Setup event listeners
      this.setupEventListeners()

      // Load initial data
      await this.loadInitialData()

      // Start periodic updates
      this.startPeriodicUpdates()

      this.toast.success("Dashboard loaded successfully!")
      console.log("Refactored admin dashboard initialized successfully")
    } catch (error) {
      console.error("Error initializing dashboard:", error)
      this.toast.error("Error loading dashboard data")
    }
  }

  setupEventListeners() {
    // Section change events
    document.addEventListener("sectionChange", (e) => {
      this.handleSectionChange(e.detail.sectionId)
    })

    // POS specific events
    const amountReceived = document.getElementById("amountReceived")
    if (amountReceived) {
      amountReceived.addEventListener("input", () => {
        this.modules.pos.calculateChange()
      })
    }

    const productSearch = document.getElementById("productSearch")
    if (productSearch) {
      productSearch.addEventListener("keyup", () => {
        this.modules.pos.searchProducts()
      })
    }

    // Global functions for backward compatibility
    window.refreshInventory = () => this.modules.inventory.refresh()
    window.clearTransaction = () => this.modules.pos.clearTransaction()
    window.processPayment = () => this.modules.pos.processPayment()
    window.openAddProductModal = () => this.modules.productManagement.openAddProductModal()
    window.markAllRead = () => this.modules.notifications.markAllRead()
    window.searchProducts = () => this.modules.pos.searchProducts()
  }

  async loadInitialData() {
    // Load products for POS and inventory
    await this.modules.pos.loadProducts()

    // Load dashboard stats
    await this.modules.dashboard.loadStats()

    // Load requests data to update dashboard stat-card
    await this.modules.requests.loadRequests()

    // Load notifications
    await this.modules.notifications.loadNotifications()

    // Initialize charts
    this.modules.dashboard.initializeCharts()
  }

  async handleSectionChange(sectionId) {
    try {
      switch (sectionId) {
        case "dashboard":
          await this.modules.dashboard.loadStats()
          break
        case "inventory":
          await this.modules.inventory.loadInventoryData()
          break
        case "pos":
          await this.modules.pos.loadProducts()
          break
        case "sales-management":
          await this.modules.productManagement.loadProducts()
          break
        case "notifications":
          await this.modules.notifications.displayNotifications()
          break
        case "requests":
          await this.modules.requests.loadRequests()
          break
      }
    } catch (error) {
      console.error(`Error loading ${sectionId} data:`, error)
      this.toast.error(`Failed to load ${sectionId} data`)
    }
  }

  startPeriodicUpdates() {
    // Update date/time every second
    setInterval(() => {
      this.modules.dashboard.updateDateTime()
    }, 1000)

    // Refresh dashboard stats every 5 minutes
    setInterval(
      async () => {
        if (this.navigation.getCurrentSection() === "dashboard") {
          await this.modules.dashboard.loadStats()
        }
      },
      5 * 60 * 1000,
    )

    // Refresh notifications every 2 minutes
    setInterval(
      async () => {
        await this.modules.notifications.loadNotifications()
      },
      2 * 60 * 1000,
    )
  }

  // Public API methods
  async refreshAllData() {
    await this.loadInitialData()
    this.toast.success("All data refreshed")
  }

  getModule(name) {
    return this.modules[name]
  }

  destroy() {
    // Cleanup modules
    if (this.modules.dashboard) {
      this.modules.dashboard.destroy()
    }

    // Clear intervals and event listeners
    // This would be expanded based on specific cleanup needs
  }
}

// Initialize dashboard when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  window.adminDashboard = new AdminDashboard()
})

// Export for module usage
export default AdminDashboard
