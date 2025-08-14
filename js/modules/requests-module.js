import { SSEClient } from "../core/sse-client.js"

export class RequestsModule {
  constructor(toast, modal) {
    this.toast = toast
    this.modal = modal
    this.requests = []
    this.filteredRequests = []
    this.currentFilter = 'all'
    this.sseClient = null
    this.lastRequestsData = null
    this.isHistoryView = false
    this.historyData = []
    this.init()
  }

  init() {
    console.log('Initializing Requests Module...')
    this.setupEventListeners()
    this.initializeSSE()
  }

  setupEventListeners() {
    // Make functions globally accessible for onclick handlers
    window.refreshRequests = () => this.loadRequests()
    window.filterRequests = () => this.filterRequests()
    window.approveRequest = (id) => this.updateRequestStatus(id, 'approved')
    window.rejectRequest = (id) => this.updateRequestStatus(id, 'rejected')
    window.viewRequest = (id) => this.viewRequestDetails(id)
    window.viewRequestHistory = () => this.viewHistory()
    window.backToRequests = () => this.backToRequests()
  }

  async loadRequests() {
    try {
      console.log('Loading requests...')
      
      const response = await fetch('api/requests.php', {
        method: 'GET',
        credentials: 'include'
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data = await response.json()
      
      if (data.success) {
        this.requests = data.requests || []
        this.updateStats(data.stats)
        this.displayRequests()
        console.log('Requests loaded successfully:', this.requests.length)
      } else {
        throw new Error(data.message || 'Failed to load requests')
      }
    } catch (error) {
      console.error('Error loading requests:', error)
      this.toast.error('Failed to load requests: ' + error.message)
      this.displayError('Failed to load requests')
    }
  }

  updateStats(stats) {
    if (!stats) return
    
    document.getElementById('pending-requests').textContent = stats.pending || 0
    document.getElementById('approved-requests').textContent = stats.approved || 0
    document.getElementById('rejected-requests').textContent = stats.rejected || 0
    
    // Update dashboard total requests
    const totalRequestsElement = document.getElementById('total-requests')
    if (totalRequestsElement) {
      totalRequestsElement.textContent = stats.pending || 0
    }
  }



  filterRequests() {
    const filterSelect = document.getElementById('requestStatusFilter')
    if (!filterSelect) return

    this.currentFilter = filterSelect.value
    this.displayRequests()
  }

  async updateRequestStatus(requestId, status) {
    try {
      // Get CSRF token
      const csrfResponse = await fetch('api/csrf_token.php', {
        credentials: 'include'
      })
      const csrfData = await csrfResponse.json()
      
      if (!csrfData.success) {
        throw new Error('Failed to get CSRF token')
      }

      const response = await fetch('api/requests.php', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
          request_id: requestId,
          status: status,
          csrf_token: csrfData.token
        })
      })

      const data = await response.json()
      
      if (data.success) {
        this.toast.success(`Request ${status} successfully!`)
        await this.loadRequests() // Reload to get updated data
      } else {
        throw new Error(data.message || `Failed to ${status} request`)
      }
    } catch (error) {
      console.error(`Error updating request status:`, error)
      this.toast.error(`Failed to ${status} request: ` + error.message)
    }
  }

  viewRequestDetails(requestId) {
    const request = this.requests.find(r => r.id === requestId)
    if (!request) {
      this.toast.error('Request not found')
      return
    }

    const modalContent = `
      <div class="request-details-modal">
        <h3>Request Details #${request.id}</h3>
        <div class="request-info">
          <div class="info-row">
            <strong>Customer:</strong> ${this.escapeHtml(request.name || 'N/A')}
          </div>
          <div class="info-row">
            <strong>Contact:</strong> ${this.escapeHtml(request.contact_number || 'N/A')}
          </div>
          <div class="info-row">
            <strong>Service:</strong> ${this.formatCategory(request.category)}
          </div>
          <div class="info-row">
            <strong>Type/Size:</strong> ${this.escapeHtml(request.size)}
          </div>
          <div class="info-row">
            <strong>Quantity:</strong> ${request.quantity}
          </div>
          <div class="info-row">
            <strong>Status:</strong> <span class="status-badge ${request.status}">${request.status}</span>
          </div>
          <div class="info-row">
            <strong>Date Submitted:</strong> ${this.formatDate(request.created_at)}
          </div>
          ${request.notes ? `
            <div class="info-row">
              <strong>Notes:</strong> ${this.escapeHtml(request.notes)}
            </div>
          ` : ''}
          ${request.image_path ? `
            <div class="info-row">
              <strong>Attached Image:</strong><br>
              <img src="${request.image_path}" alt="Request Image" style="max-width: 300px; max-height: 200px; border-radius: 4px;">
            </div>
          ` : ''}
          ${request.admin_response ? `
            <div class="info-row">
              <strong>Admin Response:</strong> ${this.escapeHtml(request.admin_response)}
            </div>
          ` : ''}
        </div>
        ${request.status === 'pending' ? `
          <div class="modal-actions">
            <button class="btn btn-success" onclick="approveRequest(${request.id}); window.modalManager.close();">
              <i class="fas fa-check"></i> Approve
            </button>
            <button class="btn btn-danger" onclick="rejectRequest(${request.id}); window.modalManager.close();">
              <i class="fas fa-times"></i> Reject
            </button>
          </div>
        ` : ''}
      </div>
    `

    this.modal.open('Request Details', modalContent)
  }

  displayError(message) {
    const tbody = document.getElementById('requestsTableBody')
    if (tbody) {
      tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: 2rem; color: #dc3545;">${message}</td></tr>`
    }
  }

  formatDate(dateString) {
    if (!dateString) return 'N/A'
    const date = new Date(dateString)
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString()
  }

  formatCategory(category) {
    if (!category) return 'N/A'
    return category.split('-').map(word => 
      word.charAt(0).toUpperCase() + word.slice(1)
    ).join(' ')
  }

  escapeHtml(text) {
    if (!text) return ''
    const div = document.createElement('div')
    div.textContent = text
    return div.innerHTML
  }

  initializeSSE() {
    try {
      // Initialize SSE client for real-time updates
      this.sseClient = new SSEClient('api/realtime.php', {
        maxReconnectAttempts: 5,
        reconnectDelay: 2000,
        maxReconnectDelay: 30000
      })

      // Handle stats updates for requests
      this.sseClient.on('stats_update', (data) => {
        this.handleRealTimeStatsUpdate(data)
      })

      // Handle activity updates (new requests)
      this.sseClient.on('activity_update', (activity) => {
        this.handleRealTimeActivityUpdate(activity)
      })

      // Handle connection events
      this.sseClient.on('connection', (status) => {
        if (status.status === 'connected') {
          console.log('Requests: Real-time connection established')
        } else if (status.status === 'error') {
          console.warn('Requests: Real-time connection error, using manual refresh')
        }
      })

    } catch (error) {
      console.error('Requests: Failed to initialize SSE client:', error)
    }
  }

  handleRealTimeStatsUpdate(data) {
    try {
      // Check if requests data has changed
      if (this.hasRequestsDataChanged(data)) {
        console.log('Requests: Received real-time stats update', data)
        
        // Update request statistics
        if (data.requests) {
          const stats = {
            pending: data.requests.pending_requests || 0,
            approved: data.requests.approved_requests || 0,
            rejected: data.requests.rejected_requests || 0
          }
          this.updateStats(stats)
          
          // Show notification for new requests
          if (data.requests.pending_requests > (this.lastRequestsData?.requests?.pending_requests || 0)) {
            // Auto-refresh the requests list to show new requests
            this.loadRequests()
          }
        }
        
        this.lastRequestsData = data
      }
    } catch (error) {
      console.error('Requests: Error handling real-time stats update:', error)
    }
  }

  handleRealTimeActivityUpdate(activity) {
    try {
      // Check if there are new request activities
      const requestActivities = activity.filter(item => item.type === 'request')
      
      if (requestActivities.length > 0) {
        console.log('Requests: New request activity detected', requestActivities)
        
        // Show notification and refresh requests list
        this.toast.success('New customer request!')
        
        // Auto-refresh the requests list to show new requests
        setTimeout(() => {
          this.loadRequests()
        }, 500) // Small delay to ensure database is updated
      }
    } catch (error) {
      console.error('Requests: Error handling real-time activity update:', error)
    }
  }

  hasRequestsDataChanged(newData) {
    if (!this.lastRequestsData) return true
    
    // Compare requests data to detect changes
    const oldRequests = this.lastRequestsData.requests
    const newRequests = newData.requests
    
    return (
      oldRequests?.pending_requests !== newRequests?.pending_requests ||
      oldRequests?.total_requests !== newRequests?.total_requests
    )
  }

  async viewHistory() {
    try {
      console.log('Loading request history...')
      this.isHistoryView = true
      
      const response = await fetch('api/request_history.php', {
        method: 'GET',
        credentials: 'include'
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data = await response.json()
      
      if (data.success) {
        this.historyData = data.history || []
        this.displayHistory(data.stats)
        console.log('Request history loaded successfully:', this.historyData.length)
      } else {
        throw new Error(data.message || 'Failed to load request history')
      }
    } catch (error) {
      console.error('Error loading request history:', error)
      this.toast.error('Failed to load request history: ' + error.message)
      this.displayError('Failed to load request history')
    }
  }

  backToRequests() {
    console.log('Switching back to current requests view...')
    this.isHistoryView = false
    this.resetToRequestsView()
    this.loadRequests()
  }

  resetToRequestsView() {
    // Reset section header
    const sectionHeader = document.querySelector('#requests .section-header h1')
    if (sectionHeader) {
      sectionHeader.innerHTML = 'Customer Requests'
    }

    // Reset section description
    const sectionDescription = document.querySelector('#requests .section-description')
    if (sectionDescription) {
      sectionDescription.textContent = 'Manage customer service requests and inquiries'
    }

    // Restore original actions
    const requestsActions = document.querySelector('.requests-actions')
    if (requestsActions) {
      requestsActions.innerHTML = `
        <form method="POST" action="api/clear_requests.php" onsubmit="return confirm('Are you sure you want to clear all requests?');" style="margin: 0;">
          <button type="submit" class="btn btn-danger">
            Clear All Requests
          </button>
        </form>
        <button class="btn btn-secondary" onclick="viewRequestHistory()">
          View History
        </button>
      `
    }

    // Show filters
    const requestsFilters = document.querySelector('.requests-filters')
    if (requestsFilters) {
      requestsFilters.style.display = 'flex'
    }

    // Reset table headers
    const tableHead = document.querySelector('#requestsTable thead tr')
    if (tableHead) {
      tableHead.innerHTML = `
        <th>ID</th>
        <th>Date</th>
        <th>Customer</th>
        <th>Service</th>
        <th>Details</th>
        <th>Quantity</th>
        <th>Contact</th>
        <th>Status</th>
        <th>Actions</th>
      `
    }
  }

  displayHistory(stats) {
    // Update section header
    const sectionHeader = document.querySelector('#requests .section-header h1')
    if (sectionHeader) {
      sectionHeader.innerHTML = '<i class="fas fa-history"></i> Request History'
    }

    // Update section description
    const sectionDescription = document.querySelector('#requests .section-description')
    if (sectionDescription) {
      sectionDescription.textContent = 'View all cleared customer requests'
    }

    // Hide current requests actions and show back button
    const requestsActions = document.querySelector('.requests-actions')
    if (requestsActions) {
      requestsActions.innerHTML = `
        <button class="btn btn-secondary" onclick="backToRequests()">
          <i class="fas fa-arrow-left"></i> Back to Current Requests
        </button>
        <button class="btn btn-primary" onclick="viewRequestHistory()">
          <i class="fas fa-sync"></i> Refresh History
        </button>
      `
    }

    // Hide filters
    const requestsFilters = document.querySelector('.requests-filters')
    if (requestsFilters) {
      requestsFilters.style.display = 'none'
    }

    // Update stats
    if (stats) {
      document.getElementById('pending-requests').textContent = stats.pending || 0
      document.getElementById('approved-requests').textContent = stats.approved || 0
      document.getElementById('rejected-requests').textContent = stats.rejected || 0
    }

    // Display history table
    const tbody = document.getElementById('requestsTableBody')
    if (!tbody) return

    if (this.historyData.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="9" style="text-align: center; padding: 3rem;">
            <div style="color: #6c757d;">
              <i class="fas fa-history" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
              <h3>No Request History</h3>
              <p>No requests have been cleared yet.</p>
            </div>
          </td>
        </tr>
      `
      return
    }

    // Update table headers for history view
    const tableHead = document.querySelector('#requestsTable thead tr')
    if (tableHead) {
      tableHead.innerHTML = `
        <th>ID</th>
        <th>Customer</th>
        <th>Category</th>
        <th>Size/Type</th>
        <th>Quantity</th>
        <th>Contact</th>
        <th>Status</th>
        <th>Admin Response</th>
        <th>Submitted</th>
        <th>Cleared</th>
      `
    }

    tbody.innerHTML = this.historyData.map(request => `
      <tr>
        <td>#${request.id}</td>
        <td>${this.escapeHtml(request.name || 'N/A')}</td>
        <td>${this.formatCategory(request.category)}</td>
        <td>${this.escapeHtml(request.size || 'N/A')}</td>
        <td>${request.quantity || 'N/A'}</td>
        <td>${this.escapeHtml(request.contact_number || 'N/A')}</td>
        <td><span class="status-badge ${request.status}">${request.status}</span></td>
        <td class="admin-response">${this.escapeHtml(request.admin_response || 'No response')}</td>
        <td>${this.formatDate(request.created_at)}</td>
        <td>${this.formatDate(request.cleared_at)}</td>
      </tr>
    `).join('')
  }

  displayRequests() {
    const tbody = document.getElementById('requestsTableBody')
    if (!tbody) return

    if (this.requests.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 2rem;">No requests found</td></tr>'
      return
    }

    // Apply current filter
    this.filteredRequests = this.currentFilter === 'all' 
      ? this.requests 
      : this.requests.filter(request => request.status === this.currentFilter)

    tbody.innerHTML = this.filteredRequests.map(request => `
      <tr>
        <td>#${request.id}</td>
        <td>${this.formatDate(request.created_at)}</td>
        <td>${this.escapeHtml(request.name || 'N/A')}</td>
        <td>${this.formatCategory(request.category)}</td>
        <td class="request-details" title="${this.escapeHtml(request.size + (request.notes ? ' - ' + request.notes : ''))}">
          ${this.escapeHtml(request.size)}
        </td>
        <td>${request.quantity}</td>
        <td>${this.escapeHtml(request.contact_number || 'N/A')}</td>
        <td><span class="status-badge ${request.status}">${request.status}</span></td>
        <td>
          <div class="request-actions">
            ${request.status === 'pending' ? `
              <button class="btn-approve" onclick="approveRequest(${request.id})" title="Approve">
                <i class="fas fa-check"></i>
              </button>
              <button class="btn-reject" onclick="rejectRequest(${request.id})" title="Reject">
                <i class="fas fa-times"></i>
              </button>
            ` : ''}
            <button class="btn-view" onclick="viewRequest(${request.id})" title="View Details">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </td>
      </tr>
    `).join('')
  }

  destroy() {
    // Clean up SSE connection
    if (this.sseClient) {
      this.sseClient.close()
      this.sseClient = null
    }
    
    console.log('Requests module destroyed')
  }
}

export default RequestsModule
