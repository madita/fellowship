import auth from './middleware/auth'

export default [{
    path: '/chat',
    name: 'chat',
    component: () => import(/* webpackChunkName: "chat" */ '@/components/chat/Chat.vue'),
    meta: {
        middleware: [
            auth
        ]
    }
},
    {
        path: '/events',
        name: 'event',
        component: () => import(/* webpackChunkName: "events" */ '@/components/event/EventPage.vue'),
        meta: {
            middleware: [
                auth
            ]
        }
    },
    {
        path: '/events/create',
        name: 'event',
        component: () => import(/* webpackChunkName: "event-create" */ '@/components/event/EventForm.vue'),
        meta: {
            middleware: [
                auth
            ]
        }
    },
    {
        path: '/events/:id',
        name: 'event-show',
        component: () => import(/* webpackChunkName: "event-show" */ '@/components/event/EventShow.vue'),
        meta: {
            middleware: [
                auth
            ]
        }
    },
    {
        path: '/events/:id/edit',
        name: 'event',
        component: () => import(/* webpackChunkName: "event-edit" */ '@/components/event/EventForm.vue'),
        meta: {
            middleware: [
                auth
            ]
        }
    }]
