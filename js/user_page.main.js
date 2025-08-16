import { ANIMATION_SETTINGS } from './modules/config-module.js';
import { csrfService } from './modules/csrf-module.js';
import './modules/ui-module.js';
import { FormManager } from './modules/form-module.js';
import { UserDropdownManager } from './modules/user-dropdown-module.js';
import { AccountManager } from './modules/account-module.js';
import { AIImageGenerator } from './modules/ai-image-generator-module.js';
import { AIPhotoEditor } from './modules/ai-photo-editor-module.js';
import { AIDropdownManager } from './modules/ai-dropdown-module.js';
import SupportMessaging from './modules/support-module.js';

class UserPageApp {
    constructor() {
        this.formManager = null;
        this.userDropdownManager = null;
        this.accountManager = null;
        this.aiImageGenerator = null;
        this.aiPhotoEditor = null;
        this.aiDropdownManager = null;
        this.supportMessaging = null;
        
        this.init();
    }
    
    async init() {
        // Load CSRF token first
        await csrfService.load();
        
        // Initialize all modules
        this.initializeModules();
        
        // Make instances globally available for backward compatibility
        this.exposeGlobalInstances();
        
        console.log('User Page Application initialized successfully');
    }
    
    initializeModules() {
        // Core functionality
        this.formManager = new FormManager();
        this.userDropdownManager = new UserDropdownManager();
        this.accountManager = new AccountManager();
        
        // AI functionality
        this.aiImageGenerator = new AIImageGenerator();
        this.aiPhotoEditor = new AIPhotoEditor();
        this.aiDropdownManager = new AIDropdownManager();
        
        // Support functionality
        this.supportMessaging = new SupportMessaging();
    }
    
    exposeGlobalInstances() {
        // Expose instances globally for backward compatibility and inter-module communication
        window.formManager = this.formManager;
        window.userDropdownManager = this.userDropdownManager;
        window.accountManager = this.accountManager;
        window.aiImageGenerator = this.aiImageGenerator;
        window.aiPhotoEditor = this.aiPhotoEditor;
        window.aiDropdownManager = this.aiDropdownManager;
        window.supportMessaging = this.supportMessaging;
        
        // Expose CSRF service
        window.csrfService = csrfService;
    }
}

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize after a small delay to ensure all other elements are ready
    setTimeout(() => {
        window.userPageApp = new UserPageApp();
    }, ANIMATION_SETTINGS.INITIALIZATION_DELAY);
});
