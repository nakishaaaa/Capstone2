// Product management functionality
import { ApiClient } from "../core/api-client.js"
import { Utils } from "../utils/helpers.js"

export class ProductManagementModule {
  constructor(toastManager, modalManager) {
    this.api = new ApiClient()
    this.toast = toastManager
    this.modal = modalManager
    this.products = []
  }

  async loadProducts() {
    try {
      console.log("Loading product management data from database...")
      const result = await this.api.getAllProducts()

      if (result.success) {
        this.products = result.data
        this.displayProductsTable(result.data)
        console.log("Product management data loaded successfully:", result.data.length, "items")
      } else {
        console.error("Error loading product management:", result.error)
        this.toast.error("Error loading product management: " + result.error)
        this.displayProductsTable([])
      }
    } catch (error) {
      console.error("Error loading product management:", error)
      this.toast.error("Error connecting to database for product management")
      this.displayProductsTable([])
    }
  }

  displayProductsTable(data) {
    const tableBody = document.getElementById("productsTableBody")
    if (!tableBody) return

    if (!data || data.length === 0) {
      tableBody.innerHTML = `
        <tr>
          <td colspan="8" style="text-align: center; padding: 2rem;">
            No products available. Click "Add Product" to create your first product.
          </td>
        </tr>
      `
      return
    }

    tableBody.innerHTML = data
      .map((item) => {
        const statusClass = Utils.getStockStatusClass(item.stock_status || "In Stock")

        return `
        <tr>
          <td>${item.id}</td>
          <td>
            <img src="${item.image_url || "images/placeholder.jpg"}" 
                 alt="${Utils.escapeHtml(item.name)}" 
                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;"
                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAiIGhlaWdodD0iNTAiIHZpZXdCb3g9IjAgMCA1MCA1MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjUwIiBoZWlnaHQ9IjUwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0xNyAyMEgzM1YzMEgxN1YyMFoiIGZpbGw9IiM5Q0EzQUYiLz4KPC9zdmc+'">
          </td>
          <td>${Utils.escapeHtml(item.name)}</td>
          <td>${Utils.escapeHtml(item.category)}</td>
          <td>${Utils.formatCurrency(item.price)}</td>
          <td>${item.stock}</td>
          <td>
            <span class="status-badge ${statusClass}">
              ${item.stock_status || "In Stock"}
            </span>
          </td>
          <td>
            <button class="action-btn edit" 
                    onclick="window.productManagementModule.editProduct(${item.id})" 
                    title="Edit Product">
              <i class="fas fa-edit"></i>
            </button>
            <button class="action-btn view" 
                    onclick="window.productManagementModule.viewProduct(${item.id})" 
                    title="View Details">
              <i class="fas fa-eye"></i>
            </button>
            <button class="action-btn delete" 
                    onclick="window.productManagementModule.deleteProduct(${item.id}, '${item.name.replace(/'/g, "\\'")}')" 
                    style="background: #dc3545; color: white;" 
                    title="Delete Product">
              <i class="fas fa-trash"></i>
            </button>
          </td>
        </tr>
      `
      })
      .join("")
  }

