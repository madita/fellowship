import axios from 'axios'

/**
 * Get the value of a specific cookie - reads directly from document.cookie
 */
const getCookieValue = (name) => {
    // Force a fresh read of document.cookie
    const cookieString = document.cookie
    const cookies = cookieString.split(';')
    for (let cookie of cookies) {
        const parts = cookie.split('=')
        const cookieName = parts[0].trim()
        if (cookieName === name && parts.length > 1) {
            // Join remaining parts in case value contains '='
            const cookieValue = parts.slice(1).join('=').trim()
            try {
                return decodeURIComponent(cookieValue)
            } catch (e) {
                // If decoding fails, return raw value
                return cookieValue
            }
        }
    }
    return null
}

/**
 * Track when we last refreshed the CSRF token
 */
let lastCsrfRefresh = 0
let csrfRefreshPromise = null
const CSRF_REFRESH_INTERVAL = 60000 // Only refresh once per minute

/**
 * Force refresh the CSRF token
 */
export const refreshCsrfToken = async () => {
    const now = Date.now()

    // If a refresh is already in progress, wait for it
    if (csrfRefreshPromise) {
        return csrfRefreshPromise
    }

    // Don't refresh too frequently
    if (now - lastCsrfRefresh < CSRF_REFRESH_INTERVAL) {
        return
    }

    lastCsrfRefresh = now
    const baseUrl = import.meta.env.API_HOST || ''

    console.log('[CSRF] Fetching fresh token from', `${baseUrl}/sanctum/csrf-cookie`)

    csrfRefreshPromise = axios.get(`${baseUrl}/sanctum/csrf-cookie`, { withCredentials: true })
        .then(() => {
            console.log('[CSRF] Token refresh complete, new cookie:', getCookieValue('XSRF-TOKEN')?.substring(0, 20) + '...')
        })
        .finally(() => {
            csrfRefreshPromise = null
        })

    return csrfRefreshPromise
}

/**
 * Reset the CSRF refresh timer - call this after login to force a fresh token on next request
 */
export const resetCsrfRefreshTimer = () => {
    lastCsrfRefresh = 0
}

/**
 * CSRF middleware for axios requests.
 * - Refreshes CSRF token if it's missing or stale
 * - Explicitly sets X-XSRF-TOKEN header from cookie
 *
 * @param {import('axios').InternalAxiosRequestConfig} config
 * @returns {Promise<import('axios').InternalAxiosRequestConfig>}
 */
const middlewareCSRF = async config => {
    const methodsNeedCSRF = ['post', 'put', 'patch', 'delete']
    const doesMethodRequireCSRF = methodsNeedCSRF.includes(config.method?.toLowerCase())

    if (!doesMethodRequireCSRF) {
        return config
    }

    // Check if we have a token
    let xsrfToken = getCookieValue('XSRF-TOKEN')

    // If token is missing, fetch it
    if (!xsrfToken) {
        console.log('[CSRF] Token missing, fetching...')
        await refreshCsrfToken()
        xsrfToken = getCookieValue('XSRF-TOKEN')
    }

    // Set the header using axios 1.x compatible method
    if (xsrfToken) {
        // Use the set method if headers is an AxiosHeaders instance, otherwise direct assignment
        if (config.headers && typeof config.headers.set === 'function') {
            config.headers.set('X-XSRF-TOKEN', xsrfToken)
        } else {
            config.headers = config.headers || {}
            config.headers['X-XSRF-TOKEN'] = xsrfToken
        }
        console.log('[CSRF] Token set for request:', xsrfToken.substring(0, 30) + '...')
    } else {
        console.warn('[CSRF] No token available after refresh!')
    }

    return config
}

export { middlewareCSRF as default }
