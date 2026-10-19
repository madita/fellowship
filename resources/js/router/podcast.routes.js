import auth from './middleware/auth'
import permission from './middleware/permission'
import verified from './middleware/verified'

// Podcast: shows and episodes. Reading is public — a show nobody can
// hear without an account is not much of a podcast — and gated instead
// by the feature toggle, which the global guard in router/index.js
// reads.
//
// Running a show is not public: the create and edit pages ask for
// manage-podcast the same way the wiki's editor asks for manage-page, so
// the people who record a show never see the admin panel. The admin
// screen at /admin/podcast stays as it was.
export const podcast = [
    {
        path: '/podcast',
        name: 'podcast',
        component: () => import(/* webpackChunkName: "podcast-list" */ '@/pages/podcast/PodcastList.vue'),
        meta: {
            layout: 'default'
        }
    },
    {
        // Above /podcast/:slug, or "new" reads as a show
        path: '/podcast/create',
        name: 'podcast-create',
        component: () => import(/* webpackChunkName: "podcast-form" */ '@/pages/podcast/PodcastForm.vue'),
        meta: {
            layout: 'default',
            middleware: [
                auth, permission, verified
            ]
        }
    },
    {
        path: '/podcast/:slug/edit',
        name: 'podcast-edit',
        component: () => import(/* webpackChunkName: "podcast-form" */ '@/pages/podcast/PodcastForm.vue'),
        meta: {
            layout: 'default',
            middleware: [
                auth, permission, verified
            ]
        }
    },
    {
        path: '/podcast/:slug/episodes/new',
        name: 'podcast-episode-new',
        component: () => import(/* webpackChunkName: "podcast-episode-form" */ '@/pages/podcast/EpisodeForm.vue'),
        meta: {
            layout: 'default',
            middleware: [
                auth, permission, verified
            ]
        }
    },
    {
        path: '/podcast/:slug/:episodeSlug/edit',
        name: 'podcast-episode-edit',
        component: () => import(/* webpackChunkName: "podcast-episode-form" */ '@/pages/podcast/EpisodeForm.vue'),
        meta: {
            layout: 'default',
            middleware: [
                auth, permission, verified
            ]
        }
    },
    {
        path: '/podcast/:slug',
        name: 'podcast-show',
        component: () => import(/* webpackChunkName: "podcast-show" */ '@/pages/podcast/PodcastShow.vue'),
        meta: {
            layout: 'default'
        }
    },
    {
        path: '/podcast/:slug/:episodeSlug',
        name: 'podcast-episode',
        component: () => import(/* webpackChunkName: "podcast-episode" */ '@/pages/podcast/EpisodePage.vue'),
        meta: {
            layout: 'default'
        }
    },
]

export default podcast
