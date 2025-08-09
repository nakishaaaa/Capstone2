// import { Chart } from "@/components/ui/chart"
// Dashboard functionality
import { ApiClient } from "../core/api-client.js"
import { Utils } from "../utils/helpers.js"
import { CONFIG } from "../core/config.js"

export class DashboardModule {
  constructor(toastManager) {
    this.api = new ApiClient()
    this.toast = toastManager
    this.charts = {}
    this.lastSSEData = null // Track last data to detect actual changes
  }

  async loadStats() {
    try {
      console.log("Loading dashboard stats from database...")
      const result = await this.api.getSalesStats()

      if (result.success) {
        this.updateStatsDisplay(result.data)
        console.log("Dashboard stats loaded successfully:", result.data)
      }
    } catch (error) {
      console.error("Error loading dashboard stats:", error)
      this.toast.error("Error connecting to database for stats")
      // Use fallback data
      this.updateStatsDisplay({
        total_sales: 0,
        total_orders: 0,
        total_products: 0,
        low_stock: 0,
      })
    }
  }

  updateStatsDisplay(data) {
    const elements = {
      "total-sales": Utils.formatCurrency(data.total_sales),
      "total-orders": data.total_orders,
      "total-products": data.total_products,
      "low-stock": data.low_stock,
      // Note: total-requests is managed by RequestsModule, not DashboardModule
    }

    Object.entries(elements).forEach(([id, value]) => {
      const element = document.getElementById(id)
      if (element) {
        element.textContent = value
      }
    })
  }

  // Real-time SSE update handler
  updateStatsFromSSE(sseData) {
    console.log("Updating dashboard stats from SSE:", sseData)
    
    // Check if data actually changed
    const dataChanged = this.hasDataChanged(sseData)
    
    // Update inventory stats
    if (sseData.inventory) {
      const totalProductsEl = document.getElementById("total-products")
      const lowStockEl = document.getElementById("low-stock")
      
      if (totalProductsEl) {
        totalProductsEl.textContent = sseData.inventory.total_products
      }
      if (lowStockEl) {
        lowStockEl.textContent = sseData.inventory.low_stock_count
        
        // Add visual indicator for low stock
        if (sseData.inventory.low_stock_count > 0) {
          lowStockEl.style.color = "#ffc107"
          lowStockEl.style.fontWeight = "bold"
        } else {
          lowStockEl.style.color = ""
          lowStockEl.style.fontWeight = ""
        }
      }
    }
    
    // Update sales stats
    if (sseData.sales) {
      const totalSalesEl = document.getElementById("total-sales")
      const totalOrdersEl = document.getElementById("total-orders")
      
      if (totalSalesEl) {
        totalSalesEl.textContent = Utils.formatCurrency(sseData.sales.total_revenue)
      }
      if (totalOrdersEl) {
        totalOrdersEl.textContent = sseData.sales.total_sales
      }
    }
    
    // Only animate if data actually changed
    if (dataChanged) {
      this.addUpdateAnimation()
    }
    
    // Store current data for next comparison
    this.lastSSEData = JSON.parse(JSON.stringify(sseData))
  }

  // Check if SSE data has actually changed
  hasDataChanged(newData) {
    if (!this.lastSSEData) {
      return true // First time, consider it changed
    }
    
    // Compare key stats that matter for visual updates
    const oldStats = this.lastSSEData
    const newStats = newData
    
    // Check inventory changes
    if (oldStats.inventory && newStats.inventory) {
      if (oldStats.inventory.total_products !== newStats.inventory.total_products ||
          oldStats.inventory.low_stock_count !== newStats.inventory.low_stock_count) {
        return true
      }
    }
    
    // Check sales changes
    if (oldStats.sales && newStats.sales) {
      if (oldStats.sales.total_revenue !== newStats.sales.total_revenue ||
          oldStats.sales.total_sales !== newStats.sales.total_sales) {
        return true
      }
    }
    
    // Check requests changes
    if (oldStats.requests && newStats.requests) {
      if (oldStats.requests.pending_requests !== newStats.requests.pending_requests) {
        return true
      }
    }
    
    // Check notifications changes
    if (oldStats.notifications && newStats.notifications) {
      if (oldStats.notifications.unread_notifications !== newStats.notifications.unread_notifications) {
        return true
      }
    }
    
    return false // No significant changes detected
  }

