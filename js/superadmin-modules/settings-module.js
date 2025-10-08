// Settings Module for Super Admin Dashboard
export class SettingsModule {
    constructor(dashboard) {
        this.dashboard = dashboard;
    }

    loadSystemSettings(container) {
        container.innerHTML = `
            <section id="system-settings" class="content-section active">
                <div class="settings-grid">
                    <div class="settings-row-top">
                        <div class="settings-card">
                            <div class="card-header">
                                <h4>Backup Settings</h4>
                            </div>
                            <div class="card-content">
                                <div class="setting-item">
                                    <label class="setting-label">Automatic Backup Frequency</label>
                                    <select id="backupFrequency" class="form-select">
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                                <div class="setting-item">
                                    <label class="setting-label">Backup Retention (days)</label>
                                    <input type="number" id="backupRetention" class="form-input" value="30" min="1" max="365">
                                </div>
                                <div class="card-actions">
                                    <button type="button" class="btn btn-primary" onclick="window.saveBackupSettings()">Save</button>
                                </div>
                            </div>
                        </div>

                        <div class="settings-card">
                            <div class="card-header">
                                <h4>System Actions</h4>
                            </div>
                            <div class="card-content">
                                <div class="system-action-buttons">
                                    <button type="button" class="system-action-btn" onclick="window.clearSystemCache()">
                                        <i class="fas fa-broom"></i>
                                        <span>Clear System Cache</span>
                                    </button>
                                    <button type="button" class="system-action-btn" onclick="window.runSystemCheck()">
                                        <i class="fas fa-check-circle"></i>
                                        <span>System Check</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-row-bottom">
                        <div class="settings-card full-width">
                            <div class="card-header">
                                <h4>Data Backup & Restore</h4>
                            </div>
                            <div class="card-content">
                                <div class="backup-section">
                                    <div class="backup-controls">
                                        <div class="control-group">
                                            <h6>Create Backup</h6>
                                            <div class="backup-form">
                                                <div class="form-group">
                                                    <label for="settingsBackupDescription">Backup Description:</label>
                                                    <textarea id="settingsBackupDescription" placeholder="Enter reason for this backup..." class="backup-description-input" rows="2" style="resize: none !important;"></textarea>
                                                </div>
                                                <button type="button" class="btn btn-primary" onclick="window.createManualBackup()">
                                                    <i class="fas fa-download"></i>
                                                    Create Backup Now
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="control-group">
                                            <h6>Restore from Backup</h6>
                                            <div class="restore-controls">
                                                <input type="file" id="backupFile" accept=".sql,.zip" class="form-input">
                                                <button type="button" class="btn btn-success" onclick="window.restoreFromBackup()">
                                                    <i class="fas fa-upload"></i>
                                                    Restore Backup
                                                </button>
                                            </div>
                                            <div class="warning-notice">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <span>Warning: Restore will overwrite current data</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="backup-history-section">
                                        <h6>Backup History</h6>
                                        <div class="backup-table-container">
                                            <table class="backup-table">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Description</th>
                                                        <th>Type</th>
                                                        <th>Size</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="backupHistoryList">
                                                    <tr>
                                                        <td colspan="5" class="loading-cell">Loading backup history...</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        `;
        this.loadCurrentSettings();
        this.setupGlobalFunctions();
        this.loadBackupHistory();
    }

    setupGlobalFunctions() {
        // Make functions available globally for onclick handlers
        window.saveBackupSettings = () => this.saveBackupSettings();
        window.clearSystemCache = () => this.clearSystemCache();
        window.runSystemCheck = () => this.runSystemCheck();
        window.createManualBackup = () => this.createManualBackup();
        window.restoreFromBackup = () => this.restoreFromBackup();
        window.downloadBackup = (id) => this.downloadBackup(id);
        window.deleteBackup = (id) => this.deleteBackup(id);
    }


    saveBackupSettings() {
        const backupFrequency = document.getElementById('backupFrequency').value;
        const backupRetention = document.getElementById('backupRetention').value;
        
        this.saveSettings({
            backup_frequency: backupFrequency,
            backup_retention: backupRetention
        });
    }

