import './bootstrap'
import Vue from 'vue'
import App from './App.vue'

// VUEX - https://vuex.vuejs.org/
import store from './store'

// VUE-ROUTER - https://router.vuejs.org/
import router from './router'

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
import './plugins/lodash'

//HELPERS
import helpers from './helper/permission'

// FILTERS
import './filters/capitalize'
import './filters/lowercase'
import './filters/uppercase'
import './filters/formatDate'

// STYLES
// Main Theme SCSS
import '../sass/theme.scss'

// Animation library - https://animate.style/
import 'animate.css/animate.min.css'

// Set this to false to prevent the production tip on Vue startup.
Vue.config.productionTip = false

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
Vue.use({
    install() {
        Vue.helpers = helpers;
        Vue.prototype.$helpers = helpers;
    }
});

// store.dispatch('auth/me').then(() => {
    new Vue({
        i18n,
        vuetify,
        router,
        store,
        created () {
            const userInfo = localStorage.getItem('user')
            if (userInfo) {
                // const userData = JSON.parse(userInfo)
                this.$store.dispatch('auth/me')
            }
            axios.interceptors.response.use(
                response => response,
                error => {
                    if (error.response.status === 401) {
                        this.$store.dispatch('auth/signOut')
                    }
                    return Promise.reject(error)
                }
            )
        },
        render: h => h(App)
    }).$mount('#app')
// })