  openAddProductModal() {
    const content = `
      ${this.modal.createHeader('<i class="fas fa-plus"></i> Add New Product')}
      <div class="modal-body">
        <form id="addProductForm" onsubmit="window.productManagementModule.createProduct(event)">
          <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label for="newProductName"><strong>Product Name: <span style="color: red;">*</span></strong></label>
              <input type="text" id="newProductName" required placeholder="Enter product name"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
            <div class="form-group">
              <label for="newProductCategory"><strong>Category: <span style="color: red;">*</span></strong></label>
              <input type="text" id="newProductCategory" required placeholder="Enter category"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
            <div class="form-group">
              <label for="newProductPrice"><strong>Price (₱): <span style="color: red;">*</span></strong></label>
              <input type="number" id="newProductPrice" step="0.01" min="0" required placeholder="0.00"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
            <div class="form-group">
              <label for="newProductMinStock"><strong>Minimum Stock:</strong></label>
              <input type="number" id="newProductMinStock" min="0" value="10" placeholder="10"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
              <small style="color: #666; font-size: 0.8rem;">Alert threshold for low stock</small>
            </div>
          </div>
          
          <div class="stock-section" style="margin: 1.5rem 0; padding: 1rem; background: #e8f5e8; border-radius: 8px; border: 2px solid #d4edda;">
            <h4 style="margin-bottom: 1rem; color: #155724;"><i class="fas fa-boxes"></i> Initial Stock</h4>
            <div class="form-group">
              <label for="newProductStock"><strong>Starting Stock Quantity: <span style="color: red;">*</span></strong></label>
              <input type="number" id="newProductStock" min="0" required value="0" placeholder="Enter initial stock"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
              <small style="color: #666; font-size: 0.8rem;">How many units do you have in stock?</small>
            </div>
            
            <div class="stock-preview" style="margin-top: 1rem; padding: 0.75rem; background: white; border-radius: 5px; border: 1px solid #c3e6cb;">
              <strong>Stock Status Preview: </strong>
              <span id="newStockPreview" style="color: #28a745;">Will be set based on entered quantity</span>
            </div>
          </div>

          <div class="form-group">
            <label for="newProductDescription"><strong>Description:</strong></label>
            <textarea id="newProductDescription" rows="3" placeholder="Enter product description (optional)..."
                      style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem; resize: vertical;"></textarea>
          </div>

          <div class="form-group">
            <label for="newProductImage"><strong>Product Image:</strong></label>
            <div class="image-upload-section" style="margin-top: 0.5rem;">
              <div class="image-preview" style="margin-bottom: 1rem;">
                <img id="newImagePreview" src="images/placeholder.jpg" alt="Image Preview" 
                     style="width: 100px; height: 100px; object-fit: cover; border-radius: 5px; border: 1px solid #ddd;">
                <div style="margin-top: 0.5rem; font-size: 0.8rem; color: #666;">Image Preview</div>
              </div>
              <input type="file" id="newProductImage" accept="image/*" 
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;"
                     onchange="window.productManagementModule.previewImage(this, 'newImagePreview')">
              <small style="color: #666; font-size: 0.8rem;">Choose an image for the product (optional)</small>
            </div>
          </div>

          ${this.modal.createActions([
            {
              type: "submit",
              text: "Create Product",
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

    this.modal.open("Add New Product", content)
    this.setupProductPreview()
  }

  setupProductPreview() {
    const inputs = {
      name: document.getElementById("newProductName"),
      category: document.getElementById("newProductCategory"),
      price: document.getElementById("newProductPrice"),
      stock: document.getElementById("newProductStock"),
      minStock: document.getElementById("newProductMinStock"),
    }

    const preview = document.getElementById("newStockPreview")

    const updatePreview = () => {
      const stock = Number.parseInt(inputs.stock.value) || 0
      const minStock = Number.parseInt(inputs.minStock.value) || 10

      let stockStatus, stockColor
      if (stock <= 0) {
        stockStatus = "Out of Stock"
        stockColor = "#dc3545"
      } else if (stock <= minStock) {
        stockStatus = "Low Stock"
        stockColor = "#ffc107"
      } else {
        stockStatus = "In Stock"
        stockColor = "#28a745"
      }

      preview.textContent = `${stock} units - ${stockStatus}`
      preview.style.color = stockColor
    }

    // Add event listeners
    Object.values(inputs).forEach((input) => {
      if (input) input.addEventListener("input", updatePreview)
    })

    // Initial preview update
    updatePreview()
  }

  previewImage(input, previewId) {
    const preview = document.getElementById(previewId)
    if (input.files && input.files[0] && preview) {
      const reader = new FileReader()
      reader.onload = (e) => {
        preview.src = e.target.result
      }
      reader.readAsDataURL(input.files[0])
    }
  }

  async createProduct(event) {
    event.preventDefault()

    try {
      this.toast.info("Creating new product...")

      // Get form values
      const formData = {
        name: document.getElementById("newProductName").value.trim(),
        category: document.getElementById("newProductCategory").value.trim(),
        price: Number.parseFloat(document.getElementById("newProductPrice").value),
        stock: Number.parseInt(document.getElementById("newProductStock").value),
        min_stock: Number.parseInt(document.getElementById("newProductMinStock").value) || 10,
        description: document.getElementById("newProductDescription").value.trim(),
      }

      // Validate form data
      if (!formData.name || !formData.category || formData.price < 0 || formData.stock < 0) {
        this.toast.error("Please fill in all required fields with valid values")
        return
      }

      if (formData.name.length < 2) {
        this.toast.error("Product name must be at least 2 characters long")
        return
      }

      // Check for duplicate names
      const existingProduct = this.products.find((p) => p.name.toLowerCase() === formData.name.toLowerCase())
      if (existingProduct) {
        this.toast.error("A product with this name already exists")
        return
      }

      let imageUrl = "images/placeholder.jpg"

      // Handle image upload
      const imageFile = document.getElementById("newProductImage").files[0]
      if (imageFile) {
        try {
          const uploadResult = await this.api.uploadImage(imageFile)
          if (uploadResult.success) {
            imageUrl = uploadResult.image_url
          } else {
            this.toast.warning("Error uploading image: " + uploadResult.error)
          }
        } catch (uploadError) {
          console.error("Error uploading image:", uploadError)
          this.toast.warning("Error uploading image, using placeholder")
        }
      }

      formData.image_url = imageUrl

      // Create product
      const result = await this.api.createProduct(formData)

      if (result.success) {
        this.toast.success("Product created successfully!")
        this.modal.close()
        await this.loadProducts()

        const stockStatus =
          formData.stock <= 0 ? "Out of Stock" : formData.stock <= formData.min_stock ? "Low Stock" : "In Stock"
        this.toast.info(`"${formData.name}" added with ${formData.stock} units (${stockStatus})`)
      } else {
        this.toast.error("Error creating product: " + result.error)
      }
    } catch (error) {
      console.error("Error creating product:", error)
      this.toast.error("Error creating product: " + error.message)
    }
  }

  async editProduct(id) {
    try {
      this.toast.info("Loading product for editing...")
      const result = await this.api.getProduct(id)

      if (result.success) {
        this.showEditProductModal(result.data)
      } else {
        this.toast.error("Error loading product: " + result.error)
      }
    } catch (error) {
      console.error("Error loading product for editing:", error)
      this.toast.error("Error loading product for editing: " + error.message)
    }
  }

  showEditProductModal(product) {
    const content = `
      ${this.modal.createHeader('<i class="fas fa-edit"></i> Edit Product')}
      <div class="modal-body">
        <form id="editProductForm" onsubmit="window.productManagementModule.saveProductChanges(event, ${product.id})">
          <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label for="editProductName"><strong>Product Name:</strong></label>
              <input type="text" id="editProductName" value="${Utils.escapeHtml(product.name)}" required 
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
            <div class="form-group">
              <label for="editProductCategory"><strong>Category:</strong></label>
              <input type="text" id="editProductCategory" value="${Utils.escapeHtml(product.category)}" required
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
            <div class="form-group">
              <label for="editProductPrice"><strong>Price (₱):</strong></label>
              <input type="number" id="editProductPrice" value="${product.price}" step="0.01" min="0" required
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
            <div class="form-group">
              <label for="editProductMinStock"><strong>Minimum Stock:</strong></label>
              <input type="number" id="editProductMinStock" value="${product.min_stock || 10}" min="0" required
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
          </div>
          
          <div class="stock-section" style="margin: 1.5rem 0; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
            <h4 style="margin-bottom: 1rem; color: #333;"><i class="fas fa-boxes"></i> Stock Management</h4>
            <div class="current-stock" style="margin-bottom: 1rem;">
              <strong>Current Stock: </strong>
              <span style="color: ${this.getStockColor(product.stock, product.min_stock)}; font-weight: bold; font-size: 1.2rem;">
                ${product.stock}
              </span>
            </div>
            
            <div class="restock-options" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label for="restockQuantity"><strong>Add Stock:</strong></label>
                <input type="number" id="restockQuantity" min="0" placeholder="Enter quantity to add"
                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
                <small style="color: #666; font-size: 0.8rem;">Leave empty if no restock needed</small>
              </div>
              <div class="form-group">
                <label for="setStockQuantity"><strong>Set Stock To:</strong></label>
                <input type="number" id="setStockQuantity" min="0" placeholder="Set exact stock amount"
                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
                <small style="color: #666; font-size: 0.8rem;">This will override current stock</small>
              </div>
            </div>
            
            <div class="stock-preview" style="margin-top: 1rem; padding: 0.75rem; background: white; border-radius: 5px; border: 1px solid #ddd;">
              <strong>New Stock Will Be: </strong><span id="newStockAmount">${product.stock}</span>
            </div>
          </div>

          <div class="form-group">
            <label for="editProductDescription"><strong>Description:</strong></label>
            <textarea id="editProductDescription" rows="3" placeholder="Product description..."
                      style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem; resize: vertical;">${Utils.escapeHtml(product.description || "")}</textarea>
          </div>

          <div class="form-group">
            <label for="editProductImage"><strong>Product Image:</strong></label>
            <div class="image-upload-section" style="margin-top: 0.5rem;">
              <div class="current-image" style="margin-bottom: 1rem;">
                <img id="editCurrentImage" src="${product.image_url || "images/placeholder.jpg"}" alt="Current Image" 
                     style="width: 100px; height: 100px; object-fit: cover; border-radius: 5px; border: 1px solid #ddd;">
                <div style="margin-top: 0.5rem; font-size: 0.8rem; color: #666;">Current Image</div>
              </div>
              <input type="file" id="editProductImage" accept="image/*" 
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;"
                     onchange="window.productManagementModule.previewImage(this, 'editCurrentImage')">
              <small style="color: #666; font-size: 0.8rem;">Choose a new image to replace current one (optional)</small>
            </div>
          </div>

          ${this.modal.createActions([
            {
              type: "submit",
              text: "Save Changes",
              icon: "fas fa-save",
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

    this.modal.open("Edit Product", content, { preventOutsideClick: true })
    this.setupEditStockPreview(product.stock, product.min_stock)
  }

  setupEditStockPreview(currentStock, minStock) {
    const restockInput = document.getElementById("restockQuantity")
    const setStockInput = document.getElementById("setStockQuantity")
    const newStockSpan = document.getElementById("newStockAmount")

    const updatePreview = () => {
      const restockQty = Number.parseInt(restockInput.value) || 0
      const setStockQty = Number.parseInt(setStockInput.value)

      let newStock
      if (setStockQty >= 0 && setStockInput.value !== "") {
        newStock = setStockQty
        restockInput.value = ""
      } else if (restockQty > 0) {
        newStock = currentStock + restockQty
        setStockInput.value = ""
      } else {
        newStock = currentStock
      }

      newStockSpan.textContent = newStock
      newStockSpan.style.color = this.getStockColor(newStock, minStock)
    }

    restockInput.addEventListener("input", updatePreview)
    setStockInput.addEventListener("input", updatePreview)
  }

  async saveProductChanges(event, productId) {
    event.preventDefault()

    try {
      this.toast.info("Saving product changes...")

      const currentProduct = this.products.find((p) => p.id === productId)
      if (!currentProduct) {
        this.toast.error("Product not found")
        return
      }

      // Get form values
      const formData = {
        name: document.getElementById("editProductName").value.trim(),
        category: document.getElementById("editProductCategory").value.trim(),
        price: Number.parseFloat(document.getElementById("editProductPrice").value),
        min_stock: Number.parseInt(document.getElementById("editProductMinStock").value),
        description: document.getElementById("editProductDescription").value.trim(),
      }

      // Calculate new stock
      const restockQty = Number.parseInt(document.getElementById("restockQuantity").value) || 0
      const setStockQty = Number.parseInt(document.getElementById("setStockQuantity").value)

      let newStock
      if (setStockQty >= 0 && document.getElementById("setStockQuantity").value !== "") {
        newStock = setStockQty
      } else if (restockQty > 0) {
        newStock = currentProduct.stock + restockQty
      } else {
        newStock = currentProduct.stock
      }

      formData.stock = newStock

      // Validate form data
      if (!formData.name || !formData.category || formData.price < 0 || formData.min_stock < 0 || newStock < 0) {
        this.toast.error("Please fill in all required fields with valid values")
        return
      }

      let imageUrl = currentProduct.image_url

      // Handle image upload
      const imageFile = document.getElementById("editProductImage").files[0]
      if (imageFile) {
        try {
          const uploadResult = await this.api.uploadImage(imageFile)
          if (uploadResult.success) {
            imageUrl = uploadResult.image_url
          } else {
            this.toast.warning("Error uploading image: " + uploadResult.error)
          }
        } catch (uploadError) {
          console.error("Error uploading image:", uploadError)
          this.toast.warning("Error uploading image, continuing with current image")
        }
      }

      formData.image_url = imageUrl

      // Update product
      const result = await this.api.updateProduct(productId, formData)

      if (result.success) {
        this.toast.success("Product updated successfully!")
        this.modal.close()
        await this.loadProducts()

        // Show stock change notification if stock was modified
        if (newStock !== currentProduct.stock) {
          const stockChange = newStock - currentProduct.stock
          const changeText = stockChange > 0 ? `+${stockChange}` : `${stockChange}`
          this.toast.info(`Stock updated: ${currentProduct.stock} → ${newStock} (${changeText})`)
        }
      } else {
        this.toast.error("Error updating product: " + result.error)
      }
    } catch (error) {
      console.error("Error saving product changes:", error)
      this.toast.error("Error saving product changes: " + error.message)
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
                 style="width: 100%; max-width: 200px; height: 200px; object-fit: cover; border-radius: 8px; margin-bottom: 1rem;">
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
            <div class="info-row"><strong>Created:</strong> ${new Date(product.created_at).toLocaleDateString("en-PH")}</div>
            <div class="info-row"><strong>Last Updated:</strong> ${new Date(product.updated_at).toLocaleDateString("en-PH")}</div>
          </div>
        </div>
        
        ${this.modal.createActions([
          {
            text: "Edit Product",
            icon: "fas fa-edit",
            className: "btn-primary",
            onclick: `window.productManagementModule.editProduct(${product.id}); window.modalManager.close();`,
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

  async deleteProduct(id, name) {
    if (!confirm(`Are you sure you want to delete "${name}"?\n\nThis action cannot be undone.`)) {
      return
    }

    try {
      this.toast.info("Deleting product...")
      const result = await this.api.deleteProduct(id)

      if (result.success) {
        this.toast.success(`"${name}" deleted successfully!`)
        await this.loadProducts()
      } else {
        this.toast.error("Error deleting product: " + result.error)
      }
    } catch (error) {
      console.error("Error deleting product:", error)
      this.toast.error("Error deleting product: " + error.message)
    }
  }

  getStockColor(stock, minStock = 10) {
    if (stock <= 0) return "#dc3545"
    if (stock <= minStock) return "#ffc107"
    return "#28a745"
  }
}
