import { createI18n } from 'vue-i18n'
import locales from '../configs/locales.js'

const { locale, availableLocales, fallbackLocale } = locales

// function loadLocaleMessages(locale) {
//     return import(`../translations/${locale}.js`);
// }
const messages = {}
availableLocales.forEach((l) => { messages[l.code] = l.messages })

export const i18n = new createI18n({
  locale: 'en',
  fallbackLocale,
  messages
})

export default i18n
