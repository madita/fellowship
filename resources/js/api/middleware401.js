import { useAuthStore } from '@/store/authStore.js'
import axios from 'axios'
import { refreshCsrfToken, resetCsrfRefreshTimer } from '@/api/middlewareCSRF.js'

/**
 * Get the value of a specific cookie
 */
const getCookieValue = (name) => {
    const cookies = document.cookie.split(';')
    for (let cookie of cookies) {
        const parts = cookie.split('=')
        const cookieName = parts[0].trim()
        if (cookieName === name && parts.length > 1) {
            const cookieValue = parts.slice(1).join('=').trim()
            try {
                return decodeURIComponent(cookieValue)
            } catch (e) {
                return cookieValue
            }
        }
    }
    return null
}

/**
 * Track retry attempts to prevent infinite loops
 */
const retryCount = new WeakMap()
const MAX_RETRIES = 1

/**
 * Middleware - handles authentication (401) and CSRF (419) errors
 * - 401: User lost authentication, redirect to login
 * - 419: CSRF token mismatch, refresh token and retry once
 */
const middleware401 = async error => {
    const { status } = error.request
    const originalRequest = error.config

    // Handle CSRF token mismatch (419)
    if (status === 419) {
        const currentRetries = retryCount.get(originalRequest) || 0

        if (currentRetries < MAX_RETRIES) {
            retryCount.set(originalRequest, currentRetries + 1)

            console.log('CSRF token mismatch (419), refreshing token and retrying...')

            try {
                // Force refresh the CSRF token
                resetCsrfRefreshTimer()
                await refreshCsrfToken()

                // Get the fresh token from cookie
                const freshToken = getCookieValue('XSRF-TOKEN')
                console.log('[419 Retry] Fresh token from cookie:', freshToken?.substring(0, 30) + '...')

                // Retry the original request with the fresh CSRF token
                originalRequest._retry = true

                // Ensure headers object exists and set the fresh token
                originalRequest.headers = originalRequest.headers || {}
                if (typeof originalRequest.headers.set === 'function') {
                    originalRequest.headers.set('X-XSRF-TOKEN', freshToken)
                } else {
                    originalRequest.headers['X-XSRF-TOKEN'] = freshToken
                }

                return axios({
                    ...originalRequest,
                    withCredentials: true
                })
            } catch (refreshError) {
                console.error('Failed to refresh CSRF token:', refreshError)
                // Fall through to logout if refresh fails
            }
        }

        console.error('CSRF token refresh failed after retry, logging out')
        const auth = useAuthStore()
        setTimeout(async () => await auth.logout(), 3000)
        return Promise.reject({
            name: 'Session expired',
            message: 'Your session has expired - redirecting to login page.',
        })
    }

    // Handle unauthorized (401)
    if (status === 401) {
        console.log('Unauthorized (401), logging out')
        const auth = useAuthStore()
        setTimeout(async () => await auth.logout(), 3000)
        return Promise.reject({
            name: 'Permission denied',
            message: 'You lost your credentials - will be redirected to login page.',
        })
    }

    return Promise.reject(error)
}

export { middleware401 as default }
