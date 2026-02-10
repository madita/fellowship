import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * Composable for managing application locale.
 * Syncs with vue-i18n and persists to localStorage/cookie.
 */
export function useLocale() {
    const { locale, availableLocales } = useI18n();

    // Available locales with metadata
    const localeOptions = [
        { code: 'en', flag: 'us', label: 'English', nativeLabel: 'English' },
        { code: 'de', flag: 'de', label: 'German', nativeLabel: 'Deutsch' },
    ];

    const currentLocale = computed(() => locale.value);

    const currentLocaleOption = computed(() => {
        return localeOptions.find(l => l.code === locale.value) || localeOptions[0];
    });

    /**
     * Change the application locale.
     * Updates vue-i18n, localStorage, cookie, and axios header.
     */
    function setLocale(newLocale) {
        if (!availableLocales.includes(newLocale)) {
            console.warn(`Locale "${newLocale}" is not available`);
            return;
        }

        // Update vue-i18n
        locale.value = newLocale;

        // Persist to localStorage
        localStorage.setItem('locale', newLocale);

        // Set cookie for server-side detection
        document.cookie = `locale=${newLocale};path=/;max-age=${60 * 60 * 24 * 365}`;

        // Update axios default header
        if (window.axios) {
            window.axios.defaults.headers.common['X-Locale'] = newLocale;
        }

        // Dispatch event for other parts of the app
        window.dispatchEvent(new CustomEvent('locale-changed', { detail: { locale: newLocale } }));
    }

    /**
     * Initialize locale from stored preference.
     */
    function initLocale() {
        // Check localStorage first
        const storedLocale = localStorage.getItem('locale');
        if (storedLocale && availableLocales.includes(storedLocale)) {
            setLocale(storedLocale);
            return;
        }

        // Check cookie
        const cookieMatch = document.cookie.match(/(?:^|;\s*)locale=([^;]*)/);
        if (cookieMatch && availableLocales.includes(cookieMatch[1])) {
            setLocale(cookieMatch[1]);
            return;
        }

        // Set axios header for current locale
        if (window.axios) {
            window.axios.defaults.headers.common['X-Locale'] = locale.value;
        }
    }

    // Watch for external locale changes (e.g., from vue-i18n)
    watch(locale, (newLocale) => {
        if (window.axios) {
            window.axios.defaults.headers.common['X-Locale'] = newLocale;
        }
    });

    return {
        locale: currentLocale,
        localeOptions,
        currentLocaleOption,
        availableLocales,
        setLocale,
        initLocale,
    };
}

/**
 * Initialize the axios X-Locale header on app startup.
 * Call this in your main app.js after bootstrap.js
 */
export function initializeLocaleHeader() {
    const storedLocale = localStorage.getItem('locale');
    const cookieMatch = document.cookie.match(/(?:^|;\s*)locale=([^;]*)/);
    const browserLang = navigator.language?.split('-')[0];

    const locale = storedLocale || cookieMatch?.[1] || browserLang || 'en';

    if (window.axios) {
        window.axios.defaults.headers.common['X-Locale'] = locale;
    }
}

export default useLocale;
