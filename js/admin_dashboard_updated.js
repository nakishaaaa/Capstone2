import { Chart } from "@/components/ui/chart"
// Global variables
let currentSection = "dashboard"
let cart = []
let products = []
let notifications = []

// API Base URL
const API_BASE = "api/"

// Initialize dashboard
document.addEventListener("DOMContentLoaded", () => {
  initializeDashboard()
  setupEventListeners()
  updateDateTime()
  setInterval(updateDateTime, 1000)

  // Handle initial hash or show dashboard
  handleInitialHash()

  // Listen for hash changes
  window.addEventListener("hashchange", handleHashChange)
})

// Handle initial hash
function handleInitialHash() {
  const hash = window.location.hash.substring(1)
  if (hash && document.getElementById(hash)) {
    showSection(hash)
  } else {
    showSection("dashboard")
    updateURL("dashboard")
  }
}

// Handle hash changes
function handleHashChange() {
  const hash = window.location.hash.substring(1)
  if (hash && document.getElementById(hash)) {
    showSection(hash, false)
  }
}

// Update URL hash
function updateURL(sectionId) {
  if (window.location.hash !== "#" + sectionId) {
    window.history.pushState(null, null, "#" + sectionId)
  }
}

// Initialize dashboard
async function initializeDashboard() {
  await loadProducts()
  await loadNotifications()
  await loadDashboardStats()
  initializeCharts()
}

// Setup event listeners
function setupEventListeners() {
  // Navigation
  document.querySelectorAll(".nav-link").forEach((link) => {
    link.addEventListener("click", function (e) {
      if (!this.classList.contains("logout")) {
        e.preventDefault()
        const section = this.getAttribute("data-section")
        if (section) {
          showSection(section, true)
        }
      }
    })
  })

  // POS events
  const amountReceived = document.getElementById("amountReceived")
  if (amountReceived) {
    amountReceived.addEventListener("input", calculateChange)
  }

  // Search events
  const productSearch = document.getElementById("productSearch")
  if (productSearch) {
    productSearch.addEventListener("keyup", searchProducts)
  }

  // Modal events
  const modalOverlay = document.getElementById("modalOverlay")
  if (modalOverlay) {
    modalOverlay.addEventListener("click", function (e) {
      if (e.target === this) {
        closeModal()
      }
    })
  }
}

// Show section
function showSection(sectionId, updateURL = true) {
  // Hide all sections
  document.querySelectorAll(".content-section").forEach((section) => {
    section.classList.remove("active")
  })

  // Remove active class from nav links
  document.querySelectorAll(".nav-link").forEach((link) => {
    link.classList.remove("active")
  })

  // Show selected section
  const section = document.getElementById(sectionId)
  if (section) {
    section.classList.add("active")
    currentSection = sectionId

    // Add active class to nav link
    const navLink = document.querySelector(`[data-section="${sectionId}"]`)
    if (navLink) {
      navLink.classList.add("active")
    }

    // Update URL hash if requested
    if (updateURL) {
      window.history.pushState(null, null, "#" + sectionId)
    }

    // Load section-specific data
    loadSectionData(sectionId)
  }
}

// Load section-specific data
async function loadSectionData(sectionId) {
  switch (sectionId) {
    case "dashboard":
      await loadDashboardStats()
      break
    case "inventory":
      await loadInventoryData()
      break
    case "sales-analytics":
      loadSalesAnalytics()
      break
    case "sales-report":
      break
    case "pos":
      await loadPOSData()
      break
    case "sales-management":
      await loadSalesManagement()
      break
    case "notifications":
      await displayNotifications()
      break
  }
}

// Update date and time
function updateDateTime() {
  const now = new Date()
  const options = {
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  }
  const dateTimeElement = document.getElementById("current-datetime")
  if (dateTimeElement) {
    dateTimeElement.textContent = now.toLocaleDateString("en-PH", options)
  }
}

// Load dashboard stats from API
async function loadDashboardStats() {
  try {
    const response = await fetch(`${API_BASE}sales.php?stats=1`)
    const result = await response.json()

    if (result.success) {
      updateDashboardStats(result.data)
    } else {
      console.error("Error loading dashboard stats:", result.error)
    }
  } catch (error) {
    console.error("Error loading dashboard stats:", error)
  }
}

