// Console Errors Module for Super Admin Dashboard
export class ConsoleErrorsModule {
    constructor(dashboard) {
        this.dashboard = dashboard;
        this.consoleErrors = [];
    }

    loadConsoleErrors(container) {
        container.innerHTML = `
            <section id="console-errors" class="content-section active">
                <div class="console-errors-header">
                    <div class="error-controls">
                        <button onclick="clearConsoleErrors()" class="clear-btn">
                            <i class="fas fa-trash"></i>
                            Clear Errors
                        </button>
                        <button onclick="exportConsoleErrors()" class="export-btn">
                            <i class="fas fa-download"></i>
                            Export
                        </button>
                    </div>
                </div>

                <div class="console-errors-table-container">
                    <table class="console-errors-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Error Type</th>
                                <th>Error Message</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="consoleErrorsTableBody">
                            <tr>
                                <td colspan="4" class="loading">Loading console errors...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        `;
        this.loadConsoleErrorsList();
    }

    async loadConsoleErrorsList() {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php?action=get_console_errors');
            const data = await response.json();
            
            const tbody = document.getElementById('consoleErrorsTableBody');
            
            if (data.success && data.errors) {
                tbody.innerHTML = data.errors.map(error => `
                    <tr>
                        <td>${new Date(error.timestamp).toLocaleString()}</td>
                        <td><span class="error-type-badge ${error.type.toLowerCase().replace(/\s+/g, '-')}">${error.type}</span></td>
                        <td>
                            <div class="error-message">
                                <div class="error-text">${error.message}</div>
                                ${error.url ? `<div class="error-url">at ${error.url}</div>` : ''}
                            </div>
                        </td>
                        <td>
                            <button onclick="viewErrorDetails(${error.id})" class="view-btn" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="deleteError(${error.id})" class="delete-btn" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="4" class="no-data">No console errors found</td></tr>';
            }
        } catch (error) {
            console.error('Error loading console errors:', error);
            document.getElementById('consoleErrorsTableBody').innerHTML = '<tr><td colspan="4" class="error-row">Error loading console errors</td></tr>';
        }
    }

    setupErrorLogging() {
        // Capture console errors
        const originalError = console.error;
        console.error = (...args) => {
            this.logError('Console Error', args.join(' '));
            originalError.apply(console, args);
        };

        // Capture unhandled promise rejections
        window.addEventListener('unhandledrejection', (event) => {
            this.logError('Unhandled Promise Rejection', event.reason);
        });

        // Capture JavaScript errors
        window.addEventListener('error', (event) => {
            this.logError('JavaScript Error', `${event.message} at ${event.filename}:${event.lineno}`);
        });
    }

    logError(type, message) {
        const error = {
            id: Date.now(),
            type: type,
            message: message,
            timestamp: new Date().toISOString(),
            url: window.location.href
        };
        
        this.consoleErrors.unshift(error);
        
        // Keep only last 100 errors
        if (this.consoleErrors.length > 100) {
            this.consoleErrors = this.consoleErrors.slice(0, 100);
        }

        // Update error count in UI if console errors section is active
        this.updateErrorCount();
    }

    updateErrorCount() {
        const errorCountElement = document.querySelector('.nav-item[data-section="console-errors"] .error-count');
        if (errorCountElement) {
            const errorCount = this.consoleErrors.length;
            errorCountElement.textContent = errorCount;
            errorCountElement.style.display = errorCount > 0 ? 'inline' : 'none';
        }
    }

    async viewErrorDetails(errorId) {
        try {
            const response = await fetch(`api/superadmin_api/super_admin_actions.php?action=get_error_details&error_id=${errorId}`);
            const data = await response.json();

            if (data.success && data.error) {
                const error = data.error;
                alert(`Error Details\n\nType: ${error.type}\nMessage: ${error.message}\nTimestamp: ${new Date(error.timestamp).toLocaleString()}\nURL: ${error.url || 'N/A'}\nStack Trace: ${error.stack || 'N/A'}`);
            } else {
                this.dashboard.showNotification('Error loading error details', 'error');
            }
        } catch (error) {
            console.error('Error viewing error details:', error);
            this.dashboard.showNotification('Error loading error details', 'error');
        }
    }

    async deleteError(errorId) {
        if (!confirm('Delete this error log?')) return;

        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_error',
                    error_id: errorId
                })
            });

            const data = await response.json();
            if (data.success) {
                this.loadConsoleErrorsList();
                this.dashboard.showNotification('Error deleted successfully', 'success');
            } else {
                this.dashboard.showNotification('Failed to delete error', 'error');
            }
        } catch (error) {
            console.error('Error deleting error:', error);
            this.dashboard.showNotification('Error deleting error', 'error');
        }
    }

    async clearConsoleErrors() {
        if (!confirm('Clear all console errors?')) return;

        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'clear_console_errors' })
            });

            const data = await response.json();
            if (data.success) {
                this.consoleErrors = [];
                this.loadConsoleErrorsList();
                this.updateErrorCount();
                this.dashboard.showNotification('Console errors cleared', 'success');
            } else {
                this.dashboard.showNotification('Failed to clear errors', 'error');
            }
        } catch (error) {
            console.error('Error clearing console errors:', error);
            this.dashboard.showNotification('Error clearing console errors', 'error');
        }
    }

    async exportConsoleErrors() {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php?action=export_console_errors');
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `console_errors_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                this.dashboard.showNotification('Console errors exported successfully', 'success');
            } else {
                this.dashboard.showNotification('Failed to export console errors', 'error');
            }
        } catch (error) {
            console.error('Error exporting console errors:', error);
            this.dashboard.showNotification('Error exporting console errors', 'error');
        }
    }
}
