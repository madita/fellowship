import { defineStore } from 'pinia'
import { useUserStore } from '@/store/userStore.js'
import { useApi } from '@/api/useAPI.js'

const web = useApi('web')

export const useAuthStore = defineStore('auth', {
    state: () => {
        // Try to get state from localStorage, fallback to default
        const savedState = localStorage.getItem('AUTH_STATE')
        const defaultState = {
            email: null,
            isLoggedIn: false,
            isVerified: false,
        }

        try {
            return savedState ? JSON.parse(savedState) : defaultState
        } catch (error) {
            console.warn('Failed to parse AUTH_STATE from localStorage:', error)
            localStorage.removeItem('AUTH_STATE')
            return defaultState
        }
    },

    getters: {
        // Add getters for computed properties if needed
        isAuthenticated: (state) => state.isLoggedIn,
        userEmail: (state) => state.email,
        emailVerified: (state) => state.isVerified,
    },

    actions: {
        // Internal method to sync state with localStorage
        _syncToStorage() {
            try {
                localStorage.setItem('AUTH_STATE', JSON.stringify({
                    email: this.email,
                    isLoggedIn: this.isLoggedIn,
                    isVerified: this.isVerified,
                }))
            } catch (error) {
                console.error('Failed to save AUTH_STATE to localStorage:', error)
            }
        },

        // Update state and sync to localStorage
        updateState(payload) {
            // Update the state properties
            Object.keys(payload).forEach(key => {
                if (key in this.$state) {
                    this[key] = payload[key]
                }
            })

            // Sync to localStorage
            this._syncToStorage()
        },

        // Clear all auth state
        clearState() {
            this.email = null
            this.isLoggedIn = false
            this.isVerified = false

            // Remove from localStorage
            localStorage.removeItem('AUTH_STATE')
        },

        // Override Pinia's $reset to use our clearState
        $reset() {
            this.clearState()
        },

        async login({ email, password }) {
            const user = useUserStore()

            try {
                await web.post('/login', { email, password })

                // Store user info first
                await user.storeInfo()

                // Determine verification status
                const isVerified = user.user?.email_verified_at !== null

                // Update auth state
                this.updateState({
                    email,
                    isLoggedIn: true,
                    isVerified
                })

                console.log('User logged in:', user.user)
            } catch (error) {
                // Clear state on login failure
                this.clearState()
                console.error('Login error:', error.message)
                throw error
            }
        },

        async signIn(credentials) {
            // Alias for login to match the component usage
            return this.login(credentials)
        },

        async register(props) {
            const user = useUserStore()

            try {
                await web.post('/register', props)

                // Update auth state
                this.updateState({
                    email: props.email,
                    isLoggedIn: true,
                    isVerified: false // New registrations typically need verification
                })

                // Store user info
                await user.storeInfo()
            } catch (error) {
                this.clearState()
                console.error('Registration error:', error.message)
                throw error
            }
        },

        async forgotPassword({ email }) {
            try {
                await web.post('/forgot-password', { email })
            } catch (error) {
                console.error('Forgot password error:', error.message)
                throw error
            }
        },

        async logout() {
            const user = useUserStore()

            try {
                // Call logout endpoint first
                await web.post('/logout')
                // user.clearState()
            } catch (error) {
                console.error('Logout endpoint error:', error.message)
                // Continue with logout even if endpoint fails
            }

            // Clear state completely
            this.clearState()
            user.clearState()

            console.log('User logged out', user)

            // Clear all localStorage
            localStorage.clear()

            // Force page reload to ensure clean state
            window.location.href = '/auth/signin'
        },

        async resetStore() {
            const user = useUserStore()

            // Clear all state
            this.clearState()
            user.clearState()

            // Clear localStorage
            localStorage.clear()
        },

        // Method to check if user is still authenticated (useful for app initialization)
        async checkAuthStatus() {
            if (!this.isLoggedIn) {
                return false
            }

            try {
                const user = useUserStore()
                await user.storeInfo()

                // Update verification status
                const isVerified = user.user?.email_verified_at !== null
                if (this.isVerified !== isVerified) {
                    this.updateState({ isVerified })
                }

                return true
            } catch (error) {
                console.error('Auth status check failed:', error)
                this.clearState()
                return false
            }
        }
    }
})
