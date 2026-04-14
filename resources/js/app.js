import './bootstrap'
import 'vuetify/styles' // Global CSS has to be imported
import { createApp } from 'vue'
import App from './App.vue'
import { createPinia } from 'pinia'
import mitt from 'mitt'

import { useAuthStore } from '@/store/authStore.js'
import { useUserStore } from '@/store/userStore.js'
import { useSettingsStore } from '@/store/settingStore.js'

// Lazy loading directive
import { vLazy } from '@/composables/useLazyLoading.js'

// Debug utility
import { debug } from '@/utils/debug.js'
const log = debug.module('App')

// Initialize theme before app mounts
import { initializeTheme, setupThemeListener, setupThemeObserver } from './utils/themeInit.js'
initializeTheme()
setupThemeListener()
setupThemeObserver()
// import { createPinia } from '@pinia/store'

// VUEX - https://vuex.vuejs.org/
// import store from './store'

// VUE-ROUTER - https://router.vuejs.org/
import router from './router'


//register vue
const emitter = mitt()
const vueApp = createApp(App)
const pinia = createPinia()
//auto register vue components
// const components = import.meta.glob('../components/*.vue', {eager: true})
// Object.entries(components).forEach(([path, definition]) => {
//     const componentName = path.split('/').pop().replace(/\.\w+$/, '')
//     vueApp.component(componentName, definition.default)
// })

// Vuetify
// import '@mdi/font/css/materialdesignicons.css'
// import "vuetify/styles";
// import { createVuetify } from "vuetify";
// import 'vuetify/dist/vuetify.min.css';
// import * as components from "vuetify/components";
// import * as directives from "vuetify/directives";
//
// PLUGINS
import vuetify from './plugins/vuetify.js'
import i18n from './plugins/vue-i18n.js'
import './plugins/vue-google-maps.js'
import './plugins/vue-shortkey.js'
import './plugins/vue-head.js'
import './plugins/vue-gtag.js'
import './plugins/apexcharts.js'
import './plugins/echarts.js'
import './plugins/animate.js'
import './plugins/clipboard.js'
import formatDate from './plugins/formatDate.js'
import sessionTimeout from './plugins/sessionTimeout.js'
// import './plugins/moment'
// import './plugins/lodash'

//HELPERS
import helpers from './helpers'

// FILTERS
import { capitalize } from './helpers/filters.js';

// STYLES

// Main Theme SCSS
// import '../sass/theme.scss'

// Animation library - https://animate.style/
import 'animate.css/animate.min.css'
import VueShortkey from 'vue-shortkey'
import 'vue3-perfect-scrollbar/style.css'; // Note: different CSS path
import { PerfectScrollbarPlugin } from 'vue3-perfect-scrollbar';
//

// Set this to false to prevent the production tip on Vue startup.
// Vue.config.productionTip = false

/*
|---------------------------------------------------------------------
| Main Vue Instance
|---------------------------------------------------------------------
|
| Render the vue application on the <div id="app"></div> in index.html
|
| https://vuejs.org/v2/guide/instance.html
|
*/
// Vue.use({
//     install() {
//         Vue.helpers = helpers;
//         Vue.prototype.$helpers = helpers;
//     }
// });
vueApp.config.globalProperties.$helpers = helpers

// const vuetify = createVuetify({
//     components,
//     directives,
// });


// createApp(App).use(vuetify).mount('#app')
vueApp.use(pinia)
vueApp.use(i18n)
vueApp.use(vuetify)

// Register lazy loading directive
vueApp.directive('lazy', vLazy)
vueApp.use(PerfectScrollbarPlugin);
// vueApp.use(store)
// vueApp.use(permissions)
vueApp.use(router)

vueApp.use(formatDate);
vueApp.use(sessionTimeout);
// Vue.use(require('vue-shortkey'))
// vueApp.use(VueShortkey)
// vueApp.mount("#app")
vueApp.config.globalProperties.emitter = emitter
router.isReady().then(() => vueApp.mount("#app"))
// vueApp.mount("#app")

