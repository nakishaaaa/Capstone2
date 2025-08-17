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
    // Notes expand/collapse toggle for long text
    window.toggleNote = (id) => this.toggleNote(id)
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

    const totalRequestsElement = document.getElementById('total-requests')
    if (totalRequestsElement) {
      totalRequestsElement.textContent = stats.pending || 0
    }

    this.updateRequestsBadge(stats.pending || 0)
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
          id: requestId,
          status: status,
          csrf_token: csrfData.token
        })
      })

      const data = await response.json()
      
      if (data.success) {
        const action = status === 'approved' ? 'approved' : status === 'rejected' ? 'rejected' : status
        this.toast.success(`Request ${action} successfully!`)
        await this.loadRequests()
      } else {
        const action = status === 'approved' ? 'approve' : status === 'rejected' ? 'reject' : status
        throw new Error((data.message || data.error) || `Failed to ${action} request`)
      }
    } catch (error) {
      console.error(`Error updating request status:`, error)
      const action = status === 'approved' ? 'approve' : status === 'rejected' ? 'reject' : status
      this.toast.error(`Failed to ${action} request: ` + error.message)
    }
  }

  viewRequestDetails(requestId) {
    const request = this.requests.find(r => r.id === requestId)
    if (!request) {
      this.toast.error('Request not found')
      return
    }

    // Clean leading whitespace/newlines so the note starts at the very top
    const cleanedNotes = this.escapeHtml((request.notes || '').trimStart())

    const modalContent = `
      <div class="request-details-modal" style="
        font-family: 'Segoe UI', Roboto, -apple-system, sans-serif;
        padding: 1.5rem;
        background: #ffffff;
        border-radius: 12px;
        border: none;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        max-width: 600px;
        margin: 1rem auto;
        color: #2d3748;
      ">
        <div class="request-modal-header" style="
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 1.25rem;
          padding-bottom: 0.75rem;
          border-bottom: 1px solid #e2e8f0;
        ">
          <h3 style="
            margin: 0;
            color: #1a202c;
            font-weight: 600;
            font-size: 1.375rem;
          ">
            Request #${request.id}
          </h3>
        </div>

        <div class="request-info" style="
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 1rem;
          margin-bottom: 1.25rem;
        ">
          <div class="info-item">
            <div style="font-size: 0.75rem; color: #718096; margin-bottom: 0.25rem;">Customer</div>
            <div style="font-weight: 500; color: #2d3748;">${this.escapeHtml(request.name || 'N/A')}</div>
          </div>
          <div class="info-item">
            <div style="font-size: 0.75rem; color: #718096; margin-bottom: 0.25rem;">Contact</div>
            <div style="font-weight: 500; color: #2d3748;">${this.escapeHtml(request.contact_number || 'N/A')}</div>
          </div>
          <div class="info-item">
            <div style="font-size: 0.75rem; color: #718096; margin-bottom: 0.25rem;">Service</div>
            <div style="font-weight: 500; color: #2d3748;">${this.formatCategory(request.category)}</div>
          </div>
          <div class="info-item">
            <div style="font-size: 0.75rem; color: #718096; margin-bottom: 0.25rem;">Type/Size</div>
            <div style="font-weight: 500; color: #2d3748;">${this.escapeHtml(request.size)}</div>
          </div>
          <!-- Notes section consolidated below (with Show more/less) -->
          
          ${request.image_path ? `
            <div class="info-item">
              <div style="font-size: 0.75rem; color: #718096; margin-bottom: 0.25rem;">Attached Image</div>
              <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:8px; display:flex; align-items:center; justify-content:center; min-height: 120px;">
                <img src="${this.escapeHtml(request.image_path)}" alt="Request Image" style="max-width:100%; max-height:200px; border-radius:6px; object-fit:contain;">
              </div>
              <div style="margin-top: 0.4rem; display: flex; justify-content: flex-start;">
                <a href="${this.escapeHtml(request.image_path)}" download target="_blank" rel="noopener" 
                   style="display:inline-flex; align-items:center; gap:0.4rem; padding: 0.4rem 0.75rem; border-radius: 6px; background: #f1f5f9; color: #1f2937; border: 1px solid #cbd5e1; text-decoration: none;">
                  <i class="fa-solid fa-download"></i>
                  Download image
                </a>
              </div>
            </div>
          ` : ''}

          <div class="info-item">
            <div style="font-size: 0.75rem; color: #718096; margin-bottom: 0.25rem;">Status</div>
            <span class="status-badge status-${request.status}" style="
              display: inline-block;
              padding: 0.25rem 0.5rem;
              border-radius: 6px;
              font-size: 0.7rem;
              font-weight: 600;
              letter-spacing: 0.3px;
              text-transform: uppercase;
              ${request.status === 'pending' ? 'background: #fffbeb; color: #b45309; border: 1px solid #fcd34d;' : ''}
              ${request.status === 'approved' ? 'background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7;' : ''}
              ${request.status === 'rejected' ? 'background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5;' : ''}
            ">
              ${request.status}
            </span>
          </div>
          ${request.notes ? `
            <div class="info-item" style="grid-column: span 2; margin-top: 0.5rem;">
              <div style="font-size: 0.75rem; color: #718096; margin-bottom: 0.25rem;">Notes</div>
              <div style="
                background: #f8fafc;
                padding: 0.3rem 0.75rem 0.75rem;
                border-radius: 8px;
                border: 1px solid #e2e8f0;
                color: #4a5568;
                position: relative;
              ">
                <div id="note-${request.id}" 
                     style="
                       max-height: 4.5rem;
                       overflow: hidden;
                       transition: max-height 0.3s ease;
                       white-space: pre-wrap;
                       word-break: break-word;
                       margin-right: 0.5rem;
                       line-height: 1.5;
                       display: block;
                       padding-top: 0;
                       margin-top: 0;
                     "
                     data-collapsed="true">${cleanedNotes}</div>
                <button id="toggle-note-${request.id}"
                        style="
                          background: none;
                          border: none;
                          color: #3b82f6;
                          cursor: pointer;
                          padding: 0.15rem 0 0;
                          font-size: 0.8rem;
                          font-weight: 500;
                          display: flex;
                          align-items: center;
                          gap: 0.25rem;
                          margin-top: 0.35rem;
                        ">
                  <i class="fa-solid fa-chevron-down" style="font-size: 0.7rem; transition: transform 0.2s;"></i>
                  Show more
                </button>
              </div>
            </div>
          ` : ''}

          ${request.admin_response ? `
            <div class="info-item" style="grid-column: span 2; margin-top: 0.5rem;">
              <div style="font-size: 0.75rem; color: #718096; margin-bottom: 0.25rem;">Admin Response</div>
              <div style="
                background: #f0f9ff;
                padding: 0.75rem;
                border-radius: 8px;
                border: 1px solid #bae6fd;
                color: #0369a1;
                line-height: 1.5;
              ">
                ${this.escapeHtml(request.admin_response)}
              </div>
            </div>
          ` : ''}
        </div>

        ${request.status === 'pending' ? `
          <div class="modal-actions" style="margin-top: 1.2rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
            <button class="btn btn-success" style="padding: 0.5rem 1rem; border-radius: 6px; background: #28a745; color: white; border: none; cursor: pointer; font-size: 0.9rem;"
                    onclick="approveRequest(${request.id}); window.modalManager.close();">
              <i class="fa-solid fa-check"></i> Approve
            </button>
            <button class="btn btn-danger" style="padding: 0.5rem 1rem; border-radius: 6px; background: #dc3545; color: white; border: none; cursor: pointer; font-size: 0.9rem;"
                    onclick="rejectRequest(${request.id}); window.modalManager.close();">
              <i class="fa-solid fa-xmark"></i> Reject
            </button>
          </div>
        ` : ''}
      </div>

    `

    this.modal.open('Request Details', modalContent)
    setTimeout(() => {
      const noteElement = document.getElementById(`note-${request.id}`)
      const toggleButton = document.getElementById(`toggle-note-${request.id}`)
      
      if (noteElement && toggleButton) {
  
        const textLen = (noteElement.textContent || '').trim().length
        const isOverflowing = noteElement.scrollHeight > (noteElement.clientHeight + 2)
        if (!isOverflowing && textLen < 50) {
          toggleButton.style.display = 'none'
        }
        
        toggleButton.addEventListener('click', (e) => {
          e.stopPropagation()
          const icon = toggleButton.querySelector('i')
          if (noteElement.dataset.collapsed === 'true') {
            noteElement.style.maxHeight = 'none'
            noteElement.dataset.collapsed = 'false'
            toggleButton.innerHTML = '<i class="fa-solid fa-chevron-up" style="font-size: 0.7rem; transition: transform 0.2s;"></i> Show less'
          } else {
            noteElement.style.maxHeight = '4.5rem'
            noteElement.dataset.collapsed = 'true'
            toggleButton.innerHTML = '<i class="fa-solid fa-chevron-down" style="font-size: 0.7rem; transition: transform 0.2s;"></i> Show more'
          }
        })
      }
    }, 0)
  }

  toggleNote(id) {
    const span = document.getElementById(`note-${id}`)
    const btn = document.getElementById(`toggle-note-${id}`)
    if (!span || !btn) return
    const collapsed = span.dataset.collapsed !== 'false'
    if (collapsed) {
      span.style.maxHeight = 'none'
      span.dataset.collapsed = 'false'
      btn.textContent = 'See less'
    } else {
      span.style.maxHeight = '4.5rem'
      span.dataset.collapsed = 'true'
      btn.textContent = 'See more'
    }
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

      // Handle activity updates (new requests) - only for actual requests, not support messages
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
      if (!this.lastActivityData) {
        this.lastActivityData = activity
        return 
      }
      
      const currentRequests = activity.filter(item => item.type === 'request')
      const previousRequests = this.lastActivityData.filter(item => item.type === 'request')
      
      const newRequests = currentRequests.filter(current => 
        !previousRequests.some(prev => prev.id === current.id)
      )
      
      console.log('Requests: New requests detected:', newRequests)
      
      if (newRequests.length > 0) {
        this.toast.success('New customer request!')
        
        setTimeout(() => {
          this.loadRequests()
        }, 500) 
      }
      
      this.lastActivityData = activity
    } catch (error) {
      console.error('Requests: Error handling real-time activity update:', error)
    }
  }

  hasRequestsDataChanged(newData) {
    if (!this.lastRequestsData) return true
    
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
      sectionHeader.innerHTML = '<i class="fa-solid fa-clock-rotate-left"></i> Request History'
    }

    // Update section description
    const sectionDescription = document.querySelector('#requests .section-description')
    if (sectionDescription) {
      sectionDescription.textContent = ''
    }

    // Hide current requests actions and show back button
    const requestsActions = document.querySelector('.requests-actions')
    if (requestsActions) {
      requestsActions.innerHTML = `
        <button class="btn btn-secondary" onclick="backToRequests()">
          <i class="fa-solid fa-arrow-left"></i> Back to Current Requests
        </button>
        <button class="btn btn-primary" onclick="viewRequestHistory()">
          <i class="fa-solid fa-rotate-right"></i> Refresh History
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
              <i class="fa-solid fa-clock-rotate-left" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
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
        <th>Date</th>
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
        <td>${this.formatDate(request.created_at)}</td>
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
                <i class="fa-solid fa-check"></i>
              </button>
              <button class="btn-reject" onclick="rejectRequest(${request.id})" title="Reject">
                <i class="fa-solid fa-xmark"></i>
              </button>
            ` : ''}
            <button class="btn-view" onclick="viewRequest(${request.id})" title="View Details">
              <i class="fa-solid fa-eye"></i>
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

// Helper to update the sidebar requests badge
RequestsModule.prototype.updateRequestsBadge = function(count) {
  try {
    const badge = document.getElementById('requestsBadge')
    if (!badge) return
    const value = Number(count) || 0
    if (value > 0) {
      badge.textContent = value > 99 ? '99+' : String(value)
      badge.style.display = 'inline-block'
    } else {
      badge.textContent = '0'
      badge.style.display = 'none'
    }
  } catch (e) {
    console.warn('Failed to update requests badge', e)
  }
}
