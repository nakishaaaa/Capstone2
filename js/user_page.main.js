import { ANIMATION_SETTINGS } from './modules/config-module.js';
import { csrfService } from './modules/csrf-module.js';
import { ApiClient } from './core/api-client.js';
import { SSEClient } from './core/sse-client.js';
import './modules/ui-module.js';
import { FormManager } from './modules/form-module.js';
import { UserDropdownManager } from './modules/user-dropdown-module.js';
import { AccountManager } from './modules/account-module.js';
import { AIImageGenerator } from './modules/ai-image-generator-module.js';
import { AIPhotoEditor } from './modules/ai-photo-editor-module.js';
import { AIDropdownManager } from './modules/ai-dropdown-module.js';
import SupportMessaging from './modules/support-module.js';
import { DevTicketManager } from './modules/dev-ticket-module.js';
import UserSupportTicketsModule from './modules/user-support-tickets-module.js';

class UserPageApp {
    constructor() {
        this.formManager = null;
        this.userDropdownManager = null;
        this.accountManager = null;
        this.aiImageGenerator = null;
        this.aiPhotoEditor = null;
        this.aiDropdownManager = null;
        this.supportMessaging = null;
        this.devTicketManager = null;
        this.userSupportTickets = null;
        this.apiClient = new ApiClient();
        this.sseClient = null;
        
        this.init();
    }
    
    async init() {
        // Load CSRF token first
        await csrfService.load();
        
        // Initialize SSE client for real-time updates
        this.initializeSSE();
        
        // Initialize all modules
        this.initializeModules();
        
        // Make instances globally available for backward compatibility
        this.exposeGlobalInstances();
        
        // Define global logout handler
        this.defineLogoutHandler();
        
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
        this.devTicketManager = new DevTicketManager();
        this.userSupportTickets = new UserSupportTicketsModule(this.sseClient);
        this.userSupportTickets.init();
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
        window.devTicketManager = this.devTicketManager;
        
        // Expose CSRF service
        window.csrfService = csrfService;
        window.apiClient = this.apiClient;
    }

    initializeSSE() {
        try {
            // Initialize Pusher for real-time messaging
            this.pusher = new Pusher('5f2e092f1e11a34b880f', {
                cluster: 'ap1',
                encrypted: true
            });
            
            // Subscribe to support channel
            this.channel = this.pusher.subscribe('support-channel');
            
            // Handle new developer replies (for tickets)
            this.channel.bind('new-developer-reply', (data) => {
                console.log('User Page: New developer reply received', data);
                
                // Handle ticket notifications
                if (this.userSupportTickets && data.conversation_id) {
                    this.userSupportTickets.handleNewReplyNotification(data);
                }
            });
            
            // Handle new admin replies (for regular customer support)
            this.channel.bind('new-admin-reply', (data) => {
                console.log('User Page: New admin reply received', data);
                
                // Refresh support messaging if viewing
                if (this.supportMessaging) {
                    this.supportMessaging.refreshMessages();
                }
            });
            
            // Handle connection events
            this.pusher.connection.bind('connected', () => {
                console.log('User Page: Real-time connection established');
            });
            
            this.pusher.connection.bind('error', (err) => {
                console.error('User Page: Connection error', err);
            });
            
            console.log('User Page: Pusher initialized successfully');
        } catch (error) {
            console.error('User Page: Failed to initialize Pusher:', error);
        }
    }
    
    handleAdminReplies(data) {
        console.log('User Page: Admin replies received:', data);
        console.log('User Page: Number of messages:', data.messages?.length);
        console.log('User Page: supportMessaging exists:', !!this.supportMessaging);
        
        // Show browser notification
        if (data.messages && data.messages.length > 0) {
            data.messages.forEach(msg => {
                this.showNotification('New Reply from Support', {
                    body: `${msg.admin_name}: ${msg.message.substring(0, 100)}...`,
                    icon: '/favicon.ico',
                    tag: 'admin-reply-' + msg.conversation_id
                });
            });
        }

        // Always update support messaging when admin replies are received
        if (this.supportMessaging) {
            console.log('User Page: Calling refreshMessages() on supportMessaging');
            this.supportMessaging.refreshMessages();
        } else {
            console.error('User Page: supportMessaging is not available!');
        }

        // Update support tickets if viewing tickets
        if (this.userSupportTickets && this.isCurrentlyViewingTickets()) {
            this.userSupportTickets.refreshTickets();
        }
    }

    showNotification(title, options = {}) {
        if ('Notification' in window) {
            if (Notification.permission === 'granted') {
                new Notification(title, options);
            } else if (Notification.permission !== 'denied') {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        new Notification(title, options);
                    }
                });
            }
        }
    }

    isCurrentlyViewingSupport() {
        // Check if user is currently viewing support section
        return document.querySelector('#support-section')?.style.display !== 'none';
    }

    isCurrentlyViewingTickets() {
        // Check if user is currently viewing support tickets
        return document.querySelector('#support-tickets-section')?.style.display !== 'none';
    }

    defineLogoutHandler() {
        // Expose a global logout function used by onclick handlers in the page
        const api = this.apiClient;
        window.handleLogout = async (role = 'user') => {
            try {
                // Close SSE connection before logout
                if (this.sseClient) {
                    this.sseClient.close();
                }
                
                // Ensure CSRF token is available
                await csrfService.ensure();
                const token = csrfService.getToken();

                const data = await api.request('logout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': token || ''
                    },
                    credentials: 'include',
                    body: JSON.stringify({ role })
                });

                if (data && data.success) {
                    window.location.href = 'index.php';
                } else {
                    alert(data?.error || 'Logout failed. Redirecting to login...');
                    window.location.href = 'index.php';
                }
            } catch (error) {
                console.error('Logout error:', error);
                window.location.href = 'index.php';
            }
        };
    }
}

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize after a small delay to ensure all other elements are ready
    setTimeout(() => {
        window.userPageApp = new UserPageApp();
    }, ANIMATION_SETTINGS.INITIALIZATION_DELAY);
});
