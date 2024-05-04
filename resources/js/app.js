import './bootstrap'
import 'vuetify/styles' // Global CSS has to be imported
import { createApp } from 'vue'
import App from './App.vue'
import { createPinia } from 'pinia'
import mitt from 'mitt'

import { useAuthStore } from '@/store/authStore.js'
import { useUserStore } from '@/store/userStore.js'
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
import 'vue3-perfect-scrollbar/dist/vue3-perfect-scrollbar.css'
import PerfectScrollbar from 'vue3-perfect-scrollbar';
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
vueApp.use(PerfectScrollbar);
// vueApp.use(store)
// vueApp.use(permissions)
vueApp.use(router)

vueApp.use(formatDate);
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

// Check user session on app initialization
async function checkUserSession() {
    try {
        const response = await axios.get('/api/user');
        const userStore = useUserStore();
        userStore.updateState(response.data);  // Assuming you have a setUser method in your store
    } catch (error) {
        if (error.response && error.response.status === 401) {
            const authStore = useAuthStore();
            if(authStore.isLoggedIn) {
                authStore.resetStore();
                //TODO refresh page
                console.log('automatic logout')
            }
        }
    }
}

checkUserSession();
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
