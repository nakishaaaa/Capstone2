// Point of Sale functionality
import { ApiClient } from "../core/api-client.js"
import { Utils } from "../utils/helpers.js"
import { CONFIG } from "../core/config.js"

export class POSModule {
  constructor(toastManager) {
    this.api = new ApiClient()
    this.toast = toastManager
    this.cart = []
    this.products = []
  }

  async loadProducts() {
    try {
      console.log("Loading products from database...")
      const result = await this.api.getAllProducts()

      if (result.success) {
        this.products = result.data
        this.displayProducts()
        console.log("Products loaded successfully:", this.products.length, "items")
      } else {
        console.error("Error loading products:", result.error)
        this.toast.error("Error loading products: " + result.error)
      }
    } catch (error) {
      console.error("Error loading products:", error)
      this.toast.error("Error connecting to database for products")
      this.products = []
      this.displayProducts()
    }
  }

  displayProducts() {
    const productGrid = document.getElementById("productGrid")
    if (!productGrid) return

    if (this.products.length === 0) {
      productGrid.innerHTML = `
        <div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: #666;">
          No products available. Please add products to inventory.
        </div>
      `
      return
    }

    productGrid.innerHTML = ""

    this.products.forEach((product) => {
      const productCard = document.createElement("div")
      productCard.className = "product-card"

      if (product.stock <= 0) {
        productCard.classList.add("out-of-stock")
        productCard.style.opacity = "0.5"
        productCard.style.cursor = "not-allowed"
        productCard.onclick = () => this.toast.error("Product is out of stock!")
      } else {
        productCard.onclick = () => this.addToCart(product)
      }

      const stockStatus = product.stock <= 0 ? "OUT OF STOCK" : `Stock: ${product.stock}`
      const stockColor =
        product.stock <= 0
          ? "color: #dc3545; font-weight: bold;"
          : product.stock <= (product.min_stock || 10)
            ? "color: #ffc107; font-weight: bold;"
            : "color: #28a745;"

      productCard.innerHTML = `
        <img src="${product.image_url || "images/placeholder.jpg"}" 
             alt="${Utils.escapeHtml(product.name)}" 
             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0zNSA0MEg2NVY2MEgzNVY0MFoiIGZpbGw9IiM5Q0EzQUYiLz4KPC9zdmc+'">
        <h4>${Utils.escapeHtml(product.name)}</h4>
        <div class="price">${Utils.formatCurrency(product.price)}</div>
        <div class="stock" style="${stockColor}">${stockStatus}</div>
      `

      productGrid.appendChild(productCard)
    })
  }

  addToCart(product) {
    if (product.stock <= 0) {
      this.toast.error("Product is out of stock!")
      return
    }

    const existingItem = this.cart.find((item) => item.id === product.id)

    if (existingItem) {
      const totalQuantityInCart = existingItem.quantity + 1

      if (totalQuantityInCart > product.stock) {
        this.toast.error(`Cannot add more! Only ${product.stock} items available in stock.`)
        return
      }
      existingItem.quantity++
    } else {
      this.cart.push({
        id: product.id,
        name: product.name,
        price: Number.parseFloat(product.price),
        quantity: 1,
        stock: product.stock,
      })
    }

    this.updateCartDisplay()
    this.updateCartTotals()
    this.toast.success(`${product.name} added to cart`)
  }

  updateCartDisplay() {
    const cartItems = document.getElementById("cartItems")
    if (!cartItems) return

    if (this.cart.length === 0) {
      cartItems.innerHTML = '<div style="text-align: center; padding: 2rem; color: #666;">No items in cart</div>'
      return
    }

    cartItems.innerHTML = ""

    this.cart.forEach((item, index) => {
      const cartItem = document.createElement("div")
      cartItem.className = `cart-item ${item.removed ? 'cart-item-removed' : ''}`

      const isRemoved = item.removed
      const opacity = isRemoved ? '0.5' : '1'
      const pointerEvents = isRemoved ? 'none' : 'auto'

      cartItem.innerHTML = `
        <div class="cart-item-info" style="opacity: ${opacity};">
          <div class="cart-item-name">${Utils.escapeHtml(item.name)}</div>
          <div class="cart-item-price">${Utils.formatCurrency(item.price)} each</div>
        </div>
        <div class="quantity-controls" style="opacity: ${opacity}; pointer-events: ${pointerEvents};">
          <button class="quantity-btn" onclick="window.posModule.updateQuantity(${index}, -1)" ${isRemoved ? 'disabled' : ''}>-</button>
          <input type="number" class="quantity-input" value="${item.quantity}" 
                 onchange="window.posModule.setQuantity(${index}, this.value)" 
                 min="1" max="${item.stock}" ${isRemoved ? 'disabled' : ''}>
          <button class="quantity-btn" onclick="window.posModule.updateQuantity(${index}, 1)" ${isRemoved ? 'disabled' : ''}>+</button>
        </div>
        <div class="item-total" style="opacity: ${opacity};">${isRemoved ? 'Removed' : Utils.formatCurrency(item.price * item.quantity)}</div>
        <button class="action-btn" onclick="window.posModule.${isRemoved ? 'restoreToCart' : 'removeFromCart'}(${index})" 
                style="background: ${isRemoved ? '#28a745' : '#dc3545'}; color: white;">
          <i class="fas fa-${isRemoved ? 'undo' : 'trash'}"></i>
        </button>
      `

      cartItems.appendChild(cartItem)
    })
  }

