import { useSettingsStore } from '@/store/settingStore.js'
import { useUserStore } from '@/store/userStore.js'

export default function maintenance({ next, to }) {
    const settingsStore = useSettingsStore()
    const userStore = useUserStore()

    // Check if maintenance mode is enabled
    const maintenanceMode = settingsStore.maintenanceMode

    // Allow access to maintenance page itself
    if (to.name === 'utility-maintenance') {
        return next()
    }

    // Always allow access to authentication routes (so admins can log in)
    const authRoutes = [
        'auth-signin',
        'auth-signup',
        'auth-forgot',
        'auth-reset',
        'auth-verify-email'
    ]

    if (authRoutes.includes(to.name)) {
        return next()
    }

    // If maintenance mode is enabled
    if (maintenanceMode) {
        // Allow admins to bypass maintenance mode
        const user = userStore.user
        const isAdmin = user && (
            user.isAdmin === true ||
            user.roles?.some(role => role.name === 'admin')
        )

        if (isAdmin) {
            return next()
        }

        // Redirect non-admin users to maintenance page
        return next({ name: 'utility-maintenance' })
    }

    return next()
}
