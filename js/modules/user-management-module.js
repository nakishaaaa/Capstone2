/**
 * User Management Module
 * Handles RBAC functionality for admin dashboard
 */

class UserManagementModule {
    constructor() {
        this.currentUsers = [];
        this.filteredUsers = [];
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadUsers();
        this.loadUserStats();
    }

    bindEvents() {
        // Search functionality
        const userSearch = document.getElementById('userSearch');
        if (userSearch) {
            userSearch.addEventListener('input', () => this.filterUsers());
        }

        // Form submissions
        const addUserForm = document.getElementById('addUserForm');
        if (addUserForm) {
            addUserForm.addEventListener('submit', (e) => this.handleAddUser(e));
        }

        const editUserForm = document.getElementById('editUserForm');
        if (editUserForm) {
            editUserForm.addEventListener('submit', (e) => this.handleEditUser(e));
        }

        // Reset password checkbox
        const resetPasswordCheckbox = document.getElementById('resetPassword');
        const newPasswordInput = document.getElementById('newPassword');
        const passwordContainer = document.getElementById('passwordContainer');
        if (resetPasswordCheckbox && newPasswordInput && passwordContainer) {
            resetPasswordCheckbox.addEventListener('change', () => {
                passwordContainer.style.display = resetPasswordCheckbox.checked ? 'block' : 'none';
                newPasswordInput.required = resetPasswordCheckbox.checked;
            });
        }

        // Setup password toggle for reset password
        this.setupPasswordToggle('newPassword', 'toggle-reset-password', 'reset-eye-icon');
    }

    async loadUsers() {
        // Check if user has permission (admin only)
        if (typeof userRole !== 'undefined' && userRole !== 'admin') {
            console.log('User management restricted to admins only');
            return;
        }
        
        try {
            const search = document.getElementById('userSearch')?.value || '';
            const roleFilter = document.getElementById('roleFilter')?.value || 'all';
            const statusFilter = document.getElementById('statusFilter')?.value || 'all';

            const params = new URLSearchParams({
                action: 'get_users',
                search: search,
                role: roleFilter,
                status: statusFilter
            });

            const response = await fetch(`api/user_management.php?${params}`);
            const data = await response.json();

            if (data.success) {
                this.currentUsers = data.users;
                this.filteredUsers = data.users;
                this.renderUsersTable();
            } else {
                throw new Error(data.error || 'Failed to load users');
            }
        } catch (error) {
            console.error('Error loading users:', error);
            this.showError('Failed to load users: ' + error.message);
        }
    }

    async loadUserStats() {
        // Check if user has permission (admin only)
        if (typeof userRole !== 'undefined' && userRole !== 'admin') {
            return;
        }
        
        try {
            const response = await fetch('api/user_management.php?action=get_user_stats');
            const data = await response.json();

            if (data.success) {
                const totalUsersEl = document.getElementById('total-users');
                const adminUsersEl = document.getElementById('admin-users');
                const cashierUsersEl = document.getElementById('cashier-users');
                
                if (totalUsersEl) totalUsersEl.textContent = data.stats.total || 0;
                if (adminUsersEl) adminUsersEl.textContent = data.stats.admin || 0;
                if (cashierUsersEl) cashierUsersEl.textContent = data.stats.cashier || 0;
            }
        } catch (error) {
            console.error('Error loading user stats:', error);
        }
    }

