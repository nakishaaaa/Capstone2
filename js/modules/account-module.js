/**
 * Account Management Module
 * Handles user account card functionality and password management
 */

import { API_ENDPOINTS } from './config-module.js';
import { csrfService } from './csrf-module.js';

export class AccountManager {
    constructor() {
        this.accountCard = null;
        this.closeAccountCard = null;
        this.tabBtns = null;
        this.changePasswordForm = null;
        this.passwordToggles = null;
        
        this.init();
    }
    
    init() {
        this.initializeElements();
        this.setupEventListeners();
    }
    
    initializeElements() {
        this.accountCard = document.getElementById('myAccountCard');
        this.closeAccountCard = document.getElementById('closeAccountCard');
        this.tabBtns = document.querySelectorAll('.tab-btn');
        this.changePasswordForm = document.getElementById('changePasswordForm');
        this.passwordToggles = document.querySelectorAll('.password-toggle');
    }
    
    setupEventListeners() {
        // Close account card
        if (this.closeAccountCard) {
            this.closeAccountCard.addEventListener('click', () => this.hideAccountCard());
        }

        // Tab switching
        this.tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabName = btn.dataset.tab;
                this.switchTab(tabName);
            });
        });

        // Password form submission
        if (this.changePasswordForm) {
            this.changePasswordForm.addEventListener('submit', (e) => this.handlePasswordChange(e));
        }

        // Account edit functionality
        this.setupAccountEditListeners();

        // Password reset event listeners
        this.setupPasswordResetEventListeners();

        // Password visibility toggles
        this.passwordToggles.forEach(toggle => {
            toggle.addEventListener('click', () => {
                const targetId = toggle.dataset.target;
                const targetInput = document.getElementById(targetId);
                const iconImg = toggle.querySelector('img');
                
                if (targetInput.type === 'password') {
                    targetInput.type = 'text';
                    if (iconImg) {
                        iconImg.src = 'images/svg/eye-black.svg';
                        iconImg.alt = 'Hide password';
                    }
                } else {
                    targetInput.type = 'password';
                    if (iconImg) {
                        iconImg.src = 'images/svg/eye-slash-black.svg';
                        iconImg.alt = 'Show password';
                    }
                }
            });
        });

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.accountCard && this.accountCard.classList.contains('show')) {
                this.hideAccountCard();
            }
        });
    }
    
    showAccountCard() {
        if (this.accountCard) {
            this.accountCard.style.display = 'flex';
            setTimeout(() => {
                this.accountCard.classList.add('show');
            }, 10);
            
            // Load user info when showing the card
            this.loadUserInfo();
        }
    }
    
    hideAccountCard() {
        if (this.accountCard) {
            this.accountCard.classList.remove('show');
            setTimeout(() => {
                this.accountCard.style.display = 'none';
            }, 300);
        }
    }
    
    switchTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

        // Update tab panels
        document.querySelectorAll('.tab-panel').forEach(panel => {
            panel.classList.remove('active');
        });
        document.getElementById(`${tabName}Tab`).classList.add('active');
    }
    
    setupAccountEditListeners() {
        // Edit profile button
        const editProfileBtn = document.getElementById('editProfileBtn');
        if (editProfileBtn) {
            editProfileBtn.addEventListener('click', () => this.showEditMode());
        }

        // Cancel edit button
        const cancelEditBtn = document.getElementById('cancelEditBtn');
        if (cancelEditBtn) {
            cancelEditBtn.addEventListener('click', () => this.showViewMode());
        }

        // Account update form submission
        const updateAccountForm = document.getElementById('updateAccountForm');
        if (updateAccountForm) {
            updateAccountForm.addEventListener('submit', (e) => this.handleAccountUpdate(e));
        }
    }

    showEditMode() {
        // Hide view mode and show edit mode
        document.getElementById('accountViewMode').style.display = 'none';
        document.getElementById('accountEditMode').style.display = 'block';
        
        // Populate edit form with current data
        this.populateEditForm();
    }

    showViewMode() {
        // Hide edit mode and show view mode
        document.getElementById('accountEditMode').style.display = 'none';
        document.getElementById('accountViewMode').style.display = 'block';
    }

    populateEditForm() {
        // Get current values from view mode and populate edit form
        const username = document.getElementById('accountUsername').textContent;
        const fullName = document.getElementById('accountName').textContent;
        const email = document.getElementById('accountEmail').textContent;
        const contact = document.getElementById('accountContact').textContent;
        const created = document.getElementById('accountCreated').textContent;

        // Split full name into first and last name
        const nameParts = fullName !== 'N/A' ? fullName.split(' ') : ['', ''];
        const firstName = nameParts[0] || '';
        const lastName = nameParts.slice(1).join(' ') || '';

        // Populate form fields
        document.getElementById('editUsername').value = username !== 'N/A' ? username : '';
        document.getElementById('editFirstName').value = firstName;
        document.getElementById('editLastName').value = lastName;
        document.getElementById('editContact').value = contact !== 'N/A' ? contact.replace('+63 ', '+63') : '';
        
        // Set readonly fields
        document.getElementById('accountEmailReadonly').textContent = email;
        document.getElementById('accountCreatedReadonly').textContent = created;
    }

    async loadUserInfo() {
        try {
            const response = await fetch(API_ENDPOINTS.USER_ACCOUNT, {
                method: 'GET',
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                const user = data.user;
                // Combine firstname and lastname for full name with proper capitalization
                const capitalizeWords = (str) => {
                    return str.split(' ').map(word => 
                        word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
                    ).join(' ');
                };
                
                const fullName = `${user.firstname || ''} ${user.lastname || ''}`.trim();
                const capitalizedFullName = fullName ? capitalizeWords(fullName) : 'N/A';
                
                // Check if elements exist before setting textContent
                const accountNameEl = document.getElementById('accountName');
                const accountEmailEl = document.getElementById('accountEmail');
                const accountUsernameEl = document.getElementById('accountUsername');
                const accountContactEl = document.getElementById('accountContact');
                const accountCreatedEl = document.getElementById('accountCreated');
                
                if (accountNameEl) accountNameEl.textContent = capitalizedFullName;
                if (accountEmailEl) accountEmailEl.textContent = user.email || 'N/A';
                if (accountUsernameEl) accountUsernameEl.textContent = user.username || 'N/A';
                if (accountContactEl) {
                    let contactNumber = user.contact_number || 'N/A';
                    if (contactNumber.startsWith('+63') && contactNumber.length > 3) {
                        contactNumber = '+63 ' + contactNumber.substring(3);
                    }
                    accountContactEl.textContent = contactNumber;
                }
                if (accountCreatedEl) accountCreatedEl.textContent = user.created_at || 'N/A';
            } else {
                window.showModal('error', data.error || 'Failed to load user information');
            }
        } catch (error) {
            console.error('Error loading user info:', error);
            window.showModal('error', 'Failed to load user information');
        }
    }

    async handleAccountUpdate(event) {
        event.preventDefault();

        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;

        try {
            const formData = new FormData(event.target);
            const token = await csrfService.ensure();
            
            const updateData = {
                username: formData.get('username'),
                firstname: formData.get('firstname'),
                lastname: formData.get('lastname'),
                contact: formData.get('contact'),
                csrf_token: token
            };

            const response = await fetch(API_ENDPOINTS.USER_ACCOUNT, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': token
                },
                credentials: 'include',
                body: JSON.stringify(updateData)
            });

            const data = await response.json();

            if (data.success) {
                window.showModal('success', data.message || 'Profile updated successfully');
                // Reload user info and switch back to view mode
                await this.loadUserInfo();
                this.showViewMode();
            } else {
                window.showModal('error', data.error || 'Failed to update profile');
            }
        } catch (error) {
            console.error('Error updating profile:', error);
            window.showModal('error', 'Failed to update profile');
        } finally {
            // Reset button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }
    
    async handlePasswordChange(event) {
        event.preventDefault();

        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        submitBtn.disabled = true;

        try {
            const formData = new FormData(event.target);
            const token = await csrfService.ensure();
            
            const passwordData = {
                current_password: formData.get('current_password'),
                new_password: formData.get('new_password'),
                confirm_password: formData.get('confirm_password'),
                csrf_token: token
            };

            const response = await fetch(API_ENDPOINTS.USER_ACCOUNT, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': token
                },
                credentials: 'include',
                body: JSON.stringify(passwordData)
            });

            const data = await response.json();

            if (data.success) {
                window.showModal('success', data.message || 'Password updated successfully');
                this.resetPasswordForm();
            } else {
                window.showModal('error', data.error || 'Failed to update password');
            }
        } catch (error) {
            console.error('Error updating password:', error);
            window.showModal('error', 'Failed to update password');
        } finally {
            // Reset button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }
    
    setupPasswordResetEventListeners() {
        // Request password reset button
        const requestResetBtn = document.getElementById('requestPasswordResetBtn');
        if (requestResetBtn) {
            requestResetBtn.addEventListener('click', () => this.handleRequestPasswordReset());
        }

        // Resend email button
        const resendEmailBtn = document.getElementById('resendEmailBtn');
        if (resendEmailBtn) {
            resendEmailBtn.addEventListener('click', () => this.handleResendEmail());
        }

        // Back to request button
        const backToRequestBtn = document.getElementById('backToRequestBtn');
        if (backToRequestBtn) {
            backToRequestBtn.addEventListener('click', () => this.showResetRequestStep());
        }

        // Check if we're coming from an email verification link
        this.checkEmailVerification();
    }

    async handleRequestPasswordReset() {
        const requestBtn = document.getElementById('requestPasswordResetBtn');
        const originalText = requestBtn.innerHTML;
        
        requestBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        requestBtn.disabled = true;

        try {
            const token = await csrfService.ensure();
            
            const response = await fetch(API_ENDPOINTS.USER_ACCOUNT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': token
                },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'request_password_reset',
                    csrf_token: token
                })
            });

            const data = await response.json();

            if (data.success) {
                window.showModal('success', data.message || 'Verification email sent successfully');
                this.showEmailSentStep();
            } else {
                window.showModal('error', data.error || 'Failed to send verification email');
            }
        } catch (error) {
            console.error('Error requesting password reset:', error);
            window.showModal('error', 'Failed to send verification email');
        } finally {
            requestBtn.innerHTML = originalText;
            requestBtn.disabled = false;
        }
    }

    async handleResendEmail() {
        const resendBtn = document.getElementById('resendEmailBtn');
        const originalText = resendBtn.innerHTML;
        
        resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        resendBtn.disabled = true;

        try {
            const token = await csrfService.ensure();
            
            const response = await fetch(API_ENDPOINTS.USER_ACCOUNT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': token
                },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'request_password_reset',
                    csrf_token: token
                })
            });

            const data = await response.json();

            if (data.success) {
                window.showModal('success', 'New verification email sent');
            } else {
                window.showModal('error', data.error || 'Failed to resend email');
            }
        } catch (error) {
            console.error('Error resending email:', error);
            window.showModal('error', 'Failed to resend email');
        } finally {
            resendBtn.innerHTML = originalText;
            resendBtn.disabled = false;
        }
    }

    checkEmailVerification() {
        // Check URL parameters for email verification
        const urlParams = new URLSearchParams(window.location.search);
        const verified = urlParams.get('password_reset_verified');
        
        if (verified === 'true') {
            this.showPasswordChangeStep();
            // Clean up URL
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }

    showResetRequestStep() {
        document.getElementById('passwordResetRequest').style.display = 'block';
        document.getElementById('emailSentConfirmation').style.display = 'none';
        document.getElementById('passwordChangeForm').style.display = 'none';
    }

    showEmailSentStep() {
        document.getElementById('passwordResetRequest').style.display = 'none';
        document.getElementById('emailSentConfirmation').style.display = 'block';
        document.getElementById('passwordChangeForm').style.display = 'none';
    }

    showPasswordChangeStep() {
        document.getElementById('passwordResetRequest').style.display = 'none';
        document.getElementById('emailSentConfirmation').style.display = 'none';
        document.getElementById('passwordChangeForm').style.display = 'block';
    }

    resetPasswordForm() {
        const form = document.getElementById('changePasswordForm');
        if (form) {
            form.reset();
            
            // Reset to initial step
            this.showResetRequestStep();
            
            // Reset password visibility
            const passwordInputs = form.querySelectorAll('input[type="text"]');
            const toggles = form.querySelectorAll('.password-toggle img');
            
            passwordInputs.forEach(input => {
                input.type = 'password';
            });
            
            toggles.forEach(iconImg => {
                iconImg.src = 'images/svg/eye-slash.svg';
                iconImg.alt = 'Show password';
            });
        }
    }
}

// Make resetPasswordForm available globally for backward compatibility
window.resetPasswordForm = function() {
    if (window.accountManager) {
        window.accountManager.resetPasswordForm();
    }
};