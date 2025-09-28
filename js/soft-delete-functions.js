// Soft Delete Global Functions for User Management
// Include this file in super_admin_dashboard.php

let currentDeleteUserId = null;
let currentRestoreUserId = null;

// Show soft delete confirmation modal
function showSoftDeleteModal(userId, username, email, role) {
    console.log('showSoftDeleteModal called with:', { userId, username, email, role });
    
    currentDeleteUserId = userId;
    
    const modal = document.getElementById('deleteConfirmModal');
    if (!modal) {
        console.error('Delete confirmation modal not found in DOM');
        alert('Error: Delete confirmation modal not found. Please refresh the page.');
        return;
    }
    
    const nameElement = document.getElementById('deleteUserName');
    const emailElement = document.getElementById('deleteUserEmail');
    const roleElement = document.getElementById('deleteUserRole');
    const reasonElement = document.getElementById('deletionReason');
    
    if (!nameElement || !emailElement || !roleElement || !reasonElement) {
        console.error('Modal elements not found:', {
            nameElement: !!nameElement,
            emailElement: !!emailElement,
            roleElement: !!roleElement,
            reasonElement: !!reasonElement
        });
        alert('Error: Modal elements not found. Please refresh the page.');
        return;
    }
    
    nameElement.textContent = username;
    emailElement.textContent = email;
    roleElement.textContent = role.charAt(0).toUpperCase() + role.slice(1);
    reasonElement.value = '';
    
    modal.style.display = 'block';
    modal.style.visibility = 'visible';
    modal.style.opacity = '1';
    console.log('Modal should now be visible');
    console.log('Modal computed styles:', window.getComputedStyle(modal).display, window.getComputedStyle(modal).visibility, window.getComputedStyle(modal).opacity);
}

// Close soft delete modal
function closeDeleteConfirmModal() {
    const modal = document.getElementById('deleteConfirmModal');
    modal.style.display = 'none';
    modal.style.visibility = 'hidden';
    modal.style.opacity = '0';
    currentDeleteUserId = null;
}

// Show restore confirmation modal
function showRestoreModal(userId, username, email, deletedAt, deletionReason) {
    currentRestoreUserId = userId;
    
    document.getElementById('restoreUserName').textContent = username;
    document.getElementById('restoreUserEmail').textContent = email;
    document.getElementById('restoreDeletedDate').textContent = new Date(deletedAt).toLocaleString();
    document.getElementById('restoreDeletionReason').textContent = deletionReason || 'No reason provided';
    
    document.getElementById('restoreConfirmModal').style.display = 'block';
}

// Close restore modal
function closeRestoreConfirmModal() {
    document.getElementById('restoreConfirmModal').style.display = 'none';
    currentRestoreUserId = null;
}

// Confirm soft delete
async function confirmSoftDelete() {
    if (!currentDeleteUserId) return;
    
    const reason = document.getElementById('deletionReason').value.trim() || 'Deleted by administrator';
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete_user');
        formData.append('user_id', currentDeleteUserId);
        formData.append('reason', reason);
        formData.append('csrf_token', window.csrfToken);
        
        const response = await fetch('api/user_management.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', `User account deactivated: ${result.username}`);
            closeDeleteConfirmModal();
            // Reload users list
            if (window.dashboard && window.dashboard.userManagementModule) {
                window.dashboard.userManagementModule.loadUsers();
            }
        } else {
            showNotification('error', result.error || 'Failed to deactivate user');
        }
    } catch (error) {
        console.error('Error deactivating user:', error);
        showNotification('error', 'Network error occurred');
    }
}

// Confirm restore user
async function confirmRestore() {
    if (!currentRestoreUserId) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'restore_user');
        formData.append('user_id', currentRestoreUserId);
        formData.append('csrf_token', window.csrfToken);
        
        const response = await fetch('api/user_management.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', `User account restored: ${result.username}`);
            closeRestoreConfirmModal();
            // Reload users list
            if (window.dashboard && window.dashboard.userManagementModule) {
                window.dashboard.userManagementModule.loadUsers();
            }
        } else {
            showNotification('error', result.error || 'Failed to restore user');
        }
    } catch (error) {
        console.error('Error restoring user:', error);
        showNotification('error', 'Network error occurred');
    }
}

