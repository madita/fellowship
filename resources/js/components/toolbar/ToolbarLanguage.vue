refactor menu sytem feature<template>
  <v-menu
    offset-y
    left
    transition="slide-y-transition"
  >
    <template v-slot:activator="{ props }">
      <v-btn v-bind="props" variant="text">
        <v-icon class="mr-1">mdi-translate</v-icon>
        <span v-if="showLabel">{{ currentLocaleOption.label }}</span>
        <v-icon v-if="showArrow" class="ml-1">mdi-chevron-down</v-icon>
      </v-btn>
    </template>

    <v-list density="compact">
      <v-list-item
        v-for="option in localeOptions"
        :key="option.code"
        :active="option.code === locale"
        @click="changeLocale(option.code)"
      >
        <template v-slot:prepend>
          <v-icon v-if="option.code === locale">mdi-check</v-icon>
          <div v-else style="width: 24px;"></div>
        </template>
        <v-list-item-title>{{ option.nativeLabel }} ({{ option.code.toUpperCase() }})</v-list-item-title>
      </v-list-item>
    </v-list>
  </v-menu>
</template>

<script setup>
/*
|---------------------------------------------------------------------
| Language Switcher Component
|---------------------------------------------------------------------
|
| Locale menu to choose the language. Changes are persisted to
| localStorage, cookie, and sent to the API via X-Locale header.
|
*/
import { useLocale } from '@/composables/useLocale.js';

const props = defineProps({
  // Show dropdown arrow
  showArrow: {
    type: Boolean,
    default: false
  },
  // Show the country label
  showLabel: {
    type: Boolean,
    default: true
  }
});

const { locale, localeOptions, currentLocaleOption, setLocale } = useLocale();

function changeLocale(newLocale) {
  setLocale(newLocale);

  // Emit custom event for components that need to refresh data
  window.dispatchEvent(new CustomEvent('locale-changed', {
    detail: { locale: newLocale }
  }));
}
</script>