  // Update activity feed with real-time data
  updateActivityFeed(activities) {
    const activityFeed = document.getElementById("recent-activity")
    if (!activityFeed) return
    
    activityFeed.innerHTML = ""
    
    activities.slice(0, 5).forEach(activity => {
      const activityItem = document.createElement("div")
      activityItem.className = "activity-item"
      activityItem.style.cssText = `
        padding: 8px 12px;
        border-left: 3px solid #007bff;
        margin-bottom: 8px;
        background: #f8f9fa;
        border-radius: 4px;
        font-size: 14px;
      `
      
      const timeAgo = this.getTimeAgo(activity.created_at)
      
      if (activity.type === 'sale') {
        activityItem.innerHTML = `
          <strong>Sale #${activity.id}</strong> - ${Utils.formatCurrency(activity.amount)}
          <div style="color: #666; font-size: 12px;">${timeAgo}</div>
        `
      } else if (activity.type === 'request') {
        activityItem.innerHTML = `
          <strong>New Request #${activity.id}</strong>
          <div style="color: #666; font-size: 12px;">${timeAgo}</div>
        `
      }
      
      activityFeed.appendChild(activityItem)
    })
  }

  // Helper method to calculate time ago
  getTimeAgo(timestamp) {
    const now = new Date()
    const past = new Date(timestamp)
    const diffMs = now - past
    const diffMins = Math.floor(diffMs / 60000)
    
    if (diffMins < 1) return "Just now"
    if (diffMins < 60) return `${diffMins} min ago`
    
    const diffHours = Math.floor(diffMins / 60)
    if (diffHours < 24) return `${diffHours}h ago`
    
    const diffDays = Math.floor(diffHours / 24)
    return `${diffDays}d ago`
  }

  // Add visual animation for real-time updates (only when data changes)
  addUpdateAnimation() {
    const statsCards = document.querySelectorAll('.stat-card')
    statsCards.forEach(card => {
      // Remove any existing animation first
      card.style.animation = ""
      
      // Add a subtle scale animation
      card.style.transition = "transform 0.3s ease"
      card.style.transform = "scale(1.05)"
      
      setTimeout(() => {
        card.style.transform = "scale(1)"
      }, 300)
    })
  }

  initializeCharts() {
    this.initSalesChart()
    this.initTopProductsChart()
  }

  initSalesChart() {
    const ctx = document.getElementById("salesOverviewChart")
    if (ctx && typeof Chart !== "undefined") {
      this.charts.sales = new Chart(ctx, {
        type: "line",
        data: {
          labels: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
          datasets: [
            {
              label: "Sales (₱)",
              data: [1200, 1900, 3000, 5000, 2000, 3000, 4500],
              borderColor: CONFIG.CHART_COLORS.primary,
              backgroundColor: `${CONFIG.CHART_COLORS.primary}1A`,
              tension: 0.4,
            },
          ],
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false },
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: (value) => Utils.formatCurrency(value),
              },
            },
          },
        },
      })
    }
  }

  initTopProductsChart() {
    const ctx = document.getElementById("topProductsChart")
    if (ctx && typeof Chart !== "undefined") {
      this.charts.topProducts = new Chart(ctx, {
        type: "doughnut",
        data: {
          labels: ["T-Shirts", "Mugs", "Stickers", "Banners", "Others"],
          datasets: [
            {
              data: [30, 25, 20, 15, 10],
              backgroundColor: [
                CONFIG.CHART_COLORS.primary,
                CONFIG.CHART_COLORS.secondary,
                "#f093fb",
                "#f5576c",
                "#4facfe",
              ],
            },
          ],
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: "bottom" },
          },
        },
      })
    }
  }

  updateDateTime() {
    const now = new Date()
    const options = {
      year: "numeric",
      month: "long",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
    }

    const element = document.getElementById("current-datetime")
    if (element) {
      element.textContent = now.toLocaleDateString(CONFIG.LOCALE, options)
    }
  }

  destroy() {
    // Clean up charts
    Object.values(this.charts).forEach((chart) => {
      if (chart && typeof chart.destroy === "function") {
        chart.destroy()
      }
    })
    this.charts = {}
  }
}
