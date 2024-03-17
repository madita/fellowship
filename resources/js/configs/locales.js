import en from '../translations/en'
import de from '../translations/de'

const supported = ['en', 'de']
let locale = 'en'

try {
  // get browser default language
  const { 0: browserLang } = navigator.language.split('-')

  if (supported.includes(browserLang)) locale = browserLang
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

export default { locale, availableLocales };
