/**
 * User Dropdown Module
 * Handles Microsoft-style user dropdown functionality
 */

export class UserDropdownManager {
    constructor() {
        this.userDropdownBtn = null;
        this.userDropdownMenu = null;
        this.myAccountBtn = null;
        this.myProfileBtn = null;
        
        this.init();
    }
    
    init() {
        this.initializeElements();
        this.setupEventListeners();
    }
    
    initializeElements() {
        this.userDropdownBtn = document.getElementById('userDropdownBtn');
        this.userDropdownMenu = document.getElementById('userDropdownMenu');
        this.myAccountBtn = document.getElementById('myAccountBtn');
        this.myProfileBtn = document.getElementById('myProfileBtn');
    }
    
    setupEventListeners() {
        if (!this.userDropdownBtn || !this.userDropdownMenu) {
            return;
        }

        // Toggle dropdown on button click
        this.userDropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleDropdown();
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!this.userDropdownBtn.contains(e.target) && !this.userDropdownMenu.contains(e.target)) {
                this.closeDropdown();
            }
        });

        // Handle my account click
        if (this.myAccountBtn) {
            this.myAccountBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (window.accountManager) {
                    window.accountManager.showAccountCard();
                }
                this.closeDropdown();
            });
        }

        // Handle my profile click
        if (this.myProfileBtn) {
            this.myProfileBtn.addEventListener('click', (e) => {
                e.preventDefault();
                window.showModal('info', 'My profile functionality will be implemented in a future update.');
                this.closeDropdown();
            });
        }

        // Close dropdown on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeDropdown();
            }
        });
    }
    
    toggleDropdown() {
        if (this.userDropdownMenu.classList.contains('show')) {
            this.closeDropdown();
        } else {
            this.openDropdown();
        }
    }
    
    openDropdown() {
        this.userDropdownBtn.classList.add('active');
        this.userDropdownMenu.classList.add('show');
    }
    
    closeDropdown() {
        if (this.userDropdownBtn) this.userDropdownBtn.classList.remove('active');
        if (this.userDropdownMenu) this.userDropdownMenu.classList.remove('show');
    }
}
