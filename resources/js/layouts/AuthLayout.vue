<template>
  <v-sheet class="d-flex text-center flex-column flex-md-row flex-grow-1 auth-layout" color="background">
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
        <strong>{{ $t('auth.maintenanceModeActive') }}</strong>
        <span class="ml-1 d-none d-sm-inline">- {{ $t('auth.onlyAdminsCanAccess') }}</span>
      </div>
    </v-alert>

    <!-- Theme Toggle and Language Switch -->
    <div class="auth-controls">
      <ToolbarLanguage :show-label="false" />
      <v-btn
        icon
        @click="toggleTheme"
        :title="isDark ? $t('common.switchToLightMode') : $t('common.switchToDarkMode')"
      >
        <v-icon>{{ isDark ? 'mdi-weather-sunny' : 'mdi-weather-night' }}</v-icon>
      </v-btn>
    </div>

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
          {{ appName }}
        </div>
        <div class="title my-2">{{ siteTagline || $t('auth.welcomeTagline') }}</div>
        <v-btn to="/" class="my-4">{{ $t('auth.takeMeBack') }}</v-btn>
      </div>
      <v-img v-if="!loginBrandingEnabled || !currentLogo" src="/images/illustrations/signin-illustration.svg" max-height="400" contain />
    </v-sheet>

    <div class="pa-2 pa-md-4 flex-grow-1 align-center justify-center d-flex flex-column">
      <div class="layout-content ma-auto w-full">
        <slot></slot>
      </div>
      <div class="overline mt-4">{{ appName }} - {{ appVersion }}</div>
    </div>
  </v-sheet>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useTheme } from 'vuetify'
import { useI18n } from 'vue-i18n'
import { useAppStore } from "../store/app/index.js"
import { useSettingsStore } from "../store/settingStore.js"
import ToolbarLanguage from "../components/toolbar/ToolbarLanguage.vue"

const { t } = useI18n()

const theme = useTheme()
const appStore = useAppStore()
const settingsStore = useSettingsStore()

// Theme state
const isDark = computed(() => theme.global.name.value === 'dark')

const toggleTheme = () => {
  theme.global.name.value = isDark.value ? 'light' : 'dark'
}

// Settings
const appName = computed(() => settingsStore.appName || appStore.product.name)
const appVersion = computed(() => appStore.product.version)
const siteTagline = computed(() => settingsStore.siteTagline)
const maintenanceMode = computed(() => settingsStore.maintenanceMode)
const loginBrandingEnabled = computed(() => settingsStore.loginBrandingEnabled)

// Logo - theme-aware
const currentLogo = computed(() => {
  const logo = isDark.value ? settingsStore.logoDark : settingsStore.logoLight
  // Fallback to light logo if dark logo is not set
  const finalLogo = logo || settingsStore.logoLight
  if (finalLogo) {
    return `/storage/${finalLogo}`
  }
  return null
})

// Apply theme settings based on saved preference
const applyThemeSettings = () => {
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

onMounted(async () => {
  if (!settingsStore.settingsLoaded) {
    await settingsStore.fetchAppSettings()
  }

  // Apply theme settings after settings are loaded
  applyThemeSettings()
})
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

.auth-controls {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 10;
  display: flex;
  gap: 4px;
  align-items: center;
}

.layout-side {
  width: 420px;
}

.layout-content {
  max-width: 480px;
}
</style>
