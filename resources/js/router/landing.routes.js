export default [
    {
        path: '/',
        name: 'home',
        component: () => import(/* webpackChunkName: "landing-home" */ '@/pages/landing/HomePage.vue'),
            meta: {
                layout: 'landing'
            }
    },
    {
        path: '/blog/:slug',
        name: 'posts',
        component: () => import(/* webpackChunkName: "landing-posts" */ '@/pages/landing/Posts.vue'),
            meta: {
                layout: 'landing'
            }
    }]
