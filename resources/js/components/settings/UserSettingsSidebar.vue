<template>
  <v-card elevation="1">
    <v-card-title class="py-3 d-flex align-center justify-space-between">
      <div class="d-flex align-center">
        <v-icon class="mr-2" color="primary">mdi-cog</v-icon>
        <span class="text-subtitle-1 font-weight-medium">
          User Settings
        </span>
      </div>
      <div class="d-flex align-center">
        <v-btn icon variant="text" size="small" @click="collapsed = !collapsed">
          <v-icon>{{ collapsed ? 'mdi-chevron-down' : 'mdi-chevron-up' }}</v-icon>
        </v-btn>
        <v-btn icon variant="text" size="small" @click="$emit('close')">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </div>
    </v-card-title>

    <v-expand-transition>
      <div v-show="!collapsed">
        <v-divider></v-divider>

        <v-card-text class="pa-4">
          <!-- Theme Mode -->
          <div class="mb-6">
            <div class="text-caption text-medium-emphasis mb-2">
              <v-icon size="small" class="mr-1">mdi-theme-light-dark</v-icon>
              Theme Mode
            </div>
            <v-btn-toggle
              v-model="themeMode"
              mandatory
              color="primary"
              variant="outlined"
              divided
              density="compact"
              class="d-flex"
            >
              <v-btn value="light" class="flex-grow-1">
                <v-icon size="small" class="mr-1">mdi-white-balance-sunny</v-icon>
                Light
              </v-btn>
              <v-btn value="dark" class="flex-grow-1">
                <v-icon size="small" class="mr-1">mdi-moon-waning-crescent</v-icon>
                Dark
              </v-btn>
              <v-btn value="system" class="flex-grow-1">
                <v-icon size="small" class="mr-1">mdi-laptop</v-icon>
                Auto
              </v-btn>
            </v-btn-toggle>
            <div class="text-caption text-medium-emphasis mt-1">
              {{ themeHint }}
            </div>
          </div>

          <v-divider class="my-4"></v-divider>

          <!-- Language -->
          <div class="mb-6" v-if="languageChangeEnabled">
            <div class="text-caption text-medium-emphasis mb-2">
              <v-icon size="small" class="mr-1">mdi-translate</v-icon>
              Language
            </div>
            <v-select
              v-model="selectedLanguage"
              :items="availableLanguages"
              item-title="name"
              item-value="code"
              variant="outlined"
              density="compact"
              hide-details
            ></v-select>
          </div>

          <v-divider class="my-4" v-if="languageChangeEnabled"></v-divider>

          <!-- Timezone -->
          <div class="mb-6">
            <div class="text-caption text-medium-emphasis mb-2">
              <v-icon size="small" class="mr-1">mdi-clock-outline</v-icon>
              Timezone
            </div>
            <v-autocomplete
              v-model="userTimezone"
              :items="timezones"
              variant="outlined"
              density="compact"
              hide-details
              placeholder="Select timezone"
            ></v-autocomplete>
            <div class="text-caption text-medium-emphasis mt-1">
              Current: {{ currentTime }}
            </div>
          </div>

          <v-divider class="my-4"></v-divider>

          <!-- Date Format -->
          <div class="mb-6">
            <div class="text-caption text-medium-emphasis mb-2">
              <v-icon size="small" class="mr-1">mdi-calendar</v-icon>
              Date Format
            </div>
            <v-select
              v-model="userDateFormat"
              :items="dateFormats"
              item-title="label"
              item-value="value"
              variant="outlined"
              density="compact"
              hide-details
            ></v-select>
            <div class="text-caption text-medium-emphasis mt-1">
              Preview: {{ datePreview }}
            </div>
          </div>

          <v-divider class="my-4"></v-divider>

          <!-- Save Button -->
          <v-btn
            block
            color="primary"
            :loading="saving"
            @click="savePreferences"
            prepend-icon="mdi-content-save"
          >
            Save Preferences
          </v-btn>

          <!-- Success/Error Messages -->
          <v-alert
            v-if="message"
            :type="messageType"
            density="compact"
            class="mt-3"
            closable
            @click:close="message = ''"
          >
            {{ message }}
          </v-alert>
        </v-card-text>
      </div>
    </v-expand-transition>
  </v-card>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useTheme } from 'vuetify'
import { useUserStore } from '@/store/userStore'
import { useSettingsStore } from '@/store/settingStore'
import { useI18n } from 'vue-i18n'
import { formatDateInTimezone, formatDate } from '@/plugins/formatDate.js'
import axios from 'axios'

const emit = defineEmits(['close'])

const theme = useTheme()
const userStore = useUserStore()
const settingsStore = useSettingsStore()
const { locale } = useI18n()

const collapsed = ref(false)
const saving = ref(false)
const message = ref('')
const messageType = ref('success')

// Theme Mode
const themeMode = ref('system')
const themeHint = computed(() => {
  const hints = {
    light: 'Always use light theme',
    dark: 'Always use dark theme',
    system: 'Follows your system preferences'
  }
  return hints[themeMode.value]
})

// Language
const selectedLanguage = ref('en')
const languageChangeEnabled = computed(() => settingsStore.languageChangeEnabled)
const availableLanguages = ref([
  { code: 'en', name: 'English' },
  { code: 'de', name: 'Deutsch (German)' },
])

