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
}, {
    path: '/admin/users',
    name: 'admin-users',
    meta: {
        middleware: [
            auth, permission, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-users" */ '@/pages/admin/User.vue')
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
    }]

export default admin
