import auth from './middleware/auth'

export const users = [{
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
}, {
    path: '/account/notifications',
    name: 'my-notifications',
    meta: {
        middleware: [
            auth
        ]
    },
    component: () => import(/* webpackChunkName: "my-notifications" */ '@/pages/users/Notifications.vue'),
    children: [
        {
            path: '/account/notifications/:id',
            name: 'my-notification',
            meta: {
                middleware: [
                    auth
                ]
            },
            component: () => import(/* webpackChunkName: "my-notifications" */ '@/pages/users/Notifications.vue'),
        }
    ]
}]
export default users