vueApp.config.globalProperties.$filters = {
    formatDate(value) {
        return '$' + value
    }
}

// Check user session and load settings on app initialization
async function initializeApp() {
    const authStore = useAuthStore();
    const userStore = useUserStore();
    const settingsStore = useSettingsStore();

    try {
        // Load public settings first (needed for fallback values)
        await settingsStore.fetchAppSettings();

        // Check for OAuth callback token in URL
        const urlParams = new URLSearchParams(window.location.search);
        const oauthToken = urlParams.get('token');

        if (oauthToken) {
            // OAuth callback - user is logged in via session, need to sync frontend state
            try {
                await userStore.storeInfo();

                // User info loaded successfully, update auth state
                const isVerified = userStore.user?.email_verified_at !== null;
                authStore.updateState({
                    email: userStore.user?.email,
                    isLoggedIn: true,
                    isVerified
                });

                // Clean up URL (remove token parameter)
                const cleanUrl = window.location.pathname;
                window.history.replaceState({}, document.title, cleanUrl);

                // Redirect to dashboard
                window.location.href = '/dashboard';
                return;
            } catch (error) {
                log.error('OAuth login failed:', error);
                // Clean up URL and continue to show page
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }

        // Only load user data if user is logged in
        if (authStore.isLoggedIn) {
            try {
                // Then load user data using the store's method (handles localStorage sync)
                await userStore.storeInfo();
            } catch (error) {
                if (error.response && error.response.status === 401) {
                    // Session expired, logout user
                    authStore.resetStore();
                    userStore.clearState();
                    log.log('Session expired, automatic logout');
                } else {
                    log.error('Failed to load user info:', error);
                }
            }
        }
    } catch (error) {
        log.error('Failed to initialize app:', error);
    }
}

initializeApp();

// Register PWA Service Worker (works on localhost and production)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
            .then(registration => {
                log.log('PWA: Service Worker registered successfully:', registration.scope);

                // Check for updates periodically (only in production)
                if (import.meta.env.PROD) {
                    setInterval(() => {
                        registration.update();
                    }, 60 * 60 * 1000); // Check every hour
                }

                // In development, log PWA status after a short delay
                if (import.meta.env.DEV) {
                    setTimeout(async () => {
                        const { logPWAStatus } = await import('./utils/pwaCheck.js');
                        await logPWAStatus();
                    }, 2000);
                }
            })
            .catch(error => {
                log.error('PWA: Service Worker registration failed:', error);
            });
    });

    // Add global function to check PWA status manually
    window.checkPWA = async () => {
        const { logPWAStatus } = await import('./utils/pwaCheck.js');
        return await logPWAStatus();
    };
}

// store.dispatch('auth/me').then(() => {
//     new Vue({
//         i18n,
//         vuetify,
//         router,
//         store,
//         created () {
//             const userInfo = localStorage.getItem('user')
//             if (userInfo) {
//                 // const userData = JSON.parse(userInfo)
//                 this.$store.dispatch('auth/me')
//             }
//             axios.interceptors.response.use(
//                 response => response,
//                 error => {
//                     if (error.response.status === 401) {
//                         this.$store.dispatch('auth/signOut')
//                     }
//                     return Promise.reject(error)
//                 }
//             )
//         },
//         render: h => h(App)
//     }).$mount('#app')
//  })


/*axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response.status === 401) {
            store.dispatch('auth/signOut')
        }
        return Promise.reject(error)
    }
)

store.dispatch('auth/me').then(() => {
    const userInfo = localStorage.getItem('user')
    if (userInfo) {
        store.dispatch('auth/me')
    }

    // Set the i18n instance on app

    app.use(i18n)

    // Set the Vuetify instance on app
    const vuetify = createVuetify({
        // options...
    })
    app.use(vuetify)

    // Use the router and the store
    app.use(router)
    app.use(store)

    // Mount the app
    app.mount('#app')
})*/
