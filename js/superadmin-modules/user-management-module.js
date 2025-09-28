// User Management Module for Super Admin Dashboard
export class UserManagementModule {
    constructor(dashboard) {
        this.dashboard = dashboard;
        this.searchTimeout = null;
    }

    loadAccountManagement(container) {
        container.innerHTML = `
            <section id="account-management" class="content-section active">
                <div class="account-header">
                    <button class="add-user-btn" onclick="showAddUserModal()">
                        <i class="fas fa-plus"></i>
                        Add New User
                    </button>
                </div>

                <div class="user-controls">
                    <div class="search-box">
                        <input type="text" id="userSearch" placeholder="Search users...">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="filter-controls">
                        <select id="roleFilter">
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="cashier">Cashier</option>
                            <option value="user">Customer</option>
                        </select>
                        <select id="deletedFilter">
                            <option value="false">Active Users</option>
                            <option value="true">All Users</option>
                            <option value="only">Deleted Users Only</option>
                        </select>
                    </div>
                </div>

                <div class="users-table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr>
                                <td colspan="7" class="loading">Loading users...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- User Modal -->
                <div id="userModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 id="modalTitle">Add New User</h4>
                            <span class="close" onclick="closeUserModal()">&times;</span>
                        </div>
                        <form id="userForm" onsubmit="saveUser(event)">
                            <input type="hidden" id="userId" name="user_id">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="role">Role</label>
                                <select id="role" name="role" required>
                                    <option value="admin">Admin</option>
                                    <option value="cashier">Cashier</option>
                                </select>
                            </div>
                            <div class="form-group" id="passwordGroup">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password">
                                <small>Leave blank to keep current password (for existing users)</small>
                            </div>
                            <div class="modal-actions">
                                <button type="button" class="cancel-btn" onclick="closeUserModal()">Cancel</button>
                                <button type="submit" class="save-btn">Save User</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Soft Delete Confirmation Modal -->
                <div id="deleteConfirmModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4>Deactivate User Account</h4>
                            <span class="close" onclick="closeDeleteConfirmModal()">&times;</span>
                        </div>
                        <div class="modal-body">
                            <div class="delete-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <p>This will deactivate the user account but preserve all data.</p>
                            </div>
                            <div class="user-info">
                                <p><strong>User:</strong> <span id="deleteUserName"></span></p>
                                <p><strong>Email:</strong> <span id="deleteUserEmail"></span></p>
                                <p><strong>Role:</strong> <span id="deleteUserRole"></span></p>
                            </div>
                            <div class="form-group">
                                <label for="deletionReason">Reason for deactivation:</label>
                                <textarea id="deletionReason" placeholder="Enter reason for deactivating this account..." rows="3"></textarea>
                            </div>
                            <div class="delete-options">
                                <p><strong>What happens when you deactivate:</strong></p>
                                <ul>
                                    <li>✓ User cannot log in</li>
                                    <li>✓ All data is preserved</li>
                                    <li>✓ Account can be restored later</li>
                                    <li>✓ Audit trail is maintained</li>
                                </ul>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-danger" onclick="confirmSoftDelete()">
                                <i class="fas fa-user-slash"></i> Deactivate Account
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="closeDeleteConfirmModal()">Cancel</button>
                        </div>
                    </div>
                </div>

                <!-- Restore User Modal -->
                <div id="restoreConfirmModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4>Restore User Account</h4>
                            <span class="close" onclick="closeRestoreConfirmModal()">&times;</span>
                        </div>
                        <div class="modal-body">
                            <div class="restore-info">
                                <i class="fas fa-user-check"></i>
                                <p>This will restore the user account and allow them to log in again.</p>
                            </div>
                            <div class="user-info">
                                <p><strong>User:</strong> <span id="restoreUserName"></span></p>
                                <p><strong>Email:</strong> <span id="restoreUserEmail"></span></p>
                                <p><strong>Deleted:</strong> <span id="restoreDeletedDate"></span></p>
                                <p><strong>Reason:</strong> <span id="restoreDeletionReason"></span></p>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-success" onclick="confirmRestore()">
                                <i class="fas fa-undo"></i> Restore Account
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="closeRestoreConfirmModal()">Cancel</button>
                        </div>
                    </div>
                </div>

                <!-- Permanent Delete Confirmation Modal -->
                <div id="permanentDeleteModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4>Permanent Delete Confirmation</h4>
                            <span class="close" onclick="closePermanentDeleteModal()">&times;</span>
                        </div>
                        <div class="modal-body">
                            <div class="warning-message">
                                <i class="fas fa-exclamation-triangle warning-icon"></i>
                                <div>
                                    <p><strong>This will permanently delete the user account and cannot be undone.</strong></p>
                                    <p>User: <strong id="permanentDeleteUserName"></strong></p>
                                </div>
                            </div>
                            
                            <div class="user-details">
                                <p><strong>Email:</strong> <span id="permanentDeleteUserEmail"></span></p>
                                <p><strong>Deleted:</strong> <span id="permanentDeleteDate"></span></p>
                                <p><strong>Reason:</strong> <span id="permanentDeleteReason"></span></p>
                            </div>

                            <div class="confirmation-section">
                                <p>Type <strong>DELETE</strong> to confirm:</p>
                                <input type="text" id="permanentDeleteConfirmation" placeholder="Type DELETE to confirm" class="confirmation-input">
                                <div class="confirmation-status" id="confirmationStatus"></div>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-danger" id="confirmPermanentDeleteBtn" onclick="executePermanentDelete()" disabled>
                                <i class="fas fa-trash"></i> Delete Permanently
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="closePermanentDeleteModal()">Cancel</button>
                        </div>
                    </div>
                </div>
            </section>
        `;
        this.loadUsers();
        this.setupUserFormHandlers();
    }

