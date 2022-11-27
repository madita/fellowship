export default [
    {
        path: '/wiki',
        name: 'wiki-index',
        component: () => import(/* webpackChunkName: "wiki" */ '@/pages/landing/Wiki.vue'),
        meta: {
            layout: 'landing'
        }
    },
    {
        path: '/wiki/create',
        name: 'wiki-create',
        component: () => import(/* webpackChunkName: "wiki-page" */ '@/components/wiki/Create.vue'),
        meta: {
            layout: 'landing'
        }
    },
    {
        path: '/wiki/category/:slug',
        name: 'wiki-category',
        component: () => import(/* webpackChunkName: "wiki-page" */ '@/components/wiki/Category.vue'),
        meta: {
            layout: 'landing'
        }
    },
    {
        path: '/wiki/:slug',
        name: 'wiki',
        component: () => import(/* webpackChunkName: "wiki-page" */ '@/components/wiki/Show.vue'),
        meta: {
            layout: 'landing'
        }
    }]