  updateQuantity(index, change) {
    const item = this.cart[index]
    
    // Skip if item is removed
    if (item.removed) return
    
    const newQuantity = item.quantity + change

    if (newQuantity <= 0) {
      this.removeFromCart(index)
      return
    }

    if (newQuantity > item.stock) {
      this.toast.error(`Cannot add more! Only ${item.stock} items available in stock.`)
      return
    }

    item.quantity = newQuantity
    this.updateCartDisplay()
    this.updateCartTotals()
  }

  setQuantity(index, quantity) {
    const item = this.cart[index]
    
    // Skip if item is removed
    if (item.removed) return
    
    const newQuantity = Number.parseInt(quantity)

    if (newQuantity <= 0) {
      this.toast.error("Quantity must be greater than 0")
      this.updateCartDisplay()
      return
    }

    if (newQuantity > item.stock) {
      this.toast.error(`Cannot set quantity to ${newQuantity}! Only ${item.stock} items available in stock.`)
      this.updateCartDisplay()
      return
    }

    item.quantity = newQuantity
    this.updateCartDisplay()
    this.updateCartTotals()
  }

  removeFromCart(index) {
    const item = this.cart[index]
    item.removed = true
    this.updateCartDisplay()
    this.updateCartTotals()
    this.toast.info(`${item.name} removed from cart (greyed out)`)
  }

  restoreToCart(index) {
    const item = this.cart[index]
    item.removed = false
    this.updateCartDisplay()
    this.updateCartTotals()
    this.toast.success(`${item.name} restored to cart`)
  }

  updateCartTotals() {
    // Only include non-removed items in totals
    const subtotal = this.cart
      .filter(item => !item.removed)
      .reduce((sum, item) => sum + item.price * item.quantity, 0)
    const tax = subtotal * CONFIG.TAX_RATE
    const total = subtotal + tax

    const elements = {
      cartSubtotal: Utils.formatCurrency(subtotal),
      cartTax: Utils.formatCurrency(tax),
      cartTotal: Utils.formatCurrency(total),
    }

    Object.entries(elements).forEach(([id, value]) => {
      const element = document.getElementById(id)
      if (element) element.textContent = value
    })

    this.calculateChange()
  }

  calculateChange() {
    const cartTotalEl = document.getElementById("cartTotal")
    const amountReceivedEl = document.getElementById("amountReceived")
    const changeAmountEl = document.getElementById("changeAmount")

    if (!cartTotalEl || !amountReceivedEl || !changeAmountEl) return

    const total = Number.parseFloat(cartTotalEl.textContent.replace(CONFIG.CURRENCY, "").replace(",", ""))
    const amountReceived = Number.parseFloat(amountReceivedEl.value) || 0
    const change = amountReceived - total

    changeAmountEl.textContent = Utils.formatCurrency(Math.max(0, change))
  }

