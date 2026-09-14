import auth from './middleware/auth'
import verified from "@/router/middleware/verified.js";

export const componentsRoutes = [{
    path: '/chat',
    name: 'chat',
    component: () => import(/* webpackChunkName: "chat" */ '@/pages/chat/ChatPage.vue'),
    meta: {
        middleware: [
            auth
        ]
    }
    },
    {
        path: '/events',
        name: 'events',
        component: () => import(/* webpackChunkName: "events" */ '@/pages/events/EventPage.vue'),
        meta: {
            middleware: [
                auth
            ]
        }
    },
    {
        path: '/events/create',
        name: 'event-create',
        component: () => import(/* webpackChunkName: "event-create" */ '@/pages/events/EventForm.vue'),
        meta: {
            middleware: [
                auth
            ]
        }
    },
    {
        path: '/events/:id',
        name: 'event-show',
        component: () => import(/* webpackChunkName: "event-show" */ '@/pages/events/EventShow.vue'),
        meta: {
            middleware: [
                auth
            ]
        }
    },
    {
        path: '/events/:id/edit',
        name: 'event-edit',
        component: () => import(/* webpackChunkName: "event-edit" */ '@/pages/events/EventForm.vue'),
        meta: {
            middleware: [
                auth
            ]
        }
    },
    {
        path: '/gallery',
        name: 'gallery-index',
        component: () => import(/* webpackChunkName: "gallery-index" */ '@/pages/gallery/GalleryPage.vue'),
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
        component: () => import(/* webpackChunkName: "gallery-album" */ '@/pages/gallery/AlbumPage.vue'),
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
        component: () => import(/* webpackChunkName: "conversations" */ '@/pages/conversation/ConversationsPage.vue'),
        meta: {
            middleware: [
                auth, verified
            ]
        }
    },
    {
        path: '/irc',
        name: 'irc-client',
        component: () => import(/* webpackChunkName: "irc-client" */ '@/pages/irc/IrcPage.vue'),
        meta: {
            middleware: [
                auth, verified
            ]
        }
    }]

export default componentsRoutes;
