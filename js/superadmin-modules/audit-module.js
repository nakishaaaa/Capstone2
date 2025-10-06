// Audit Module for Super Admin Dashboard
export class AuditModule {
    constructor(dashboard) {
        this.dashboard = dashboard;
    }

    async loadAuditTrails() {
        const dateRange = document.getElementById('auditDateRange').value;
        const action = document.getElementById('auditAction').value;
        const user = document.getElementById('auditUser').value;
        
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'get_audit_logs',
                    date_range: dateRange,
                    filter_action: action,
                    filter_user: user
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                this.displayAuditLogs(data.logs);
            } else {
                throw new Error(data.message || 'Failed to load audit logs');
            }
        } catch (error) {
            console.error('Error loading audit trails:', error);
            document.getElementById('auditTableBody').innerHTML = `
                <tr>
                    <td colspan="5" class="error-row">
                        <i class="fas fa-exclamation-triangle"></i>
                        Error loading audit logs: ${error.message}
                    </td>
                </tr>
            `;
        }
    }

    displayAuditLogs(logs) {
        const tbody = document.getElementById('auditTableBody');
        
        if (!logs || logs.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="no-data">
                        <i class="fas fa-info-circle"></i>
                        No audit logs found for the selected criteria
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = logs.map(log => {
            // Handle different timestamp formats
            let timestamp = log.timestamp || log.created_at;
            let formattedDate = 'Invalid Date';
            
            if (timestamp) {
                try {
                    // Try parsing the timestamp
                    const date = new Date(timestamp);
                    if (!isNaN(date.getTime())) {
                        formattedDate = date.toLocaleString();
                    } else {
                        // If direct parsing fails, try with different formats
                        const dateStr = timestamp.replace(' ', 'T');
                        const altDate = new Date(dateStr);
                        if (!isNaN(altDate.getTime())) {
                            formattedDate = altDate.toLocaleString();
                        }
                    }
                } catch (e) {
                    console.error('Date parsing error:', e, timestamp);
                }
            }
            
            return `
                <tr>
                    <td class="audit-timestamp">${formattedDate}</td>
                    <td>
                        <div class="audit-user-info">
                            
                            <div class="audit-user-name" style="font-weight: bold;">${log.user_name || log.username || 'Anonymous'}</div>
                        </div>
                    </td>
                    <td>
                        <span class="action-badge ${this.getActionBadgeClass(log.action)}">
                            <i class="fas fa-${this.getActionIcon(log.action)}"></i>
                            ${this.getActionDisplayName(log.action)}
                        </span>
                    </td>
                    <td class="audit-description">${log.description || log.resource || '-'}</td>
                    <td>
                        <div class="user-agent">${log.user_agent || ''}</div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    getActionIcon(action) {
        if (!action) return 'info-circle';
        
        const iconMap = {
            'login': 'sign-in-alt',
            'logout': 'sign-out-alt',
            'user_create': 'user-plus',
            'user_update': 'user-edit',
            'maintenance_toggle': 'tools',
            'system_check': 'check-circle',
            'create': 'plus',
            'update': 'edit',
            'delete': 'trash',
            'view': 'eye',
            'export': 'download',
            'import': 'upload',
            'backup': 'database',
            'restore': 'undo',
            'settings': 'cog',
            'settings_update': 'tools',
            'settings_updated': 'tools',
            'user_management': 'users',
            'system': 'server',
            'resource_error': 'exclamation-triangle',
            'default': 'info-circle'
        };
        
        // Handle SETTINGS_UPDATE as maintenance toggle
        if (action.toUpperCase() === 'SETTINGS_UPDATE') {
            return 'tools';
        }
        
        return iconMap[action.toLowerCase()] || 'info-circle';
    }

    getActionDisplayName(action) {
        if (!action) return 'Unknown';
        
        // Handle SETTINGS_UPDATE as maintenance toggle
        if (action.toUpperCase() === 'SETTINGS_UPDATE') {
            return 'Maintenance Toggle';
        }
        
        const displayMap = {
            'login': 'Login',
            'logout': 'Logout',
            'user_create': 'User Create',
            'user_update': 'User Update',
            'maintenance_toggle': 'Maintenance Toggle',
            'system_check': 'System Check',
            'settings_update': 'Maintenance Toggle',
            'settings_updated': 'Maintenance Toggle',
            'resource_error': 'Resource Error'
        };
        
        return displayMap[action.toLowerCase()] || action;
    }

    getActionBadgeClass(action) {
        if (!action) return 'default';
        
        // Handle SETTINGS_UPDATE as maintenance toggle
        if (action.toUpperCase() === 'SETTINGS_UPDATE') {
            return 'maintenance_toggle';
        }
        
        return action.toLowerCase().replace(/\s+/g, '_');
    }

    async loadRecentActivityData() {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php?action=recent_activity');
            const data = await response.json();
            
            const container = document.getElementById('recentActivityList');
            if (data.success && data.activities) {
                container.innerHTML = data.activities.map(activity => `
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-${this.getActionIcon(activity.action || 'default')}"></i>
                        </div>
                        <div class="activity-details">
                            <div class="activity-text">
                                <strong>${activity.user_name || activity.username || 'Anonymous'}</strong> ${activity.description || activity.action || 'performed an action'}
                            </div>
                            <div class="activity-time">${this.formatRelativeTime(activity.timestamp || activity.created_at)}</div>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<div class="no-data">No recent activity</div>';
            }
        } catch (error) {
            console.error('Error loading recent activity:', error);
            const container = document.getElementById('recentActivityList');
            if (container) {
                container.innerHTML = '<div class="error-loading">Error loading recent activity</div>';
            }
        }
    }

    formatRelativeTime(timestamp) {
        const now = new Date();
        const time = new Date(timestamp);
        const diffMs = now - time;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        return time.toLocaleDateString();
    }
}
