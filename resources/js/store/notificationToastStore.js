import { defineStore } from 'pinia';

/**
 * The short-lived cards that appear when a notification arrives over the
 * socket, so something happening elsewhere on the site is visible without
 * watching the number on the bell.
 *
 * This is for notifications only. Feedback on something the member just
 * did stays modal — see `store/dialogStore.js`.
 *
 * The switch lives on the member (`users.notification_toasts`, saved
 * through the settings drawer) and is mirrored into localStorage so the
 * first notification of a session is already handled correctly, before
 * `/api/user` has answered.
 */

export const TOAST_PREFERENCE_KEY = 'notification_toasts';

// How long a toast stays, and how many are allowed to stack. A burst —
// somebody working through a thread, a batch of tickets — would otherwise
// cover the page it is reporting on.
export const TOAST_TIMEOUT = 7000;
export const MAX_TOASTS = 4;

let sequence = 0;

/** The stored answer, defaulting to on for anyone who never chose. */
function storedPreference() {
    try {
        return localStorage.getItem(TOAST_PREFERENCE_KEY) !== 'false';
    } catch (e) {
        // Private windows and blocked site data
        return true;
    }
}

function storePreference(enabled) {
    try {
        localStorage.setItem(TOAST_PREFERENCE_KEY, enabled ? 'true' : 'false');
    } catch (e) {
        // Nothing to do: the member's record still carries the answer
    }
}

export const useNotificationToastStore = defineStore('notificationToast', {
    state: () => ({
        enabled: storedPreference(),
        toasts: [],
    }),

    actions: {
        /**
         * Turn toasts on or off. Takes effect at once; saving it to the
         * member's record is the settings drawer's job.
         */
        setEnabled(enabled) {
            this.enabled = enabled !== false;
            storePreference(this.enabled);

            // Nobody wants the ones already on screen to outlive the switch
            if (!this.enabled) this.clear();
        },

        /**
         * Adopt what the member's record says, once it has loaded. A
         * record that predates the column (or a guest) leaves the stored
         * answer alone.
         */
        syncFromUser(user) {
            if (!user || user.notification_toasts === undefined || user.notification_toasts === null) return;

            this.setEnabled(user.notification_toasts !== false && user.notification_toasts !== 0);
        },

        /**
         * Show one notification. `data` is the notification payload — the
         * same shape the bell and the notifications page read, so
         * `utils/notifications.js` describes it.
         *
         * Returns the toast, or null when toasts are off.
         */
        show(data) {
            if (!this.enabled || !data) return null;

            const toast = {
                // The notification's own id where there is one, so the
                // same notification arriving twice is only shown once
                key: data.id ?? `local-${++sequence}`,
                id: ++sequence,
                data,
            };

            if (data.id && this.toasts.some(t => t.key === data.id)) return null;

            this.toasts.push(toast);

            // Oldest first out, so the newest is always readable
            if (this.toasts.length > MAX_TOASTS) {
                this.toasts.splice(0, this.toasts.length - MAX_TOASTS);
            }

            setTimeout(() => this.dismiss(toast.id), TOAST_TIMEOUT);

            return toast;
        },

        dismiss(id) {
            const index = this.toasts.findIndex(t => t.id === id);
            if (index !== -1) this.toasts.splice(index, 1);
        },

        clear() {
            this.toasts = [];
        },
    },
});