// Update dashboard stats
function updateDashboardStats(data) {
  const totalSalesEl = document.getElementById("total-sales")
  const totalOrdersEl = document.getElementById("total-orders")
  const totalProductsEl = document.getElementById("total-products")
  const lowStockEl = document.getElementById("low-stock")

  if (totalSalesEl)
    totalSalesEl.textContent = `₱${data.total_sales.toLocaleString("en-PH", { minimumFractionDigits: 2 })}`
  if (totalOrdersEl) totalOrdersEl.textContent = data.total_orders
  if (totalProductsEl) totalProductsEl.textContent = data.total_products
  if (lowStockEl) lowStockEl.textContent = data.low_stock
}

// Load products from API
async function loadProducts() {
  try {
    const response = await fetch(`${API_BASE}inventory.php`)
    const result = await response.json()

    if (result.success) {
      products = result.data
      displayProducts()
    } else {
      console.error("Error loading products:", result.error)
    }
  } catch (error) {
    console.error("Error loading products:", error)
  }
}

// Display products in POS
function displayProducts() {
  const productGrid = document.getElementById("productGrid")
  if (!productGrid) return

  productGrid.innerHTML = ""

  products.forEach((product) => {
    const productCard = document.createElement("div")
    productCard.className = "product-card"
    productCard.onclick = () => addToCart(product)

    productCard.innerHTML = `
            <img src="${product.image_url || "images/placeholder.jpg"}" alt="${product.name}" onerror="this.src='images/placeholder.jpg'">
            <h4>${product.name}</h4>
            <div class="price">₱${Number.parseFloat(product.price).toFixed(2)}</div>
            <div class="stock">Stock: ${product.stock}</div>
        `

    productGrid.appendChild(productCard)
  })
}

// Add to cart
function addToCart(product) {
  const existingItem = cart.find((item) => item.id === product.id)

  if (existingItem) {
    if (existingItem.quantity < product.stock) {
      existingItem.quantity++
    } else {
      showNotification("Insufficient stock!", "error")
      return
    }
  } else {
    cart.push({
      id: product.id,
      name: product.name,
      price: Number.parseFloat(product.price),
      quantity: 1,
      stock: product.stock,
    })
  }

  updateCartDisplay()
  updateCartTotals()
}

// Update cart display
function updateCartDisplay() {
  const cartItems = document.getElementById("cartItems")
  if (!cartItems) return

  cartItems.innerHTML = ""

  cart.forEach((item, index) => {
    const cartItem = document.createElement("div")
    cartItem.className = "cart-item"

    cartItem.innerHTML = `
            <div class="cart-item-info">
                <div class="cart-item-name">${item.name}</div>
                <div class="cart-item-price">₱${item.price.toFixed(2)} each</div>
            </div>
            <div class="quantity-controls">
                <button class="quantity-btn" onclick="updateQuantity(${index}, -1)">-</button>
                <input type="number" class="quantity-input" value="${item.quantity}" 
                       onchange="setQuantity(${index}, this.value)" min="1" max="${item.stock}">
                <button class="quantity-btn" onclick="updateQuantity(${index}, 1)">+</button>
            </div>
            <div class="item-total">₱${(item.price * item.quantity).toFixed(2)}</div>
            <button class="action-btn delete" onclick="removeFromCart(${index})">
                <i class="fas fa-trash"></i>
            </button>
        `

    cartItems.appendChild(cartItem)
  })
}

// Update quantity
function updateQuantity(index, change) {
  const item = cart[index]
  const newQuantity = item.quantity + change

  if (newQuantity > 0 && newQuantity <= item.stock) {
    item.quantity = newQuantity
    updateCartDisplay()
    updateCartTotals()
  }
}

// Set quantity
function setQuantity(index, quantity) {
  const item = cart[index]
  const newQuantity = Number.parseInt(quantity)

  if (newQuantity > 0 && newQuantity <= item.stock) {
    item.quantity = newQuantity
    updateCartDisplay()
    updateCartTotals()
  }
}

// Remove from cart
function removeFromCart(index) {
  cart.splice(index, 1)
  updateCartDisplay()
  updateCartTotals()
}

