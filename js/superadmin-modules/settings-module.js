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

            <!-- Delete Backup Confirmation Modal -->
            <div id="deleteBackupModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(0, 0, 0, 0.5); z-index: 9999; justify-content: center; align-items: center;">
                <div class="modal-content" style="background-color: #ffffff; border-radius: 8px; padding: 0; max-width: 500px; width: 90%; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); position: relative;">
                    <div class="modal-header" style="padding: 20px; border-bottom: 1px solid #e5e5e5; display: flex; justify-content: space-between; align-items: center;">
                        <h4 style="margin: 0; color: #000000; font-size: 1.2rem;">Delete Backup</h4>
                        <span class="close" onclick="closeDeleteBackupModal()" style="cursor: pointer; font-size: 24px; color: #000000;">&times;</span>
                    </div>
                    <div class="modal-body" style="padding: 24px;">
                        <div class="warning-message" style="display: flex; align-items: flex-start; gap: 12px;">
                            <i class="fas fa-exclamation-triangle" style="color: #dc3545; font-size: 24px; margin-top: 2px;"></i>
                            <div>
                                <p style="margin: 0 0 8px 0; font-weight: 600;"><strong>This will permanently delete the backup file.</strong></p>
                                <p style="margin: 0; color: #666;">Backup ID: <span id="deleteBackupId"></span></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-actions" style="display: flex; gap: 12px; justify-content: flex-end; padding: 0 24px 24px 24px;">
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteBackupModal()" style="padding: 8px 16px; border: 1px solid #ccc; background: #fff; color: #333; border-radius: 4px; cursor: pointer;">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="executeDeleteBackup()" style="padding: 8px 16px; border: 1px solid #dc3545; background: #dc3545; color: #fff; border-radius: 4px; cursor: pointer;">
                            <i class="fas fa-trash"></i> Delete Backup
                        </button>
                    </div>
                </div>
            </div>

            <!-- Restore Backup Confirmation Modal -->
            <div id="restoreConfirmModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(0, 0, 0, 0.5); z-index: 9999; justify-content: center; align-items: center;">
                <div class="modal-content" style="background-color: #ffffff; border-radius: 8px; padding: 0; max-width: 500px; width: 90%; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); position: relative;">
                    <div class="modal-header" style="padding: 20px; border-bottom: 1px solid #e5e5e5; display: flex; justify-content: space-between; align-items: center;">
                        <h4 style="margin: 0; color: #000000; font-size: 1.2rem;">Restore Database Backup</h4>
                        <span class="close" onclick="closeRestoreModal()" style="cursor: pointer; font-size: 24px; color: #000000;">&times;</span>
                    </div>
                    <div class="modal-body" style="padding: 24px;">
                        <div class="warning-message" style="display: flex; align-items: flex-start; gap: 12px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 16px;">
                            <i class="fas fa-exclamation-triangle" style="color: #856404; font-size: 24px; margin-top: 2px;"></i>
                            <div>
                                <p style="margin: 0 0 8px 0; font-weight: 600; color: #856404;"><strong>This will overwrite all current data and cannot be undone.</strong></p>
                                <p style="margin: 0; color: #856404;">Selected file: <span id="selectedFileName"></span></p>
                                <p style="margin: 8px 0 0 0; color: #856404;">Are you sure you want to continue?</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-actions" style="display: flex; gap: 12px; justify-content: flex-end; padding: 0 24px 24px 24px;">
                        <button type="button" class="btn btn-secondary" onclick="closeRestoreModal()" style="padding: 8px 16px; border: 1px solid #ccc; background: #fff; color: #333; border-radius: 4px; cursor: pointer;">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="executeRestore()" style="padding: 8px 16px; border: 1px solid #dc3545; background: #dc3545; color: #fff; border-radius: 4px; cursor: pointer;">
                            <i class="fas fa-upload"></i> Restore Backup
                        </button>
                    </div>
                </div>
            </div>
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
        window.restoreFromBackup = () => this.showRestoreModal();
        window.downloadBackup = (id) => this.downloadBackup(id);
        window.deleteBackup = (id) => this.showDeleteBackupModal(id);
        window.closeDeleteBackupModal = () => this.closeDeleteBackupModal();
        window.executeDeleteBackup = () => this.executeDeleteBackup();
        window.closeRestoreModal = () => this.closeRestoreModal();
        window.executeRestore = () => this.executeRestore();
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
        const confirmed = await this.showConfirmModal(
            'Clear System Cache?',
            'This may temporarily slow down the system while the cache is being rebuilt.',
            'Clear Cache',
            'Cancel'
        );
        
        if (confirmed) {
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

    showConfirmModal(title, message, confirmText = 'OK', cancelText = 'Cancel') {
        return new Promise((resolve) => {
            const existingModal = document.getElementById('customConfirmModal');
            if (existingModal) existingModal.remove();

            const modal = document.createElement('div');
            modal.id = 'customConfirmModal';
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
            `;

            modal.innerHTML = `
                <div style="
                    background: white;
                    border-radius: 12px;
                    padding: 2rem;
                    max-width: 400px;
                    width: 90%;
                    text-align: center;
                    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
                ">
                    <div style="
                        width: 48px;
                        height: 48px;
                        border-radius: 50%;
                        background: #dbeafe;
                        color: #3b82f6;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        margin: 0 auto 1rem auto;
                    ">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="16" x2="12" y2="12"/>
                            <line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                    </div>
                    <h3 style="
                        margin: 0 0 0.5rem 0;
                        color: #1f2937;
                        font-size: 1.125rem;
                        font-weight: 600;
                    ">
                        ${title}
                    </h3>
                    <p style="
                        margin: 0 0 1.5rem 0;
                        color: #6b7280;
                        font-size: 0.875rem;
                        line-height: 1.5;
                    ">
                        ${message}
                    </p>
                    <div style="display: flex; gap: 0.75rem; justify-content: center;">
                        <button id="confirmCancel" style="
                            background: #f3f4f6;
                            color: #374151;
                            border: 1px solid #d1d5db;
                            border-radius: 6px;
                            padding: 0.5rem 1.5rem;
                            font-size: 0.875rem;
                            font-weight: 500;
                            cursor: pointer;
                            transition: all 0.2s;
                        ">
                            ${cancelText}
                        </button>
                        <button id="confirmOk" style="
                            background: #3b82f6;
                            color: white;
                            border: none;
                            border-radius: 6px;
                            padding: 0.5rem 1.5rem;
                            font-size: 0.875rem;
                            font-weight: 500;
                            cursor: pointer;
                            transition: all 0.2s;
                        ">
                            ${confirmText}
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            const handleResponse = (result) => {
                modal.remove();
                resolve(result);
            };

            document.getElementById('confirmOk').onclick = () => handleResponse(true);
            document.getElementById('confirmCancel').onclick = () => handleResponse(false);
            modal.onclick = (e) => {
                if (e.target === modal) handleResponse(false);
            };
        });
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

    showRestoreModal() {
        console.log('showRestoreModal called');
        const fileInput = document.getElementById('backupFile');
        if (!fileInput.files[0]) {
            this.dashboard.showNotification('Please select a backup file', 'error');
            return;
        }

        const fileName = fileInput.files[0].name;
        console.log('Selected file:', fileName);
        
        const selectedFileNameElement = document.getElementById('selectedFileName');
        const modal = document.getElementById('restoreConfirmModal');
        
        console.log('selectedFileName element:', selectedFileNameElement);
        console.log('modal element:', modal);
        
        if (selectedFileNameElement) {
            selectedFileNameElement.textContent = fileName;
        }
        
        if (modal) {
            // Remove modal from its current parent and append to body
            if (modal.parentNode) {
                modal.parentNode.removeChild(modal);
            }
            document.body.appendChild(modal);
            
            // Force all the styles to ensure modal appears with !important
            modal.style.setProperty('display', 'flex', 'important');
            modal.style.setProperty('position', 'fixed', 'important');
            modal.style.setProperty('top', '0', 'important');
            modal.style.setProperty('left', '0', 'important');
            modal.style.setProperty('width', '100vw', 'important');
            modal.style.setProperty('height', '100vh', 'important');
            modal.style.setProperty('background-color', 'rgba(0, 0, 0, 0.5)', 'important');
            modal.style.setProperty('z-index', '999999', 'important'); // Maximum z-index
            modal.style.setProperty('justify-content', 'center', 'important');
            modal.style.setProperty('align-items', 'center', 'important');
            modal.style.setProperty('visibility', 'visible', 'important');
            modal.style.setProperty('opacity', '1', 'important');
            modal.style.setProperty('pointer-events', 'auto', 'important');
            modal.style.setProperty('transition', 'none', 'important');
            modal.style.setProperty('animation', 'none', 'important');
            
            // Also disable animations on modal-content
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.style.setProperty('transition', 'none', 'important');
                modalContent.style.setProperty('animation', 'none', 'important');
                modalContent.style.setProperty('transform', 'none', 'important');
            }
            
            console.log('Restore modal moved to body and display set to flex with all styles applied');
            console.log('Modal parent is now:', modal.parentNode);
        } else {
            console.error('Restore modal element not found!');
        }
    }

    closeRestoreModal() {
        const modal = document.getElementById('restoreConfirmModal');
        modal.style.display = 'none';
    }

    async executeRestore() {
        const fileInput = document.getElementById('backupFile');
        
        try {
            const formData = new FormData();
            formData.append('action', 'restore_backup');
            formData.append('backup_file', fileInput.files[0]);

            this.dashboard.showNotification('Restoring from backup...', 'info');
            this.closeRestoreModal();

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

    showDeleteBackupModal(backupId) {
        console.log('showDeleteBackupModal called with ID:', backupId);
        this.currentDeleteBackupId = backupId;
        
        const deleteBackupIdElement = document.getElementById('deleteBackupId');
        const modal = document.getElementById('deleteBackupModal');
        
        console.log('deleteBackupId element:', deleteBackupIdElement);
        console.log('modal element:', modal);
        
        if (deleteBackupIdElement) {
            deleteBackupIdElement.textContent = backupId;
        }
        
        if (modal) {
            // Force all the styles to ensure modal appears
            modal.style.display = 'flex';
            modal.style.position = 'fixed';
            modal.style.top = '0';
            modal.style.left = '0';
            modal.style.width = '100vw';
            modal.style.height = '100vh';
            modal.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
            modal.style.zIndex = '9999';
            modal.style.justifyContent = 'center';
            modal.style.alignItems = 'center';
            modal.style.visibility = 'visible';
            modal.style.opacity = '1';
            
            console.log('Modal display set to flex with all styles applied');
        } else {
            console.error('Modal element not found!');
        }
    }

    closeDeleteBackupModal() {
        const modal = document.getElementById('deleteBackupModal');
        modal.style.display = 'none';
        this.currentDeleteBackupId = null;
    }

    async executeDeleteBackup() {
        if (!this.currentDeleteBackupId) return;

        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_backup',
                    backup_id: this.currentDeleteBackupId
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
        
        this.closeDeleteBackupModal();
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}
