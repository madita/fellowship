/**
 * Session Timeout Handler
 *
 * Sets up axios interceptors to detect session expiration (401/419 responses)
 * and triggers a custom event to show the session timeout modal.
 *
 * HTTP Status Codes:
 * - 401 Unauthorized: User is not authenticated
 * - 419 Page Expired: CSRF token mismatch (Laravel session expired)
 */

import { debug } from '@/utils/debug.js';

const log = debug.module('SessionTimeout');

// Track if modal has already been shown to prevent multiple triggers
let sessionTimeoutTriggered = false;

// Endpoints that should NOT trigger session timeout modal
// (e.g., login attempts, public endpoints)
const EXCLUDED_ENDPOINTS = [
    '/login',
    '/register',
    '/forgot-password',
    '/reset-password',
    '/sanctum/csrf-cookie',
    '/api/settings', // Public settings endpoint
];

/**
 * Check if the request URL should be excluded from session timeout handling
 */
const isExcludedEndpoint = (url) => {
    if (!url) return false;
    return EXCLUDED_ENDPOINTS.some(endpoint => url.includes(endpoint));
};

/**
 * Trigger the session timeout modal
 */
const triggerSessionTimeout = () => {
    if (sessionTimeoutTriggered) {
        log.debug('Session timeout already triggered, skipping');
        return;
    }

    sessionTimeoutTriggered = true;
    log.info('Session expired, showing timeout modal');

    // Dispatch custom event that SessionTimeoutModal listens for
    window.dispatchEvent(new CustomEvent('session-timeout'));
};

/**
 * Reset the session timeout state (call after successful login)
 */
export const resetSessionTimeoutState = () => {
    sessionTimeoutTriggered = false;
    log.debug('Session timeout state reset');
};

/**
 * Setup axios interceptors for session timeout detection
 */
export const setupSessionTimeoutInterceptor = () => {
    if (!window.axios) {
        log.error('Axios not found on window object');
        return;
    }

    // Response interceptor
    window.axios.interceptors.response.use(
        // Success handler - pass through
        (response) => response,

        // Error handler
        (error) => {
            const status = error.response?.status;
            const requestUrl = error.config?.url;

            // Check for session expiration status codes
            if (status === 401 || status === 419) {
                // Don't trigger for excluded endpoints (login, register, etc.)
                if (!isExcludedEndpoint(requestUrl)) {
                    log.warn(`Received ${status} response from ${requestUrl}`);
                    triggerSessionTimeout();
                }
            }

            // Always reject the promise so calling code can handle if needed
            return Promise.reject(error);
        }
    );

    log.debug('Session timeout interceptor installed');
};

/**
 * Vue plugin for session timeout handling
 */
export default {
    install(app) {
        setupSessionTimeoutInterceptor();

        // Expose reset function globally for use after login
        app.config.globalProperties.$resetSessionTimeout = resetSessionTimeoutState;

        // Also expose on window for non-Vue contexts
        window.resetSessionTimeout = resetSessionTimeoutState;
    }
};
