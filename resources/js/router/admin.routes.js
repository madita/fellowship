import auth from './middleware/auth'
import verified from "./middleware/verified";

export default [{
    path: '/admin',
    redirect: 'users-list',
}, {
    path: '/admin/pages',
    name: 'admin-pages',
    meta: {
        middleware: [
            auth, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-pages" */ '@/pages/admin/Page.vue')
}, {
    path: '/admin/posts',
    name: 'admin-posts',
    meta: {
        middleware: [
            auth, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-pages" */ '@/pages/admin/Post.vue')
} ,{
    path: '/admin/users',
    name: 'admin-users',
    meta: {
        middleware: [
            auth, verified
        ]
    },
    component: () => import(/* webpackChunkName: "admin-pages" */ '@/pages/admin/User.vue')
}]
