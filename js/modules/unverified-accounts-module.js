/**
 * Unverified Accounts Management Module
 * Handles cleanup and management of unverified user accounts
 */

class UnverifiedAccountsModule {
    constructor() {
        this.accounts = [];
        this.stats = {};
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadStats();
        this.loadAccounts();
    }

    bindEvents() {
        // Refresh button
        const refreshBtn = document.getElementById('refreshUnverifiedAccounts');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.refreshData());
        }

        // Cleanup expired button
        const cleanupBtn = document.getElementById('cleanupExpiredAccounts');
        if (cleanupBtn) {
            cleanupBtn.addEventListener('click', () => this.cleanupExpiredAccounts());
        }

        // Show expired only toggle
        const expiredOnlyToggle = document.getElementById('showExpiredOnly');
        if (expiredOnlyToggle) {
            expiredOnlyToggle.addEventListener('change', () => this.loadAccounts());
        }

        // Cleanup hours input
        const cleanupHoursInput = document.getElementById('cleanupHours');
        if (cleanupHoursInput) {
            cleanupHoursInput.addEventListener('change', () => this.refreshData());
        }
    }

    async loadStats() {
        try {
            const cleanupHours = document.getElementById('cleanupHours')?.value || 24;
            const response = await fetch(`api/unverified_account_management.php?action=get_stats&cleanup_hours=${cleanupHours}`);
            const data = await response.json();

            if (data.success) {
                this.stats = data;
                this.renderStats();
            } else {
                throw new Error(data.error || 'Failed to load stats');
            }
        } catch (error) {
            console.error('Error loading stats:', error);
            this.showError('Failed to load statistics: ' + error.message);
        }
    }

    async loadAccounts() {
        try {
            const cleanupHours = document.getElementById('cleanupHours')?.value || 24;
            const expiredOnly = document.getElementById('showExpiredOnly')?.checked || false;
            
            const params = new URLSearchParams({
                action: 'get_accounts',
                cleanup_hours: cleanupHours,
                expired_only: expiredOnly
            });

            const response = await fetch(`api/unverified_account_management.php?${params}`);
            const data = await response.json();

            if (data.success) {
                this.accounts = data.accounts;
                this.renderAccountsTable();
            } else {
                throw new Error(data.error || 'Failed to load accounts');
            }
        } catch (error) {
            console.error('Error loading accounts:', error);
            this.showError('Failed to load accounts: ' + error.message);
        }
    }

    async cleanupExpiredAccounts() {
        if (!confirm('Are you sure you want to delete all expired unverified accounts? This action cannot be undone.')) {
            return;
        }

        try {
            const cleanupHours = document.getElementById('cleanupHours')?.value || 24;
            
            const formData = new FormData();
            formData.append('action', 'cleanup_expired');
            formData.append('cleanup_hours', cleanupHours);
            formData.append('csrf_token', window.csrfToken);

            const response = await fetch('api/unverified_account_management.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess(`Successfully deleted ${data.deleted_count} expired unverified accounts`);
                await this.refreshData();
            } else {
                throw new Error(data.error || 'Failed to cleanup accounts');
            }
        } catch (error) {
            console.error('Error cleaning up accounts:', error);
            this.showError('Failed to cleanup accounts: ' + error.message);
        }
    }

    async deleteAccount(userId, username) {
        if (!confirm(`Are you sure you want to delete the unverified account for "${username}"? This action cannot be undone.`)) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'delete_account');
            formData.append('user_id', userId);
            formData.append('csrf_token', window.csrfToken);

            const response = await fetch('api/unverified_account_management.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess(data.message);
                await this.refreshData();
            } else {
                throw new Error(data.error || 'Failed to delete account');
            }
        } catch (error) {
            console.error('Error deleting account:', error);
            this.showError('Failed to delete account: ' + error.message);
        }
    }

    async refreshData() {
        await Promise.all([
            this.loadStats(),
            this.loadAccounts()
        ]);
        this.showSuccess('Data refreshed successfully');
    }

    renderStats() {
        const statsContainer = document.getElementById('unverifiedAccountsStats');
        if (!statsContainer) return;

        const { total_unverified, expired_ready_for_cleanup, recent_unverified, cleanup_hours } = this.stats;

        statsContainer.innerHTML = `
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">${total_unverified}</div>
                    <div class="stat-label">Total Unverified</div>
                </div>
                <div class="stat-card expired">
                    <div class="stat-number">${expired_ready_for_cleanup}</div>
                    <div class="stat-label">Expired (${cleanup_hours}h+)</div>
                </div>
                <div class="stat-card recent">
                    <div class="stat-number">${recent_unverified}</div>
                    <div class="stat-label">Recent</div>
                </div>
            </div>
        `;
    }

    renderAccountsTable() {
        const tableContainer = document.getElementById('unverifiedAccountsTable');
        if (!tableContainer) return;

        if (this.accounts.length === 0) {
            tableContainer.innerHTML = `
                <div class="no-accounts">
                    <p>No unverified accounts found.</p>
                </div>
            `;
            return;
        }

        const tableHTML = `
            <table class="accounts-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Age (Hours)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${this.accounts.map(account => this.renderAccountRow(account)).join('')}
                </tbody>
            </table>
        `;

        tableContainer.innerHTML = tableHTML;
    }

    renderAccountRow(account) {
        const statusClass = account.status === 'expired' ? 'status-expired' : 'status-pending';
        const statusText = account.status === 'expired' ? 'Expired' : `Expires in ${Math.ceil(account.expires_in_hours)}h`;
        
        return `
            <tr class="${statusClass}">
                <td>${this.escapeHtml(account.username)}</td>
                <td>${this.escapeHtml(account.email)}</td>
                <td><span class="role-badge role-${account.role}">${account.role}</span></td>
                <td>${this.formatDate(account.created_at)}</td>
                <td>${Math.floor(account.hours_since_creation)}</td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="window.unverifiedAccountsModule.deleteAccount(${account.id}, '${this.escapeHtml(account.username)}')">
                        Delete
                    </button>
                </td>
            </tr>
        `;
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showSuccess(message) {
        if (window.toastManager) {
            window.toastManager.show(message, 'success');
        } else {
            alert(message);
        }
    }

    showError(message) {
        if (window.toastManager) {
            window.toastManager.show(message, 'error');
        } else {
            alert('Error: ' + message);
        }
    }
}

// Initialize module when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('unverifiedAccountsStats')) {
        window.unverifiedAccountsModule = new UnverifiedAccountsModule();
    }
});
