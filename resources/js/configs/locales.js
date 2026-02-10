import en from '../translations/en'
import de from '../translations/de'

const supported = ['en', 'de']
let locale = 'en'

try {
  // Check localStorage first (user's explicit preference)
  const storedLocale = localStorage.getItem('locale')
  if (storedLocale && supported.includes(storedLocale)) {
    locale = storedLocale
  } else {
    // Check cookie (set by server or previous session)
    const cookieMatch = document.cookie.match(/(?:^|;\s*)locale=([^;]*)/)
    if (cookieMatch && supported.includes(cookieMatch[1])) {
      locale = cookieMatch[1]
    } else {
      // Fall back to browser default language
      const { 0: browserLang } = navigator.language.split('-')
      if (supported.includes(browserLang)) locale = browserLang
    }
  }
} catch (e) {
  console.log(e)
}

const availableLocales = [
    {
        code: 'en',
        flag: 'us',
        label: 'EN',
        messages: en,
    },
    {
        code: 'de',
        flag: 'de',
        label: 'DE',
        messages: de,
    },
];

const fallbackLocale = 'en'

export default { locale, availableLocales, fallbackLocale, supported };
