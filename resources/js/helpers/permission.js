// Import the Pinia auth store
// import { useAuthStore } from '@/store/authStore'
import { useUserStore } from '@/store/userStore'

// Instantiate the auth store

export default {
    hasRole(role) {
        const userStore = useUserStore()
        const roles = userStore.roles
        // Access the roles directly from the store
        if (roles === null) {
            return false
        }
        return userStore.roles.includes(role)
    },

    can(permission) {
        const userStore = useUserStore()
        const permissions = userStore.permissions

        // Access the permissions directly from the store
        if (permissions == null) {
            return false
        }
        return permissions.includes(permission)
    },

    applyPermissions(menuItem) {
        // const auth = useAuthStore()

        const menuHasPermission = menuItem.hasOwnProperty('permission')
        const menuHasRole = menuItem.hasOwnProperty('role')

        if (!menuHasRole && !menuHasPermission) {
            return true
        }

        if (menuHasRole && menuHasPermission) {
            return this.can(menuItem.permission) || this.hasRole(menuItem.role)
        } else {
            // Else means item has ROLE OR Permission, not both
            return menuHasPermission ? this.can(menuItem.permission) : this.hasRole(menuItem.role)
        }
    }
}
