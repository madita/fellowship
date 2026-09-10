import auth from './middleware/auth'
import verified from "./middleware/verified";
import permission from "./middleware/permission";

export const wiki = [
    {
        path: '/wiki',
        name: 'wiki-index',
        component: () => import(/* webpackChunkName: "wiki-index" */ '@/pages/wiki/WikiIndex.vue'),
        meta: {
            layout: 'landing'
        }
    },
    {
        path: '/wiki/create',
        name: 'wiki-create',
        component: () => import(/* webpackChunkName: "wiki-create" */ '@/pages/wiki/WikiCreate.vue'),
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
        component: () => import(/* webpackChunkName: "wiki-category" */ '@/pages/wiki/WikiCategory.vue'),
        meta: {
            layout: 'landing'
        }
    },
    {
        path: '/wiki/category/:slug/create',
        name: 'wiki-category-create',
        component: () => import(/* webpackChunkName: "wiki-category-create" */ '@/pages/wiki/WikiCategoryCreate.vue'),
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
        component: () => import(/* webpackChunkName: "wiki-category-edit" */ '@/pages/wiki/WikiCategoryEdit.vue'),
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
        component: () => import(/* webpackChunkName: "wiki-page" */ '@/pages/wiki/WikiShow.vue'),
        meta: {
            layout: 'landing'
        }
    },
    {
        path: '/wiki/:slug/create',
        name: 'wiki-create-slug',
        component: () => import(/* webpackChunkName: "wiki-create-slug" */ '@/pages/wiki/WikiCreate.vue'),
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
        component: () => import(/* webpackChunkName: "wiki-edit" */ '@/pages/wiki/WikiEdit.vue'),
        meta: {
            layout: 'landing',
            middleware: [
                auth, permission, verified
            ]
        },
    }]

export default wiki
