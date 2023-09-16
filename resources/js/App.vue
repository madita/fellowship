<template>
  <v-app>
    <!-- Layout component -->
<!--    <component :is="currentLayout" v-if="isRouterLoaded">-->
<!--      <transition name="fade" mode="out-in">-->
<!--        <router-view />-->
<!--      </transition>-->
<!--    </component>-->

      <component :is="currentLayout" v-if="isRouterLoaded">
          <router-view v-slot="{ Component }">
              <transition name="fade" mode="out-in">
                  <component :is="Component" />
              </transition>
          </router-view>
      </component>

<!--      <router-view v-slot="{ Component }">-->
<!--          <transition name="fade" mode="out-in">-->
<!--              <component :is="currentLayout" v-if="isRouterLoaded"/>-->
<!--          </transition>-->
<!--      </router-view>-->

    <v-snackbar v-model="toast.show" :timeout="toast.timeout" :color="toast.color" bottom>
      {{ toast.message }}
      <v-btn v-if="toast.timeout === 0" color="white" text @click="toast.show = false">{{ $t('common.close') }}</v-btn>
    </v-snackbar>
  </v-app>
</template>

<script>
import { mapState } from 'vuex'

import config from './configs'

// Layouts
import defaultLayout from './layouts/DefaultLayout.vue'
import simpleLayout from './layouts/SimpleLayout.vue'
import landingLayout from './layouts/LandingLayout.vue'
import authLayout from './layouts/AuthLayout.vue'
import errorLayout from './layouts/ErrorLayout.vue'
import { mapGetters } from 'vuex'

/*
|---------------------------------------------------------------------
| Main Application Component
|---------------------------------------------------------------------
|
| In charge of choosing the layout according to the router metadata
|
*/
export default {
  components: {
    defaultLayout,
    simpleLayout,
    landingLayout,
    authLayout,
    errorLayout
  },
  computed: {
      ...mapGetters({
          authenticated: 'auth/authenticated',
          user: 'auth/user',
      }),
    ...mapState('app', ['toast']),
    isRouterLoaded: function() {
      return this.$route !== undefined && this.$route.name !== null;
    },
    currentLayout: function() {
      const layout = this.$route.meta.layout || 'default'

      return layout + 'Layout'
    }
  },
  head: {
    link: [
      // adds config/icons into the html head tag
      ...config.icons.map((href) => ({ rel: 'stylesheet', href }))
    ]
  }
}
</script>

<style scoped>
/**
 * Transition animation between pages
 */
.fade-enter-active,
.fade-leave-active {
  transition-duration: 0.2s;
  transition-property: opacity;
  transition-timing-function: ease;
}

.fade-enter,
.fade-leave-active {
  opacity: 0;
}
</style>
