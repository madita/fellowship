// import * as Vue from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
// import * as store from '../store'

// import authMiddleware from './middleware/auth-middleware'
import { middlewarePipeline } from './middlewarePipeline.js';

import auth from './middleware/auth'
import verified from './middleware/verified'
import maintenance from './middleware/maintenance'

// Routes
import ComponentsRoutes from './components.routes'
import PagesRoutes from './pages.routes'
import UsersRoutes from './users.routes'
import LandingRoutes from './landing.routes'
import WikiRoutes from './wiki.routes'
import ForumRoutes from './forum.routes'
import AdminRoutes from './admin.routes'
//import permission from "@/router/middleware/permission.js";

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
    ...ForumRoutes,
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
    // {
    //     path: '/game',
    //     name: 'game',
    //     component: () => import(/* webpackChunkName: "game" */ '@/pages/GameDemo.vue'),
    //     meta: {
    //         layout: 'landing'
    //     }
    // },
    // {
    //     path: '/thud',
    //     name: 'thud',
    //     component: () => import(/* webpackChunkName: "game" */ '@/pages/ThudDemo.vue'),
    //     meta: {
    //         layout: 'landing'
    //     }
    // },
    // {
    //     path: '/map-admin',
    //     name: 'map-admin',
    //     component: () => import(/* webpackChunkName: "map-admin" */ '@/pages/MapAdminDemo.vue'),
    //     meta: {
    //         layout: 'landing'
    //     }
    // },
    // {
    //     path: '/sheet',
    //     name: 'sheet',
    //     component: () => import(/* webpackChunkName: "game" */ '@/pages/CharSheetDemo.vue'),
    //     meta: {
    //         layout: 'landing'
    //     }
    // },
    {
        path: '/p/:slug',
        name: 'page',
        component: () => import(/* webpackChunkName: "landing-pages" */ '@/pages/landing/Pages.vue'),
        meta: {
            layout: 'landing',
            middleware: [
                auth, verified
            ]
        }
    },
    {
        path: '/error',
        name: 'access-denied',
        component: () => import(/* webpackChunkName: "error" */ '@/pages/error/NotFoundPage.vue'),
        meta: {
            layout: 'error'
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
    history: createWebHistory('/'),  // Explicitly set to '/' instead of using BASE_URL
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
router.beforeEach(async (to, from, next) => {
    lastLink = from.fullPath; // Store the last visited link

    // Ensure settings are loaded before checking maintenance mode
    const { useSettingsStore } = await import('@/store/settingStore.js')
    const settingsStore = useSettingsStore()

    if (!settingsStore.settingsLoaded) {
        await settingsStore.fetchAppSettings()
    }

    // Check maintenance mode first (global middleware)
    const maintenanceResult = await new Promise((resolve) => {
        maintenance({
            to,
            from,
            next: (result) => resolve(result)
        })
    })

    // If maintenance middleware redirected, handle it
    if (maintenanceResult && typeof maintenanceResult === 'object' && maintenanceResult.name) {
        return next(maintenanceResult)
    }

    // If maintenance middleware returned false or stopped the flow
    if (maintenanceResult === false) {
        return next(false)
    }

    // Continue with route-specific middleware
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