    async loadCurrentSettings() {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php?action=get_settings');
            const data = await response.json();
            
            if (data.success) {
                // Populate form fields with current settings
                Object.keys(data.settings).forEach(key => {
                    const element = document.getElementById(key);
                    if (element) {
                        if (element.type === 'checkbox') {
                            element.checked = data.settings[key] === 'true';
                        } else {
                            element.value = data.settings[key];
                        }
                    }
                });
            }
            
            // Add event listener for maintenance mode toggle
            const maintenanceToggle = document.getElementById('maintenanceMode');
            if (maintenanceToggle) {
                maintenanceToggle.addEventListener('change', (e) => {
                    this.toggleMaintenanceMode(e.target.checked);
                });
            }
        } catch (error) {
            console.error('Error loading settings:', error);
        }
    }

    async saveSettings(settingsData) {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'save_settings', settings: settingsData })
            });

            const data = await response.json();
            
            if (data.success) {
                this.dashboard.showNotification('Settings saved successfully', 'success');
            } else {
                this.dashboard.showNotification('Error saving settings', 'error');
            }
        } catch (error) {
            console.error('Error saving settings:', error);
            this.dashboard.showNotification('Error saving settings', 'error');
        }
    }

    async toggleMaintenanceMode(enabled) {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'save_settings', 
                    settings: { maintenance_mode: enabled ? 'true' : 'false' }
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.dashboard.showNotification(
                    `Maintenance mode ${enabled ? 'enabled' : 'disabled'}`, 
                    'success'
                );
            } else {
                this.dashboard.showNotification('Error updating maintenance mode', 'error');
            }
        } catch (error) {
            console.error('Error toggling maintenance mode:', error);
            this.dashboard.showNotification('Error updating maintenance mode', 'error');
        }
    }

    async clearSystemCache() {
        if (confirm('Clear system cache? This may temporarily slow down the system.')) {
            try {
                const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'clear_cache' })
                });

                const data = await response.json();
                
                if (data.success) {
                    this.dashboard.showNotification('System cache cleared successfully', 'success');
                } else {
                    this.dashboard.showNotification('Error clearing cache', 'error');
                }
            } catch (error) {
                console.error('Error clearing cache:', error);
                this.dashboard.showNotification('Error clearing cache', 'error');
            }
        }
    }

    async runSystemCheck() {
        this.dashboard.showNotification('Running system check...', 'info');
        
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'system_check' })
            });
            const data = await response.json();
            
            if (data.success) {
                this.dashboard.showNotification('System check completed successfully', 'success');
            } else {
                this.dashboard.showNotification('System check found issues', 'error');
            }
        } catch (error) {
            this.dashboard.showNotification('Error running system check', 'error');
        }
    }

    async loadBackupHistory() {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php?action=get_backup_history');
            const data = await response.json();
            
            const tbody = document.getElementById('backupHistoryList');
            if (data.success && data.backups && data.backups.length > 0) {
                tbody.innerHTML = data.backups.slice(0, 10).map(backup => {
                    const description = backup.description || 'No description';
                    const isLongDescription = description.length > 50;
                    const truncatedDescription = isLongDescription ? description.substring(0, 50) + '...' : description;
                    
                    return `
                        <tr>
                            <td>${new Date(backup.created_at).toLocaleDateString()}</td>
                            <td class="description-cell ${isLongDescription ? 'clickable' : ''}" 
                                onclick="${isLongDescription ? `toggleDescriptionCell('${backup.id}', '${description.replace(/'/g, "&#39;").replace(/"/g, "&quot;")}', '${truncatedDescription.replace(/'/g, "&#39;").replace(/"/g, "&quot;")}')` : ''}" 
                                title="${isLongDescription ? 'Click to expand/collapse' : description}" 
                                id="desc-cell-${backup.id}" 
                                data-expanded="false">
                                <div class="description-truncated">${truncatedDescription}</div>
                                ${isLongDescription ? `<div class="description-full" style="display: none;">${description}</div>` : ''}
                            </td>
                            <td>${backup.backup_type || 'manual'}</td>
                            <td>${this.formatFileSize(backup.file_size || 0)}</td>
                            <td>
                                <button onclick="window.downloadBackup('${backup.id}')" class="backup-download-btn" title="Download">
                                    <i class="fas fa-download"></i>
                                </button>
                                <button onclick="window.deleteBackup('${backup.id}')" class="backup-delete-btn" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="no-data">No backups found</td></tr>';
            }
        } catch (error) {
            console.error('Error loading backup history:', error);
            document.getElementById('backupHistoryList').innerHTML = '<tr><td colspan="5" class="error-cell">Error loading backup history</td></tr>';
        }
    }

    async createManualBackup() {
        // Get description from textarea
        const descriptionInput = document.getElementById('settingsBackupDescription');
        const description = descriptionInput ? descriptionInput.value.trim() || 'Manual backup from settings' : 'Manual backup from settings';
        
        const options = {
            database: true,
            userFiles: true,
            systemLogs: true
        };

        try {
            this.dashboard.showNotification('Creating backup...', 'info');
            
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create_backup',
                    description: description,
                    options: options
                })
            });

            const data = await response.json();
            if (data.success) {
                this.dashboard.showNotification('Backup created successfully', 'success');
                // Clear the description input
                if (descriptionInput) {
                    descriptionInput.value = '';
                }
                this.loadBackupHistory();
            } else {
                this.dashboard.showNotification(data.message || 'Failed to create backup', 'error');
            }
        } catch (error) {
            console.error('Error creating backup:', error);
            this.dashboard.showNotification('Error creating backup', 'error');
        }
    }

    async restoreFromBackup() {
        const fileInput = document.getElementById('backupFile');
        if (!fileInput.files[0]) {
            this.dashboard.showNotification('Please select a backup file', 'error');
            return;
        }

        if (!confirm('This will overwrite current data. Are you sure?')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'restore_backup');
            formData.append('backup_file', fileInput.files[0]);

            this.dashboard.showNotification('Restoring from backup...', 'info');

            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                this.dashboard.showNotification('Backup restored successfully', 'success');
            } else {
                this.dashboard.showNotification(data.message || 'Failed to restore backup', 'error');
            }
        } catch (error) {
            console.error('Error restoring backup:', error);
            this.dashboard.showNotification('Error restoring backup', 'error');
        }
    }

    async downloadBackup(backupId) {
        try {
            const response = await fetch(`api/superadmin_api/super_admin_actions.php?action=download_backup&backup_id=${backupId}`);
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `backup_${backupId}.sql`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } else {
                this.dashboard.showNotification('Failed to download backup', 'error');
            }
        } catch (error) {
            console.error('Error downloading backup:', error);
            this.dashboard.showNotification('Error downloading backup', 'error');
        }
    }

    async deleteBackup(backupId) {
        if (!confirm('Delete this backup? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_backup',
                    backup_id: backupId
                })
            });

            const data = await response.json();
            if (data.success) {
                this.dashboard.showNotification('Backup deleted successfully', 'success');
                this.loadBackupHistory();
            } else {
                this.dashboard.showNotification(data.message || 'Failed to delete backup', 'error');
            }
        } catch (error) {
            console.error('Error deleting backup:', error);
            this.dashboard.showNotification('Error deleting backup', 'error');
        }
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}
