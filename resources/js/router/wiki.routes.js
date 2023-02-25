import auth from './middleware/auth'
import verified from "./middleware/verified";
import permission from "./middleware/permission";

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
        path: '/wiki/category/:slug?',
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
    },
    {
        path: '/wiki/:slug/create',
        name: 'wiki-edit',
        component: () => import(/* webpackChunkName: "wiki-page" */ '@/components/wiki/Create.vue'),
        meta: {
            layout: 'landing',
            middleware: [
                auth, permission, verified
            ]
        },
    },
    {
        path: '/wiki/:slug/edit',
        name: 'wiki-edit',
        component: () => import(/* webpackChunkName: "wiki-page" */ '@/components/wiki/Edit.vue'),
        meta: {
            layout: 'landing',
            middleware: [
                auth, permission, verified
            ]
        },
    }]