  async processPayment() {
    if (this.cart.length === 0) {
      this.toast.error("Cart is empty!")
      return
    }

    // Final stock validation
    for (const cartItem of this.cart) {
      const currentProduct = this.products.find((p) => p.id === cartItem.id)
      if (!currentProduct) {
        this.toast.error(`Product ${cartItem.name} no longer exists!`)
        return
      }

      if (currentProduct.stock < cartItem.quantity) {
        this.toast.error(
          `Insufficient stock for ${cartItem.name}! Available: ${currentProduct.stock}, Requested: ${cartItem.quantity}`,
        )
        await this.loadProducts()
        return
      }
    }

    const total = Number.parseFloat(
      document.getElementById("cartTotal").textContent.replace(CONFIG.CURRENCY, "").replace(",", ""),
    )
    const amountReceived = Number.parseFloat(document.getElementById("amountReceived").value) || 0
    const paymentMethod = document.getElementById('posPaymentMethod').value;

    if (amountReceived < total) {
      this.toast.error("Insufficient payment amount!")
      return
    }

    const subtotal = this.cart.reduce((sum, item) => sum + item.price * item.quantity, 0)
    const tax = subtotal * CONFIG.TAX_RATE

    const transactionData = {
      transaction_id: Utils.generateTransactionId(),
      total_amount: total,
      tax_amount: tax,
      payment_method: paymentMethod,
      amount_received: amountReceived,
      change_amount: Math.max(0, amountReceived - total),
      items: this.cart,
    }

    try {
      this.toast.info("Processing payment...")
      const result = await this.api.processSale(transactionData)

      if (result.success) {
        this.toast.success("Transaction completed successfully!")
        this.printReceipt(transactionData.transaction_id, transactionData)
        this.clearTransaction()
        await this.loadProducts() // Refresh products to update stock
        // Immediately refresh notifications (badge + list if open)
        try {
          if (window.notificationsModule && typeof window.notificationsModule.loadNotifications === 'function') {
            window.notificationsModule.loadNotifications()
          }
        } catch (e) {
          console.warn('POS: Failed to refresh notifications after sale', e)
        }
      } else {
        this.toast.error("Error processing transaction: " + result.error)
        await this.loadProducts()
      }
    } catch (error) {
      console.error("Error processing payment:", error)
      this.toast.error("Error processing payment: " + error.message)
      await this.loadProducts()
    }
  }

  clearTransaction() {
    this.cart = []
    this.updateCartDisplay()
    this.updateCartTotals()

    const amountReceivedEl = document.getElementById("amountReceived")
    const paymentMethodEl = document.getElementById("paymentMethod")

    if (amountReceivedEl) amountReceivedEl.value = ""
    if (paymentMethodEl) paymentMethodEl.value = "cash"
  }

