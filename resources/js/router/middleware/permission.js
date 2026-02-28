import { useUserStore } from "@/store/userStore.js";

export default function permission({ to, next }) {
    const userStore = useUserStore();
    const permissionsMap = {
        "admin-users": "manage-user",
        "admin-roles": "manage-role",
        "admin-permissions": "manage-role",
        "admin-pages": "manage-page",
        "admin-pages-form": "manage-page",
        "admin-posts": "manage-post",
        "admin-events": "manage-post",
        "admin-settings": "manage-post",
        "admin-settings-category": "manage-post",
        "admin-settings-page": "manage-post",
        "admin-events-types": "manage-post",
        "admin-events-profiles": "manage-post",
        "admin-gallery": "manage-post",
        "admin-album": "manage-post",
        "admin-announcements": "manage-post",
        "wiki-create": "manage-page",
        "wiki-create-slug": "manage-page",
        "wiki-edit": "manage-page",
        "wiki-category-create": "manage-page",
        "wiki-category-edit": "manage-page",
        "admin-media": "manage-page",
        "admin-migrations": "manage-page",
        "admin-translations": "manage-page",
        "admin-forums": "manage-page",
    };

    const requiredPermission = permissionsMap[to.name];

    // If the route name is not in the permissions map, throw an error
    if (!requiredPermission) {
        console.error(`Permission middleware: Route "${to.name}" is not defined in the permissions map.`);
        throw new Error(`No permission defined for the route "${to.name}". Please check the permissions configuration.`);
    }

    const hasAccess = userStore.permissions.includes(requiredPermission);

    if (!hasAccess) {
        // return next({
        //     name: 'error'
        // });
        return next({
            name: 'access-denied' // Make sure this route is correctly defined in your router
        });
    }

    return next();
}
