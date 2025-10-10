/**
 * Pusher Client for Real-time Messaging
 * Works on Hostinger Premium/Shared hosting
 * Enhanced for dashboard stats and notifications
 */

export class PusherClient {
    constructor() {
        this.pusher = null;
        this.channels = new Map();
        this.listeners = new Map();
        this.userId = null;
        this.init();
    }
    
    init() {
        // Initialize Pusher
        this.pusher = new Pusher('5f2e092f1e11a34b880f', {
            cluster: 'ap1',
            encrypted: true
        });
        
        // Get user ID
        this.userId = window.userId || sessionStorage.getItem('userId');
        
        // Subscribe to support channel
        const supportChannel = this.pusher.subscribe('support-channel');
        this.channels.set('support-channel', supportChannel);
        
        // Subscribe to admin channel for request notifications (admin/super_admin only)
        const userRole = window.userRole || sessionStorage.getItem('userRole');
        if (userRole === 'admin' || userRole === 'super_admin') {
            const adminChannel = this.pusher.subscribe('admin-channel');
            this.channels.set('admin-channel', adminChannel);
            
            // Listen for new request notifications
            adminChannel.bind('new-request', (data) => {
                console.log('Pusher: New request notification received', data);
                this.emit('new_request', data);
            });
            
            console.log('Pusher: Subscribed to admin-channel');
        }
        
        // Subscribe to user-specific channel if user ID available
        if (this.userId) {
            const userChannel = this.pusher.subscribe(`user-${this.userId}`);
            this.channels.set(`user-${this.userId}`, userChannel);
            
            // Listen for stats updates on user channel
            userChannel.bind('stats-update', (data) => {
                console.log('Pusher: Stats update received', data);
                this.emit('stats_update', data);
            });
            
            // Listen for notification updates
            userChannel.bind('notification-update', (data) => {
                console.log('Pusher: Notification update received', data);
                this.emit('notification_update', data);
            });
            
            console.log(`Pusher: Subscribed to user-${this.userId}`);
        }
        
        // Set up event handlers
        this.setupEventHandlers();
        
        console.log('Pusher: Connected to real-time messaging');
    }
    
    setupEventHandlers() {
        // Handle connection state
        this.pusher.connection.bind('connected', () => {
            console.log('Pusher: Connection established');
            this.emit('connection', { status: 'connected' });
        });
        
        this.pusher.connection.bind('disconnected', () => {
            console.log('Pusher: Connection lost');
            this.emit('connection', { status: 'disconnected' });
        });
        
        this.pusher.connection.bind('error', (err) => {
            console.error('Pusher: Connection error', err);
            this.emit('connection', { status: 'error', error: err });
        });
        
        // Get support channel
        const supportChannel = this.channels.get('support-channel');
        if (supportChannel) {
            // Handle new customer messages (for developers/admins)
            supportChannel.bind('new-customer-message', (data) => {
                console.log('Pusher: New customer message received', data);
                this.emit('support_messages_update', {
                    messages: [data],
                    unread_count: 1
                });
            });
            
            // Handle new developer replies (for customers)
            supportChannel.bind('new-developer-reply', (data) => {
                console.log('Pusher: New developer reply received', data);
                this.emit('realtime_notifications', [{
                    type: 'support_new_reply',
                    data: data
                }]);
            });
        }
    }
    
    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);
        }
        this.listeners.get(event).push(callback);
    }
    
    emit(event, data) {
        if (this.listeners.has(event)) {
            this.listeners.get(event).forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`Pusher: Error in event handler for ${event}:`, error);
                }
            });
        }
    }
    
    disconnect() {
        if (this.pusher) {
            this.pusher.disconnect();
            console.log('Pusher: Disconnected');
        }
    }
}
