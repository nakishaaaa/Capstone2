// Navigation and routing functionality
export class NavigationModule {
    constructor() {
      this.currentSection = "dashboard"
      this.setupEventListeners()
      this.handleInitialHash()
    }
  
    setupEventListeners() {
      // Navigation links
      document.querySelectorAll(".nav-link").forEach((link) => {
        link.addEventListener("click", (e) => {
          if (!link.classList.contains("logout")) {
            e.preventDefault()
            const section = link.getAttribute("data-section")
            if (section) {
              this.showSection(section, true)
            }
          }
        })
      })
  
      // Hash change events
      window.addEventListener("hashchange", () => this.handleHashChange())
    }
  
    handleInitialHash() {
      const hash = window.location.hash.substring(1)
      if (hash && document.getElementById(hash)) {
        this.showSection(hash)
      } else {
        this.showSection("dashboard")
        this.updateURL("dashboard")
      }
    }
  
    handleHashChange() {
      const hash = window.location.hash.substring(1)
      if (hash && document.getElementById(hash)) {
        this.showSection(hash, false)
      }
    }
  
    showSection(sectionId, updateURL = true) {
      console.log("Showing section:", sectionId)
  
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
        this.currentSection = sectionId
  
        // Add active class to nav link
        const navLink = document.querySelector(`[data-section="${sectionId}"]`)
        if (navLink) {
          navLink.classList.add("active")
        }
  
        // Update URL hash if requested
        if (updateURL) {
          window.history.pushState(null, null, "#" + sectionId)
        }
  
        // Trigger section load event
        this.onSectionChange(sectionId)
      }
    }
  
    updateURL(sectionId) {
      if (window.location.hash !== "#" + sectionId) {
        window.history.pushState(null, null, "#" + sectionId)
      }
    }
  
    onSectionChange(sectionId) {
      // Emit custom event for section changes
      const event = new CustomEvent("sectionChange", {
        detail: { sectionId, previousSection: this.currentSection },
      })
      document.dispatchEvent(event)
    }
  
    getCurrentSection() {
      return this.currentSection
    }
  }
  