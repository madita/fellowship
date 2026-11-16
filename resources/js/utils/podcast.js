/**
 * Formatting shared by the player, the episode lists and the admin
 * screen — the same episode has to read the same everywhere.
 */
import permission from '@/utils/permission.js';

/**
 * Whether the signed-in member may run a show: hold manage-podcast, or
 * be an admin, which carries everything.
 *
 * The API decides for real — see the manage routes — but the pages have
 * to know whether to offer an "Edit" button at all, and every page that
 * offers one has to agree on the answer.
 */
export const canManagePodcasts = () =>
    permission.hasRole('admin') || permission.can('manage-podcast');

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

/**
 * Why an audio upload failed, in words a person can act on.
 *
 * A 422 carries the reason in `errors.audio` — "must be a file of type:
 * mp3, …" — while `message` is only ever "Validation failed", so showing
 * the message alone tells the uploader nothing. A 413 never reaches the
 * application: the web server refuses the body, so there is no JSON to
 * read and the limit has to be named instead. Both upload screens read
 * this, so neither can go back to reporting a blank wall.
 *
 * `t` is the caller's translator, since this lives outside a component.
 */
export const uploadFailureMessage = (error, t) => {
    const response = error?.response;

    if ( ! response) {
        return t('podcast.manage.uploadNoResponse');
    }

    if (response.status === 413) {
        return t('podcast.manage.audioTooLargeForServer');
    }

    const fieldErrors = response.data?.errors;

    if (fieldErrors && typeof fieldErrors === 'object') {
        const messages = Object.values(fieldErrors).flat().filter(Boolean);

        if (messages.length) {
            return messages.join(' ');
        }
    }

    return response.data?.message || error?.message || '';
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
