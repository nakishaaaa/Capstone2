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
    
    async loadUserInfo() {
        try {
            const response = await fetch(API_ENDPOINTS.USER_ACCOUNT, {
                method: 'GET',
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                const user = data.user;
                document.getElementById('accountName').textContent = user.name || 'N/A';
                document.getElementById('accountEmail').textContent = user.email || 'N/A';
                document.getElementById('accountRole').textContent = user.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : 'N/A';
                document.getElementById('accountCreated').textContent = user.created_at || 'N/A';
            } else {
                window.showModal('error', data.error || 'Failed to load user information');
            }
        } catch (error) {
            console.error('Error loading user info:', error);
            window.showModal('error', 'Failed to load user information');
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
    
    resetPasswordForm() {
        const form = document.getElementById('changePasswordForm');
        if (form) {
            form.reset();
            
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
