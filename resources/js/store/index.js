import Vue from 'vue'
import Vuex from 'vuex'
import auth from './auth'

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
  }
})

export default store
