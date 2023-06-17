import auth from './middleware/auth'
import verified from "./middleware/verified";
import permission from "./middleware/permission";

export default [
    {
        path: '/wiki',
        name: 'wiki-index',
        component: () => import(/* webpackChunkName: "wiki-index" */ '@/pages/landing/Wiki.vue'),
        meta: {
            layout: 'landing'
        }
    },
    {
        path: '/wiki/create',
        name: 'wiki-create',
        component: () => import(/* webpackChunkName: "wiki-create" */ '@/components/wiki/Create.vue'),
        meta: {
            layout: 'landing',
            middleware: [
                auth, permission, verified
            ]
        }
    },
    {
        path: '/wiki/category/:slug?',
        name: 'wiki-category',
        component: () => import(/* webpackChunkName: "wiki-category" */ '@/components/wiki/Category.vue'),
        meta: {
            layout: 'landing'
        }
    },
    {
        path: '/wiki/category/:slug/create',
        name: 'wiki-category-create',
        component: () => import(/* webpackChunkName: "wiki-category-create" */ '@/components/wiki/Category-Create.vue'),
        meta: {
            layout: 'landing',
            middleware: [
                auth, permission, verified
            ]
        }
    },
    {
        path: '/wiki/category/:slug/edit',
        name: 'wiki-category-edit',
        component: () => import(/* webpackChunkName: "wiki-category-edit" */ '@/components/wiki/Category-Edit.vue'),
        meta: {
            layout: 'landing',
            middleware: [
                auth, permission, verified
            ]
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
        name: 'wiki-create-slug',
        component: () => import(/* webpackChunkName: "wiki-create-slug" */ '@/components/wiki/Create.vue'),
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
        component: () => import(/* webpackChunkName: "wiki-edit" */ '@/components/wiki/Edit.vue'),
        meta: {
            layout: 'landing',
            middleware: [
                auth, permission, verified
            ]
        },
    }]
