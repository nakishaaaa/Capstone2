// import { Chart } from "@/components/ui/chart"
// Dashboard functionality
import { ApiClient } from "../core/api-client.js"
import { Utils } from "../utils/helpers.js"
import { CONFIG } from "../core/config.js"
import { SSEClient } from "../core/sse-client.js"

export class DashboardModule {
  constructor(toastManager) {
    this.api = new ApiClient()
    this.toast = toastManager
    this.charts = {}
    this.sseClient = null
    this.lastStatsData = null
    this.initializeSSE()
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
        pending_requests: 0,
      })
    }
  }

  updateStatsDisplay(data) {
    const elements = {
      "total-sales": Utils.formatCurrency(data.total_sales),
      "total-orders": data.total_orders,
      "total-products": data.total_products,
      "low-stock": data.low_stock,
      "total-requests": data.pending_requests || 0,
    }

    Object.entries(elements).forEach(([id, value]) => {
      const element = document.getElementById(id)
      if (element) {
        element.textContent = value
      }
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

  initializeSSE() {
    try {
      // Initialize SSE client for real-time updates
      this.sseClient = new SSEClient('api/realtime.php', {
        maxReconnectAttempts: 5,
        reconnectDelay: 2000,
        maxReconnectDelay: 30000
      })

      // Handle stats updates
      this.sseClient.on('stats_update', (data) => {
        this.handleRealTimeStatsUpdate(data)
      })

      // Handle connection events
      this.sseClient.on('connection', (status) => {
        if (status.status === 'connected') {
          console.log('Dashboard: Real-time connection established')
        } else if (status.status === 'error') {
          console.warn('Dashboard: Real-time connection error, falling back to periodic updates')
        }
      })

      // Handle heartbeat to ensure connection is alive
      this.sseClient.on('heartbeat', (data) => {
        console.log('Dashboard: Heartbeat received', new Date(data.timestamp * 1000))
      })

    } catch (error) {
      console.error('Dashboard: Failed to initialize SSE client:', error)
      this.toast.warning('Real-time updates unavailable, using periodic updates')
    }
  }

  handleRealTimeStatsUpdate(data) {
    try {
      // Check if data has actually changed to prevent unnecessary animations
      if (this.hasDataChanged(data)) {
        console.log('Dashboard: Received real-time stats update', data)
        
        // Update dashboard stats with real-time data
        const statsData = {
          total_sales: data.sales?.total_revenue || 0,
          total_orders: data.sales?.total_sales || 0,
          total_products: data.inventory?.total_products || 0,
          low_stock: data.inventory?.low_stock_count || 0,
          pending_requests: data.requests?.pending_requests || 0
        }
        
        this.updateStatsDisplay(statsData)
        this.lastStatsData = data
        
        // Show subtle notification for new requests
        if (data.requests?.pending_requests > (this.lastStatsData?.requests?.pending_requests || 0)) {
          this.toast.info('New customer request received!')
        }
      }
    } catch (error) {
      console.error('Dashboard: Error handling real-time stats update:', error)
    }
  }

  hasDataChanged(newData) {
    if (!this.lastStatsData) return true
    
    // Compare key stats to detect changes
    const oldStats = this.lastStatsData
    const newStats = newData
    
    return (
      oldStats.sales?.total_revenue !== newStats.sales?.total_revenue ||
      oldStats.sales?.total_sales !== newStats.sales?.total_sales ||
      oldStats.inventory?.total_products !== newStats.inventory?.total_products ||
      oldStats.inventory?.low_stock_count !== newStats.inventory?.low_stock_count ||
      oldStats.requests?.pending_requests !== newStats.requests?.pending_requests ||
      oldStats.notifications?.unread_notifications !== newStats.notifications?.unread_notifications
    )
  }

  destroy() {
    // Clean up SSE connection
    if (this.sseClient) {
      this.sseClient.close()
      this.sseClient = null
    }
    
    // Clean up charts
    Object.values(this.charts).forEach((chart) => {
      if (chart && typeof chart.destroy === "function") {
        chart.destroy()
      }
    })
    this.charts = {}
  }
}
