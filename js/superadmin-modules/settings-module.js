// Settings Module for Super Admin Dashboard
export class SettingsModule {
    constructor(dashboard) {
        this.dashboard = dashboard;
    }

    loadSystemSettings(container) {
        container.innerHTML = `
            <section id="system-settings" class="content-section active">
                <div class="settings-grid">
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
            </section>
        `;
        this.loadCurrentSettings();
        this.setupGlobalFunctions();
    }

    setupGlobalFunctions() {
        // Make functions available globally for onclick handlers
        window.saveBackupSettings = () => this.saveBackupSettings();
        window.clearSystemCache = () => this.clearSystemCache();
        window.runSystemCheck = () => this.runSystemCheck();
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
}
