export default function permission ({ to, from, store, next }){

    const hasAccess = function (name) {
        const permissions = store.getters['auth/permissions'];

        switch (name) {

            case "admin-users":
                return permissions.includes("manage-user")

            case "admin-roles":
                return permissions.includes("manage-role")

            case "admin-permissions":
                return permissions.includes("manage-role")

            case "admin-pages":
                return permissions.includes("manage-page")

            case "admin-posts":
                return permissions.includes("manage-post")

            case "admin-announcements":
                return permissions.includes("manage-post")



            default:
                return false;
        }
    };



    if(!hasAccess(to.name)){
        return next({
            name: 'error'
        })
    }

    return next()
}