// Update cart totals
function updateCartTotals() {
  const subtotal = cart.reduce((sum, item) => sum + item.price * item.quantity, 0)
  const tax = subtotal * 0.12 // 12% VAT
  const total = subtotal + tax

  const cartSubtotal = document.getElementById("cartSubtotal")
  const cartTax = document.getElementById("cartTax")
  const cartTotal = document.getElementById("cartTotal")

  if (cartSubtotal) cartSubtotal.textContent = `₱${subtotal.toFixed(2)}`
  if (cartTax) cartTax.textContent = `₱${tax.toFixed(2)}`
  if (cartTotal) cartTotal.textContent = `₱${total.toFixed(2)}`

  calculateChange()
}

// Calculate change
function calculateChange() {
  const cartTotalEl = document.getElementById("cartTotal")
  const amountReceivedEl = document.getElementById("amountReceived")
  const changeAmountEl = document.getElementById("changeAmount")

  if (!cartTotalEl || !amountReceivedEl || !changeAmountEl) return

  const total = Number.parseFloat(cartTotalEl.textContent.replace("₱", "").replace(",", ""))
  const amountReceived = Number.parseFloat(amountReceivedEl.value) || 0
  const change = amountReceived - total

  changeAmountEl.textContent = `₱${Math.max(0, change).toFixed(2)}`
}

// Process payment
async function processPayment() {
  if (cart.length === 0) {
    showNotification("Cart is empty!", "error")
    return
  }

  const total = Number.parseFloat(document.getElementById("cartTotal").textContent.replace("₱", "").replace(",", ""))
  const amountReceived = Number.parseFloat(document.getElementById("amountReceived").value) || 0
  const paymentMethod = document.getElementById("paymentMethod").value

  if (paymentMethod === "cash" && amountReceived < total) {
    showNotification("Insufficient payment amount!", "error")
    return
  }

  const subtotal = cart.reduce((sum, item) => sum + item.price * item.quantity, 0)
  const tax = subtotal * 0.12

  const transactionData = {
    transaction_id: "TXN-" + Date.now(),
    total_amount: total,
    tax_amount: tax,
    payment_method: paymentMethod,
    amount_received: amountReceived,
    change_amount: Math.max(0, amountReceived - total),
    items: cart,
  }

  try {
    const response = await fetch(`${API_BASE}sales.php`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(transactionData),
    })

    const result = await response.json()

    if (result.success) {
      showNotification("Transaction completed successfully!", "success")
      printReceipt(transactionData.transaction_id, transactionData)
      clearTransaction()
      await loadDashboardStats()
      await loadProducts() // Refresh products to update stock
    } else {
      showNotification("Error processing transaction: " + result.error, "error")
    }
  } catch (error) {
    showNotification("Error processing transaction: " + error.message, "error")
  }
}

// Clear transaction
function clearTransaction() {
  cart = []
  updateCartDisplay()
  updateCartTotals()
  const amountReceivedEl = document.getElementById("amountReceived")
  const paymentMethodEl = document.getElementById("paymentMethod")

  if (amountReceivedEl) amountReceivedEl.value = ""
  if (paymentMethodEl) paymentMethodEl.value = "cash"
}

// Load inventory data
async function loadInventoryData() {
  try {
    const response = await fetch(`${API_BASE}inventory.php`)
    const result = await response.json()

    if (result.success) {
      displayInventoryTable(result.data)
    } else {
      console.error("Error loading inventory:", result.error)
    }
  } catch (error) {
    console.error("Error loading inventory:", error)
  }
}

// Display inventory table
function displayInventoryTable(data) {
  const tableBody = document.getElementById("inventoryTableBody")
  if (!tableBody) return

  tableBody.innerHTML = ""

  data.forEach((item) => {
    const row = document.createElement("tr")
    const statusClass = item.stock_status.toLowerCase().replace(" ", "-")

    row.innerHTML = `
            <td>${item.id}</td>
            <td>${item.name}</td>
            <td>${item.category}</td>
            <td>${item.stock}</td>
            <td>${item.min_stock}</td>
            <td>₱${Number.parseFloat(item.price).toFixed(2)}</td>
            <td><span class="status-badge status-${statusClass}">${item.stock_status}</span></td>
            <td>
                <button class="action-btn edit" onclick="editProduct(${item.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="action-btn view" onclick="viewProduct(${item.id})">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        `

    tableBody.appendChild(row)
  })
}