    renderUsersTable() {
        const tbody = document.getElementById('usersTableBody');
        if (!tbody) return;

        if (this.filteredUsers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem;">No users found</td></tr>';
            return;
        }

        tbody.innerHTML = this.filteredUsers.map((user, index) => `
            <tr style="
                border-bottom: 1px solid #e5e5e5;
                background: ${index % 2 === 0 ? '#ffffff' : '#f9fafb'};
                transition: background-color 0.2s;
            " onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='${index % 2 === 0 ? '#ffffff' : '#f9fafb'}'">
                <td style="padding: 0.75rem; color: #374151; font-weight: 500;">${user.id}</td>
                <td style="padding: 0.75rem; color: #374151;">${this.escapeHtml(user.username)}</td>
                <td style="padding: 0.75rem; color: #6b7280;">${this.escapeHtml(user.email)}</td>
                <td style="padding: 0.75rem;">
                    <span style="
                        display: inline-block;
                        font-size: 0.75rem;
                        font-weight: 600;
                        text-transform: uppercase;
                        color: #374151;
                    ">${this.capitalizeFirst(user.role || 'unknown')}</span>
                </td>
                <td style="padding: 0.75rem;">
                    <span style="
                        display: inline-block;
                        font-size: 0.75rem;
                        font-weight: 600;
                        text-transform: uppercase;
                        color: #374151;
                    ">${this.capitalizeFirst(user.status || 'active')}</span>
                </td>
                <td style="padding: 0.75rem; color: #6b7280; font-size: 0.875rem;">${this.formatDate(user.created_at)}</td>
                <td style="padding: 0.75rem; color: #6b7280; font-size: 0.875rem;">${user.last_login ? this.formatDate(user.last_login) : 'Never'}</td>
                <td style="padding: 0.75rem;">
                    <div style="display: flex; gap: 0.5rem;">
                        <button onclick="userManagement.openEditUserModal(${user.id})" title="Edit User" style="
                            padding: 0.375rem;
                            border: 1px solid #d1d5db;
                            border-radius: 4px;
                            background: #ffffff;
                            color: #374151;
                            cursor: pointer;
                            transition: all 0.2s;
                        " onmouseover="this.style.backgroundColor='#f3f4f6'; this.style.borderColor='#9ca3af';" onmouseout="this.style.backgroundColor='#ffffff'; this.style.borderColor='#d1d5db';">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="userManagement.toggleUserStatus(${user.id})" title="Toggle Status" style="
                            padding: 0.375rem;
                            border: 1px solid #d1d5db;
                            border-radius: 4px;
                            background: #ffffff;
                            color: #374151;
                            cursor: pointer;
                            transition: all 0.2s;
                        " onmouseover="this.style.backgroundColor='#f3f4f6'; this.style.borderColor='#9ca3af';" onmouseout="this.style.backgroundColor='#ffffff'; this.style.borderColor='#d1d5db';">
                            <i class="fas fa-toggle-${(user.status || 'active') === 'active' ? 'on' : 'off'}"></i>
                        </button>
                        ${user.id != this.getCurrentAdminId() ? `
                        <button onclick="userManagement.deleteUser(${user.id})" title="Delete User" style="
                            padding: 0.375rem;
                            border: 1px solid #dc2626;
                            border-radius: 4px;
                            background: #ffffff;
                            color: #dc2626;
                            cursor: pointer;
                            transition: all 0.2s;
                        " onmouseover="this.style.backgroundColor='#dc2626'; this.style.color='#ffffff';" onmouseout="this.style.backgroundColor='#ffffff'; this.style.color='#dc2626';">
                            <i class="fas fa-trash"></i>
                        </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
    }

    filterUsers() {
        const search = document.getElementById('userSearch')?.value.toLowerCase() || '';
        const roleFilter = document.getElementById('roleFilter')?.value || 'all';
        const statusFilter = document.getElementById('statusFilter')?.value || 'all';

        this.filteredUsers = this.currentUsers.filter(user => {
            const matchesSearch = user.username.toLowerCase().includes(search) || 
                                user.email.toLowerCase().includes(search);
            const matchesRole = roleFilter === 'all' || user.role === roleFilter;
            const matchesStatus = statusFilter === 'all' || user.status === statusFilter;

            return matchesSearch && matchesRole && matchesStatus;
        });

        this.renderUsersTable();
    }

    async refreshUsers() {
        await this.loadUsers();
        await this.loadUserStats();
        this.showSuccess('Users refreshed successfully');
    }

    // Modal functions
    openAddUserModal() {
        document.getElementById('addUserModal').style.display = 'flex';
        document.getElementById('addUserForm').reset();
    }

    closeAddUserModal() {
        document.getElementById('addUserModal').style.display = 'none';
    }

    async openEditUserModal(userId) {
        const user = this.currentUsers.find(u => u.id == userId);
        if (!user) return;

        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUserName').value = user.username;
        document.getElementById('editUserEmail').value = user.email;
        document.getElementById('editUserRole').value = user.role;
        document.getElementById('resetPassword').checked = false;
        document.getElementById('passwordContainer').style.display = 'none';
        document.getElementById('newPassword').required = false;

        document.getElementById('editUserModal').style.display = 'flex';
    }

    closeEditUserModal() {
        document.getElementById('editUserModal').style.display = 'none';
    }

    showRolePermissions() {
        document.getElementById('rolePermissionsModal').style.display = 'flex';
    }

    closeRolePermissionsModal() {
        document.getElementById('rolePermissionsModal').style.display = 'none';
    }

    // User operations
    async handleAddUser(event) {
        event.preventDefault();
        
        try {
            const formData = new FormData(event.target);
            formData.append('action', 'add_user');
            formData.append('csrf_token', window.csrfToken);

            const response = await fetch('api/user_management.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess(data.message);
                this.closeAddUserModal();
                await this.loadUsers();
                await this.loadUserStats();
            } else {
                throw new Error(data.error || 'Failed to add user');
            }
        } catch (error) {
            console.error('Error adding user:', error);
            this.showError('Failed to add user: ' + error.message);
        }
    }

    async handleEditUser(event) {
        event.preventDefault();
        
        try {
            const formData = new FormData(event.target);
            formData.append('action', 'edit_user');
            formData.append('csrf_token', window.csrfToken);

            const response = await fetch('api/user_management.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess(data.message);
                this.closeEditUserModal();
                await this.loadUsers();
                await this.loadUserStats();
            } else {
                throw new Error(data.error || 'Failed to update user');
            }
        } catch (error) {
            console.error('Error updating user:', error);
            this.showError('Failed to update user: ' + error.message);
        }
    }

    async deleteUser(userId) {
        if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('user_id', userId);
            formData.append('csrf_token', window.csrfToken);

            const response = await fetch('api/user_management.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess(data.message);
                await this.loadUsers();
                await this.loadUserStats();
            } else {
                throw new Error(data.error || 'Failed to delete user');
            }
        } catch (error) {
            console.error('Error deleting user:', error);
            this.showError('Failed to delete user: ' + error.message);
        }
    }

