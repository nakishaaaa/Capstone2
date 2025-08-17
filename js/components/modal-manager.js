// Modal management system
export class ModalManager {
    constructor() {
      this.overlay = document.getElementById("modalOverlay")
      this.content = document.getElementById("modalContent")
      this.setupEventListeners()
    }
  
    setupEventListeners() {
      if (this.overlay) {
        this.overlay.addEventListener("click", (e) => {
          if (e.target === this.overlay) {
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
  
    open(title, content) {
      if (!this.overlay || !this.content) return
  
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
              ? `
            <button class="close-modal" onclick="window.modalManager.close()">
              <i class="fa-solid fa-xmark"></i>
            </button>
          `
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
  }
  