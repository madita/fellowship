import { format, parseISO, formatDistance, formatDistanceToNow } from 'date-fns';
import { enGB, de } from 'date-fns/locale';
import { useSettingsStore } from '../store/settingStore.js'; // Adjust the path

// Helper function to get locale
function getLocale(localeString) {
    switch (localeString) {
        case 'enGB':
            return enGB;
        case 'de':
            return de;
        default:
            return de;
    }
}

// Helper function to safely parse date
function safeParseDate(dateString) {
    if (!dateString) return null;

    // If it's already a Date object, return it
    if (dateString instanceof Date) return dateString;

    // Try to parse ISO string first
    try {
        return parseISO(dateString);
    } catch {
        // Fallback to new Date()
        return new Date(dateString);
    }
}

// Format functions that can be used in Composition API
export function formatDate(dateString, formatString = "yyyy-MM-dd") {
    const settings = useSettingsStore();
    const date = safeParseDate(dateString);

    if (!date || isNaN(date.getTime())) {
        return 'Invalid Date';
    }

    const locale = getLocale(settings.locale);
    return format(date, formatString, { locale });
}

export function formatDateDistance(date1, date2 = new Date()) {
    const settings = useSettingsStore();
    const parsedDate1 = safeParseDate(date1);
    const parsedDate2 = safeParseDate(date2);

    if (!parsedDate1 || isNaN(parsedDate1.getTime())) {
        return 'Invalid Date';
    }

    const locale = getLocale(settings.locale);
    return formatDistance(parsedDate1, parsedDate2, { addSuffix: true, locale });
}

export function formatDateDistanceToNow(dateString) {
    const settings = useSettingsStore();
    const date = safeParseDate(dateString);

    if (!date || isNaN(date.getTime())) {
        return 'Invalid Date';
    }

    const locale = getLocale(settings.locale);
    return formatDistanceToNow(date, { addSuffix: true, locale });
}

// Vue 3 Plugin for global properties (backwards compatibility)
export default {
    install(app) {
        // Register global properties for Options API compatibility
        app.config.globalProperties.$formatDate = formatDate;
        app.config.globalProperties.$formatDistance = formatDateDistance;
        app.config.globalProperties.$formatDistanceToNow = formatDateDistanceToNow;

        // Provide functions for Composition API
        app.provide('formatDate', formatDate);
        app.provide('formatDistance', formatDateDistance);
        app.provide('formatDistanceToNow', formatDateDistanceToNow);
    }
};

// Composable for use in Composition API components
export function useDateFormat() {
    return {
        formatDate,
        formatDistance: formatDateDistance,
        formatDistanceToNow: formatDateDistanceToNow
    };
}
