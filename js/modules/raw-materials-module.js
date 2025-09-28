// Raw Materials management functionality
import { ApiClient } from "../core/api-client.js"
import { Utils } from "../utils/helpers.js"

export class RawMaterialsModule {
  constructor(toastManager, modalManager) {
    this.api = new ApiClient()
    this.toast = toastManager
    this.modal = modalManager
    this.materials = []
    this.currentView = 'active' // 'active' or 'trash'
  }

  async loadRawMaterials(viewType = 'active') {
    try {
      console.log(`Loading raw materials data (${viewType} view)...`)
      
      let endpoint = 'raw_materials.php'
      if (viewType === 'trash') {
        endpoint += '?only_deleted=true'
      }
      
      const result = await this.api.request(endpoint)

      if (result.success) {
        this.materials = result.data
        this.currentView = viewType
        this.displayRawMaterialsTable(result.data)
        this.updateViewToggle()
        console.log("Raw materials loaded:", result.data.length, "items")
      } else {
        this.toast.error("Error loading raw materials: " + result.error)
        this.displayRawMaterialsTable([])
      }
    } catch (error) {
      console.error("Error loading raw materials:", error)
      this.toast.error("Error connecting to database for raw materials")
      this.displayRawMaterialsTable([])
    }
  }

  updateViewToggle() {
    const activeBtn = document.getElementById('activeRawMaterialsBtn')
    const trashBtn = document.getElementById('trashRawMaterialsBtn')
    
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

  displayRawMaterialsTable(data) {
    const tableBody = document.getElementById("rawMaterialsTableBody")
    if (!tableBody) return

    if (!data || data.length === 0) {
      tableBody.innerHTML = `
        <tr>
          <td colspan="11" style="text-align: center; padding: 2rem;">
            No raw materials available. Click "Add Raw Material" to create your first material.
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
          <td>${Utils.escapeHtml(item.supplier || 'N/A')}</td>
          <td>${item.current_stock} ${item.unit_type}</td>
          <td>${item.min_stock} ${item.unit_type}</td>
          <td>${Utils.formatCurrency(item.unit_cost)}</td>
          <td>${Utils.escapeHtml(item.unit_type)}</td>
          <td>
            <span class="status-badge ${statusClass}">
              ${item.stock_status || "In Stock"}
            </span>
          </td>
          <td>
            ${this.currentView === 'trash' ? 
              `<button class="action-btn restore" 
                      onclick="window.rawMaterialsModule.restoreMaterial(${item.id})" 
                      title="Restore Material">
                <i class="fas fa-undo"></i>
              </button>
              <button class="action-btn delete-permanent" 
                      onclick="window.rawMaterialsModule.permanentDeleteMaterial(${item.id})" 
                      title="Delete Permanently">
                <i class="fas fa-trash-alt"></i>
              </button>` :
              `<button class="action-btn restock" 
                      onclick="window.rawMaterialsModule.restockMaterial(${item.id})" 
                      title="Restock Material">
                <i class="fas fa-plus"></i>
              </button>
              <button class="action-btn view" 
                      onclick="window.rawMaterialsModule.viewMaterial(${item.id})" 
                      title="View Details">
                <i class="fas fa-eye"></i>
              </button>
              <button class="action-btn edit" 
                      onclick="window.rawMaterialsModule.editMaterial(${item.id})" 
                      title="Edit Material">
                <i class="fas fa-edit"></i>
              </button>
              <button class="action-btn delete" 
                      onclick="window.rawMaterialsModule.deleteMaterial(${item.id}, '${item.name.replace(/'/g, "\\'")}')" 
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

  async createMaterial(data) {
    try {
      const response = await fetch('/Capstone2/api/raw_materials.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      })
      return await response.json()
    } catch (error) {
      return { success: false, error: error.message }
    }
  }

  async updateMaterial(id, data) {
    try {
      const response = await fetch(`/Capstone2/api/raw_materials.php?id=${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      })
      return await response.json()
    } catch (error) {
      return { success: false, error: error.message }
    }
  }

  async deleteMaterial(id, name) {
    try {
      const confirmed = await this.modal.confirm(
        'Move to Trash',
        `Are you sure you want to move "${name}" to trash? You can restore it later.`,
        'Move to Trash',
        'Cancel'
      )
      
      if (confirmed) {
        const result = await this.api.request(`raw_materials.php?id=${id}`, { method: 'DELETE' })
        
        if (result.success) {
          this.toast.success(result.message || 'Raw material moved to trash successfully')
          this.loadRawMaterials(this.currentView)
        } else {
          this.toast.error(result.error || 'Failed to delete raw material')
        }
      }
    } catch (error) {
      console.error('Error deleting raw material:', error)
      this.toast.error('Error deleting raw material: ' + error.message)
    }
  }

  async restoreMaterial(id) {
    try {
      const confirmed = await this.modal.confirm(
        'Restore Raw Material',
        'Are you sure you want to restore this raw material? It will be moved back to active materials.',
        'Restore',
        'Cancel'
      )
      
      if (confirmed) {
        const result = await this.api.request(`raw_materials.php?id=${id}&action=restore`, { method: 'DELETE' })
        
        if (result.success) {
          this.toast.success(result.message || 'Raw material restored successfully')
          this.loadRawMaterials(this.currentView)
        } else {
          this.toast.error(result.error || 'Failed to restore raw material')
        }
      }
    } catch (error) {
      console.error('Error restoring raw material:', error)
      this.toast.error('Error restoring raw material: ' + error.message)
    }
  }

  async permanentDeleteMaterial(id) {
    try {
      const confirmed = await this.modal.confirm(
        'Permanent Delete',
        'Are you sure you want to permanently delete this raw material? This action cannot be undone and will also delete associated image files.',
        'Delete Permanently',
        'Cancel',
        'danger'
      )
      
      if (confirmed) {
        const result = await this.api.request(`raw_materials.php?id=${id}&action=permanent`, { method: 'DELETE' })
        
        if (result.success) {
          this.toast.success(result.message || 'Raw material permanently deleted')
          this.loadRawMaterials(this.currentView)
        } else {
          this.toast.error(result.error || 'Failed to permanently delete raw material')
        }
      }
    } catch (error) {
      console.error('Error permanently deleting raw material:', error)
      this.toast.error('Error permanently deleting raw material: ' + error.message)
    }
  }

  switchView(viewType) {
    this.loadRawMaterials(viewType)
  }

  openAddMaterialModal() {
    const content = `
      ${this.modal.createHeader('<i class="fas fa-plus"></i> Add New Raw Material')}
      <div class="modal-body">
        <form id="addMaterialForm" onsubmit="window.rawMaterialsModule.createMaterialFromForm(event)">
          <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label for="newMaterialName"><strong>Material Name: <span style="color: red;">*</span></strong></label>
              <input type="text" id="newMaterialName" required placeholder="Enter material name"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
            <div class="form-group">
              <label for="newMaterialCategory"><strong>Category: <span style="color: red;">*</span></strong></label>
              <input type="text" id="newMaterialCategory" required placeholder="e.g., Paper, Ink, Tools"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
            <div class="form-group">
              <label for="newMaterialSupplier"><strong>Supplier:</strong></label>
              <input type="text" id="newMaterialSupplier" placeholder="Supplier name"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
            <div class="form-group">
              <label for="newMaterialUnitType"><strong>Unit Type: <span style="color: red;">*</span></strong></label>
              <select id="newMaterialUnitType" required
                      style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
                <option value="">Select unit type</option>
                <option value="pieces">Pieces</option>
                <option value="sheets">Sheets</option>
                <option value="rolls">Rolls</option>
                <option value="liters">Liters</option>
                <option value="kilograms">Kilograms</option>
                <option value="meters">Meters</option>
                <option value="boxes">Boxes</option>
                <option value="packs">Packs</option>
              </select>
            </div>
            <div class="form-group">
              <label for="newMaterialUnitCost"><strong>Unit Cost (₱): <span style="color: red;">*</span></strong></label>
              <input type="number" id="newMaterialUnitCost" step="0.01" min="0" required placeholder="0.00"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
            <div class="form-group">
              <label for="newMaterialCurrentStock"><strong>Current Stock: <span style="color: red;">*</span></strong></label>
              <input type="number" id="newMaterialCurrentStock" step="0.01" min="0" required placeholder="0"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
            <div class="form-group">
              <label for="newMaterialMinStock"><strong>Minimum Stock:</strong></label>
              <input type="number" id="newMaterialMinStock" step="0.01" min="0" value="10" placeholder="10"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
            <div class="form-group">
              <label for="newMaterialMaxStock"><strong>Maximum Stock:</strong></label>
              <input type="number" id="newMaterialMaxStock" step="0.01" min="0" placeholder="Optional"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
          </div>
          
          <div class="form-group">
            <label for="newMaterialDescription"><strong>Description:</strong></label>
            <textarea id="newMaterialDescription" rows="3" placeholder="Enter material description (optional)..."
                      style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem; resize: vertical;"></textarea>
          </div>

          ${this.modal.createActions([
            {
              type: "submit",
              text: "Create Material",
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

    this.modal.open("Add New Raw Material", content, { preventOutsideClick: true })
  }

  async createMaterialFromForm(event) {
    event.preventDefault()

    try {
      this.toast.info("Creating new raw material...")

      const formData = {
        name: document.getElementById("newMaterialName").value.trim(),
        category: document.getElementById("newMaterialCategory").value.trim(),
        supplier: document.getElementById("newMaterialSupplier").value.trim(),
        unit_type: document.getElementById("newMaterialUnitType").value,
        unit_cost: parseFloat(document.getElementById("newMaterialUnitCost").value),
        current_stock: parseFloat(document.getElementById("newMaterialCurrentStock").value),
        min_stock: parseFloat(document.getElementById("newMaterialMinStock").value) || 10,
        max_stock: document.getElementById("newMaterialMaxStock").value ? parseFloat(document.getElementById("newMaterialMaxStock").value) : null,
        description: document.getElementById("newMaterialDescription").value.trim(),
      }

      // Validate form data
      if (!formData.name || !formData.category || !formData.unit_type || formData.unit_cost < 0 || formData.current_stock < 0) {
        this.toast.error("Please fill in all required fields with valid values")
        return
      }

      const result = await this.createMaterial(formData)

      if (result.success) {
        this.toast.success("Raw material created successfully!")
        this.modal.close()
        await this.loadRawMaterials()
      } else {
        this.toast.error("Error creating raw material: " + result.error)
      }
    } catch (error) {
      console.error("Error creating raw material:", error)
      this.toast.error("Error creating raw material: " + error.message)
    }
  }

  async restockMaterial(id) {
    try {
      this.toast.info("Loading material for restocking...")
      const response = await fetch(`/Capstone2/api/raw_materials.php?id=${id}`)
      const result = await response.json()

      if (result.success) {
        this.showRestockModal(result.data)
      } else {
        this.toast.error("Error loading material: " + result.error)
      }
    } catch (error) {
      console.error("Error loading material for restocking:", error)
      this.toast.error("Error loading material for restocking: " + error.message)
    }
  }

  showRestockModal(material) {
    const content = `
      ${this.modal.createHeader('<i class="fas fa-plus"></i> Restock Raw Material')}
      <div class="modal-body">
        <div class="material-info-section" style="margin-bottom: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
          <h4 style="margin-bottom: 1rem; color: #333;">
            <i class="fas fa-info-circle"></i> Material Information
          </h4>
          <div style="display: grid; grid-template-columns: auto 1fr; gap: 0.5rem 1rem;">
            <strong>Name:</strong> <span>${Utils.escapeHtml(material.name)}</span>
            <strong>Category:</strong> <span>${Utils.escapeHtml(material.category)}</span>
            <strong>Supplier:</strong> <span>${Utils.escapeHtml(material.supplier || 'N/A')}</span>
            <strong>Unit Cost:</strong> <span>${Utils.formatCurrency(material.unit_cost)}</span>
            <strong>Current Stock:</strong> 
            <span style="color: ${this.getStockColor(material.current_stock, material.min_stock)}; font-weight: bold;">
              ${material.current_stock} ${material.unit_type}
            </span>
            <strong>Min Stock:</strong> <span>${material.min_stock} ${material.unit_type}</span>
          </div>
        </div>
        
        <form id="restockMaterialForm" onsubmit="window.rawMaterialsModule.saveRestockChanges(event, ${material.id})">
          <div class="restock-section" style="padding: 1rem; background: #e8f5e8; border-radius: 8px; border: 2px solid #d4edda;">
            <h4 style="margin-bottom: 1rem; color: #155724;">
              <i class="fas fa-boxes"></i> Restock Options
            </h4>
            
            <div class="restock-options" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
              <div class="form-group">
                <label for="restockQuantity"><strong>Add Stock:</strong></label>
                <input type="number" id="restockQuantity" step="0.01" min="0.01" placeholder="Enter quantity to add" 
                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
                <small style="color: #666; font-size: 0.8rem;">Add to current stock</small>
              </div>
              <div class="form-group">
                <label for="setStockQuantity"><strong>Set Stock To:</strong></label>
                <input type="number" id="setStockQuantity" step="0.01" min="0" placeholder="Set exact stock amount"
                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
                <small style="color: #666; font-size: 0.8rem;">Override current stock</small>
              </div>
            </div>
            
            <div class="stock-preview" style="padding: 0.75rem; background: white; border-radius: 5px; border: 1px solid #c3e6cb;">
              <strong>New Stock Will Be: </strong>
              <span id="newStockAmount" style="font-size: 1.2rem; font-weight: bold;">${material.current_stock}</span>
              <span> ${material.unit_type}</span>
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

    this.modal.open("Restock Raw Material", content, { preventOutsideClick: true })
    this.setupRestockPreview(material.current_stock, material.min_stock)
  }

  setupRestockPreview(currentStock, minStock) {
    const restockInput = document.getElementById("restockQuantity")
    const setStockInput = document.getElementById("setStockQuantity")
    const newStockSpan = document.getElementById("newStockAmount")
    const stockChangeSpan = document.getElementById("stockChange")

    const updatePreview = () => {
      const restockQty = parseFloat(restockInput.value) || 0
      const setStockQty = parseFloat(setStockInput.value)

      let newStock, changeText
      if (setStockQty >= 0 && setStockInput.value !== "") {
        newStock = setStockQty
        changeText = `(${setStockQty >= currentStock ? "+" : ""}${(setStockQty - currentStock).toFixed(2)})`
        restockInput.value = ""
      } else if (restockQty > 0) {
        newStock = currentStock + restockQty
        changeText = `(+${restockQty.toFixed(2)})`
        setStockInput.value = ""
      } else {
        newStock = currentStock
        changeText = ""
      }

      newStockSpan.textContent = newStock.toFixed(2)
      stockChangeSpan.textContent = changeText
      newStockSpan.style.color = this.getStockColor(newStock, minStock)
    }

    restockInput.addEventListener("input", updatePreview)
    setStockInput.addEventListener("input", updatePreview)
  }

  async saveRestockChanges(event, materialId) {
    event.preventDefault()

    try {
      this.toast.info("Updating stock...")

      const restockQty = parseFloat(document.getElementById("restockQuantity").value) || 0
      const setStockQty = parseFloat(document.getElementById("setStockQuantity").value)

      // Get current material data
      const response = await fetch(`/Capstone2/api/raw_materials.php?id=${materialId}`)
      const result = await response.json()
      
      if (!result.success) {
        throw new Error("Material not found")
      }
      
      const currentMaterial = result.data

      let newStock
      if (setStockQty >= 0 && document.getElementById("setStockQuantity").value !== "") {
        newStock = setStockQty
      } else if (restockQty > 0) {
        newStock = currentMaterial.current_stock + restockQty
      } else {
        this.toast.error("Please enter a restock quantity or set stock amount")
        return
      }

      if (newStock < 0) {
        this.toast.error("Stock cannot be negative")
        return
      }

      const updateData = {
        name: currentMaterial.name,
        category: currentMaterial.category,
        supplier: currentMaterial.supplier,
        unit_type: currentMaterial.unit_type,
        unit_cost: currentMaterial.unit_cost,
        current_stock: newStock,
        min_stock: currentMaterial.min_stock,
        max_stock: currentMaterial.max_stock,
        description: currentMaterial.description,
        image_url: currentMaterial.image_url,
      }

      const updateResult = await this.updateMaterial(materialId, updateData)

      if (updateResult.success) {
        this.toast.success("Stock updated successfully!")
        this.modal.close()
        await this.loadRawMaterials()

        const stockChange = newStock - currentMaterial.current_stock
        const changeText = stockChange > 0 ? `+${stockChange.toFixed(2)}` : `${stockChange.toFixed(2)}`
        this.toast.info(`"${currentMaterial.name}" stock: ${currentMaterial.current_stock} → ${newStock.toFixed(2)} (${changeText})`)
      } else {
        this.toast.error("Error updating stock: " + updateResult.error)
      }
    } catch (error) {
      console.error("Error updating stock:", error)
      this.toast.error("Error updating stock: " + error.message)
    }
  }

  async viewMaterial(id) {
    try {
      this.toast.info("Loading material details...")
      const response = await fetch(`/Capstone2/api/raw_materials.php?id=${id}`)
      const result = await response.json()

      if (result.success) {
        this.showMaterialDetailsModal(result.data)
      } else {
        this.toast.error("Error loading material: " + result.error)
      }
    } catch (error) {
      console.error("Error viewing material:", error)
      this.toast.error("Error loading material details: " + error.message)
    }
  }

  showMaterialDetailsModal(material) {
    const content = `
      ${this.modal.createHeader('<i class="fas fa-eye"></i> Raw Material Details')}
      <div class="modal-body">
        <div class="material-details">
          <div class="material-image-section">
            <img src="${material.image_url || "images/placeholder.jpg"}" 
                 alt="${Utils.escapeHtml(material.name)}" 
                 style="width: 100%; max-width: 200px; height: 200px; object-fit: cover; border-radius: 8px; margin-bottom: 1rem;">
          </div>
          <div class="material-info">
            <div class="info-row"><strong>Material ID:</strong> ${material.id}</div>
            <div class="info-row"><strong>Name:</strong> ${Utils.escapeHtml(material.name)}</div>
            <div class="info-row"><strong>Category:</strong> ${Utils.escapeHtml(material.category)}</div>
            <div class="info-row"><strong>Supplier:</strong> ${Utils.escapeHtml(material.supplier || 'N/A')}</div>
            <div class="info-row"><strong>Unit Cost:</strong> ${Utils.formatCurrency(material.unit_cost)}</div>
            <div class="info-row"><strong>Unit Type:</strong> ${Utils.escapeHtml(material.unit_type)}</div>
            <div class="info-row">
              <strong>Current Stock:</strong> 
              <span style="color: ${this.getStockColor(material.current_stock, material.min_stock)}; font-weight: bold;">
                ${material.current_stock} ${material.unit_type}
              </span>
            </div>
            <div class="info-row"><strong>Minimum Stock:</strong> ${material.min_stock} ${material.unit_type}</div>
            <div class="info-row"><strong>Maximum Stock:</strong> ${material.max_stock ? material.max_stock + ' ' + material.unit_type : 'Not set'}</div>
            <div class="info-row">
              <strong>Status:</strong> 
              <span class="status-badge ${Utils.getStockStatusClass(material.stock_status)}">
                ${material.stock_status || "In Stock"}
              </span>
            </div>
            <div class="info-row"><strong>Description:</strong> ${Utils.escapeHtml(material.description || "No description available")}</div>
            <div class="info-row"><strong>Created:</strong> ${new Date(material.created_at).toLocaleDateString("en-PH")}</div>
            <div class="info-row"><strong>Last Updated:</strong> ${new Date(material.updated_at).toLocaleDateString("en-PH")}</div>
          </div>
        </div>
        
        ${this.modal.createActions([
          {
            text: "Restock Material",
            icon: "fas fa-plus",
            className: "btn-success",
            onclick: `window.rawMaterialsModule.restockMaterial(${material.id}); window.modalManager.close();`,
          },
          {
            text: "Edit Material",
            icon: "fas fa-edit",
            className: "btn-primary",
            onclick: `window.rawMaterialsModule.editMaterial(${material.id}); window.modalManager.close();`,
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

    this.modal.open("Raw Material Details", content)
    this.toast.success("Material details loaded successfully")
  }

  async editMaterial(id) {
    try {
      this.toast.info("Loading material for editing...")
      const response = await fetch(`/Capstone2/api/raw_materials.php?id=${id}`)
      const result = await response.json()

      if (result.success) {
        this.showEditMaterialModal(result.data)
      } else {
        this.toast.error("Error loading material: " + result.error)
      }
    } catch (error) {
      console.error("Error loading material for editing:", error)
      this.toast.error("Error loading material for editing: " + error.message)
    }
  }

  showEditMaterialModal(material) {
    const content = `
      ${this.modal.createHeader('<i class="fas fa-edit"></i> Edit Raw Material')}
      <div class="modal-body">
        <form id="editMaterialForm" onsubmit="window.rawMaterialsModule.updateMaterialFromForm(event, ${material.id})">
          <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label for="editMaterialName"><strong>Material Name: <span style="color: red;">*</span></strong></label>
              <input type="text" id="editMaterialName" required placeholder="Enter material name" value="${Utils.escapeHtml(material.name)}"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
            <div class="form-group">
              <label for="editMaterialCategory"><strong>Category: <span style="color: red;">*</span></strong></label>
              <input type="text" id="editMaterialCategory" required placeholder="e.g., Paper, Ink, Tools" value="${Utils.escapeHtml(material.category)}"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
            <div class="form-group">
              <label for="editMaterialSupplier"><strong>Supplier:</strong></label>
              <input type="text" id="editMaterialSupplier" placeholder="Supplier name" value="${Utils.escapeHtml(material.supplier || '')}"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
            <div class="form-group">
              <label for="editMaterialUnitType"><strong>Unit Type: <span style="color: red;">*</span></strong></label>
              <select id="editMaterialUnitType" required
                      style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
                <option value="">Select unit type</option>
                <option value="pieces" ${material.unit_type === 'pieces' ? 'selected' : ''}>Pieces</option>
                <option value="sheets" ${material.unit_type === 'sheets' ? 'selected' : ''}>Sheets</option>
                <option value="rolls" ${material.unit_type === 'rolls' ? 'selected' : ''}>Rolls</option>
                <option value="liters" ${material.unit_type === 'liters' ? 'selected' : ''}>Liters</option>
                <option value="kilograms" ${material.unit_type === 'kilograms' ? 'selected' : ''}>Kilograms</option>
                <option value="meters" ${material.unit_type === 'meters' ? 'selected' : ''}>Meters</option>
                <option value="boxes" ${material.unit_type === 'boxes' ? 'selected' : ''}>Boxes</option>
                <option value="packs" ${material.unit_type === 'packs' ? 'selected' : ''}>Packs</option>
              </select>
            </div>
            <div class="form-group">
              <label for="editMaterialUnitCost"><strong>Unit Cost (₱): <span style="color: red;">*</span></strong></label>
              <input type="number" id="editMaterialUnitCost" step="0.01" min="0" required placeholder="0.00" value="${material.unit_cost}"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
            <div class="form-group">
              <label for="editMaterialCurrentStock"><strong>Current Stock: <span style="color: red;">*</span></strong></label>
              <input type="number" id="editMaterialCurrentStock" step="0.01" min="0" required placeholder="0" value="${material.current_stock}"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
              <small style="color: #666; font-size: 0.8rem;">Current: ${material.current_stock} ${material.unit_type}</small>
            </div>
            <div class="form-group">
              <label for="editMaterialMinStock"><strong>Minimum Stock:</strong></label>
              <input type="number" id="editMaterialMinStock" step="0.01" min="0" placeholder="10" value="${material.min_stock}"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
            <div class="form-group">
              <label for="editMaterialMaxStock"><strong>Maximum Stock:</strong></label>
              <input type="number" id="editMaterialMaxStock" step="0.01" min="0" placeholder="Optional" value="${material.max_stock || ''}"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;">
            </div>
          </div>
          
          <div class="form-group">
            <label for="editMaterialDescription"><strong>Description:</strong></label>
            <textarea id="editMaterialDescription" rows="3" placeholder="Enter material description (optional)..."
                      style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem; resize: vertical;">${Utils.escapeHtml(material.description || '')}</textarea>
          </div>

          ${this.modal.createActions([
            {
              type: "submit",
              text: "Update Material",
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

    this.modal.open("Edit Raw Material", content, { preventOutsideClick: true })
  }

  async updateMaterialFromForm(event, materialId) {
    event.preventDefault()

    try {
      this.toast.info("Updating raw material...")

      const formData = {
        name: document.getElementById("editMaterialName").value.trim(),
        category: document.getElementById("editMaterialCategory").value.trim(),
        supplier: document.getElementById("editMaterialSupplier").value.trim(),
        unit_type: document.getElementById("editMaterialUnitType").value,
        unit_cost: parseFloat(document.getElementById("editMaterialUnitCost").value),
        current_stock: parseFloat(document.getElementById("editMaterialCurrentStock").value),
        min_stock: parseFloat(document.getElementById("editMaterialMinStock").value) || 10,
        max_stock: document.getElementById("editMaterialMaxStock").value ? parseFloat(document.getElementById("editMaterialMaxStock").value) : null,
        description: document.getElementById("editMaterialDescription").value.trim(),
      }

      // Validate form data
      if (!formData.name || !formData.category || !formData.unit_type || formData.unit_cost < 0 || formData.current_stock < 0) {
        this.toast.error("Please fill in all required fields with valid values")
        return
      }

      const result = await this.updateMaterial(materialId, formData)

      if (result.success) {
        this.toast.success("Raw material updated successfully!")
        this.modal.close()
        await this.loadRawMaterials()
      } else {
        this.toast.error("Error updating raw material: " + result.error)
      }
    } catch (error) {
      console.error("Error updating raw material:", error)
      this.toast.error("Error updating raw material: " + error.message)
    }
  }

  getStockColor(stock, minStock = 10) {
    if (stock <= 0) return "#dc3545"
    if (stock <= minStock) return "#ffc107"
    return "#28a745"
  }
}
