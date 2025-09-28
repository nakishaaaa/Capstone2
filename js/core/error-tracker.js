/**
 * Global Error Tracking System
 * Captures and reports JavaScript errors, network failures, and other client-side issues
 */

class ErrorTracker {
    constructor() {
        this.errorQueue = [];
        this.isOnline = navigator.onLine;
        this.userId = this.getCurrentUserId();
        this.setupErrorHandlers();
        this.setupNetworkMonitoring();
    }

    getCurrentUserId() {
        // Try to get user ID from various sources
        if (window.currentUserId) return window.currentUserId;
        if (window.userId) return window.userId;
        
        // Try to extract from session storage or local storage
        try {
            const userData = sessionStorage.getItem('userData') || localStorage.getItem('userData');
            if (userData) {
                const parsed = JSON.parse(userData);
                return parsed.id || parsed.user_id;
            }
        } catch (e) {
            // Silent fail
        }
        
        return null;
    }

    setupErrorHandlers() {
        // Global JavaScript error handler
        window.addEventListener('error', (event) => {
            this.logError({
                type: 'javascript_error',
                error: event.message,
                file: event.filename,
                line: event.lineno,
                column: event.colno,
                stack: event.error ? event.error.stack : '',
                url: window.location.href,
                timestamp: new Date().toISOString()
            });
        });

        // Unhandled promise rejection handler
        window.addEventListener('unhandledrejection', (event) => {
            this.logError({
                type: 'promise_rejection',
                error: event.reason ? event.reason.toString() : 'Unhandled promise rejection',
                file: 'unknown',
                line: 0,
                column: 0,
                stack: event.reason && event.reason.stack ? event.reason.stack : '',
                url: window.location.href,
                timestamp: new Date().toISOString()
            });
        });

        // Resource loading errors
        window.addEventListener('error', (event) => {
            if (event.target !== window) {
                this.logError({
                    type: 'resource_error',
                    error: `Failed to load resource: ${event.target.src || event.target.href}`,
                    file: event.target.src || event.target.href || 'unknown',
                    line: 0,
                    column: 0,
                    stack: '',
                    url: window.location.href,
                    timestamp: new Date().toISOString()
                });
            }
        }, true);
    }

    setupNetworkMonitoring() {
        // Monitor network status
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.flushErrorQueue();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.logError({
                type: 'network_error',
                error: 'Network connection lost',
                file: 'network',
                line: 0,
                column: 0,
                stack: '',
                url: window.location.href,
                timestamp: new Date().toISOString()
            });
        });
    }

    logError(errorData) {
        // Add user ID if available
        errorData.user_id = this.userId;

        // Add to queue
        this.errorQueue.push(errorData);

        // Try to send immediately if online
        if (this.isOnline) {
            this.flushErrorQueue();
        }
    }

    async flushErrorQueue() {
        if (this.errorQueue.length === 0) return;

        const errorsToSend = [...this.errorQueue];
        this.errorQueue = [];

        for (const error of errorsToSend) {
            try {
                await this.sendError(error);
            } catch (e) {
                // Re-queue if failed to send
                this.errorQueue.push(error);
                console.warn('Failed to send error log:', e);
            }
        }
    }

    async sendError(errorData) {
        const response = await fetch('/Capstone2/api/log_client_error.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(errorData)
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return response.json();
    }

    // Manual error logging method
    logManualError(message, context = {}) {
        this.logError({
            type: 'manual_error',
            error: message,
            file: context.file || 'manual',
            line: context.line || 0,
            column: 0,
            stack: context.stack || new Error().stack,
            url: window.location.href,
            timestamp: new Date().toISOString(),
            context: JSON.stringify(context)
        });
    }

    // API call monitoring
    monitorApiCall(apiCall, context) {
        return apiCall.catch(error => {
            this.logError({
                type: 'api_error',
                error: `API call failed: ${error.message}`,
                file: context.endpoint || 'api',
                line: 0,
                column: 0,
                stack: error.stack || '',
                url: window.location.href,
                timestamp: new Date().toISOString(),
                context: JSON.stringify(context)
            });
            throw error; // Re-throw to maintain original behavior
        });
    }
}

// Initialize global error tracker
const errorTracker = new ErrorTracker();

// Make it globally available
window.errorTracker = errorTracker;

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ErrorTracker;
}
