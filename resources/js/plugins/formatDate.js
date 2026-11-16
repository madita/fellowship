import { format, parseISO, formatDistance, formatDistanceToNow } from 'date-fns';
import { toZonedTime, formatInTimeZone } from 'date-fns-tz';
import { enGB, de } from 'date-fns/locale';
import { useSettingsStore } from '../store/settingStore.js';
import { useUserStore } from '../store/userStore.js';
import { getDateFnsFormat, isDateFnsFormat } from '../utils/formatConverter.js';

// Helper function to get locale
function getLocale(localeString) {
    switch (localeString) {
        case 'enGB':
        case 'en':
            return enGB;
        case 'de':
            return de;
        default:
            return enGB;
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

/**
 * Get date format settings with fallback chain:
 * user preference → global setting → default
 */
function getDateFormatSettings() {
    const settingsStore = useSettingsStore();
    const userStore = useUserStore();

    const timezone = userStore.user?.timezone ||
                    settingsStore.appSettings?.default_timezone ||
                    'UTC';

    const dateFormat = userStore.user?.date_format ||
                      settingsStore.appSettings?.date_format ||
                      'Y-m-d';

    const locale = getLocale(settingsStore.locale || 'en');

    return {
        timezone,
        dateFormat: getDateFnsFormat(dateFormat),
        rawDateFormat: dateFormat,
        locale
    };
}

/**
 * Format a date string with timezone conversion and user preferences
 * @param {string|Date} dateString - The date to format
 * @param {string|null} formatString - Optional format string (PHP or date-fns format)
 * @param {object} options - Optional configuration
 * @param {boolean} options.useTimezone - Whether to apply timezone conversion (default: true)
 * @returns {string} Formatted date string
 */
export function formatDate(dateString, formatString = null, options = {}) {
    const { timezone, dateFormat, locale } = getDateFormatSettings();
    const { useTimezone = true } = options;

    // Determine the effective format
    let effectiveFormat = dateFormat; // Default to user preference

    if (formatString) {
        // If format string is provided, check if it's already date-fns format
        if (isDateFnsFormat(formatString)) {
            effectiveFormat = formatString;
        } else {
            // Convert PHP format to date-fns format
            effectiveFormat = getDateFnsFormat(formatString);
        }
    }

    const date = safeParseDate(dateString);
    if (!date || isNaN(date.getTime())) {
        return 'Invalid Date';
    }

    // Apply timezone conversion if enabled
    // Use formatInTimeZone for proper UTC → target timezone conversion
    if (useTimezone && timezone) {
        return formatInTimeZone(date, timezone, effectiveFormat, { locale });
    }

    return format(date, effectiveFormat, { locale });
}

/**
 * Format a date in a specific timezone
 * @param {string|Date} dateString - The date to format
 * @param {string|null} formatString - Optional format string
 * @param {string|null} tz - Timezone (defaults to user preference)
 * @returns {string} Formatted date string
 */
export function formatDateInTimezone(dateString, formatString = null, tz = null) {
    const { timezone, dateFormat, locale } = getDateFormatSettings();
    const effectiveTimezone = tz || timezone;

    // Determine the effective format
    let effectiveFormat = dateFormat;

    if (formatString) {
        if (isDateFnsFormat(formatString)) {
            effectiveFormat = formatString;
        } else {
            effectiveFormat = getDateFnsFormat(formatString);
        }
    }

    const date = safeParseDate(dateString);
    if (!date || isNaN(date.getTime())) {
        return 'Invalid Date';
    }

    return formatInTimeZone(date, effectiveTimezone, effectiveFormat, { locale });
}

/**
 * Format distance between two dates
 * @param {string|Date} date1 - First date
 * @param {string|Date} date2 - Second date (defaults to now)
 * @returns {string} Formatted distance string
 */
export function formatDateDistance(date1, date2 = new Date()) {
    const { locale } = getDateFormatSettings();
    const parsedDate1 = safeParseDate(date1);
    const parsedDate2 = safeParseDate(date2);

    if (!parsedDate1 || isNaN(parsedDate1.getTime())) {
        return 'Invalid Date';
    }

    return formatDistance(parsedDate1, parsedDate2, { addSuffix: true, locale });
}

/**
 * Format distance from now
 * @param {string|Date} dateString - The date
 * @returns {string} Formatted distance string (e.g., "2 hours ago")
 */
export function formatDateDistanceToNow(dateString) {
    const { locale } = getDateFormatSettings();
    const date = safeParseDate(dateString);

    if (!date || isNaN(date.getTime())) {
        return 'Invalid Date';
    }

    return formatDistanceToNow(date, { addSuffix: true, locale });
}

// Vue 3 Plugin for global properties (backwards compatibility)
export default {
    install(app) {
        // Register global properties for Options API compatibility
        app.config.globalProperties.$formatDate = formatDate;
        app.config.globalProperties.$formatDateInTimezone = formatDateInTimezone;
        app.config.globalProperties.$formatDistance = formatDateDistance;
        app.config.globalProperties.$formatDistanceToNow = formatDateDistanceToNow;

        // Provide functions for Composition API
        app.provide('formatDate', formatDate);
        app.provide('formatDateInTimezone', formatDateInTimezone);
        app.provide('formatDistance', formatDateDistance);
        app.provide('formatDistanceToNow', formatDateDistanceToNow);
    }
};

// Composable for use in Composition API components
export function useDateFormat() {
    return {
        formatDate,
        formatDateInTimezone,
        formatDistance: formatDateDistance,
        formatDistanceToNow: formatDateDistanceToNow
    };
}
