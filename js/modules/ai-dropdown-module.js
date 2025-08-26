/**
 * AI Dropdown Module
 * Handles AI tools dropdown functionality
 */

export class AIDropdownManager {
    constructor() {
        this.aiTrigger = null;
        this.aiDropdownMenu = null;
        this.aiGeneratorOption = null;
        this.aiEditorOption = null;
        
        this.init();
    }
    
    init() {
        this.initializeElements();
        this.setupEventListeners();
    }
    
    initializeElements() {
        this.aiTrigger = document.getElementById('aiTrigger');
        this.aiDropdownMenu = document.getElementById('aiDropdownMenu');
        this.aiGeneratorOption = document.getElementById('aiGeneratorOption');
        this.aiEditorOption = document.getElementById('aiEditorOption');
    }
    
    setupEventListeners() {
        if (this.aiTrigger) {
            this.aiTrigger.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleDropdown();
            });
        }
        
        // AI generator option
        if (this.aiGeneratorOption) {
            this.aiGeneratorOption.addEventListener('click', () => {
                if (window.aiImageGenerator) {
                    window.aiImageGenerator.showModal();
                }
                this.closeDropdown();
            });
        }
        
        // AI editor option
        if (this.aiEditorOption) {
            this.aiEditorOption.addEventListener('click', () => {
                if (window.aiPhotoEditor) {
                    window.aiPhotoEditor.showModal();
                }
                this.closeDropdown();
            });
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.ai-dropdown')) {
                this.closeDropdown();
            }
        });
        
        // Close dropdown on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeDropdown();
            }
        });
    }
    
    toggleDropdown() {
        if (this.aiDropdownMenu && this.aiTrigger) {
            const isOpen = this.aiDropdownMenu.classList.contains('show');
            
            if (isOpen) {
                this.closeDropdown();
            } else {
                this.openDropdown();
            }
        }
    }
    
    openDropdown() {
        if (this.aiTrigger) this.aiTrigger.classList.add('active');
        if (this.aiDropdownMenu) this.aiDropdownMenu.classList.add('show');
    }
    
    closeDropdown() {
        if (this.aiTrigger) this.aiTrigger.classList.remove('active');
        if (this.aiDropdownMenu) this.aiDropdownMenu.classList.remove('show');
    }
}

// Global function for backward compatibility
window.closeAIDropdown = function() {
    if (window.aiDropdownManager) {
        window.aiDropdownManager.closeDropdown();
    }
};

// Remove the DOMContentLoaded listener since the module is now imported
// The main app will handle initialization
