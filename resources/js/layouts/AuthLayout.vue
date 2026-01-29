<template>
  <div class="d-flex text-center flex-column flex-md-row flex-grow-1 auth-layout">
    <!-- Maintenance Mode Warning for Auth Pages -->
    <v-alert
      v-if="maintenanceMode"
      type="warning"
      variant="tonal"
      class="maintenance-banner"
      density="compact"
    >
      <div class="d-flex align-center">
        <v-icon class="mr-2" size="small">mdi-wrench</v-icon>
        <strong>Maintenance Mode Active</strong>
        <span class="ml-1 d-none d-sm-inline">- Only admins can access the site</span>
      </div>
    </v-alert>

    <!-- Theme Toggle Button -->
    <v-btn
      icon
      class="theme-toggle-btn"
      @click="toggleTheme"
      :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
    >
      <v-icon>{{ isDark ? 'mdi-weather-sunny' : 'mdi-weather-night' }}</v-icon>
    </v-btn>

    <v-sheet class="layout-side mx-auto mx-md-1 d-none d-md-flex flex-md-column justify-space-between px-2" color="surface">
      <div class="mt-3 mt-md-10 pa-2">
        <!-- Logo when branding is enabled -->
        <div v-if="loginBrandingEnabled && currentLogo" class="mb-4">
          <v-img
            :src="currentLogo"
            max-height="80"
            max-width="280"
            contain
            class="mx-auto"
          />
        </div>
        <div class="display-2 font-weight-bold text-primary">
          {{ product.name }}
        </div>
        <div class="title my-2">{{ product.tagline || 'Welcome! Let\'s build amazing things together.' }}</div>
        <v-btn to="/" class="my-4">Take me back</v-btn>
      </div>
      <v-img v-if="!loginBrandingEnabled || !currentLogo" src="/images/illustrations/signin-illustration.svg" max-height="400" contain />
    </v-sheet>

    <div class="pa-2 pa-md-4 flex-grow-1 align-center justify-center d-flex flex-column">
      <div class="layout-content ma-auto w-full">
        <slot></slot>
      </div>
      <div class="overline mt-4">{{ product.name }} - {{ product.version }}</div>
    </div>
  </div>
</template>

<script>
import { useTheme } from 'vuetify'
import { computed } from 'vue'
import { useAppStore } from "../store/app/index.js"
import { useSettingsStore } from "../store/settingStore.js"

export default {
  setup() {
    const theme = useTheme()

    const toggleTheme = () => {
      theme.global.name.value = theme.global.name.value === 'dark' ? 'light' : 'dark'
    }

    const isDark = computed(() => theme.global.name.value === 'dark')

    const applyThemeSettings = () => {
      const settingsStore = useSettingsStore()
      const themeMode = settingsStore.themeMode

      if (themeMode === 'light') {
        theme.global.name.value = 'light'
      } else if (themeMode === 'dark') {
        theme.global.name.value = 'dark'
      } else if (themeMode === 'system') {
        // Detect system preference
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
        theme.global.name.value = prefersDark ? 'dark' : 'light'
      }
    }

    return {
      toggleTheme,
      isDark,
      applyThemeSettings
    }
  },
  computed: {
    product() {
      const app = useAppStore()
      const settings = useSettingsStore()
      return {
        name: settings.appName || app.product.name,
        version: app.product.version,
        tagline: settings.siteTagline || ''
      }
    },
    maintenanceMode() {
      const settings = useSettingsStore()
      return settings.maintenanceMode
    },
    loginBrandingEnabled() {
      const settings = useSettingsStore()
      return settings.loginBrandingEnabled
    },
    currentLogo() {
      const settings = useSettingsStore()
      // Use theme-appropriate logo
      const logo = this.isDark ? settings.logoDark : settings.logoLight
      // Fallback to light logo if dark logo is not set
      const finalLogo = logo || settings.logoLight
      if (finalLogo) {
        return `/storage/${finalLogo}`
      }
      return null
    }
  },
  async mounted() {
    const settingsStore = useSettingsStore()
    if (!settingsStore.settingsLoaded) {
      await settingsStore.fetchAppSettings()
    }

    // Apply theme settings after settings are loaded
    this.applyThemeSettings()
  }
}
</script>

<style scoped>
.auth-layout {
  position: relative;
  min-height: 100vh;
}

.maintenance-banner {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 9;
  margin: 0;
  border-radius: 0;
}

.theme-toggle-btn {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 10;
}

.layout-side {
  width: 420px;
}

.layout-content {
  max-width: 480px;
}
</style>
