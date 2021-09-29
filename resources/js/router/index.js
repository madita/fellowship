import Vue from 'vue'
import Router from 'vue-router'
import store from '../store'

import middlewarePipeline from './middlewarePipeline'

import auth from './middleware/auth'
import verified from './middleware/verified'

// Routes
import PagesRoutes from './pages.routes'
import UsersRoutes from './users.routes'
import LandingRoutes from './landing.routes'

Vue.use(Router)

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
    ...PagesRoutes,
    ...UsersRoutes,
    ...LandingRoutes,
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
        path: '*',
        name: 'error',
        component: () => import(/* webpackChunkName: "error" */ '@/pages/error/NotFoundPage.vue'),
        meta: {
            layout: 'error'
        }
    }]

const router = new Router({
    mode: 'history',
    base: process.env.BASE_URL || '/',
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) return savedPosition

        return {x: 0, y: 0}
    },
    routes
})

/**
 * Before each route update
 */
router.beforeEach((to, from, next) => {
    if (!to.meta.middleware) {
        return next()
    }

    const middleware = to.meta.middleware

    const context = {
        to,
        from,
        store,
        next
    }

    return middleware[0]({
        ...context,
        next: middlewarePipeline(context, middleware, 1)
    })
})

/**
 * After each route update
 */
router.afterEach((to, from) => {
})

export default router