  printReceipt(transactionId, transactionData) {
    const receiptWindow = window.open("", "_blank")
    
    // Check if popup was blocked
    if (!receiptWindow) {
      this.showReceiptModal(transactionId, transactionData)
      return
    }
    
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
          <p>Date: ${new Date().toLocaleString(CONFIG.LOCALE)}</p>
        </div>
        <div class="items">
          ${transactionData.items
            .map(
              (item) => `
            <div class="item">
              <span>${Utils.escapeHtml(item.name)} x${item.quantity}</span>
              <span>${Utils.formatCurrency(item.price * item.quantity)}</span>
            </div>
          `,
            )
            .join("")}
        </div>
        <div class="total">
          <div class="item">
            <span>Subtotal:</span>
            <span>${Utils.formatCurrency(transactionData.total_amount - transactionData.tax_amount)}</span>
          </div>
          <div class="item">
            <span>VAT (12%):</span>
            <span>${Utils.formatCurrency(transactionData.tax_amount)}</span>
          </div>
          <div class="item">
            <strong>Total: ${Utils.formatCurrency(transactionData.total_amount)}</strong>
          </div>
          <div class="item">
            <span>Payment (${transactionData.payment_method}):</span>
            <span>${Utils.formatCurrency(transactionData.amount_received)}</span>
          </div>
          <div class="item">
            <span>Change:</span>
            <span>${Utils.formatCurrency(transactionData.change_amount)}</span>
          </div>
        </div>
        <div class="footer">
          <p>Thank you for your business!</p>
        </div>
      </body>
      </html>
    `

    try {
      receiptWindow.document.write(receiptHTML)
      receiptWindow.document.close()
      
      // Wait a moment for the content to load before printing
      setTimeout(() => {
        receiptWindow.print()
        // Close the window after printing (optional)
        setTimeout(() => {
          receiptWindow.close()
        }, 1000)
      }, 100)
    } catch (error) {
      console.error("Error printing receipt:", error)
      this.toast.error("Error printing receipt. Showing in modal instead.")
      if (receiptWindow) {
        receiptWindow.close()
      }
      this.showReceiptModal(transactionId, transactionData)
    }
  }

  showReceiptModal(transactionId, transactionData) {
    const receiptHTML = `
      <div style="font-family: monospace; max-width: 400px; margin: 0 auto;">
        <div style="text-align: center; margin-bottom: 20px;">
          <h2>053 PRINTS</h2>
          <h3>RECEIPT</h3>
          <p>Transaction ID: ${transactionId}</p>
          <p>Date: ${new Date().toLocaleString()}</p>
        </div>
        <div style="margin-bottom: 20px;">
          ${transactionData.items.map(item => `
            <div style="display: flex; justify-content: space-between; margin: 5px 0;">
              <span>${item.name} x${item.quantity}</span>
              <span>₱${(item.price * item.quantity).toFixed(2)}</span>
            </div>
          `).join('')}
        </div>
        <div style="border-top: 1px solid #000; padding-top: 10px;">
          <div style="display: flex; justify-content: space-between; margin: 5px 0;">
            <span>Subtotal:</span>
            <span>₱${(transactionData.total_amount - transactionData.tax_amount).toFixed(2)}</span>
          </div>
          <div style="display: flex; justify-content: space-between; margin: 5px 0;">
            <span>VAT (12%):</span>
            <span>₱${transactionData.tax_amount.toFixed(2)}</span>
          </div>
          <div style="display: flex; justify-content: space-between; margin: 5px 0;">
            <span><strong>Total:</strong></span>
            <span><strong>₱${transactionData.total_amount.toFixed(2)}</strong></span>
          </div>
          <div style="display: flex; justify-content: space-between; margin: 5px 0;">
            <span>Payment (${transactionData.payment_method}):</span>
            <span>₱${transactionData.amount_received.toFixed(2)}</span>
          </div>
          <div style="display: flex; justify-content: space-between; margin: 5px 0;">
            <span>Change:</span>
            <span>₱${transactionData.change_amount.toFixed(2)}</span>
          </div>
        </div>
        <div style="text-align: center; margin-top: 20px;">
          <p>Thank you for your business!</p>
        </div>
        <div style="text-align: center; margin-top: 20px;">
          <button onclick="window.print()" class="btn btn-primary" style="margin-right: 10px;">Print Receipt</button>
          <button onclick="document.getElementById('receiptModal').style.display='none'" class="btn btn-secondary">Close</button>
        </div>
      </div>
    `

    // Create modal if it doesn't exist
    let modal = document.getElementById('receiptModal')
    if (!modal) {
      modal = document.createElement('div')
      modal.id = 'receiptModal'
      modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(0,0,0,0.5); display: flex; align-items: center; 
        justify-content: center; z-index: 10000;
      `
      document.body.appendChild(modal)
    }

    modal.innerHTML = `
      <div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; max-height: 80vh; overflow-y: auto;">
        ${receiptHTML}
      </div>
    `
    modal.style.display = 'flex'
  }

  searchProducts() {
    const searchTerm = document.getElementById("productSearch").value.toLowerCase()
    const filteredProducts = this.products.filter(
      (product) =>
        product.name.toLowerCase().includes(searchTerm) || product.category.toLowerCase().includes(searchTerm),
    )

    this.displayFilteredProducts(filteredProducts)
  }

  displayFilteredProducts(filteredProducts) {
    const productGrid = document.getElementById("productGrid")
    if (!productGrid) return

    if (filteredProducts.length === 0) {
      productGrid.innerHTML = `
        <div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: #666;">
          No products found matching your search.
        </div>
      `
      return
    }

    productGrid.innerHTML = ""

    filteredProducts.forEach((product) => {
      const productCard = document.createElement("div")
      productCard.className = "product-card"

      if (product.stock > 0) {
        productCard.onclick = () => this.addToCart(product)
      } else {
        productCard.style.opacity = "0.5"
        productCard.style.cursor = "not-allowed"
      }

      productCard.innerHTML = `
        <img src="${product.image_url || "images/placeholder.jpg"}" 
             alt="${Utils.escapeHtml(product.name)}" 
             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0zNSA0MEg2NVY2MEgzNVY0MFoiIGZpbGw9IiM5Q0EzQUYiLz4KPC9zdmc+'">
        <h4>${Utils.escapeHtml(product.name)}</h4>
        <div class="price">${Utils.formatCurrency(product.price)}</div>
        <div class="stock">Stock: ${product.stock}</div>
      `

      productGrid.appendChild(productCard)
    })
  }
}