// Timezone
const userTimezone = ref('UTC')
const timezones = ref([
  'UTC',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'America/Toronto',
  'America/Mexico_City',
  'America/Sao_Paulo',
  'America/Buenos_Aires',
  'Europe/London',
  'Europe/Paris',
  'Europe/Berlin',
  'Europe/Rome',
  'Europe/Madrid',
  'Europe/Amsterdam',
  'Europe/Brussels',
  'Europe/Vienna',
  'Europe/Stockholm',
  'Europe/Warsaw',
  'Europe/Prague',
  'Europe/Athens',
  'Europe/Istanbul',
  'Europe/Moscow',
  'Asia/Dubai',
  'Asia/Kolkata',
  'Asia/Bangkok',
  'Asia/Singapore',
  'Asia/Hong_Kong',
  'Asia/Shanghai',
  'Asia/Tokyo',
  'Asia/Seoul',
  'Australia/Sydney',
  'Australia/Melbourne',
  'Pacific/Auckland',
  'Pacific/Fiji',
])

// Date Format
const userDateFormat = ref('Y-m-d')
const dateFormats = ref([
  { label: 'YYYY-MM-DD (2025-12-20)', value: 'Y-m-d' },
  { label: 'DD/MM/YYYY (20/12/2025)', value: 'd/m/Y' },
  { label: 'MM/DD/YYYY (12/20/2025)', value: 'm/d/Y' },
  { label: 'DD.MM.YYYY (20.12.2025)', value: 'd.m.Y' },
])

// Current time in selected timezone
const currentTime = ref('')
const updateCurrentTime = () => {
  try {
    currentTime.value = formatDateInTimezone(new Date(), 'H:i:s', userTimezone.value)
  } catch (error) {
    currentTime.value = formatDate(new Date(), 'H:i:s')
  }
}

// Date preview
const datePreview = computed(() => {
  return formatDateInTimezone(new Date(), userDateFormat.value, userTimezone.value)
})

// Watch theme mode changes and apply immediately
watch(themeMode, (newMode) => {
  applyTheme(newMode)
  // Persist to localStorage immediately
  localStorage.setItem('theme_mode', newMode)
})

// Watch language changes and apply immediately
watch(selectedLanguage, (newLang) => {
  locale.value = newLang
  localStorage.setItem('language', newLang)
})

// Watch timezone changes to update current time
watch(userTimezone, () => {
  updateCurrentTime()
})

function applyTheme(mode) {
  if (mode === 'system') {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
    theme.global.name.value = prefersDark ? 'dark' : 'light'
  } else {
    theme.global.name.value = mode
  }

  // Force Vuetify to update the theme on the document
  if (mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('v-theme--dark')
    document.documentElement.classList.remove('v-theme--light')
  } else {
    document.documentElement.classList.add('v-theme--light')
    document.documentElement.classList.remove('v-theme--dark')
  }
}

async function savePreferences() {
  saving.value = true
  message.value = ''

  try {
    // Save to backend
    await axios.patch('/api/account/preferences', {
      timezone: userTimezone.value,
      date_format: userDateFormat.value,
      theme_mode: themeMode.value,
      language: selectedLanguage.value,
    })

    // Update user store
    if (userStore.user) {
      userStore.user.timezone = userTimezone.value
      userStore.user.date_format = userDateFormat.value
      userStore.user.theme_mode = themeMode.value
      userStore.user.language = selectedLanguage.value
    }

    message.value = 'Preferences saved successfully!'
    messageType.value = 'success'
  } catch (error) {
    console.error('Failed to save preferences:', error)
    message.value = 'Failed to save preferences. Please try again.'
    messageType.value = 'error'
  } finally {
    saving.value = false
  }
}

// Initialize values from user store or defaults
function loadPreferences() {
  const user = userStore.user

  // Load theme mode - check localStorage first for immediate application
  const savedTheme = localStorage.getItem('theme_mode')
  if (savedTheme) {
    themeMode.value = savedTheme
  } else if (user?.theme_mode) {
    themeMode.value = user.theme_mode
  } else {
    themeMode.value = 'system'
  }
  // Apply theme immediately without triggering watcher
  applyTheme(themeMode.value)

  // Load language - check localStorage first
  const savedLanguage = localStorage.getItem('language')
  if (savedLanguage) {
    selectedLanguage.value = savedLanguage
    locale.value = savedLanguage
  } else if (user?.language) {
    selectedLanguage.value = user.language
    locale.value = user.language
  } else {
    selectedLanguage.value = settingsStore.defaultLanguage || 'en'
    locale.value = selectedLanguage.value
  }

  // Load timezone
  userTimezone.value = user?.timezone || settingsStore.defaultTimezone || 'UTC'

  // Load date format
  userDateFormat.value = user?.date_format || settingsStore.dateFormat || 'Y-m-d'

  // Update current time
  updateCurrentTime()
}

onMounted(() => {
  loadPreferences()

  // Update time every second
  const timeInterval = setInterval(updateCurrentTime, 1000)

  // Listen for system theme changes when in system mode
  const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
  const handleThemeChange = () => {
    if (themeMode.value === 'system') {
      applyTheme('system')
    }
  }
  mediaQuery.addEventListener('change', handleThemeChange)

  // Cleanup
  return () => {
    clearInterval(timeInterval)
    mediaQuery.removeEventListener('change', handleThemeChange)
  }
})
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
