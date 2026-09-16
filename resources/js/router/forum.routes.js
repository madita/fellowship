import auth from './middleware/auth'
import verified from './middleware/verified'

export const forum = [
    {
        path: '/forum',
        name: 'forum-index',
        component: () => import(/* webpackChunkName: "forum-index" */ '@/pages/forum/ForumIndex.vue'),
        meta: {
            layout: 'default'
        }
    },
    {
        path: '/forum/search',
        name: 'forum-search',
        component: () => import(/* webpackChunkName: "forum-search" */ '@/pages/forum/ForumSearch.vue'),
        meta: {
            layout: 'default'
        }
    },
    {
        path: '/forum/:slug',
        name: 'forum-category',
        component: () => import(/* webpackChunkName: "forum-category" */ '@/pages/forum/ForumCategory.vue'),
        meta: {
            layout: 'default'
        }
    },
    {
        // Old thread address, kept for links already shared
        path: '/forum/:forumSlug/thread/:threadSlug',
        redirect: to => ({ name: 'forum-thread', params: to.params, query: to.query, hash: to.hash }),
    },
    {
        // Static siblings (/forum/search, /forum/:slug/new-thread) still win:
        // vue-router ranks static segments above params.
        path: '/forum/:forumSlug/:threadSlug',
        name: 'forum-thread',
        component: () => import(/* webpackChunkName: "forum-thread" */ '@/pages/forum/ForumThread.vue'),
        meta: {
            layout: 'default'
        }
    },
    {
        path: '/forum/:slug/new-thread',
        name: 'forum-new-thread',
        component: () => import(/* webpackChunkName: "forum-new-thread" */ '@/pages/forum/ForumNewThread.vue'),
        meta: {
            layout: 'default',
            middleware: [
                auth, verified
            ]
        }
    }
]

export default forum
