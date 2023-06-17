export default function permission ({ to, from, store, next }){
 //this is the one
    /** middleware for routes **/
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
            case "admin-pages-form":
                return permissions.includes("manage-page")

            case "admin-posts":
                return permissions.includes("manage-post")

            case "admin-announcements":
                return permissions.includes("manage-post")

            case "wiki-create":
                return permissions.includes("manage-page")
            case "wiki-create-slug":
                return permissions.includes("manage-page")
            case "wiki-edit":
                return permissions.includes("manage-page")
            case "wiki-category-create":
                return permissions.includes("manage-page")
            case "wiki-category-edit":
                return permissions.includes("manage-page")
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

