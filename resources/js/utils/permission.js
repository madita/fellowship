// Import the Pinia auth store
// import { useAuthStore } from '@/store/authStore'
import { useUserStore } from '@/store/userStore'
import { useSettingsStore } from '@/store/settingStore'
import { isFeatureAvailable } from '@/configs/features.js'

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

    /**
     * Whether a whole section of the menu should be drawn.
     *
     * A section carries its own gate — Administration is role: 'admin' —
     * and that stays the main answer. But a section may also hold one
     * item that names a permission of its own, for work an admin can
     * delegate: writing the newsletter is the first of those. Whoever
     * holds that permission sees the section, and sees only the items in
     * it they are allowed (NavMenuItem gates each one).
     *
     * Only an item that explicitly names a permission can open a section
     * this way. An item with no gate of its own would otherwise open
     * Administration to everybody.
     */
    sectionVisible(section) {
        if (this.applyPermissions(section)) {
            return true
        }

        return (section.items || []).some(
            (item) => item.permission && this.applyPermissions(item)
        )
    },

    applyPermissions(menuItem) {
        // const auth = useAuthStore()

        // Items tagged with a feature disappear when that feature is
        // deactivated in Admin → Settings → Features, or while the feature
        // cannot be used at all (IRC without its daemon, say).
        if (menuItem.feature) {
            if (!useSettingsStore().isFeatureEnabled(menuItem.feature)) {
                return false
            }

            if (!isFeatureAvailable(menuItem.feature)) {
                return false
            }
        }

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
