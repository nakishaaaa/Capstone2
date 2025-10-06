// Server-Sent Events (SSE) client for real-time updates
export class SSEClient {
  constructor(url, options = {}) {
    this.url = url
    this.eventSource = null
    this.reconnectAttempts = 0
    this.maxReconnectAttempts = options.maxReconnectAttempts || 5
    this.reconnectDelay = options.reconnectDelay || 1000
    this.maxReconnectDelay = options.maxReconnectDelay || 30000
    this.listeners = new Map()
    this.isConnected = false
    this.shouldReconnect = true
    
    this.connect()
  }

  connect() {
    try {
      console.log('SSE: Attempting to connect to', this.url)
      
      this.eventSource = new EventSource(this.url, {
        withCredentials: true
      })

      this.eventSource.onopen = (event) => {
        console.log('SSE: Connection opened')
        this.isConnected = true
        this.reconnectAttempts = 0
        this.reconnectDelay = 1000
        this.emit('connection', { status: 'connected' })
      }

      this.eventSource.onerror = (event) => {
        console.error('SSE: Connection error', event)
        this.isConnected = false
        this.emit('connection', { status: 'error', event })
        
        if (this.shouldReconnect && this.reconnectAttempts < this.maxReconnectAttempts) {
          this.scheduleReconnect()
        }
      }

      this.eventSource.onmessage = (event) => {
        try {
          const data = JSON.parse(event.data)
          this.emit('message', data)
        } catch (error) {
          console.error('SSE: Error parsing message data', error)
        }
      }

      // Handle custom events
      this.setupEventHandlers()

    } catch (error) {
      console.error('SSE: Failed to create EventSource', error)
      if (this.shouldReconnect) {
        this.scheduleReconnect()
      }
    }
  }

  setupEventHandlers() {
    // Stats update event
    this.eventSource.addEventListener('stats_update', (event) => {
      try {
        const data = JSON.parse(event.data)
        this.emit('stats_update', data)
      } catch (error) {
        console.error('SSE: Error parsing stats_update data', error)
      }
    })

    // Activity update event
    this.eventSource.addEventListener('activity_update', (event) => {
      try {
        const data = JSON.parse(event.data)
        this.emit('activity_update', data)
      } catch (error) {
        console.error('SSE: Error parsing activity_update data', error)
      }
    })

    // Connection events
    this.eventSource.addEventListener('connected', (event) => {
      try {
        const data = JSON.parse(event.data)
        console.log('SSE: Server confirmed connection', data)
        this.emit('connected', data)
      } catch (error) {
        console.error('SSE: Error parsing connected data', error)
      }
    })

    // Heartbeat event
    this.eventSource.addEventListener('heartbeat', (event) => {
      try {
        const data = JSON.parse(event.data)
        this.emit('heartbeat', data)
      } catch (error) {
        console.error('SSE: Error parsing heartbeat data', error)
      }
    })

    // Timeout event
    this.eventSource.addEventListener('timeout', (event) => {
      try {
        const data = JSON.parse(event.data)
        console.warn('SSE: Connection timeout', data)
        this.emit('timeout', data)
        // Instead of permanently closing, attempt a reconnect
        if (this.eventSource) {
          this.eventSource.close()
          this.eventSource = null
        }
        this.isConnected = false
        if (this.shouldReconnect && this.reconnectAttempts < this.maxReconnectAttempts) {
          this.scheduleReconnect()
        }
      } catch (error) {
        console.error('SSE: Error parsing timeout data', error)
      }
    })

    // Memory warning event
    this.eventSource.addEventListener('memory_warning', (event) => {
      try {
        const data = JSON.parse(event.data)
        console.warn('SSE: Memory warning', data)
        this.emit('memory_warning', data)
      } catch (error) {
        console.error('SSE: Error parsing memory_warning data', error)
      }
    })

    // Real-time notifications event
    this.eventSource.addEventListener('realtime_notifications', (event) => {
      try {
        const data = JSON.parse(event.data)
        console.log('SSE: Real-time notifications received', data)
        this.emit('realtime_notifications', data)
      } catch (error) {
        console.error('SSE: Error parsing realtime_notifications data', error)
      }
    })
  }

  scheduleReconnect() {
    if (!this.shouldReconnect) return

    this.reconnectAttempts++
    console.log(`SSE: Scheduling reconnect attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts} in ${this.reconnectDelay}ms`)

    setTimeout(() => {
      if (this.shouldReconnect) {
        this.close()
        this.connect()
      }
    }, this.reconnectDelay)

    // Exponential backoff with maximum delay
    this.reconnectDelay = Math.min(this.reconnectDelay * 2, this.maxReconnectDelay)
  }

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

  emit(event, data) {
    if (this.listeners.has(event)) {
      this.listeners.get(event).forEach(callback => {
        try {
          callback(data)
        } catch (error) {
          console.error(`SSE: Error in event handler for ${event}:`, error)
        }
      })
    }
  }

  close() {
    this.shouldReconnect = false
    if (this.eventSource) {
      console.log('SSE: Closing connection')
      this.eventSource.close()
      this.eventSource = null
    }
    this.isConnected = false
    this.emit('connection', { status: 'closed' })
  }

  getConnectionStatus() {
    return {
      isConnected: this.isConnected,
      reconnectAttempts: this.reconnectAttempts,
      readyState: this.eventSource ? this.eventSource.readyState : null
    }
  }
}
