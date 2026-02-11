import en from '../translations/en'
import de from '../translations/de'

/**
 * ====================================================================
 * SINGLE SOURCE OF TRUTH FOR LOCALE CONFIGURATION
 * ====================================================================
 * 
 * This is the master configuration for all application locales.
 * Any changes to available locales should be made here ONLY.
 * 
 * Other files that reference this:
 * - resources/js/composables/useLocale.js (imports from here)
 * - config/translatable.php (keep manually in sync - see note there)
 * 
 * To add a new language:
 * 1. Add import: import xx from '../translations/xx'
 * 2. Add to localeMetadata array below
 * 3. Update config/translatable.php 'locales' array
 * ====================================================================
 */

/**
 * Locale metadata configuration.
 * This is the single source of truth for all locale information.
 */
export const localeMetadata = [
    {
        code: 'en',
        flag: 'us',
        label: 'EN',
        nativeLabel: 'English',
        messages: en,
    },
    {
        code: 'de',
        flag: 'de',
        label: 'DE',
        nativeLabel: 'Deutsch',
        messages: de,
    },
];

// Extract supported locale codes from metadata
export const supported = localeMetadata.map(l => l.code);

// Default fallback locale
export const fallbackLocale = 'en';

// Detect initial locale from user preferences
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

// Export for vue-i18n config
export default { 
    locale, 
    availableLocales: localeMetadata, 
    fallbackLocale, 
    supported 
};
