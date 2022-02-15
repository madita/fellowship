import auth from './middleware/auth'

export default [{
    path: '/chat',
    name: 'chat',
    component: () => import(/* webpackChunkName: "auth-signin" */ '@/components/chat/Chat.vue'),
    meta: {
        middleware: [
            auth
        ]
    }
}]
