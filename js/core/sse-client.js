// Server-Sent Events client for real-time updates
import { CONFIG } from "./config.js"

export class SSEClient {
  constructor() {
    this.eventSource = null
    this.reconnectAttempts = 0
    this.maxReconnectAttempts = 5
    this.reconnectDelay = 1000 // Start with 1 second
    this.listeners = new Map()
    this.isConnected = false
    this.heartbeatTimeout = null
  }

  // Connect to SSE endpoint
  connect() {
    if (this.eventSource) {
      this.disconnect()
    }

    console.log("Establishing SSE connection...")
    
    try {
      this.eventSource = new EventSource(`${CONFIG.API_BASE}realtime.php`)
      
      // Connection opened
      this.eventSource.onopen = (event) => {
        console.log("SSE connection established")
        this.isConnected = true
        this.reconnectAttempts = 0
        this.reconnectDelay = 1000
        this.emit('connected', { timestamp: Date.now() })
      }

      // Handle different event types
      this.eventSource.addEventListener('connected', (event) => {
        const data = JSON.parse(event.data)
        console.log("SSE server confirmed connection:", data.message)
        this.resetHeartbeatTimeout()
      })

      this.eventSource.addEventListener('stats_update', (event) => {
        const data = JSON.parse(event.data)
        // Removed frequent logging - only log if there are significant changes
        this.emit('stats_update', data)
        this.resetHeartbeatTimeout()
      })

      this.eventSource.addEventListener('activity_update', (event) => {
        const data = JSON.parse(event.data)
        // Only log when there's actual new activity
        if (data && data.length > 0) {
          console.log("New activity received:", data.length, "items")
        }
        this.emit('activity_update', data)
        this.resetHeartbeatTimeout()
      })

      this.eventSource.addEventListener('heartbeat', (event) => {
        const data = JSON.parse(event.data)
        // Removed frequent heartbeat logging - only reset timeout
        this.resetHeartbeatTimeout()
      })

      // Handle connection errors
      this.eventSource.onerror = (event) => {
        console.error("SSE connection error:", event)
        this.isConnected = false
        this.emit('disconnected', { reason: 'error' })
        
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
          this.scheduleReconnect()
        } else {
          console.error("Max reconnection attempts reached")
          this.emit('connection_failed', { attempts: this.reconnectAttempts })
        }
      }

    } catch (error) {
      console.error("Failed to create SSE connection:", error)
      this.scheduleReconnect()
    }
  }

  // Disconnect from SSE
  disconnect() {
    if (this.eventSource) {
      this.eventSource.close()
      this.eventSource = null
    }
    
    if (this.heartbeatTimeout) {
      clearTimeout(this.heartbeatTimeout)
      this.heartbeatTimeout = null
    }
    
    this.isConnected = false
    console.log("SSE connection closed")
  }

  // Schedule reconnection with exponential backoff
  scheduleReconnect() {
    this.reconnectAttempts++
    const delay = Math.min(this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1), 30000)
    
    console.log(`Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts})`)
    
    setTimeout(() => {
      if (this.reconnectAttempts <= this.maxReconnectAttempts) {
        this.connect()
      }
    }, delay)
  }

  // Reset heartbeat timeout (connection health check)
  resetHeartbeatTimeout() {
    if (this.heartbeatTimeout) {
      clearTimeout(this.heartbeatTimeout)
    }
    
    // If no heartbeat received in 60 seconds, consider connection dead
    this.heartbeatTimeout = setTimeout(() => {
      console.warn("SSE heartbeat timeout - connection may be dead")
      this.disconnect()
      this.scheduleReconnect()
    }, 60000)
  }

  // Event listener management
  on(event, callback) {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, [])
    }
    this.listeners.get(event).push(callback)
  }

  off(event, callback) {
    if (this.listeners.has(event)) {
      const callbacks = this.listeners.get(event)
      const index = callbacks.indexOf(callback)
      if (index > -1) {
        callbacks.splice(index, 1)
      }
    }
  }

  // Emit events to listeners
  emit(event, data) {
    if (this.listeners.has(event)) {
      this.listeners.get(event).forEach(callback => {
        try {
          callback(data)
        } catch (error) {
          console.error(`Error in SSE event listener for ${event}:`, error)
        }
      })
    }
  }

  // Get connection status
  getStatus() {
    return {
      connected: this.isConnected,
      reconnectAttempts: this.reconnectAttempts,
      readyState: this.eventSource ? this.eventSource.readyState : EventSource.CLOSED
    }
  }

  // Force reconnection
  reconnect() {
    this.disconnect()
    this.reconnectAttempts = 0
    this.connect()
  }
}
