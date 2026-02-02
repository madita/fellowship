<template>
  <v-menu
    offset-y
    left
    transition="slide-y-transition"
  >
    <template v-slot:activator="{ props }">
      <v-btn v-bind="props" variant="text">
        <v-icon class="mr-1">mdi-translate</v-icon>
        <span v-if="showLabel">{{ currentLocale.label }}</span>
        <v-icon v-if="showArrow" class="ml-1">mdi-chevron-down</v-icon>
      </v-btn>
    </template>

    <v-list>
      <v-list-item v-for="locale in allLocales" :key="locale.code" @click="setLocale(locale.code)">
        <template v-slot:prepend>
          <v-icon v-if="locale.code === currentLocale.code">mdi-check</v-icon>
          <div v-else style="width: 24px;"></div>
        </template>
        <v-list-item-title>{{ locale.label }} - {{ locale.code.toUpperCase() }}</v-list-item-title>
      </v-list-item>
    </v-list>
  </v-menu>
</template>

<script>

/*
|---------------------------------------------------------------------
| Language Switcher Component
|---------------------------------------------------------------------
|
| Locale menu to choose the language based on the locales present in
| vue-i18n locales available array
|
*/
import { i18n } from "@/plugins/vue-i18n.js";
import localesConfig from "@/configs/locales.js";

export default {
  props: {
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
  },
  computed: {
    currentLocale() {
      const currentCode = i18n.global?.locale?.value || i18n.global?.locale || 'en';
      return localesConfig.availableLocales.find((i) => i.code === currentCode) || localesConfig.availableLocales[0];
    },
    availableLocales() {
      const currentCode = i18n.global?.locale?.value || i18n.global?.locale || 'en';
      return localesConfig.availableLocales.filter((i) => i.code !== currentCode);
    },
    allLocales() {
      return localesConfig.availableLocales;
    }
  },
  methods: {
    setLocale(locale) {
      if (i18n.global?.locale) {
        i18n.global.locale.value = locale;
      }
      // this.$vuetify.lang.current = locale

      // example on how certain languages can be RTL
      // if (locale === 'ar') {
      //   this.$vuetify.rtl = true
      // } else {
      //   this.$vuetify.rtl = false
      // }
    }
  }
}
</script>
