import auth from './middleware/auth'
import verified from "@/router/middleware/verified.js";

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
    },
    {
        path: '/gallery',
        name: 'gallery-index',
        component: () => import(/* webpackChunkName: "gallery-index" */ '@/components/gallery/Gallery.vue'),
        meta: {
            layout: 'landing',
            middleware: [
                auth, verified
            ]
        }
    },
    {
        path: '/gallery/:album',
        name: 'gallery-album',
        component: () => import(/* webpackChunkName: "gallery-album" */ '@/components/gallery/Album.vue'),
        meta: {
            layout: 'landing',
            middleware: [
                auth, verified
            ]
        }
    },
    {
        path: '/conversations',
        name: 'conversations',
        component: () => import(/* webpackChunkName: "conversations" */ '@/components/conversation/ConversationsDashboard.vue'),
        meta: {
            middleware: [
                auth, verified
            ]
        }
    }]

export default componentsRoutes;
