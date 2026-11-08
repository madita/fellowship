import { useUserStore } from "@/store/userStore.js";
import { settingsPermissions } from "@/configs/settingsConfig.js";
import { feature, satisfiesAny } from "@/configs/featurePermissions.js";

// Which permission each admin page asks for. One per feature, the
// same names the API guards itself with (App\Support\
// FeaturePermissions) - a page let open here that the API refuses
// would hand somebody a form and then throw their work away.
export const ADMIN_PERMISSIONS = {
    "admin-dashboard": "manage-user",
    "admin-users": "manage-user",
    "admin-roles": "manage-role",
    "admin-permissions": "manage-role",

    // A feature split by action opens for any part of it: somebody given
    // only create-page is shown the list and the form, and the API then
    // refuses the parts they were not given. feature() names the whole
    // set; naming actions narrows a page to the ones that reach it.
    "admin-pages": feature("page"),
    "admin-pages-form": feature("page", "create", "edit"),
    "admin-posts": feature("post"),
    "admin-announcements": "manage-announcement",

    "wiki-create": feature("wiki", "create"),
    "wiki-create-slug": feature("wiki", "create"),
    "wiki-edit": feature("wiki", "create", "edit"),
    // A category is the wiki's shape rather than an article in it, so it
    // asks for the feature as a whole - the same line WikiController
    // draws in storeCategory()
    "wiki-category-create": "manage-wiki",
    "wiki-category-edit": "manage-wiki",

    "admin-forums": feature("forum"),
    "admin-taxonomie": "manage-taxonomy",
    "admin-terms": "manage-taxonomy",
    "admin-relations": "manage-relation",

    "admin-events": feature("event"),
    // The kinds of event and the sign-up forms are what the calendar is
    // built out of rather than entries in it
    "admin-events-types": "manage-event",
    "admin-events-profiles": "manage-event",

    "admin-gallery": feature("gallery"),
    "admin-album": feature("gallery"),
    "admin-media": "manage-media",

    "admin-polls": "manage-poll",
    "admin-tickets": "manage-ticket",
    "admin-ticket": "manage-ticket",
    "admin-ticket-create": "manage-ticket",
    "admin-ticket-edit": "manage-ticket",

    "admin-migrations": "manage-migration",
    "admin-translations": "manage-translation",
    "admin-settings-menus": "manage-menu",
    "admin-settings-discord": "manage-discord",

    // The settings overview is the only way in to the translations, the
    // menus, the forum categories and the migration tool, so it opens
    // for anyone holding any of their permissions. Each entry inside is
    // then shown or not on its own (allowedCategories in
    // configs/settingsConfig.js), so what a member may touch is all
    // they are offered.
    "admin-settings": settingsPermissions(),
    "admin-settings-category": settingsPermissions(),
    "admin-settings-page": settingsPermissions(),

    // Running a show from the podcast's own pages, the way the wiki
    // is written from the wiki rather than from the admin panel. A
    // member trusted with create-podcast reaches these, and what they
    // record waits for approve-podcast.
    "admin-podcast": feature("podcast"),
    "podcast-create": feature("podcast", "create"),
    "podcast-edit": feature("podcast", "create", "edit"),
    "podcast-episode-new": feature("podcast", "create", "edit"),
    "podcast-episode-edit": feature("podcast", "create", "edit"),

    // Writing to every member
    "admin-mailings": "manage-mailings",
};

export default async function permission({ to, next }) {
    const userStore = useUserStore();
    const requiredPermission = ADMIN_PERMISSIONS[to.name];

    // If the route name is not in the permissions map, throw an error
    if (!requiredPermission) {
        console.error(`Permission middleware: Route "${to.name}" is not defined in the permissions map.`);
        throw new Error(`No permission defined for the route "${to.name}". Please check the permissions configuration.`);
    }

    // Opening a guarded page directly races app start-up, which fetches
    // the member's permissions without the router waiting for it. Not
    // knowing yet is not the same as not being allowed, so the answer is
    // waited for rather than guessed at — and an absent one can no longer
    // throw, which blanked the page instead of refusing it.
    if (!Array.isArray(userStore.permissions)) {
        try {
            await userStore.storeInfo();
        } catch (error) {
            console.warn('Permission middleware: could not load the permissions for this member.', error);
        }
    }

    // A page may name one permission or any of several - the settings
    // overview is the way in to a dozen features and belongs to all of
    // them, and a feature split by action names each of its parts.
    //
    // Through satisfies(), so holding a feature whole opens its pages
    // without every line of the map having to repeat manage-<feature>.
    const hasAccess = satisfiesAny(requiredPermission, userStore.permissions || []);

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
