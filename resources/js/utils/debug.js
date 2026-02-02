/**
 * Debug utility for conditional logging
 *
 * Usage:
 *   import { debug } from '@/utils/debug'
 *   debug.log('message')
 *   debug.error('error message')
 *   debug.warn('warning')
 *   debug.info('info')
 *
 * Enable/disable in browser console:
 *   window.DEBUG = true   // Enable debug mode
 *   window.DEBUG = false  // Disable debug mode
 *
 * Or set in .env:
 *   VITE_DEBUG=true
 */

const isDev = import.meta.env.DEV
const envDebug = import.meta.env.VITE_DEBUG === 'true'

/**
 * Check if debug mode is enabled
 */
function isDebugEnabled() {
    // Check window.DEBUG first (runtime toggle)
    if (typeof window !== 'undefined' && window.DEBUG !== undefined) {
        return window.DEBUG
    }
    // Fall back to env variable or dev mode
    return envDebug || isDev
}

/**
 * Create a prefixed log message
 */
function formatMessage(prefix, args) {
    const timestamp = new Date().toISOString().split('T')[1].slice(0, 12)
    return [`[${timestamp}] [${prefix}]`, ...args]
}

export const debug = {
    /**
     * Log a debug message (only in debug mode)
     */
    log(...args) {
        if (isDebugEnabled()) {
            console.log(...formatMessage('DEBUG', args))
        }
    },

    /**
     * Log an info message (only in debug mode)
     */
    info(...args) {
        if (isDebugEnabled()) {
            console.info(...formatMessage('INFO', args))
        }
    },

    /**
     * Log a warning (only in debug mode)
     */
    warn(...args) {
        if (isDebugEnabled()) {
            console.warn(...formatMessage('WARN', args))
        }
    },

    /**
     * Log an error (always shown, but with debug formatting in debug mode)
     */
    error(...args) {
        if (isDebugEnabled()) {
            console.error(...formatMessage('ERROR', args))
        } else {
            // Always log errors, but without debug prefix in production
            console.error(...args)
        }
    },

    /**
     * Log a group of related messages
     */
    group(label, fn) {
        if (isDebugEnabled()) {
            console.group(`[DEBUG] ${label}`)
            fn()
            console.groupEnd()
        }
    },

    /**
     * Log with a specific category/module name
     */
    module(moduleName) {
        return {
            log: (...args) => debug.log(`[${moduleName}]`, ...args),
            info: (...args) => debug.info(`[${moduleName}]`, ...args),
            warn: (...args) => debug.warn(`[${moduleName}]`, ...args),
            error: (...args) => debug.error(`[${moduleName}]`, ...args),
        }
    },

    /**
     * Check if debug mode is currently enabled
     */
    isEnabled() {
        return isDebugEnabled()
    },

    /**
     * Enable debug mode at runtime
     */
    enable() {
        if (typeof window !== 'undefined') {
            window.DEBUG = true
            console.log('[DEBUG] Debug mode enabled')
        }
    },

    /**
     * Disable debug mode at runtime
     */
    disable() {
        if (typeof window !== 'undefined') {
            window.DEBUG = false
            console.log('[DEBUG] Debug mode disabled')
        }
    },
}

// Expose debug toggle to window for easy access in browser console
if (typeof window !== 'undefined') {
    window.enableDebug = debug.enable
    window.disableDebug = debug.disable
}

export default debug
