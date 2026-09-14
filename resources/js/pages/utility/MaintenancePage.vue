<template>
  <div>
    <v-img
      class="mb-5"
      max-height="400"
      contain
      src="/images/illustrations/maintenance-illustration.svg"
    ></v-img>

    <h1 class="text-h3 font-weight-bold">{{ $t('utility.maintenance') }}</h1>
    <div class="text-h6 text-medium-emphasis mt-4" style="white-space: pre-line;">{{ maintenanceMessage }}</div>
  </div>
</template>

<script>
import { useSettingsStore } from '@/store/settingStore.js'

export default {
  computed: {
    maintenanceMessage() {
      const settingsStore = useSettingsStore()
      return settingsStore.maintenanceMessage || this.$t('utility.maintenanceDescription')
    }
  },
  async mounted() {
    const settingsStore = useSettingsStore()
    if (!settingsStore.settingsLoaded) {
      await settingsStore.fetchAppSettings()
    }
  }
}
</script>
