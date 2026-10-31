<template>
  <v-app>
    <!-- Layout component -->
<!--    <component :is="currentLayout" v-if="isRouterLoaded">-->
<!--      <transition name="fade" mode="out-in">-->
<!--        <router-view />-->
<!--      </transition>-->
<!--    </component>-->

<!--      <component :is="currentLayout" v-if="isRouterLoaded">-->
<!--          <router-view v-slot="{ Component }">-->
<!--              <transition name="fade" mode="out-in">-->
<!--                  <component :is="Component" />-->
<!--              </transition>-->
<!--          </router-view>-->
<!--      </component>-->

      <Component :is="currentLayout" v-if="$route && isRouterLoaded">
          <RouterView :key="$route.fullPath" />
      </Component>
<!--      <router-view v-slot="{ Component }">-->
<!--          <transition name="fade" mode="out-in">-->
<!--              <component :is="currentLayout" v-if="isRouterLoaded"/>-->
<!--          </transition>-->
<!--      </router-view>-->

<!--    <v-snackbar v-model="toast.show" :timeout="toast.timeout" :color="toast.color" bottom>-->
<!--      {{ toast.message }}-->
<!--      <v-btn v-if="toast.timeout === 0" color="white" text @click="toast.show = false">{{ $t('common.close') }}</v-btn>-->
<!--    </v-snackbar>-->

    <!-- GDPR Cookie Consent Banner -->
    <CookieConsent ref="cookieConsent" />

    <!-- Session Timeout Modal -->
    <SessionTimeoutModal />

    <!-- App-wide message / confirm dialogs ($dialog, useDialog) -->
    <DialogHost />

    <!-- The one <audio> element, above the layout and the router view so
         an episode survives every navigation and every layout, including
         the ones with no toolbar. Renders nothing. -->
    <PodcastAudioHost v-if="podcastEnabled" />

    <!-- The full player with its scrubber, only where someone is working
         with an episode. Everywhere else the toolbar button carries it. -->
    <PodcastPlayerBar v-if="podcastEnabled && onPodcastPage" />
  </v-app>
</template>

<script>
// import { mapState } from 'vuex'

import config from './configs'

// Layouts
import defaultLayout from './layouts/DefaultLayout.vue'
import simpleLayout from './layouts/SimpleLayout.vue'
import landingLayout from './layouts/LandingLayout.vue'
import oneScrollLayout from './layouts/OneScrollLayout.vue'
import authLayout from './layouts/AuthLayout.vue'
import errorLayout from './layouts/ErrorLayout.vue'

// Components
import CookieConsent from './components/common/CookieConsent.vue'
import SessionTimeoutModal from './components/common/SessionTimeoutModal.vue'
import DialogHost from './components/common/DialogHost.vue'
import PodcastAudioHost from './components/podcast/PodcastAudioHost.vue'
import PodcastPlayerBar from './components/podcast/PodcastPlayerBar.vue'
// import { mapGetters } from 'vuex'


// Import the Pinia store hook
import { useAuthStore } from '@/store/authStore.js';
import { useAppStore } from '@/store/app/index.js';
import { useSettingsStore } from '@/store/settingStore.js';

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
    oneScrollLayout,
    authLayout,
    errorLayout,
    CookieConsent,
    SessionTimeoutModal,
    DialogHost,
    PodcastAudioHost,
    PodcastPlayerBar
  },
  computed: {
      podcastEnabled() {
          return useSettingsStore().isFeatureEnabled('podcast');
      },
      // The player bar belongs to the podcast's own pages. Matched on the
      // path rather than the route name so the list, a show, an episode
      // and both editors are all covered by one rule.
      onPodcastPage() {
          return (this.$route?.path || '').startsWith('/podcast');
      },
      authenticated() {
          const authStore = useAuthStore();
          return authStore.isLoggedIn;
      },
      user() {
          const userStore = useUserStore();
          return userStore.user;
      },
      // toast() {
      //     // If you have also migrated the `app` store to Pinia,
      //     // you'll access it similarly using its Pinia hook.
      //     // For the sake of this example, I'll assume you have a `useAppStore` hook.
      //     // If not, you'll need to migrate that Vuex store module as well.
      //     const appStore = useAppStore();
      //     return appStore.toast;
      // },
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
