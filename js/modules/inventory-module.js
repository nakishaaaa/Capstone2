// Inventory management functionality
import { ApiClient } from "../core/api-client.js"
import { Utils } from "../utils/helpers.js"
import { CONFIG } from "../core/config.js" // Declare CONFIG variable

export class InventoryModule {
  constructor(toastManager, modalManager) {
    this.api = new ApiClient()
    this.toast = toastManager
    this.modal = modalManager
    this.products = []
  }

  async loadInventoryData() {
    try {
      console.log("Loading inventory data from database...")
      const result = await this.api.getAllProducts()

      if (result.success) {
        this.displayInventoryTable(result.data)
        console.log("Inventory data loaded successfully:", result.data.length, "items")
      } else {
        this.toast.error("Error loading inventory: " + result.error)
        this.displayInventoryTable([])
      }
    } catch (error) {
      console.error("Error loading inventory:", error)
      this.toast.error("Error connecting to database for inventory")
      this.displayInventoryTable([])
    }
  }

  displayInventoryTable(data) {
    const tableBody = document.getElementById("inventoryTableBody")
    if (!tableBody) return

    if (!data || data.length === 0) {
      tableBody.innerHTML = `
        <tr>
          <td colspan="8" style="text-align: center; padding: 2rem;">
            No inventory data available. Please add products to inventory.
          </td>
        </tr>
      `
      return
    }

    tableBody.innerHTML = data
      .map(
        (item) => `
      <tr>
        <td>${item.id}</td>
        <td>${Utils.escapeHtml(item.name)}</td>
        <td>${Utils.escapeHtml(item.category)}</td>
        <td>${item.stock}</td>
        <td>${item.min_stock || 10}</td>
        <td>${Utils.formatCurrency(item.price)}</td>
        <td>
          <span class="status-badge ${Utils.getStockStatusClass(item.stock_status)}">
            ${item.stock_status || "In Stock"}
          </span>
        </td>
        <td>
          <button class="action-btn restock" 
                  onclick="window.inventoryModule.restockProduct(${item.id})" 
                  title="Restock Product">
            <i class="fas fa-plus"></i>
          </button>
          <button class="action-btn view" 
                  onclick="window.inventoryModule.viewProduct(${item.id})" 
                  title="View Details">
            <i class="fas fa-eye"></i>
          </button>
        </td>
      </tr>
    `,
      )
      .join("")
  }

  async restockProduct(id) {
    try {
      this.toast.info("Loading product for restocking...")
      const result = await this.api.getProduct(id)

      if (result.success) {
        this.showRestockModal(result.data)
      } else {
        this.toast.error("Error loading product: " + result.error)
      }
    } catch (error) {
      console.error("Error loading product for restocking:", error)
      this.toast.error("Error loading product for restocking: " + error.message)
    }
  }

