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
                            <option value="user">User</option>
                            <option value="cashier">Cashier</option>
                        </select>
                        <select id="statusFilter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="users-table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
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
                                <td colspan="6" class="loading">Loading users...</td>
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
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                    <option value="cashier">Cashier</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
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
            </section>
        `;
        this.loadUsers();
        this.setupUserFormHandlers();
    }

    setupUserFormHandlers() {
        const searchInput = document.getElementById('userSearch');
        const roleFilter = document.getElementById('roleFilter');
        const statusFilter = document.getElementById('statusFilter');

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

        if (statusFilter) {
            statusFilter.addEventListener('change', () => this.loadUsers());
        }
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
            status: formData.get('status'),
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

    async deleteUser(userId) {
        if (!confirm('Are you sure you want to delete this user account? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch('api/superadmin_api/super_admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_user',
                    user_id: userId
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.dashboard.showNotification(data.message, 'success');
                this.loadUsers();
            } else {
                this.dashboard.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error deleting user:', error);
            this.dashboard.showNotification('Error deleting user', 'error');
        }
    }

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
                document.getElementById('status').value = user.status || 'active';
                
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

    async loadUsers() {
        try {
            const searchInput = document.getElementById('userSearch');
            const roleFilter = document.getElementById('roleFilter');
            const statusFilter = document.getElementById('statusFilter');
            
            const searchTerm = searchInput ? searchInput.value : '';
            const roleValue = roleFilter ? roleFilter.value : '';
            const statusValue = statusFilter ? statusFilter.value : '';
            
            let url = 'api/superadmin_api/super_admin_actions.php?action=get_users';
            
            if (searchTerm) {
                url += `&search=${encodeURIComponent(searchTerm)}`;
            }
            if (roleValue && roleValue !== 'all') {
                url += `&role=${encodeURIComponent(roleValue)}`;
            }
            if (statusValue && statusValue !== 'all') {
                url += `&status=${encodeURIComponent(statusValue)}`;
            }
            
            const response = await fetch(url);
            const data = await response.json();
            
            const tbody = document.getElementById('usersTableBody');
            
            if (data.success && data.users) {
                tbody.innerHTML = data.users.map(user => `
                    <tr>
                        <td>
                            <div class="user-info">
                                <div class="user-details">
                                    <h4>${user.username || 'Unknown User'}</h4>
                                </div>
                            </div>
                        </td>
                        <td>${user.email}</td>
                        <td><span class="role-badge ${user.role}">${user.role.toUpperCase()}</span></td>
                        <td><span class="status-badge ${user.status}">${user.status.toUpperCase()}</span></td>
                        <td>${user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never'}</td>
                        <td>
                            <div class="action-buttons">
                                <button onclick="editUser(${user.id})" class="action-btn edit-btn" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteUser(${user.id})" class="action-btn delete-btn" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="no-data">No users found</td></tr>';
            }
        } catch (error) {
            console.error('Error loading users:', error);
            document.getElementById('usersTableBody').innerHTML = '<tr><td colspan="6" class="error-row">Error loading users</td></tr>';
        }
    }
}
