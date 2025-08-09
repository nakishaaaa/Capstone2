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
