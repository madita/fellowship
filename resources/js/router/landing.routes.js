export const landing =  [
    {
        path: '/',
        name: 'home',
        component: () => import(/* webpackChunkName: "landing-home" */ '@/pages/landing/HomePage.vue'),
            meta: {
                layout: 'oneScroll'
            }
    },
    {
        path: '/blog',
        name: 'blog',
        component: () => import(/* webpackChunkName: "landing-blog" */ '@/pages/landing/BlogList.vue'),
        meta: {
            layout: 'landing'
        }
    },
    {
        path: '/blog/:slug',
        name: 'blog-post',
        component: () => import(/* webpackChunkName: "landing-posts" */ '@/pages/landing/Posts.vue'),
        meta: {
            layout: 'landing'
        }
    },
    // {
    //     path: '/pages/:taxonomy/:category',
    //     name: 'pages-category',
    //     component: () => import(/* webpackChunkName: "landing-posts" */ '@/pages/landing/PagesTag.vue'),
    //     meta: {
    //         layout: 'landing'
    //     }
    // },
    {
        path: '/tags/:term/:model?',
        name: 'taxable',
        component: () => import(/* webpackChunkName: "landing-posts" */ '@/pages/landing/Tags.vue'),
        meta: {
            layout: 'landing'
        }
    }]

export default landing
