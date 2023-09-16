import './bootstrap'
// import 'vuetify/styles' // Global CSS has to be imported
import { createApp } from 'vue'
import App from './App.vue'

// VUEX - https://vuex.vuejs.org/
import store from './store'

// VUE-ROUTER - https://router.vuejs.org/
import router from './router'

//register vue
const vueApp = createApp(App)

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
import vuetify from './plugins/vuetify'
import i18n from './plugins/vue-i18n'
import './plugins/vue-google-maps'
import './plugins/vue-shortkey'
import './plugins/vue-head'
import './plugins/vue-gtag'
import './plugins/apexcharts'
import './plugins/echarts'
import './plugins/animate'
import './plugins/clipboard'
import './plugins/moment'
// import './plugins/lodash'

//HELPERS
import helpers from './helpers'

// FILTERS
import { capitalize } from './helpers/filters.js';

// STYLES
// Main Theme SCSS
import '../sass/theme.scss'

// Animation library - https://animate.style/
import 'animate.css/animate.min.css'
import VueShortkey from 'vue-shortkey'
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
vueApp.use(i18n)
vueApp.use(vuetify)
vueApp.use(store)
// vueApp.use(permissions)
vueApp.use(router)
// Vue.use(require('vue-shortkey'))
vueApp.use(VueShortkey)
vueApp.mount("#app")

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