    setupUserFormHandlers() {
        const searchInput = document.getElementById('userSearch');
        const roleFilter = document.getElementById('roleFilter');

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.loadUsers();
                }, 300);
            });
        }

        if (roleFilter) {
            roleFilter.addEventListener('change', () => this.loadUsers());
        }

        const deletedFilter = document.getElementById('deletedFilter');
        if (deletedFilter) {
            deletedFilter.addEventListener('change', () => this.loadUsers());
        }
    }

    async loadUsers() {
        try {
            const searchInput = document.getElementById('userSearch');
            const roleFilter = document.getElementById('roleFilter');
            const deletedFilter = document.getElementById('deletedFilter');
            
            const params = new URLSearchParams({
                action: 'get_users',
                search: searchInput?.value || '',
                role: roleFilter?.value || 'all',
                include_deleted: deletedFilter?.value || 'false',
                include_customers: 'true'
            });

            const response = await fetch(`api/user_management.php?${params}`);
            const data = await response.json();
            if (data.success) {
                this.renderUsersTable(data.users);
            } else {
                console.error('Error loading users:', data.error);
                document.getElementById('usersTableBody').innerHTML = 
                    '<tr><td colspan="7" class="error-loading">Error loading users: ' + data.error + '</td></tr>';
            }
        } catch (error) {
            console.error('Error loading users:', error);
            document.getElementById('usersTableBody').innerHTML = 
                '<tr><td colspan="7" class="error-loading">Network error: ' + error.message + '</td></tr>';
        }
    }

    renderUsersTable(users) {
        const tbody = document.getElementById('usersTableBody');
        
        if (!users || users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="no-users">No users found</td></tr>';
            return;
        }

        tbody.innerHTML = users.map(user => {
            const isDeleted = user.deleted_at !== null;
            const rowClass = isDeleted ? 'deleted-user' : '';
            
            const statusBadge = isDeleted 
                ? '<span class="status-badge deleted">Deleted</span>'
                : `<span class="status-badge ${user.status}">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</span>`;
            
            const actions = isDeleted 
                ? `<button class="action-btn restore-btn" onclick="showRestoreModal(${user.id}, '${user.username}', '${user.email}', '${user.deleted_at}', '${user.deletion_reason || ''}')" title="Restore User">
                     <i class="fas fa-undo"></i>
                   </button>
                   <button class="action-btn permanent-delete-btn" onclick="permanentDeleteUser(${user.id}, '${user.username}', '${user.email}', '${user.deleted_at}', '${user.deletion_reason || ''}')" title="Permanent Delete">
                     <i class="fas fa-trash-alt"></i>
                   </button>`
                : `<button class="action-btn edit-btn" onclick="editUser(${user.id})" title="Edit User">
                     <i class="fas fa-edit"></i>
                   </button>
                   <button class="action-btn delete-btn" onclick="showSoftDeleteModal(${user.id}, '${user.username}', '${user.email}', '${user.role}')" title="Deactivate User">
                     <i class="fas fa-user-slash"></i>
                   </button>`;
            
            return `
                <tr class="${rowClass}">
                    <td>${user.id}</td>
                    <td>
                        <div class="user-info">
                            <span class="username">${user.username}</span>
                            ${isDeleted ? '<i class="fas fa-user-slash deleted-icon" title="Deleted User"></i>' : ''}
                        </div>
                    </td>
                    <td>${user.email}</td>
                    <td><span class="role-badge ${user.role}">${user.role === 'user' ? 'Customer' : user.role.charAt(0).toUpperCase() + user.role.slice(1)}</span></td>
                    <td>${statusBadge}</td>
                    <td>${user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never'}</td>
                    <td class="actions-cell">${actions}</td>
                </tr>
            `;
        }).join('');
    }

    async saveUser() {
        const form = document.getElementById('userForm');
        const formData = new FormData(form);
        const userId = formData.get('user_id');
        
        const userData = {
            action: 'save_user',
            user_id: userId || null,
            username: formData.get('username'),
            email: formData.get('email'),
            role: formData.get('role'),
            password: formData.get('password') || ''
        };

        console.log('Sending user data:', userData);
        
        try {
            // First test the connection
            const testResponse = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'test_connection' })
            });
            
            if (testResponse.ok) {
                const testData = await testResponse.json();
                console.log('Connection test:', testData);
            }
            
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(userData)
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Error response body:', errorText);
                throw new Error(`HTTP error! status: ${response.status}, body: ${errorText.substring(0, 200)}`);
            }

            const responseText = await response.text();
            console.log('Raw response:', responseText);
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', responseText);
                console.error('Response length:', responseText.length);
                throw new Error('Invalid JSON response from server: ' + responseText.substring(0, 200));
            }
            
            if (data.success) {
                this.dashboard.showNotification(data.message, 'success');
                this.closeUserModal();
                this.loadUsers();
            } else {
                this.dashboard.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error saving user:', error);
            this.dashboard.showNotification('Error saving user', 'error');
        }
    }

    // Soft delete functionality is now handled by global functions in soft-delete-functions.js

    showAddUserModal() {
        const modal = document.getElementById('userModal');
        const modalTitle = document.getElementById('modalTitle');
        const form = document.getElementById('userForm');
        const passwordGroup = document.getElementById('passwordGroup');
        
        modalTitle.textContent = 'Add New User';
        form.reset();
        document.getElementById('userId').value = '';
        
        if (passwordGroup) {
            passwordGroup.querySelector('input').required = true;
            passwordGroup.querySelector('small').style.display = 'none';
        }
        
        modal.style.display = 'flex';
        modal.style.visibility = 'visible';
        modal.style.opacity = '1';
    }

    async showEditUserModal(userId) {
        try {
            const response = await fetch(`api/superadmin_api/super_admin_actions.php?action=get_user&user_id=${userId}`);
            const data = await response.json();
            
            if (data.success && data.user) {
                const user = data.user;
                const modal = document.getElementById('userModal');
                const modalTitle = document.getElementById('modalTitle');
                const passwordGroup = document.getElementById('passwordGroup');
                
                modalTitle.textContent = 'Edit User';
                
                document.getElementById('userId').value = user.id;
                document.getElementById('username').value = user.username || '';
                document.getElementById('email').value = user.email || '';
                document.getElementById('role').value = user.role || 'user';
                
                if (passwordGroup) {
                    passwordGroup.querySelector('input').required = false;
                    passwordGroup.querySelector('small').style.display = 'block';
                }
                
                modal.style.display = 'flex';
                modal.style.visibility = 'visible';
                modal.style.opacity = '1';
            } else {
                this.dashboard.showNotification('Error loading user data', 'error');
            }
        } catch (error) {
            console.error('Error loading user:', error);
            this.dashboard.showNotification('Error loading user data', 'error');
        }
    }

    closeUserModal() {
        const modal = document.getElementById('userModal');
        modal.style.display = 'none';
        modal.style.visibility = 'hidden';
        modal.style.opacity = '0';
    }

    // Old loadUsers method removed - using the new soft delete enabled version above
}
