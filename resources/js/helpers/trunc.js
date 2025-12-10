/**
 * Truncate a string to the specified length and add ellipsis if necessary.
 *
 * @param {string} str - The string to be truncated.
 * @param {number} limit - The maximum allowed length for the string.
 * @returns {string} - The truncated string with ellipsis if applicable.
 */
export default function truncate(str, limit) {
    if (typeof str !== 'string' || typeof limit !== 'number') {
        throw new TypeError('Invalid arguments: Expected a string and a number.');
    }

    return str.length > limit ? str.substring(0, limit) + '...' : str;
}
