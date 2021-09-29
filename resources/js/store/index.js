import Vue from 'vue'
import Vuex from 'vuex'
import auth from './auth'
import createPersistedState from "vuex-persistedstate";

// Global vuex
import AppModule from './app'

Vue.use(Vuex)

/**
 * Main Vuex Store
 */
const store = new Vuex.Store({
    modules: {
        auth,
        app: AppModule
    },
    plugins: [createPersistedState()]
})

export default store