  showRestockModal(product) {
    const content = `
      ${this.modal.createHeader('<i class="fas fa-plus"></i> Restock Product')}
      <div class="modal-body">
        <div class="product-info-section" style="margin-bottom: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
          <h4 style="margin-bottom: 1rem; color: #333;">
            <i class="fas fa-info-circle"></i> Product Information
          </h4>
          <div style="display: grid; grid-template-columns: auto 1fr; gap: 0.5rem 1rem;">
            <strong>Name:</strong> <span>${Utils.escapeHtml(product.name)}</span>
            <strong>Category:</strong> <span>${Utils.escapeHtml(product.category)}</span>
            <strong>Price:</strong> <span>${Utils.formatCurrency(product.price)}</span>
            <strong>Current Stock:</strong> 
            <span style="color: ${this.getStockColor(product.stock, product.min_stock)}; font-weight: bold;">
              ${product.stock} units
            </span>
            <strong>Min Stock:</strong> <span>${product.min_stock || 10} units</span>
          </div>
        </div>
        
        <form id="restockForm" onsubmit="window.inventoryModule.saveRestockChanges(event, ${product.id})">
          <div class="restock-section" style="padding: 1rem; background: #e8f5e8; border-radius: 8px; border: 2px solid #d4edda;">
            <h4 style="margin-bottom: 1rem; color: #155724;">
              <i class="fas fa-boxes"></i> Restock Options
            </h4>
            
            <div class="restock-options" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
              <div class="form-group">
                <label for="restockQuantity"><strong>Add Stock:</strong></label>
                <input type="number" id="restockQuantity" min="1" placeholder="Enter quantity to add" 
                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
                <small style="color: #666; font-size: 0.8rem;">Add to current stock</small>
              </div>
              <div class="form-group">
                <label for="setStockQuantity"><strong>Set Stock To:</strong></label>
                <input type="number" id="setStockQuantity" min="0" placeholder="Set exact stock amount"
                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
                <small style="color: #666; font-size: 0.8rem;">Override current stock</small>
              </div>
            </div>
            
            <div class="stock-preview" style="padding: 0.75rem; background: white; border-radius: 5px; border: 1px solid #c3e6cb;">
              <strong>New Stock Will Be: </strong>
              <span id="newStockAmount" style="font-size: 1.2rem; font-weight: bold;">${product.stock}</span>
              <span id="stockChange" style="margin-left: 0.5rem; color: #666;"></span>
            </div>
          </div>

          ${this.modal.createActions([
            {
              type: "submit",
              text: "Update Stock",
              icon: "fas fa-plus",
              className: "btn-success",
            },
            {
              text: "Cancel",
              icon: "fas fa-times",
              className: "btn-secondary",
              onclick: "window.modalManager.close()",
            },
          ])}
        </form>
      </div>
    `

    this.modal.open("Restock Product", content, { preventOutsideClick: true })
    this.setupRestockPreview(product.stock, product.min_stock)
  }

  setupRestockPreview(currentStock, minStock) {
    const restockInput = document.getElementById("restockQuantity")
    const setStockInput = document.getElementById("setStockQuantity")
    const newStockSpan = document.getElementById("newStockAmount")
    const stockChangeSpan = document.getElementById("stockChange")

    const updatePreview = () => {
      const restockQty = Number.parseInt(restockInput.value) || 0
      const setStockQty = Number.parseInt(setStockInput.value)

      let newStock, changeText
      if (setStockQty >= 0 && setStockInput.value !== "") {
        newStock = setStockQty
        changeText = `(${setStockQty >= currentStock ? "+" : ""}${setStockQty - currentStock})`
        restockInput.value = ""
      } else if (restockQty > 0) {
        newStock = currentStock + restockQty
        changeText = `(+${restockQty})`
        setStockInput.value = ""
      } else {
        newStock = currentStock
        changeText = ""
      }

      newStockSpan.textContent = newStock
      stockChangeSpan.textContent = changeText
      newStockSpan.style.color = this.getStockColor(newStock, minStock)
    }

    restockInput.addEventListener("input", updatePreview)
    setStockInput.addEventListener("input", updatePreview)
  }

  async saveRestockChanges(event, productId) {
    event.preventDefault()

    try {
      this.toast.info("Updating stock...")

      const restockQty = Number.parseInt(document.getElementById("restockQuantity").value) || 0
      const setStockQty = Number.parseInt(document.getElementById("setStockQuantity").value)

      let currentProduct = this.products.find((p) => p.id === productId) // Use let instead of const
      if (!currentProduct) {
        // Fetch current product data
        const result = await this.api.getProduct(productId)
        if (!result.success) {
          throw new Error("Product not found")
        }
        currentProduct = result.data
      }

      let newStock
      if (setStockQty >= 0 && document.getElementById("setStockQuantity").value !== "") {
        newStock = setStockQty
      } else if (restockQty > 0) {
        newStock = currentProduct.stock + restockQty
      } else {
        this.toast.error("Please enter a restock quantity or set stock amount")
        return
      }

      if (newStock < 0) {
        this.toast.error("Stock cannot be negative")
        return
      }

      const updateData = {
        name: currentProduct.name,
        category: currentProduct.category,
        price: currentProduct.price,
        stock: newStock,
        min_stock: currentProduct.min_stock,
        description: currentProduct.description,
        image_url: currentProduct.image_url,
      }

      const result = await this.api.updateProduct(productId, updateData)

      if (result.success) {
        this.toast.success("Stock updated successfully!")
        this.modal.close()
        await this.loadInventoryData()

        try {
          if (window.notificationsModule && typeof window.notificationsModule.loadNotifications === 'function') {
            window.notificationsModule.loadNotifications()
            const currentSection = window.adminDashboard?.navigation?.getCurrentSection?.()
            if (currentSection === 'notifications' && typeof window.notificationsModule.displayNotifications === 'function') {
              window.notificationsModule.displayNotifications()
            }
          }
        } catch (e) {
          console.warn('Inventory: Failed to refresh notifications after stock update', e)
        }

        const stockChange = newStock - currentProduct.stock
        const changeText = stockChange > 0 ? `+${stockChange}` : `${stockChange}`
        this.toast.info(`"${currentProduct.name}" stock: ${currentProduct.stock} → ${newStock} (${changeText})`)
      } else {
        this.toast.error("Error updating stock: " + result.error)
      }
    } catch (error) {
      console.error("Error updating stock:", error)
      this.toast.error("Error updating stock: " + error.message)
    }
  }

