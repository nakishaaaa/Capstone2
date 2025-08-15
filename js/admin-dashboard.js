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

    // Fallback refresh for dashboard stats every 30 seconds (SSE provides real-time updates)
    setInterval(
      async () => {
        if (this.navigation.getCurrentSection() === "dashboard" && !this.modules.dashboard.sseClient?.isConnected) {
          await this.modules.dashboard.loadStats()
        }
      },
      30 * 1000,
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
  }
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  console.log('Admin Dashboard: Initializing...')
  
  try {
    initializeDashboard()
    initializeMobileMenu()
    console.log('Admin Dashboard: Initialization complete')
  } catch (error) {
    console.error('Admin Dashboard: Initialization failed:', error)
  }
})

function initializeDashboard() {
  window.adminDashboard = new AdminDashboard()
}

// Mobile menu functionality
function initializeMobileMenu() {
  // Create mobile menu toggle functionality
  const mainContent = document.querySelector('.main-content')
  const sidebar = document.querySelector('.sidebar')
  
  if (mainContent && sidebar) {
    // Add click event to mobile header
    mainContent.addEventListener('click', function(e) {
      // Only trigger on mobile screens and when clicking the header area
      if (window.innerWidth <= 768 && e.target === mainContent && e.clientY <= 60) {
        sidebar.classList.toggle('active')
        
        // Add overlay when sidebar is open
        toggleMobileOverlay()
      }
    })
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
      if (window.innerWidth <= 768 && 
          sidebar.classList.contains('active') && 
          !sidebar.contains(e.target) && 
          !mainContent.contains(e.target)) {
        sidebar.classList.remove('active')
        removeMobileOverlay()
      }
    })
    
    // Close sidebar when clicking nav links on mobile
    const navLinks = sidebar.querySelectorAll('.nav-link')
    navLinks.forEach(link => {
      link.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
          sidebar.classList.remove('active')
          removeMobileOverlay()
        }
      })
    })
    
    window.addEventListener('resize', function() {
      if (window.innerWidth > 768) {
        sidebar.classList.remove('active')
        removeMobileOverlay()
      }
    })
  }
}

function toggleMobileOverlay() {
  let overlay = document.querySelector('.mobile-overlay')
  
  if (!overlay) {
    overlay = document.createElement('div')
    overlay.className = 'mobile-overlay'
    overlay.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      opacity: 0;
      transition: opacity 0.3s ease;
    `
    document.body.appendChild(overlay)
    
    setTimeout(() => {
      overlay.style.opacity = '1'
    }, 10)
    
    overlay.addEventListener('click', function() {
      document.querySelector('.sidebar').classList.remove('active')
      removeMobileOverlay()
    })
  }
}

function removeMobileOverlay() {
  const overlay = document.querySelector('.mobile-overlay')
  if (overlay) {
    overlay.style.opacity = '0'
    setTimeout(() => {
      overlay.remove()
    }, 300)
  }
}

// Export for module usage
export default AdminDashboard
