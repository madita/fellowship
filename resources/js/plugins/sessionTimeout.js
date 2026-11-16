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
// Returns true if this call actually triggered the timeout, false if it was
// already in progress. Callers use the return value to ensure only the first
// expired request schedules the logout/redirect.
export const triggerSessionTimeout = () => {
    if (sessionTimeoutTriggered) {
        log.log('Session timeout already triggered, skipping');
        return false;
    }

    sessionTimeoutTriggered = true;
    log.info('Session expired, showing timeout modal');

    // Dispatch custom event that SessionTimeoutModal listens for
    window.dispatchEvent(new CustomEvent('session-timeout'));

    return true;
};

/**
 * Reset the session timeout state (call after successful login)
 */
export const resetSessionTimeoutState = () => {
    sessionTimeoutTriggered = false;
    log.log('Session timeout state reset');
};

/**
 * Setup axios interceptors for session timeout detection
 */
export const setupSessionTimeoutInterceptor = () => {
    if (!window.axios) {
        log.error('Axios not found on window object');
        return;
    }

    // 401/419 handling is owned by middleware401.js (CSRF retry + delayed logout).
    // This plugin only exposes triggerSessionTimeout for middleware401 to call
    // after all recovery attempts are exhausted.
    log.log('Session timeout handler registered');
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