// Show permanent delete modal
function permanentDeleteUser(userId, username, email, deletedAt, deletionReason) {
    console.log('Opening permanent delete modal for:', { userId, username, email });
    
    // Store the user data for the confirmation
    window.currentPermanentDeleteUser = { userId, username, email, deletedAt, deletionReason };
    
    // Populate modal with user information
    document.getElementById('permanentDeleteUserName').textContent = username;
    document.getElementById('permanentDeleteUserEmail').textContent = email;
    document.getElementById('permanentDeleteDate').textContent = deletedAt ? new Date(deletedAt).toLocaleString() : 'Unknown';
    document.getElementById('permanentDeleteReason').textContent = deletionReason || 'No reason provided';
    
    // Reset confirmation input and button
    document.getElementById('permanentDeleteConfirmation').value = '';
    document.getElementById('confirmPermanentDeleteBtn').disabled = true;
    document.getElementById('confirmationStatus').innerHTML = '';
    
    // Show the modal
    document.getElementById('permanentDeleteModal').style.display = 'block';
    document.getElementById('permanentDeleteModal').style.visibility = 'visible';
    document.getElementById('permanentDeleteModal').style.opacity = '1';
    
    // Focus on the confirmation input
    setTimeout(() => {
        document.getElementById('permanentDeleteConfirmation').focus();
    }, 100);
    
    // Add event listener for confirmation input
    const confirmationInput = document.getElementById('permanentDeleteConfirmation');
    const confirmButton = document.getElementById('confirmPermanentDeleteBtn');
    const statusDiv = document.getElementById('confirmationStatus');
    
    confirmationInput.oninput = function() {
        const value = this.value.trim();
        if (value === 'DELETE') {
            confirmButton.disabled = false;
            confirmButton.classList.add('enabled');
            statusDiv.innerHTML = '<span class="confirmation-valid">✅ Confirmation valid - deletion enabled</span>';
        } else if (value.length > 0) {
            confirmButton.disabled = true;
            confirmButton.classList.remove('enabled');
            statusDiv.innerHTML = '<span class="confirmation-invalid">❌ Must type exactly "DELETE"</span>';
        } else {
            confirmButton.disabled = true;
            confirmButton.classList.remove('enabled');
            statusDiv.innerHTML = '';
        }
    };
}

// Close permanent delete modal
function closePermanentDeleteModal() {
    const modal = document.getElementById('permanentDeleteModal');
    modal.style.display = 'none';
    modal.style.visibility = 'hidden';
    modal.style.opacity = '0';
    
    // Clear stored user data
    window.currentPermanentDeleteUser = null;
    
    // Reset form
    document.getElementById('permanentDeleteConfirmation').value = '';
    document.getElementById('confirmPermanentDeleteBtn').disabled = true;
    document.getElementById('confirmationStatus').innerHTML = '';
}

// Execute permanent delete after confirmation
async function executePermanentDelete() {
    const userData = window.currentPermanentDeleteUser;
    if (!userData) {
        showNotification('error', 'No user selected for deletion');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'permanent_delete');
        formData.append('user_id', userData.userId);
        formData.append('csrf_token', window.csrfToken);
        
        const response = await fetch('api/user_management.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', `User permanently deleted: ${result.username}`);
            closePermanentDeleteModal();
            // Reload users list
            if (window.dashboard && window.dashboard.userManagementModule) {
                window.dashboard.userManagementModule.loadUsers();
            }
        } else {
            showNotification('error', result.error || 'Failed to permanently delete user');
        }
    } catch (error) {
        console.error('Error permanently deleting user:', error);
        showNotification('error', 'Network error occurred');
    }
}

// Generate user table row with soft delete support
function generateUserTableRow(user) {
    const isDeleted = user.deleted_at !== null;
    const rowClass = isDeleted ? 'deleted-user' : '';
    
    const statusBadge = isDeleted 
        ? '<span class="status-badge deleted">Deleted</span>'
        : `<span class="status-badge ${user.status}">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</span>`;
    
    const deletedInfo = isDeleted 
        ? `<div class="deleted-info">
             <small>Deleted: ${new Date(user.deleted_at).toLocaleDateString()}</small><br>
             <small>By: ${user.deleted_by_username || 'System'}</small>
             ${user.deletion_reason ? `<br><small>Reason: ${user.deletion_reason}</small>` : ''}
           </div>`
        : '<span class="not-deleted">Active</span>';
    
    const actions = isDeleted 
        ? `<button class="action-btn restore-btn" onclick="showRestoreModal(${user.id}, '${user.username}', '${user.email}', '${user.deleted_at}', '${user.deletion_reason || ''}')" title="Restore User">
             <i class="fas fa-undo"></i>
           </button>
           <button class="action-btn permanent-delete-btn" onclick="permanentDeleteUser(${user.id}, '${user.username}')" title="Permanent Delete">
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
            <td><span class="role-badge ${user.role}">${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</span></td>
            <td>${statusBadge}</td>
            <td>${user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never'}</td>
            <td>${deletedInfo}</td>
            <td class="actions-cell">${actions}</td>
        </tr>
    `;
}

// Show notification helper
function showNotification(type, message) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 5000);
}

// Close modals when clicking outside
window.onclick = function(event) {
    const deleteModal = document.getElementById('deleteConfirmModal');
    const restoreModal = document.getElementById('restoreConfirmModal');
    const permanentModal = document.getElementById('permanentDeleteModal');
    
    if (event.target === deleteModal) {
        closeDeleteConfirmModal();
    }
    if (event.target === restoreModal) {
        closeRestoreConfirmModal();
    }
    if (event.target === permanentModal) {
        closePermanentDeleteModal();
    }
}