    async toggleUserStatus(userId) {
        try {
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('user_id', userId);
            formData.append('csrf_token', window.csrfToken);

            const response = await fetch('api/user_management.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess(data.message);
                await this.loadUsers();
                await this.loadUserStats();
            } else {
                throw new Error(data.error || 'Failed to toggle user status');
            }
        } catch (error) {
            console.error('Error toggling user status:', error);
            this.showError('Failed to toggle user status: ' + error.message);
        }
    }

    // Password toggle functionality
    setupPasswordToggle(inputId, buttonId, iconId) {
        const input = document.getElementById(inputId);
        const button = document.getElementById(buttonId);
        const iconImg = document.getElementById(iconId);
        const eyeSrc = 'images/svg/eye-black.svg';
        const eyeSlashSrc = 'images/svg/eye-slash-black.svg';
        
        if (input && button && iconImg) {
            button.addEventListener('click', () => {
                if (input.type === 'password') {
                    input.type = 'text';
                    iconImg.src = eyeSrc;
                    iconImg.alt = 'Hide Password';
                } else {
                    input.type = 'password';
                    iconImg.src = eyeSlashSrc;
                    iconImg.alt = 'Show Password';
                }
            });
            // Set initial icon state
            iconImg.src = eyeSlashSrc;
            iconImg.alt = 'Show Password';
        }
    }

    // Utility functions
    getCurrentAdminId() {
        // This should be set from PHP session data
        return window.currentAdminId || null;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    capitalizeFirst(str) {
        if (typeof str !== 'string' || str.length === 0) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }

    showSuccess(message) {
        // Use existing toast system if available
        if (window.toastManager) {
            window.toastManager.show(message, 'success');
        } else {
            alert(message);
        }
    }

    showError(message) {
        // Use existing toast system if available
        if (window.toastManager) {
            window.toastManager.show(message, 'error');
        } else {
            alert('Error: ' + message);
        }
    }
}

// Global functions for onclick handlers
window.openAddUserModal = () => userManagement.openAddUserModal();
window.closeAddUserModal = () => userManagement.closeAddUserModal();
window.closeEditUserModal = () => userManagement.closeEditUserModal();
window.closeRolePermissionsModal = () => userManagement.closeRolePermissionsModal();
window.filterUsers = () => userManagement.filterUsers();
window.refreshUsers = () => userManagement.refreshUsers();

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.userManagement = new UserManagementModule();
});

export default UserManagementModule;
