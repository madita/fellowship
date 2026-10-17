/**
 * Formatting shared by the player, the episode lists and the admin
 * screen — the same episode has to read the same everywhere.
 */

/**
 * Seconds as a clock: 4:05, and 1:04:05 once there is an hour to show.
 * Anything unusable (null, NaN, negative) reads as 0:00 rather than
 * putting "NaN:NaN" in front of a listener.
 */
export const formatDuration = (seconds) => {
    const total = Math.floor(Number(seconds));

    if ( ! Number.isFinite(total) || total < 0) {
        return '0:00';
    }

    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const secs = total % 60;
    const pad = (value) => String(value).padStart(2, '0');

    return hours > 0
        ? `${hours}:${pad(minutes)}:${pad(secs)}`
        : `${minutes}:${pad(secs)}`;
};

/**
 * The other direction: "1:04:05", "64:05" or plain seconds from the
 * admin form back into a number, so an editor can type what they read.
 */
export const parseDuration = (value) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parts = String(value).trim().split(':');

    if (parts.some((part) => part !== '' && ! /^\d+$/.test(part.trim()))) {
        return null;
    }

    const numbers = parts.map((part) => parseInt(part, 10) || 0);

    return numbers.reduce((total, part) => total * 60 + part, 0);
};

export const formatFileSize = (bytes) => {
    const size = Number(bytes);

    if ( ! Number.isFinite(size) || size <= 0) {
        return '–';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    const exponent = Math.min(Math.floor(Math.log(size) / Math.log(1024)), units.length - 1);

    return `${(size / 1024 ** exponent).toFixed(exponent === 0 ? 0 : 1)} ${units[exponent]}`;
};
