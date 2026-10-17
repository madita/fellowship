// Podcast: shows and episodes. Public — a show nobody can hear without
// an account is not much of a podcast — and gated instead by the feature
// toggle, which the global guard in router/index.js reads.
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
