import { defineStore } from 'pinia'
import { useApi } from '@/api/useAPI.js'
import { debug } from '@/utils/debug.js'

const log = debug.module('UserStore')
const api = useApi()
const web = useApi('web')

export const useUserStore = defineStore('user', {
    state: () => {
        // Try to get state from localStorage, fallback to default
        const savedState = localStorage.getItem('USER_INFO')
        const defaultState = {
            user: null,
            roles: null,
            permissions: null,
        }

        try {
            return savedState ? JSON.parse(savedState) : defaultState
        } catch (error) {
            // Silent fail - corrupted localStorage data
            localStorage.removeItem('USER_INFO')
            return defaultState
        }
    },

    getters: {
        // User information getters
        currentUser: (state) => state.user,
        userRoles: (state) => state.roles,
        userPermissions: (state) => state.permissions,

        // Utility getters
        isUserLoaded: (state) => state.user !== null,
        userName: (state) => state.user?.name || null,
        userEmail: (state) => state.user?.email || null,
        isEmailVerified: (state) => state.user?.email_verified_at !== null,

        // Permission checking helper
        hasRole: (state) => (roleName) => {
            if (!state.roles || !Array.isArray(state.roles)) return false
            return state.roles.some(role => role.name === roleName)
        },

        hasPermission: (state) => (permissionName) => {
            if (!state.permissions || !Array.isArray(state.permissions)) return false
            return state.permissions.some(permission => permission.name === permissionName)
        },

        // User preference getters
        userTimezone: (state) => state.user?.timezone || null,
        userDateFormat: (state) => state.user?.date_format || null,
        userTimeFormat: (state) => state.user?.time_format || null,
    },

    actions: {
        // Internal method to sync state with localStorage
        _syncToStorage() {
            try {
                const stateToSave = {
                    user: this.user,
                    roles: this.roles,
                    permissions: this.permissions,
                }
                localStorage.setItem('USER_INFO', JSON.stringify(stateToSave))
            } catch (error) {
                log.error('Failed to save USER_INFO to localStorage:', error)
            }
        },

        // Update state and sync to localStorage
        updateState(payload) {
            // Update the state properties
            if (payload.user !== undefined) this.user = payload.user
            if (payload.roles !== undefined) this.roles = payload.roles
            if (payload.permissions !== undefined) this.permissions = payload.permissions

            // Sync to localStorage
            this._syncToStorage()
        },

        // Clear all user state
        clearState() {
            this.user = null
            this.roles = null
            this.permissions = null

            // Remove from localStorage
            localStorage.removeItem('USER_INFO')
        },

        // Override Pinia's $reset to use our clearState
        $reset() {
            this.clearState()
        },

        // Get CSRF token
        async getToken() {
            try {
                await web.get('/sanctum/csrf-cookie')
            } catch (error) {
                log.error('Failed to get CSRF token:', error)
                throw error
            }
        },

        // Fetch and store user information
        async storeInfo() {
            try {
                const { data: userInfo } = await api.get('/user')

                // Update state with received user info
                this.updateState({
                    user: userInfo.user || userInfo, // Handle different response structures
                    roles: userInfo.roles || null,
                    permissions: userInfo.permissions || null
                })

                log.log('User info stored:', userInfo)
                return userInfo
            } catch (error) {
                log.error('Failed to fetch user info:', error)
                // Clear state on failure
                this.clearState()
                throw error
            }
        },

        // Refresh user data
        async refreshUserInfo() {
            return this.storeInfo()
        },

        // Update specific user field
        updateUserField(field, value) {
            if (this.user) {
                this.user = { ...this.user, [field]: value }
                this._syncToStorage()
            }
        },

        // Check if user has specific role
        checkRole(roleName) {
            return this.hasRole(roleName)
        },

        // Check if user has specific permission
        checkPermission(permissionName) {
            return this.hasPermission(permissionName)
        },

        // Update user preferences (timezone, date_format, time_format)
        async updatePreferences(preferences) {
            try {
                const { data } = await api.patch('/account/preferences', preferences)

                // Refresh the full user data from the server to get updated values
                // This ensures we have the complete user object with all computed attributes
                await this.refreshUserInfo()

                return data
            } catch (error) {
                log.error('Failed to update preferences:', error)
                throw error
            }
        },

        // Method to initialize user data (useful for app startup)
        async initializeUser() {
            // If we have user data in state, verify it's still valid
            if (this.user) {
                try {
                    await this.refreshUserInfo()
                    return true
                } catch (error) {
                    log.warn('User data validation failed:', error)
                    this.clearState()
                    return false
                }
            }
            return false
        }
    }
})
