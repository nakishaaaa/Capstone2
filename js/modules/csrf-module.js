/**
 * CSRF Module
 * Centralized CSRF token management
 */

import { API_ENDPOINTS } from './config-module.js';

class CsrfService {
    constructor() {
        this.token = null;
        this.loading = null;
    }

    async load() {
        if (this.loading) return this.loading;
        
        this.loading = (async () => {
            try {
                const response = await fetch(API_ENDPOINTS.CSRF_TOKEN, {
                    credentials: 'include'
                });
                const data = await response.json();
                if (data.success) {
                    this.token = data.token;
                }
            } catch (error) {
                console.error('Failed to load CSRF token:', error);
            } finally {
                this.loading = null;
            }
            return this.token;
        })();
        
        return this.loading;
    }

    getToken() {
        return this.token;
    }

    async ensure() {
        if (!this.token) {
            await this.load();
        }
        return this.token;
    }
}

export const csrfService = new CsrfService();
