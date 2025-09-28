// Modal management system
export class ModalManager {
    constructor() {
      this.overlay = document.getElementById("modalOverlay")
      this.content = document.getElementById("modalContent")
      this.preventOutsideClick = false
      this.setupEventListeners()
    }
  
    setupEventListeners() {
      if (this.overlay) {
        this.overlay.addEventListener("click", (e) => {
          if (e.target === this.overlay && !this.preventOutsideClick) {
            this.close()
          }
        })
      }
  
      // ESC key to close modal
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && this.isOpen()) {
          this.close()
        }
      })
    }
  
    open(title, content, options = {}) {
      if (!this.overlay || !this.content) return
  
      this.preventOutsideClick = options.preventOutsideClick || false
      this.content.innerHTML = content
      this.overlay.style.display = "flex"
      this.overlay.classList.add("active")
  
      // Focus management
      const firstFocusable = this.content.querySelector("input, button, select, textarea")
      if (firstFocusable) {
        firstFocusable.focus()
      }
    }
  
    close() {
      if (!this.overlay) return
  
      this.overlay.style.display = "none"
      this.overlay.classList.remove("active")
    }
  
    isOpen() {
      return this.overlay && this.overlay.classList.contains("active")
    }
  
    createHeader(title, showCloseButton = true) {
      return `
        <div class="modal-header">
          <h3>${title}</h3>
          ${
            showCloseButton
              ? `<button class="modal-close" onclick="window.modalManager.close()">×</button>`
              : ""
          }
        </div>
      `
    }
  
    createActions(buttons) {
      const buttonHtml = buttons
        .map(
          (btn) => `
        <button type="${btn.type || "button"}" 
                class="btn ${btn.className || ""}" 
                onclick="${btn.onclick || ""}"
                ${btn.disabled ? "disabled" : ""}>
          ${btn.icon ? `<i class="${btn.icon}"></i>` : ""} ${btn.text}
        </button>
      `,
        )
        .join("")
  
      return `
        <div class="modal-actions" style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end;">
          ${buttonHtml}
        </div>
      `
    }

    // Confirmation dialog method
    confirm(title, message, confirmText = 'Confirm', cancelText = 'Cancel', type = 'default') {
      return new Promise((resolve) => {
        const confirmId = 'confirm_' + Date.now()
        const cancelId = 'cancel_' + Date.now()
        
        const typeClass = type === 'danger' ? 'btn-danger' : 'btn-primary'
        const iconClass = type === 'danger' ? 'fas fa-exclamation-triangle' : 'fas fa-question-circle'
        
        const content = `
          ${this.createHeader(title)}
          <div class="modal-body">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
              <i class="${iconClass}" style="font-size: 2rem; color: ${type === 'danger' ? '#dc3545' : '#007bff'};"></i>
              <p style="margin: 0; font-size: 1.1rem;">${message}</p>
            </div>
            <div class="modal-actions" style="display: flex; gap: 1rem; justify-content: flex-end;">
              <button type="button" class="btn btn-secondary" id="${cancelId}">
                <i class="fas fa-times"></i> ${cancelText}
              </button>
              <button type="button" class="btn ${typeClass}" id="${confirmId}">
                <i class="fas fa-check"></i> ${confirmText}
              </button>
            </div>
          </div>
        `
        
        this.open(title, content, { preventOutsideClick: true })
        
        // Add event listeners
        document.getElementById(confirmId).addEventListener('click', () => {
          this.close()
          resolve(true)
        })
        
        document.getElementById(cancelId).addEventListener('click', () => {
          this.close()
          resolve(false)
        })
      })
    }
  }