// Load notifications
async function loadNotifications() {
  try {
    const response = await fetch(`${API_BASE}notifications.php`)
    const result = await response.json()

    if (result.success) {
      notifications = result.data
    } else {
      console.error("Error loading notifications:", result.error)
    }
  } catch (error) {
    console.error("Error loading notifications:", error)
  }
}

// Display notifications
async function displayNotifications() {
  await loadNotifications()

  const container = document.getElementById("notificationsContainer")
  if (!container) return

  container.innerHTML = ""

  notifications.forEach((notification) => {
    const notificationItem = document.createElement("div")
    notificationItem.className = `notification-item ${!notification.is_read ? "unread" : ""}`

    notificationItem.innerHTML = `
            <div class="notification-icon">
                <i class="fas fa-${getNotificationIcon(notification.type)}"></i>
            </div>
            <div class="notification-content">
                <div class="notification-title">${notification.title}</div>
                <div class="notification-message">${notification.message}</div>
                <div class="notification-time">${formatTime(notification.created_at)}</div>
            </div>
            <button class="action-btn delete" onclick="dismissNotification(${notification.id})">
                <i class="fas fa-times"></i>
            </button>
        `

    container.appendChild(notificationItem)
  })
}

// Get notification icon
function getNotificationIcon(type) {
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

// Format time
function formatTime(timestamp) {
  const date = new Date(timestamp)
  const now = new Date()
  const diff = now - date

  if (diff < 60000) return "Just now"
  if (diff < 3600000) return `${Math.floor(diff / 60000)} minutes ago`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)} hours ago`
  return date.toLocaleDateString("en-PH")
}

// Dismiss notification
async function dismissNotification(id) {
  try {
    const response = await fetch(`${API_BASE}notifications.php?id=${id}`, {
      method: "DELETE",
    })

    const result = await response.json()

    if (result.success) {
      notifications = notifications.filter((n) => n.id !== id)
      displayNotifications()
      showNotification("Notification dismissed", "info")
    }
  } catch (error) {
    console.error("Error dismissing notification:", error)
  }
}

// Show notification toast
function showNotification(message, type = "info") {
  document.querySelectorAll(".notification-toast").forEach((toast) => {
    toast.remove()
  })

  const notification = document.createElement("div")
  notification.className = `notification-toast notification-${type}`
  notification.innerHTML = `
        <i class="fas fa-${getNotificationIcon(type)}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; cursor: pointer; margin-left: auto;">
            <i class="fas fa-times"></i>
        </button>
    `

  document.body.appendChild(notification)

  setTimeout(() => {
    if (notification.parentElement) {
      notification.remove()
    }
  }, 5000)
}

// Initialize charts (keeping the existing chart code)
function initializeCharts() {
  // Sales Overview Chart
  const salesCtx = document.getElementById("salesOverviewChart")
  if (salesCtx) {
    new Chart(salesCtx, {
      type: "line",
      data: {
        labels: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
        datasets: [
          {
            label: "Sales (₱)",
            data: [1200, 1900, 3000, 5000, 2000, 3000, 4500],
            borderColor: "#667eea",
            backgroundColor: "rgba(102, 126, 234, 0.1)",
            tension: 0.4,
          },
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: (value) => "₱" + value.toLocaleString(),
            },
          },
        },
      },
    })
  }

  // Top Products Chart
  const topProductsCtx = document.getElementById("topProductsChart")
  if (topProductsCtx) {
    new Chart(topProductsCtx, {
      type: "doughnut",
      data: {
        labels: ["T-Shirts", "Mugs", "Stickers", "Banners", "Others"],
        datasets: [
          {
            data: [30, 25, 20, 15, 10],
            backgroundColor: ["#667eea", "#764ba2", "#f093fb", "#f5576c", "#4facfe"],
          },
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: "bottom",
          },
        },
      },
    })
  }
}

// Search products
function searchProducts() {
  const searchTerm = document.getElementById("productSearch").value.toLowerCase()
  const filteredProducts = products.filter(
    (product) => product.name.toLowerCase().includes(searchTerm) || product.category.toLowerCase().includes(searchTerm),
  )

  displayFilteredProducts(filteredProducts)
}

// Display filtered products
function displayFilteredProducts(filteredProducts) {
  const productGrid = document.getElementById("productGrid")
  if (!productGrid) return

  productGrid.innerHTML = ""

  filteredProducts.forEach((product) => {
    const productCard = document.createElement("div")
    productCard.className = "product-card"
    productCard.onclick = () => addToCart(product)

    productCard.innerHTML = `
            <img src="${product.image_url || "images/placeholder.jpg"}" alt="${product.name}" onerror="this.src='images/placeholder.jpg'">
            <h4>${product.name}</h4>
            <div class="price">₱${Number.parseFloat(product.price).toFixed(2)}</div>
            <div class="stock">Stock: ${product.stock}</div>
        `

    productGrid.appendChild(productCard)
  })
}

// Print receipt
function printReceipt(transactionId, transactionData) {
  const receiptWindow = window.open("", "_blank")
  const receiptHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Receipt</title>
            <style>
                body { font-family: monospace; width: 300px; margin: 0 auto; }
                .header { text-align: center; margin-bottom: 20px; }
                .item { display: flex; justify-content: space-between; margin: 5px 0; }
                .total { border-top: 1px solid #000; margin-top: 10px; padding-top: 10px; }
                .footer { text-align: center; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>RECEIPT</h2>
                <p>Transaction ID: ${transactionId}</p>
                <p>Date: ${new Date().toLocaleString("en-PH")}</p>
            </div>
            <div class="items">
                ${transactionData.items
                  .map(
                    (item) => `
                    <div class="item">
                        <span>${item.name} x${item.quantity}</span>
                        <span>₱${(item.price * item.quantity).toFixed(2)}</span>
                    </div>
                `,
                  )
                  .join("")}
            </div>
            <div class="total">
                <div class="item">
                    <span>Subtotal:</span>
                    <span>₱${(transactionData.total_amount - transactionData.tax_amount).toFixed(2)}</span>
                </div>
                <div class="item">
                    <span>Tax (12%):</span>
                    <span>₱${transactionData.tax_amount.toFixed(2)}</span>
                </div>
                <div class="item">
                    <strong>Total: ₱${transactionData.total_amount.toFixed(2)}</strong>
                </div>
                <div class="item">
                    <span>Payment (${transactionData.payment_method}):</span>
                    <span>₱${transactionData.amount_received.toFixed(2)}</span>
                </div>
                <div class="item">
                    <span>Change:</span>
                    <span>₱${transactionData.change_amount.toFixed(2)}</span>
                </div>
            </div>
            <div class="footer">
                <p>Thank you for your business!</p>
            </div>
        </body>
        </html>
    `

  receiptWindow.document.write(receiptHTML)
  receiptWindow.document.close()
  receiptWindow.print()
}