  async viewProduct(id) {
    try {
      this.toast.info("Loading product details...")
      const result = await this.api.getProduct(id)

      if (result.success) {
        this.showProductDetailsModal(result.data)
      } else {
        this.toast.error("Error loading product: " + result.error)
      }
    } catch (error) {
      console.error("Error viewing product:", error)
      this.toast.error("Error loading product details: " + error.message)
    }
  }

  showProductDetailsModal(product) {
    const content = `
      ${this.modal.createHeader('<i class="fas fa-eye"></i> Product Details')}
      <div class="modal-body">
        <div class="product-details">
          <div class="product-image-section">
            <img src="${product.image_url || "images/placeholder.jpg"}" 
                 alt="${Utils.escapeHtml(product.name)}" 
                 style="width: 100%; max-width: 200px; height: 200px; object-fit: cover; border-radius: 8px; margin-bottom: 1rem;"
                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik03MCA4MEgxMzBWMTIwSDcwVjgwWiIgZmlsbD0iIzlDQTNBRiIvPgo8L3N2Zz4='">
          </div>
          <div class="product-info">
            <div class="info-row"><strong>Product ID:</strong> ${product.id}</div>
            <div class="info-row"><strong>Name:</strong> ${Utils.escapeHtml(product.name)}</div>
            <div class="info-row"><strong>Category:</strong> ${Utils.escapeHtml(product.category)}</div>
            <div class="info-row"><strong>Price:</strong> ${Utils.formatCurrency(product.price)}</div>
            <div class="info-row">
              <strong>Current Stock:</strong> 
              <span style="color: ${this.getStockColor(product.stock, product.min_stock)}; font-weight: bold;">
                ${product.stock}
              </span>
            </div>
            <div class="info-row"><strong>Minimum Stock:</strong> ${product.min_stock || 10}</div>
            <div class="info-row">
              <strong>Status:</strong> 
              <span class="status-badge ${Utils.getStockStatusClass(product.stock_status)}">
                ${product.stock_status || "In Stock"}
              </span>
            </div>
            <div class="info-row"><strong>Description:</strong> ${Utils.escapeHtml(product.description || "No description available")}</div>
            <div class="info-row"><strong>Created:</strong> ${new Date(product.created_at).toLocaleDateString(CONFIG.LOCALE)}</div>
            <div class="info-row"><strong>Last Updated:</strong> ${new Date(product.updated_at).toLocaleDateString(CONFIG.LOCALE)}</div>
          </div>
        </div>
        
        ${this.modal.createActions([
          {
            text: "Restock Product",
            icon: "fas fa-plus",
            className: "btn-success",
            onclick: `window.inventoryModule.restockProduct(${product.id}); window.modalManager.close();`,
          },
          {
            text: "Close",
            icon: "fas fa-times",
            className: "btn-secondary",
            onclick: "window.modalManager.close()",
          },
        ])}
      </div>
    `

    this.modal.open("Product Details", content)
    this.toast.success("Product details loaded successfully")
  }

  getStockColor(stock, minStock = 10) {
    if (stock <= 0) return "#dc3545"
    if (stock <= minStock) return "#ffc107"
    return "#28a745"
  }

  async refresh() {
    await this.loadInventoryData()
    this.toast.success("Inventory refreshed")
  }
}
