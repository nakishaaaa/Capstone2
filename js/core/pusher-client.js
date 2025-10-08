/**
 * Pusher Client for Real-time Messaging
 * Works on Hostinger Premium/Shared hosting
 */

export class PusherClient {
    constructor() {
        this.pusher = null;
        this.channel = null;
        this.listeners = new Map();
        this.init();
    }
    
    init() {
        // Initialize Pusher
        this.pusher = new Pusher('5f2e092f1e11a34b880f', {
            cluster: 'ap1',
            encrypted: true
        });
        
        // Subscribe to support channel
        this.channel = this.pusher.subscribe('support-channel');
        
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
        
        // Handle new customer messages (for developers/admins)
        this.channel.bind('new-customer-message', (data) => {
            console.log('Pusher: New customer message received', data);
            this.emit('support_messages_update', {
                messages: [data],
                unread_count: 1
            });
        });
        
        // Handle new developer replies (for customers)
        this.channel.bind('new-developer-reply', (data) => {
            console.log('Pusher: New developer reply received', data);
            this.emit('realtime_notifications', [{
                type: 'support_new_reply',
                data: data
            }]);
        });
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
