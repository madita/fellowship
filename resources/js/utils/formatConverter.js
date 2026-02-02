/**
 * PHP to date-fns format conversion utility
 *
 * Converts PHP date format strings to date-fns format strings
 * Based on: https://www.php.net/manual/en/datetime.format.php
 * and: https://date-fns.org/docs/format
 */

/**
 * PHP to date-fns format conversion map
 */
const PHP_TO_DATE_FNS_MAP = {
    // Day
    'd': 'dd',      // 01-31
    'D': 'EEE',     // Mon-Sun
    'j': 'd',       // 1-31
    'l': 'EEEE',    // Monday-Sunday
    'N': 'i',       // 1-7 (ISO-8601)
    'S': 'do',      // st, nd, rd, th
    'w': 'e',       // 0-6
    'z': 'D',       // 0-365

    // Week
    'W': 'II',      // ISO-8601 week number

    // Month
    'F': 'MMMM',    // January-December
    'm': 'MM',      // 01-12
    'M': 'MMM',     // Jan-Dec
    'n': 'M',       // 1-12
    't': '',        // No equivalent

    // Year
    'L': '',        // No equivalent (leap year)
    'o': 'YYYY',    // ISO-8601 year
    'Y': 'yyyy',    // 1999, 2025
    'y': 'yy',      // 99, 25

    // Time
    'a': 'a',       // am/pm
    'A': 'a',       // AM/PM (date-fns uses 'a' for both)
    'B': '',        // Swatch Internet time (no equivalent)
    'g': 'h',       // 1-12
    'G': 'H',       // 0-23
    'h': 'hh',      // 01-12
    'H': 'HH',      // 00-23
    'i': 'mm',      // Minutes 00-59
    's': 'ss',      // Seconds 00-59
    'u': 'SSSSSS',  // Microseconds
    'v': 'SSS',     // Milliseconds

    // Timezone
    'e': 'zzz',     // Timezone identifier
    'I': '',        // Daylight saving (no direct equivalent)
    'O': 'xx',      // +0200
    'P': 'xxx',     // +02:00
    'T': 'zzz',     // EST, MDT
    'Z': '',        // Timezone offset in seconds

    // Full date/time
    'c': "yyyy-MM-dd'T'HH:mm:ssxxx",  // ISO 8601
    'r': "EEE, dd MMM yyyy HH:mm:ss xx",  // RFC 2822
    'U': 't',       // Unix timestamp
};

/**
 * Common preset conversions for performance
 */
export const COMMON_FORMATS = {
    // Date formats
    'Y-m-d': 'yyyy-MM-dd',
    'd/m/Y': 'dd/MM/yyyy',
    'm/d/Y': 'MM/dd/yyyy',
    'd.m.Y': 'dd.MM.yyyy',

    // Date-time formats
    'Y-m-d H:i:s': 'yyyy-MM-dd HH:mm:ss',
    'Y-m-d h:i:s A': 'yyyy-MM-dd hh:mm:ss a',

    // Time formats (24-hour)
    'H:i:s': 'HH:mm:ss',
    'H:i': 'HH:mm',

    // Time formats (12-hour)
    'h:i:s A': 'hh:mm:ss a',
    'h:i A': 'hh:mm a',

    // Other formats
    'd LLL yyyy': 'dd LLL yyyy',
    'll': 'MMM dd, yyyy',
    'lll': 'MMM dd, yyyy HH:mm',
};

/**
 * Convert PHP date format to date-fns format
 * @param {string} phpFormat - PHP date format string
 * @returns {string} - date-fns format string
 */
export function phpToDateFns(phpFormat) {
    if (!phpFormat || typeof phpFormat !== 'string') {
        return 'yyyy-MM-dd'; // Default fallback
    }

    let result = '';
    let i = 0;
    let inLiteral = false;

    while (i < phpFormat.length) {
        const char = phpFormat[i];

        // Handle escaped characters
        if (char === '\\' && i + 1 < phpFormat.length) {
            result += "'" + phpFormat[i + 1] + "'";
            i += 2;
            continue;
        }

        // Handle literal strings (already in quotes)
        if (char === "'" || char === '"') {
            inLiteral = !inLiteral;
            result += "'";
            i++;
            continue;
        }

        // If in literal, keep as-is
        if (inLiteral) {
            result += char;
            i++;
            continue;
        }

        // Convert PHP format character to date-fns
        if (PHP_TO_DATE_FNS_MAP.hasOwnProperty(char)) {
            const converted = PHP_TO_DATE_FNS_MAP[char];
            result += converted || char;
        } else {
            // Not a format character, treat as literal
            // Wrap in single quotes for date-fns
            if (/[a-zA-Z]/.test(char)) {
                result += "'" + char + "'";
            } else {
                result += char;
            }
        }

        i++;
    }

    return result;
}

/**
 * Get date-fns format with caching for common formats
 * @param {string} phpFormat - PHP format string
 * @returns {string} - date-fns format string
 */
export function getDateFnsFormat(phpFormat) {
    // Check common formats first for performance
    if (COMMON_FORMATS[phpFormat]) {
        return COMMON_FORMATS[phpFormat];
    }

    // Convert on-the-fly for custom formats
    return phpToDateFns(phpFormat);
}

/**
 * Check if a string is already a date-fns format
 * (Simple heuristic: contains lowercase 'y' or 'M' patterns, or uppercase 'HH', 'EEE', etc.)
 * @param {string} format
 * @returns {boolean}
 */
export function isDateFnsFormat(format) {
    // Check for common date-fns patterns:
    // - yyyy, yy (year)
    // - MMMM, MMM, MM, M (month)
    // - dd, d (day)
    // - HH, H (24-hour)
    // - hh, h (12-hour)
    // - mm (minutes)
    // - ss (seconds)
    // - EEE, EEEE (day of week)
    // - a (am/pm)

    return /y{2,4}|M{2,4}|d{2}|H{2}|h{2}|m{2}|s{2}|E{3,4}/.test(format);
}