// Placeholder functions for other features
function newTransaction() {
  clearTransaction()
}
function holdTransaction() {
  showNotification("Transaction held", "info")
}
function refreshInventory() {
  loadInventoryData()
}
function loadSalesAnalytics() {
  showNotification("Sales analytics loaded", "info")
}
function loadPOSData() {
  displayProducts()
}
function loadSalesManagement() {
  loadInventoryData()
}
function editProduct(id) {
  showNotification(`Editing product ${id}`, "info")
}
function viewProduct(id) {
  showNotification(`Viewing product ${id}`, "info")
}

// Modal functions
function openModal(title, content) {
  const modal = document.getElementById("modalOverlay")
  const modalContent = document.getElementById("modalContent")

  if (modal && modalContent) {
    modalContent.innerHTML = `
            <div class="modal-header">
                <h3>${title}</h3>
                <button class="close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                ${content}
            </div>
        `

    modal.classList.add("active")
  }
}

function closeModal() {
  const modal = document.getElementById("modalOverlay")
  if (modal) {
    modal.classList.remove("active")
  }
}

// Add CSS for notification toasts
const notificationCSS = `
    .notification-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        padding: 1rem;
        border-radius: 5px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        z-index: 3000;
        min-width: 300px;
        animation: slideIn 0.3s ease;
    }
    
    .notification-toast.notification-success {
        border-left: 4px solid #28a745;
    }
    
    .notification-toast.notification-error {
        border-left: 4px solid #dc3545;
    }
    
    .notification-toast.notification-warning {
        border-left: 4px solid #ffc107;
    }
    
    .notification-toast.notification-info {
        border-left: 4px solid #17a2b8;
    }
    
    @keyframes slideIn {
        from { transform: translateX(100%); }
        to { transform: translateX(0); }
    }
`

const style = document.createElement("style")
style.textContent = notificationCSS
document.head.appendChild(style)
