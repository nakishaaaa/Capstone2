// Product management functionality
import { ApiClient } from "../core/api-client.js"
import { Utils } from "../utils/helpers.js"

export class ProductManagementModule {
  constructor(toastManager, modalManager) {
    this.api = new ApiClient()
    this.toast = toastManager
    this.modal = modalManager
    this.products = []
    this.currentView = 'active' // 'active' or 'trash'
  }

  async loadProducts(viewType = 'active') {
    try {
      console.log(`Loading product management data from database (${viewType} view)...`)
      
      let endpoint = 'inventory.php'
      if (viewType === 'trash') {
        endpoint += '?only_deleted=true'
      }
      
      const result = await this.api.request(endpoint)

      if (result.success) {
        this.products = result.data
        this.currentView = viewType
        this.displayProductsTable(result.data)
        this.updateViewToggle()
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

  updateViewToggle() {
    const activeBtn = document.getElementById('activeProductsBtn')
    const trashBtn = document.getElementById('trashProductsBtn')
    
    if (activeBtn && trashBtn) {
      if (this.currentView === 'active') {
        activeBtn.classList.add('active')
        trashBtn.classList.remove('active')
      } else {
        activeBtn.classList.remove('active')
        trashBtn.classList.add('active')
      }
    }
  }

  getExistingCategories() {
    // Extract unique categories from loaded products
    const categories = [...new Set(this.products.map(product => product.category).filter(cat => cat && cat.trim()))]
    return categories.sort()
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
            ${this.currentView === 'trash' ? 
              `<button class="action-btn restore" 
                      onclick="window.productManagementModule.restoreProduct(${item.id})" 
                      title="Restore Product">
                <i class="fas fa-undo"></i>
              </button>
              <button class="action-btn delete-permanent" 
                      onclick="window.productManagementModule.permanentDeleteProduct(${item.id})" 
                      title="Delete Permanently">
                <i class="fas fa-trash-alt"></i>
              </button>` :
              `<button class="action-btn edit" 
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
                      title="Move to Trash">
                <i class="fas fa-trash"></i>
              </button>`
            }
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
              <select id="newProductCategorySelect" onchange="window.productManagementModule.handleCategoryChange()"
                      style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
                <option value="" disabled selected>Select existing category</option>
                ${this.getExistingCategories().map(category => 
                  `<option value="${category}">${category}</option>`
                ).join('')}
                <option value="__custom__">+ Add New Category</option>
              </select>
              <div id="customCategoryContainer" style="display: none;">
                <input type="text" id="newProductCategory" required placeholder="Enter new category name" 
                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
                <button type="button" onclick="window.productManagementModule.showCategoryDropdown()" 
                        style="margin-top: 0.5rem; padding: 0.5rem 1rem; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; cursor: pointer; font-size: 0.8rem;">
                  <i class="fas fa-arrow-left"></i> Back to Categories
                </button>
              </div>
              <small style="color: #666; font-size: 0.8rem; margin-top: 0.25rem; display: block;">
                Choose from existing categories or create a new one
              </small>
            </div>
            <div class="form-group">
              <label for="newProductPrice"><strong>Price (₱): <span style="color: red;">*</span></strong></label>
              <input type="number" id="newProductPrice" step="0.01" min="0" max="15000" required placeholder="0.00"
                     oninput="if(this.value > 15000) this.value = 15000;"
                     onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
              <div class="price-preview" style="margin-top: 0.5rem; padding: 0.75rem; background: #e3f2fd; border-radius: 5px; border: 1px solid #bbdefb;">
                <div style="font-size: 0.8rem; color:rgb(0, 0, 0); margin-bottom: 0.25rem;">
                  <i class="fas fa-calculator"></i> Price with VAT (12%)
                </div>
                <div id="priceWithVatPreview" style="font-size: 1rem; font-weight: bold; color:rgb(0, 0, 0);">
                  ₱0.00
                </div>
              </div>
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

    this.modal.open("Add New Product", content, { preventOutsideClick: true })
    this.setupProductPreview()
  }

  handleCategoryChange() {
    const categorySelect = document.getElementById("newProductCategorySelect")
    const categoryInput = document.getElementById("newProductCategory")
    const customContainer = document.getElementById("customCategoryContainer")
    
    if (categorySelect.value === "__custom__") {
      // Show custom input, hide dropdown
      categorySelect.style.display = "none"
      customContainer.style.display = "block"
      categoryInput.focus()
      categoryInput.value = ""
    } else if (categorySelect.value) {
      // Set the hidden input value to selected category
      categoryInput.value = categorySelect.value
    } else {
      // Clear the hidden input if no selection
      categoryInput.value = ""
    }
  }

  showCategoryDropdown() {
    const categorySelect = document.getElementById("newProductCategorySelect")
    const customContainer = document.getElementById("customCategoryContainer")
    
    // Show dropdown, hide custom input
    categorySelect.style.display = "block"
    customContainer.style.display = "none"
    categorySelect.value = ""
    document.getElementById("newProductCategory").value = ""
  }

  handleEditCategoryChange() {
    const categorySelect = document.getElementById("editProductCategorySelect")
    const categoryInput = document.getElementById("editProductCategory")
    const customContainer = document.getElementById("editCustomCategoryContainer")
    
    if (categorySelect.value === "__custom__") {
      // Show custom input, hide dropdown
      categorySelect.style.display = "none"
      customContainer.style.display = "block"
      categoryInput.focus()
      categoryInput.value = ""
    } else if (categorySelect.value) {
      // Set the hidden input value to selected category
      categoryInput.value = categorySelect.value
    } else {
      // Clear the hidden input if no selection
      categoryInput.value = ""
    }
  }

  showEditCategoryDropdown() {
    const categorySelect = document.getElementById("editProductCategorySelect")
    const customContainer = document.getElementById("editCustomCategoryContainer")
    
    // Show dropdown, hide custom input
    categorySelect.style.display = "block"
    customContainer.style.display = "none"
    categorySelect.value = ""
    document.getElementById("editProductCategory").value = ""
  }

  setupProductPreview() {
    const inputs = {
      name: document.getElementById("newProductName"),
      category: document.getElementById("newProductCategory"),
      price: document.getElementById("newProductPrice"),
      stock: document.getElementById("newProductStock"),
      minStock: document.getElementById("newProductMinStock"),
    }

    const stockPreview = document.getElementById("newStockPreview")
    const priceWithVatPreview = document.getElementById("priceWithVatPreview")

    const updatePreview = () => {
      // Update stock preview
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

      stockPreview.textContent = `${stock} units - ${stockStatus}`
      stockPreview.style.color = stockColor

      // Update price with VAT preview
      const basePrice = Number.parseFloat(inputs.price.value) || 0
      const vatRate = 0.12 // 12% VAT
      const priceWithVat = basePrice * (1 + vatRate)
      
      if (priceWithVatPreview) {
        priceWithVatPreview.textContent = `₱${priceWithVat.toFixed(2)}`
      }
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

      if (formData.price > 15000) {
        this.toast.error("Price cannot exceed ₱15,000.00")
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
              <select id="editProductCategorySelect" onchange="window.productManagementModule.handleEditCategoryChange()"
                      style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
                <option value="" disabled>Select existing category</option>
                ${this.getExistingCategories().map(category => 
                  `<option value="${category}" ${category === product.category ? 'selected' : ''}>${category}</option>`
                ).join('')}
                <option value="__custom__">+ Add New Category</option>
              </select>
              <div id="editCustomCategoryContainer" style="display: none;">
                <input type="text" id="editProductCategory" value="${Utils.escapeHtml(product.category)}" required placeholder="Enter new category name" 
                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
                <button type="button" onclick="window.productManagementModule.showEditCategoryDropdown()" 
                        style="margin-top: 0.5rem; padding: 0.5rem 1rem; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; cursor: pointer; font-size: 0.8rem;">
                  <i class="fas fa-arrow-left"></i> Back to Categories
                </button>
              </div>
              <small style="color: #666; font-size: 0.8rem; margin-top: 0.25rem; display: block;">
                Choose from existing categories or create a new one
              </small>
            </div>
            <div class="form-group">
              <label for="editProductPrice"><strong>Price (₱):</strong></label>
              <input type="number" id="editProductPrice" value="${product.price}" step="0.01" min="0" max="15000" required
                     oninput="if(this.value > 15000) this.value = 15000;"
                     onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
              <div class="price-preview" style="margin-top: 0.5rem; padding: 0.75rem; background: #e3f2fd; border-radius: 5px; border: 1px solid #bbdefb;">
                <div style="font-size: 0.8rem; color:rgb(0, 0, 0); margin-bottom: 0.25rem;">
                  <i class="fas fa-calculator"></i> Price with VAT (12%)
                </div>
                <div id="editPriceWithVatPreview" style="font-size: 1rem; font-weight: bold; color:rgb(0, 0, 0);">
                  ₱${(product.price * 1.12).toFixed(2)}
                </div>
              </div>
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
    this.setupEditVatPreview()
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

  setupEditVatPreview() {
    const priceInput = document.getElementById("editProductPrice")
    const vatPreview = document.getElementById("editPriceWithVatPreview")

    const updateVatPreview = () => {
      const basePrice = parseFloat(priceInput.value) || 0
      const vatRate = 0.12 // 12% VAT
      const priceWithVat = basePrice * (1 + vatRate)
      
      if (vatPreview) {
        vatPreview.textContent = `₱${priceWithVat.toFixed(2)}`
      }
    }

    if (priceInput) {
      priceInput.addEventListener("input", updateVatPreview)
      // Update immediately with current value
      updateVatPreview()
    }
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

      if (formData.price > 15000) {
        this.toast.error("Price cannot exceed ₱15,000.00")
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
              <strong>Price with VAT (12%):</strong> 
              <span style="color: rgb(0, 0, 0); font-weight: bold; background: #e3f2fd; padding: 0.25rem 0.5rem; border-radius: 4px; border: 1px solid #bbdefb;">
                <i class="fas fa-calculator" style="margin-right: 0.25rem;"></i>${Utils.formatCurrency(product.price * 1.12)}
              </span>
            </div>
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
    try {
      const confirmed = await this.modal.confirm(
        'Move to Trash',
        `Are you sure you want to move "${name}" to trash? You can restore it later.`,
        'Move to Trash',
        'Cancel'
      )
      
      if (confirmed) {
        const result = await this.api.request(`inventory.php?id=${id}`, { method: 'DELETE' })
        
        if (result.success) {
          this.toast.success(result.message || 'Product moved to trash successfully')
          this.loadProducts(this.currentView)
        } else {
          this.toast.error(result.error || 'Failed to delete product')
        }
      }
    } catch (error) {
      console.error('Error deleting product:', error)
      this.toast.error('Error deleting product: ' + error.message)
    }
  }

  async restoreProduct(id) {
    try {
      const confirmed = await this.modal.confirm(
        'Restore Product',
        'Are you sure you want to restore this product? It will be moved back to active products.',
        'Restore',
        'Cancel'
      )
      
      if (confirmed) {
        const result = await this.api.request(`inventory.php?id=${id}&action=restore`, { method: 'DELETE' })
        
        if (result.success) {
          this.toast.success(result.message || 'Product restored successfully')
          this.loadProducts(this.currentView)
        } else {
          this.toast.error(result.error || 'Failed to restore product')
        }
      }
    } catch (error) {
      console.error('Error restoring product:', error)
      this.toast.error('Error restoring product: ' + error.message)
    }
  }

  async permanentDeleteProduct(id) {
    try {
      const confirmed = await this.modal.confirm(
        'Permanent Delete',
        'Are you sure you want to permanently delete this product? This action cannot be undone and will also delete associated image files.',
        'Delete Permanently',
        'Cancel',
        'danger'
      )
      
      if (confirmed) {
        const result = await this.api.request(`inventory.php?id=${id}&action=permanent`, { method: 'DELETE' })
        
        if (result.success) {
          this.toast.success(result.message || 'Product permanently deleted')
          this.loadProducts(this.currentView)
        } else {
          this.toast.error(result.error || 'Failed to permanently delete product')
        }
      }
    } catch (error) {
      console.error('Error permanently deleting product:', error)
      this.toast.error('Error permanently deleting product: ' + error.message)
    }
  }

  switchView(viewType) {
    this.loadProducts(viewType)
  }

  getStockColor(stock, minStock = 10) {
    if (stock <= 0) return "#dc3545"
    if (stock <= minStock) return "#ffc107"
    return "#28a745"
  }
}
