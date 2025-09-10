// Backup Module for Super Admin Dashboard
export class BackupModule {
    constructor(dashboard) {
        this.dashboard = dashboard;
    }

    loadBackup(container) {
        container.innerHTML = `
            <section id="backup" class="content-section active">
                <div class="backup-header">
                    <p>Database backup and restoration tools</p>
                </div>

                <div class="backup-section">
                    <h4>Create Backup</h4>
                    <button class="backup-btn" onclick="window.createManualBackup()">
                        <i class="fas fa-download"></i>
                        Create Backup Now
                    </button>
                </div>

                <div class="restore-section">
                    <h4>Restore from Backup</h4>
                    <input type="file" id="backupFile" accept=".sql,.zip" class="file-input">
                    <button class="restore-btn" onclick="window.restoreFromBackup()">
                        <i class="fas fa-upload"></i>
                        Restore Backup
                    </button>
                    <div class="warning-text">
                        <i class="fas fa-exclamation-triangle"></i>
                        Warning: This will overwrite current data
                    </div>
                </div>

                <div class="backup-history">
                    <h4>Backup History</h4>
                    <table class="backup-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="backupHistoryBody">
                            <tr>
                                <td colspan="5" class="loading">Loading backup history...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        `;
        this.setupGlobalFunctions();
        this.loadBackupHistory();
    }
    
    setupGlobalFunctions() {
        // Make functions available globally for onclick handlers
        window.createManualBackup = () => this.createManualBackup();
        window.restoreFromBackup = () => this.restoreFromBackup();
        window.downloadBackup = (id) => this.downloadBackup(id);
        window.deleteBackup = (id) => this.deleteBackup(id);
    }

    async loadBackupHistory() {
        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php?action=get_backup_history');
            const data = await response.json();
            
            const tbody = document.getElementById('backupHistoryBody');
            if (data.success && data.backups) {
                tbody.innerHTML = data.backups.map(backup => `
                    <tr>
                        <td>${new Date(backup.created_at).toLocaleString()}</td>
                        <td>${backup.backup_type || 'manual'}</td>
                        <td>${this.formatFileSize(backup.file_size || 0)}</td>
                        <td><span class="status-badge ${backup.status}">${backup.status}</span></td>
                        <td>
                            <button onclick="window.downloadBackup('${backup.id}')" class="download-btn" title="Download">
                                <i class="fas fa-download"></i>
                            </button>
                            <button onclick="window.deleteBackup('${backup.id}')" class="delete-btn" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="no-data">No backup history found</td></tr>';
            }
        } catch (error) {
            console.error('Error loading backup history:', error);
            document.getElementById('backupHistoryBody').innerHTML = '<tr><td colspan="5" class="error-row">Error loading backup history</td></tr>';
        }
    }

    async createManualBackup() {
        // Always include all backup components
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
                    options: options
                })
            });

            const data = await response.json();
            if (data.success) {
                this.dashboard.showNotification('Backup created successfully', 'success');
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
