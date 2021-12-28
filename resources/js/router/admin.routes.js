import auth from './middleware/auth'
import verified from "./middleware/verified";
import permission from "./middleware/permission";

export default [{
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
    path: '/admin/posts',
    name: 'admin-posts',
    meta: {
        middleware: [
            auth, permission, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-pages" */ '@/pages/admin/Post.vue')
} ,{
    path: '/admin/users',
    name: 'admin-users',
    meta: {
        middleware: [
            auth, permission, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-pages" */ '@/pages/admin/User.vue')
},{
    path: '/admin/roles',
    name: 'admin-roles',
    meta: {
        middleware: [
            auth, permission, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-pages" */ '@/pages/admin/Role.vue')
},{
    path: '/admin/permissions',
    name: 'admin-permissions',
    meta: {
        middleware: [
            auth, permission, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-pages" */ '@/pages/admin/Permission.vue')
}]
