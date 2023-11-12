<template>
  <v-menu
    offset-y
    left
    transition="slide-y-transition"
  >
<!--    <template v-slot:activator="{ probs }">-->
<!--      <v-btn :icon="$vuetify.display.smAndDown" v-bind="probs">-->
<!--        <span v-show="$vuetify.display.mdAndUp && showLabel" :class="[$vuetify.rtl ? 'mr-1' : 'ml-1']">{{ currentLocale.label }}</span>-->
<!--        <v-icon v-if="showArrow" right>mdi-chevron-down</v-icon>-->
<!--      </v-btn>-->
<!--    </template>-->

    <v-list>
      <v-list-item v-for="locale in availableLocales" :key="locale.code" @click="setLocale(locale.code)">
        <v-list-item-title>{{ locale.label }}</v-list-item-title>
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
import  { i18n } from "@/plugins/vue-i18n.js";

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
        // return 'de'
        // console.log('i18n',this.$i18n)
      return i18n.locales.find((i) => i.code === i18n.locale)
    },
    availableLocales () {
      return i18n.locales.filter((i) => i.code !== i18n.locale)
    }
  },
  methods: {
    setLocale(locale) {
      i18n.locale = locale
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
