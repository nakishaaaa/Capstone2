import { ToastManager } from "./components/toast-manager.js"
import { ModalManager } from "./components/modal-manager.js"
import { DashboardModule } from "./modules/dashboard-module.js"
import { InventoryModule } from "./modules/inventory-module.js"
import { POSModule } from "./modules/pos-module.js"
import { ProductManagementModule } from "./modules/product-management-module.js"
import { NotificationsModule } from "./modules/notifications-module.js"
import { RequestsModule } from "./modules/requests-module.js"
import { NavigationModule } from "./modules/navigation-module.js"
import AdminSupportModule from './modules/admin-support-module.js';
import UserManagementModule from './modules/user-management-module.js';
import SalesReportModule from './modules/sales-report-module.js';
import { csrfService } from "./modules/csrf-module.js"
import { ApiClient } from "./core/api-client.js"

class AdminDashboard {
  constructor() {
    this.modules = {}
    this.apiClient = new ApiClient()
    this.init()
  }

  defineLogoutHandler() {
    // Expose a global logout function used by onclick handlers in the page
    window.handleLogout = async (role = "admin") => {
      try {
        await csrfService.ensure()
        const token = csrfService.getToken()

        const data = await this.apiClient.request("logout.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": token || "",
          },
          credentials: "include",
          body: JSON.stringify({ role }),
        })

        if (data && data.success) {
          window.location.href = "index.php"
        } else {
          alert((data && data.error) || "Logout failed. Redirecting to login...")
          window.location.href = "index.php"
        }
      } catch (error) {
        console.error("Logout error:", error)
        window.location.href = "index.php"
      }
    }
  }

  async init() {
    console.log("Initializing refactored admin dashboard with database connection...")

    try {
      // Ensure CSRF is loaded early
      await csrfService.load()

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
      
      // Only initialize requests module for admin users
      if (window.userRole === 'admin') {
        this.modules.requests = new RequestsModule(this.toast, this.modal)
        this.modules.salesReport = new SalesReportModule(this.toast, this.apiClient)
      }
      
      // Pass the SSE client from dashboard module to admin support module
      this.modules.adminSupport = new AdminSupportModule(this.toast, this.modules.dashboard.sseClient)

      // Make modules globally accessible for onclick handlers
      window.modalManager = this.modal
      window.inventoryModule = this.modules.inventory
      window.posModule = this.modules.pos
      window.productManagementModule = this.modules.productManagement
      window.notificationsModule = this.modules.notifications
      
      // Only expose requests module for admin users
      if (this.modules.requests) {
        window.requestsModule = this.modules.requests
        window.refreshRequests = () => window.requestsModule.loadRequests()
        window.viewRequestHistory = () => window.requestsModule.viewHistory()
      }
      
      // Only expose sales report module for admin users
      if (this.modules.salesReport) {
        window.salesReportModule = this.modules.salesReport
        // Expose global functions for onclick handlers
        window.generateReport = () => this.modules.salesReport.generateReport()
        window.exportReport = () => this.modules.salesReport.exportReport()
        window.updateDateInputs = () => this.modules.salesReport.updateDateInputs()
        window.filterReportTable = () => this.modules.salesReport.filterReportTable()
        window.sortReportTable = () => this.modules.salesReport.sortReportTable()
      }
      
      window.adminSupportModule = this.modules.adminSupport

      // Setup event listeners
      this.setupEventListeners()

      // Define global logout handler
      this.defineLogoutHandler()

      // Load initial data
      await this.loadInitialData()

      // Start periodic updates
      this.startPeriodicUpdates()

      this.toast.success("Dashboard loaded successfully!")
      console.log("admin dashboard initialized successfully")
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
          if (this.modules.requests) {
            await this.modules.requests.loadRequests()
          }
          break
        case "sales-report":
          if (this.modules.salesReport) {
            // Sales report loads data on demand when user clicks generate
            console.log('Sales report section activated')
          }
          break
        case "customer-support":
          await this.modules.adminSupport.loadConversations()
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

export default AdminDashboard
