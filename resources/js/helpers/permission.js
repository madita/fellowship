import store from "../store"





export default {

    hasAccess (name) {
        const permissions = store.getters['auth/permissions'];

        switch (name) {

            case "admin-users":
                return permissions.includes("manage-user")

            case "admin-roles":
                return permissions.includes("manage-role")

            case "admin-pages":
                return permissions.includes("manage-page")

            case "admin-posts":
                return permissions.includes("manage-post")


            default:
                return false;
        }
    },
    hasRole (role) {
        const roles = store.getters['auth/roles'];
        if(Object.keys(roles).length === 0) {
            return false;
        }
        return roles.includes(role)
    },
    can (permission) {
        const permissions = store.getters['auth/permissions'];
        if(Object.keys(permissions).length === 0) {
            return false;
        }
        return permissions.includes(permission)
    },
   applyPermissions(menuItem) {
       const menuHasPermission = menuItem.hasOwnProperty('permission');
       const menuHasRole = menuItem.hasOwnProperty('role');

       if(!menuHasRole && !menuHasPermission) {
           return true;
       }

       if(menuHasRole && menuHasPermission) {
           return this.can(menuItem.permission) || this.hasRole(menuItem.role);
       } else {
           //else means item has ROLE OR Permission not both
           return menuHasPermission ? this.can(menuItem.permission): this.hasRole(menuItem.role)
       }

   }
};

