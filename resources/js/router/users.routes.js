import auth from './middleware/auth'

export default [{
    path: '/users',
    redirect: 'users-list'
}, {
    path: '/users/list',
    name: 'users-list',
    meta: {
        middleware: [
            auth
        ]
    },
    component: () => import(/* webpackChunkName: "users-list" */ '@/pages/users/UsersPage.vue')
}, {
    path: '/users/edit',
    name: 'users-edit',
    meta: {
        middleware: [
            auth
        ]
    },
    component: () => import(/* webpackChunkName: "users-edit" */ '@/pages/users/EditUserPage.vue')
}]
