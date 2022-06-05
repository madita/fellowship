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
    },
    {
        path: '/pages/:taxonomy/:category',
        name: 'pages-category',
        component: () => import(/* webpackChunkName: "landing-posts" */ '@/pages/landing/PagesTag.vue'),
        meta: {
            layout: 'landing'
        }
    },
    {
        path: '/tags/:term/:model?',
        name: 'taxable',
        component: () => import(/* webpackChunkName: "landing-posts" */ '@/pages/landing/Tags.vue'),
        meta: {
            layout: 'landing'
        }
    }]
