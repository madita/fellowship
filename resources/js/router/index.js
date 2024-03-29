import * as Vue from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
// import * as store from '../store'

// import authMiddleware from './middleware/auth-middleware'
import { middlewarePipeline } from './middlewarePipeline.js';

import auth from './middleware/auth'
import verified from './middleware/verified'

// Routes
import ComponentsRoutes from './components.routes'
import PagesRoutes from './pages.routes'
import UsersRoutes from './users.routes'
import LandingRoutes from './landing.routes'
import WikiRoutes from './wiki.routes'
import AdminRoutes from './admin.routes'

//Vue.use(Router)

export const routes = [{
    path: '/dashboard',
    name: 'dashboard',
    meta: {
        middleware: [
            auth, verified
        ]
    },
    component: () => import(/* webpackChunkName: "dashboard" */ '@/pages/dashboard/DashboardPage.vue')
},
    ...ComponentsRoutes,
    ...PagesRoutes,
    ...UsersRoutes,
    ...LandingRoutes,
    ...WikiRoutes,
    ...AdminRoutes,
    {
        path: '/blank',
        name: 'blank',
        meta: {
            middleware: [
                auth
            ]
        },
        component: () => import(/* webpackChunkName: "blank" */ '@/pages/BlankPage.vue')
    },
    {
        path: '/p/:slug',
        name: 'page',
        component: () => import(/* webpackChunkName: "landing-pages" */ '@/pages/landing/Pages.vue'),
        meta: {
            layout: 'landing'
        }
    },
    {
        path: '/:catchAll(.*)',
        name: 'error',
        component: () => import(/* webpackChunkName: "error" */ '@/pages/error/NotFoundPage.vue'),
        meta: {
            layout: 'error'
        }
    }]

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) return savedPosition

        return {x: 0, y: 0}
    },
    routes
})

let lastLink = null;

/**
 * Before each route update
 */
router.beforeEach((to, from, next) => {
    lastLink = from.fullPath; // Store the last visited link
    console.log('to middleware',to)
    if (!to.meta.middleware) {
        return next()
    }

    const middleware = to.meta.middleware

    if (!Array.isArray(middleware) || typeof middleware[0] !== 'function') {
        console.error('Invalid middleware', middleware);
        return next();
    }

    const context = {
        to,
        from,
        next
    }

    return middleware[0]({
        ...context,
        next: middlewarePipeline(context, middleware, 1)
    })
})

export default router
