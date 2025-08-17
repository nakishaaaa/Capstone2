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
    this.currentPeriod = 'daily'
    this.lastChartData = null
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
        
        // Add attention-grabbing effects for requests and low stock when value > 0
        const statCard = element.closest('.stat-card')
        if (statCard) {
          // Remove existing attention classes
          statCard.classList.remove('needs-attention', 'low-stock-attention')
          
          // Add attention classes based on values
          if (id === 'total-requests' && parseInt(value) > 0) {
            statCard.classList.add('needs-attention')
            console.log('Added attention to requests card:', value)
          } else if (id === 'low-stock' && parseInt(value) > 0) {
            statCard.classList.add('low-stock-attention', 'needs-attention')
            console.log('Added attention to low stock card:', value)
          }
        }
      }
    })
  }

  initializeCharts() {
    this.setupPeriodControls()
    this.loadChartData(this.currentPeriod)
  }

  setupPeriodControls() {
    // Add event listeners to existing period selector buttons
    const periodSelector = document.getElementById('chart-period-selector')
    if (periodSelector) {
      // Add event listeners
      periodSelector.addEventListener('click', (e) => {
        if (e.target.classList.contains('period-btn')) {
          const period = e.target.dataset.period
          this.changePeriod(period)
        }
      })
    }
  }

  changePeriod(period) {
    if (period === this.currentPeriod) return
    
    // Update active button
    document.querySelectorAll('.period-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.period === period)
    })
    
    this.currentPeriod = period
    this.loadChartData(period)
  }

  async loadChartData(period = 'daily') {
    try {
      console.log(`Loading chart data for period: ${period}`)
      
      const response = await fetch(`api/sales.php?chart=1&period=${period}`, {
        method: 'GET',
        credentials: 'include'
      })
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }
      
      const result = await response.json()
      
      if (result.success) {
        this.updateCharts(result.data)
        this.lastChartData = result.data
        console.log('Chart data loaded successfully:', result.data)
      } else {
        throw new Error(result.error || 'Failed to load chart data')
      }
    } catch (error) {
      console.error('Error loading chart data:', error)
      this.toast.error('Failed to load chart data: ' + error.message)
      // Use fallback data
      this.updateCharts(this.getFallbackChartData(period))
    }
  }

  updateCharts(data) {
    this.initSalesChart(data.sales_chart)
    this.initTopProductsChart(data.products_chart)
  }

  initSalesChart(chartData) {
    const ctx = document.getElementById("salesOverviewChart")
    if (!ctx || typeof Chart === "undefined") return
    
    // Destroy existing chart
    if (this.charts.sales) {
      this.charts.sales.destroy()
    }
    
    this.charts.sales = new Chart(ctx, {
      type: "line",
      data: {
        labels: chartData?.labels || [],
        datasets: [
          {
            label: "Sales (₱)",
            data: chartData?.data || [],
            borderColor: CONFIG.CHART_COLORS.primary,
            backgroundColor: `${CONFIG.CHART_COLORS.primary}1A`,
            tension: 0.4,
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (context) => `Sales: ${Utils.formatCurrency(context.parsed.y)}`
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: (value) => Utils.formatCurrency(value),
            },
          },
          x: {
            grid: {
              display: false
            }
          }
        },
        animation: {
          duration: 750,
          easing: 'easeInOutQuart'
        }
      },
    })
  }

  initTopProductsChart(chartData) {
    const ctx = document.getElementById("topProductsChart")
    if (!ctx || typeof Chart === "undefined") return
    
    // Destroy existing chart
    if (this.charts.topProducts) {
      this.charts.topProducts.destroy()
    }
    
    this.charts.topProducts = new Chart(ctx, {
      type: "doughnut",
      data: {
        labels: chartData?.labels || ["No Data"],
        datasets: [
          {
            data: chartData?.data || [0],
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
        maintainAspectRatio: false,
        plugins: {
          legend: { 
            position: "bottom",
            labels: {
              padding: 20,
              usePointStyle: true
            }
          },
          tooltip: {
            callbacks: {
              label: (context) => {
                const label = context.label || ''
                const value = context.parsed || 0
                return `${label}: ${value} sold`
              }
            }
          }
        },
        animation: {
          duration: 750,
          easing: 'easeInOutQuart'
        }
      },
    })
  }

  getFallbackChartData(period) {
    const fallbackData = {
      daily: {
        sales_chart: {
          labels: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
          data: [0, 0, 0, 0, 0, 0, 0]
        },
        products_chart: {
          labels: ["No Sales Data"],
          data: [0]
        }
      },
      weekly: {
        sales_chart: {
          labels: ["Week 1", "Week 2", "Week 3", "Week 4"],
          data: [0, 0, 0, 0]
        },
        products_chart: {
          labels: ["No Sales Data"],
          data: [0]
        }
      },
      monthly: {
        sales_chart: {
          labels: ["6 months ago", "5 months ago", "4 months ago", "3 months ago", "2 months ago", "Last month"],
          data: [0, 0, 0, 0, 0, 0]
        },
        products_chart: {
          labels: ["No Sales Data"],
          data: [0]
        }
      },
      annually: {
        sales_chart: {
          labels: ["2 years ago", "Last year", "This year"],
          data: [0, 0, 0]
        },
        products_chart: {
          labels: ["No Sales Data"],
          data: [0]
        }
      }
    }
    
    return fallbackData[period] || fallbackData.daily
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
        
        // Check if sales data changed - if so, refresh charts
        const salesChanged = this.lastStatsData?.sales?.total_revenue !== data.sales?.total_revenue ||
                            this.lastStatsData?.sales?.total_sales !== data.sales?.total_sales
        
        if (salesChanged) {
          console.log('Dashboard: Sales data changed, refreshing charts...')
          this.loadChartData(this.currentPeriod)
        }
        
        // Show subtle notification for new requests (only if we have previous data to compare)
        if (this.lastStatsData && data.requests?.pending_requests > (this.lastStatsData?.requests?.pending_requests || 0)) {
          console.log('Dashboard: Request count increased, but NOT showing toast for support messages')
          // Don't show toast - this could be triggered by support messages affecting stats
        }
        
        this.lastStatsData = data
        
        // Show notification for new sales
        if (salesChanged && data.sales?.total_revenue > 0) {
          this.toast.success('New sale recorded! Charts updated.')
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
