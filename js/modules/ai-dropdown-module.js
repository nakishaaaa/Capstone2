/**
 * AI Dropdown Module
 * Handles AI tools dropdown functionality
 */

export class AIDropdownManager {
    constructor() {
        this.aiTrigger = null;
        this.aiToolsContainer = null;
        this.aiOptions = null;
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
        this.aiToolsContainer = document.getElementById('aiToolsContainer');
        this.aiOptions = document.getElementById('aiOptions');
        this.aiGeneratorOption = document.getElementById('aiGeneratorOption');
        this.aiEditorOption = document.getElementById('aiEditorOption');
    }
    
    setupEventListeners() {
        if (this.aiTrigger) {
            this.aiTrigger.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleOptions();
            });
        }
        
        // AI generator option
        if (this.aiGeneratorOption) {
            this.aiGeneratorOption.addEventListener('click', () => {
                if (window.aiImageGenerator) {
                    window.aiImageGenerator.showModal();
                }
                this.closeOptions();
            });
        }
        
        // AI editor option
        if (this.aiEditorOption) {
            this.aiEditorOption.addEventListener('click', () => {
                if (window.aiPhotoEditor) {
                    window.aiPhotoEditor.showModal();
                }
                this.closeOptions();
            });
        }
        
        // Close options when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.ai-tools-container')) {
                this.closeOptions();
            }
        });
        
        // Close options on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeOptions();
            }
        });
    }
    
    toggleOptions() {
        if (this.aiToolsContainer && this.aiOptions) {
            const isExpanded = this.aiToolsContainer.classList.contains('expanded');
            
            if (isExpanded) {
                this.closeOptions();
            } else {
                this.openOptions();
            }
        }
    }
    
    openOptions() {
        if (this.aiToolsContainer) this.aiToolsContainer.classList.add('expanded');
        if (this.aiOptions) this.aiOptions.classList.add('show');
    }
    
    closeOptions() {
        if (this.aiToolsContainer) this.aiToolsContainer.classList.remove('expanded');
        if (this.aiOptions) this.aiOptions.classList.remove('show');
    }
}

// Global function for backward compatibility
window.closeAIDropdown = function() {
    if (window.aiDropdownManager) {
        window.aiDropdownManager.closeOptions();
    }
};

// Remove the DOMContentLoaded listener since the module is now imported
// The main app will handle initialization
