/**
 * UI Module
 * Shared UI utilities and modal management
 */

import { ANIMATION_SETTINGS } from './config-module.js';

export class UIManager {
    constructor() {
        this.modal = null;
        this.modalMessage = null;
        this.closeBtn = null;
        
        this.init();
    }
    
    init() {
        this.initializeElements();
        this.setupEventListeners();
    }
    
    initializeElements() {
        this.modal = document.getElementById('messageModal');
        this.modalMessage = document.getElementById('modalMessage');
        this.closeBtn = document.querySelector('#messageModal .close');
    }
    
    setupEventListeners() {
        // Modal close events
        if (this.closeBtn) {
            this.closeBtn.addEventListener('click', () => this.closeModal());
        }
        
        window.addEventListener('click', (event) => {
            if (event.target === this.modal) {
                this.closeModal();
            }
        });
    }
    
    showModal(type, message) {
        // Re-initialize elements in case they weren't available during initial load
        if (!this.modal || !this.modalMessage) {
            this.initializeElements();
        }
        
        if (!this.modalMessage || !this.modal) return;
        
        this.modalMessage.innerHTML = `
            <div class="alert alert-${type}">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                <span>${message}</span>
            </div>
        `;
        this.modal.style.display = 'block';
        
        // Re-setup event listeners if close button wasn't available before
        if (!this.closeBtn) {
            this.closeBtn = document.querySelector('#messageModal .close');
            if (this.closeBtn) {
                this.closeBtn.addEventListener('click', () => this.closeModal());
            }
        }
        
        // Auto-close success messages after 3 seconds
        if (type === 'success') {
            setTimeout(() => this.closeModal(), ANIMATION_SETTINGS.MODAL_AUTO_CLOSE_DELAY);
        }
    }
    
    closeModal() {
        if (this.modal) {
            this.modal.style.display = 'none';
        }
    }
}

// Create global instance and expose showModal function
const uiManager = new UIManager();
window.showModal = (type, message) => uiManager.showModal(type, message);

// Re-initialize when DOM is fully loaded as a fallback
document.addEventListener('DOMContentLoaded', () => {
    uiManager.initializeElements();
    uiManager.setupEventListeners();
});
