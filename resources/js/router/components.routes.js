import auth from './middleware/auth'

export const componentsRoutes = [{
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
        name: 'events',
        component: () => import(/* webpackChunkName: "events" */ '@/components/event/EventPage.vue'),
        meta: {
            middleware: [
                auth
            ]
        }
    },
    {
        path: '/events/create',
        name: 'event-create',
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
        name: 'event-edit',
        component: () => import(/* webpackChunkName: "event-edit" */ '@/components/event/EventForm.vue'),
        meta: {
            middleware: [
                auth
            ]
        }
    }]

export default componentsRoutes;
