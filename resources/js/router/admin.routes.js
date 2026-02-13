import auth from './middleware/auth'
import verified from "./middleware/verified";
import permission from "./middleware/permission";

export const admin = [{
    path: '/admin',
    redirect: 'users-list',
}, {
    path: '/admin/pages',
    name: 'admin-pages',
    meta: {
        middleware: [
            auth, permission, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-pages" */ '@/pages/admin/Page.vue')
}, {
    path: '/admin/pages/:form/:id?',
    name: 'admin-pages-form',
    meta: {
        middleware: [
            auth, permission, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-pages-form" */ '@/pages/admin/PageForm.vue')
}, {
    path: '/admin/posts',
    name: 'admin-posts',
    meta: {
        middleware: [
            auth, permission, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-posts" */ '@/pages/admin/Post.vue')
},{
    path: '/admin/events',
    name: 'admin-events',
    meta: {
        middleware: [
            auth, permission, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-events" */ '@/pages/admin/Event.vue')
},{
    path: '/admin/events/types',
    name: 'admin-events-types',
    meta: {
        middleware: [
            auth, permission, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-events-types" */ '@/pages/admin/EventType.vue')
},{
    path: '/admin/events/profiles',
    name: 'admin-events-profiles',
    meta: {
        middleware: [
            auth, permission, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-events-profiles" */ '@/pages/admin/EventProfile.vue')
},{
    path: '/admin/users',
    name: 'admin-users',
    meta: {
        middleware: [
            auth, permission, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-users" */ '@/pages/admin/User.vue')
}, {
    path: '/admin/gallery',
    name: 'admin-gallery',
    meta: {
        middleware: [
            auth, permission, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-gallery" */ '@/pages/admin/Gallery.vue')
}, {
    path: '/admin/media',
    name: 'admin-media',
    meta: {
        middleware: [
            auth, permission, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-media" */ '@/pages/admin/MediaCenter.vue')
}, {
    path: '/admin/translations',
    name: 'admin-translations',
    meta: {
        middleware: [
            auth, permission, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-translations" */ '@/pages/admin/TranslationManager.vue')
}, {
    path: '/admin/roles',
    name: 'admin-roles',
    meta: {
        middleware: [
            auth, permission, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-roles" */ '@/pages/admin/Role.vue')
}, {
    path: '/admin/permissions',
    name: 'admin-permissions',
    meta: {
        middleware: [
            auth, permission, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-permissions" */ '@/pages/admin/Permission.vue')
},
    {
        path: '/admin/tags/taxonomie',
        name: 'admin-taxonomie',
        meta: {
            middleware: [
                auth, verified
            ]
        },
        component: () => import(/* webpackChunkName: "admin-taxonomie" */ '@/pages/admin/Taxonomie.vue')
    },
    {
        path: '/admin/tags/terms',
        name: 'admin-terms',
        meta: {
            middleware: [
                auth, verified
            ]
        },
        component: () => import(/* webpackChunkName: "admin-permissions" */ '@/pages/admin/Terms.vue')
    }, {
        path: '/admin/announcements',
        name: 'admin-announcements',
        meta: {
            middleware: [
                auth, permission, verified
            ]
        },
        component: () => import(/* webpackChunkName: "admin-announcements" */ '@/pages/admin/Announcement.vue')
    },
    // Settings routes - hierarchical structure
    {
        path: '/admin/settings',
        name: 'admin-settings',
        meta: {
            middleware: [
                auth, permission, verified
            ]
        },
        component: () => import(/* webpackChunkName: "admin-settings-overview" */ '@/pages/admin/settings/SettingsOverview.vue')
    },
    {
        path: '/admin/settings/:category',
        name: 'admin-settings-category',
        meta: {
            middleware: [
                auth, permission, verified
            ]
        },
        component: () => import(/* webpackChunkName: "admin-settings-category" */ '@/pages/admin/settings/SettingsCategory.vue')
    },
    {
        path: '/admin/settings/:category/:setting',
        name: 'admin-settings-page',
        meta: {
            middleware: [
                auth, permission, verified
            ]
        },
        component: () => import(/* webpackChunkName: "admin-settings-page" */ '@/pages/admin/settings/SettingsPage.vue')
    }, {
        path: '/admin/menus',
        name: 'admin-menus',
        meta: {
            middleware: [
                auth, permission, verified
            ]
        },
        component: () => import(/* webpackChunkName: "admin-menus" */ '@/pages/admin/MenuManager.vue')
    }]

export default admin
},{
    path: '/admin/menus',
    name: 'admin-menus',
    meta: { middleware: [auth, admin] },
    component: () => import('@/pages/admin/MenuManager.vue')
