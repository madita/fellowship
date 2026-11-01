import { onUnmounted } from 'vue';
import eventBus from '@/components/common/eventBus.js';

/**
 * The one subscription to `users.{id}`, the private channel Laravel
 * broadcasts a member's notifications on.
 *
 * It is one channel with several interested parties — the toolbar bell,
 * the sandbox widget, the toasts — so subscribing per component meant the
 * first of them to unmount called `Echo.leave()` and silenced the rest.
 * Here it is opened once for the session and handed on over the event bus
 * instead; components listen with `onNotification()` and never touch Echo.
 *
 * The payload is the notification's own data, carrying the same `type` the
 * stored notification has (see the `broadcastType()` on the notification
 * classes), so `utils/notifications.js` can describe it directly.
 */

export const NOTIFICATION_EVENT = 'notification.received';

let subscribed = null;

/**
 * Open the channel for this member. Safe to call again: a second call for
 * the same member does nothing, and one for a different member (after a
 * sign-out and back in) moves the subscription over.
 */
export function startNotificationChannel(userId) {
    if ( ! window.Echo || ! userId) return;

    const channel = `users.${userId}`;
    if (subscribed === channel) return;

    stopNotificationChannel();

    try {
        window.Echo.private(channel).notification((payload) => {
            eventBus.emit(NOTIFICATION_EVENT, payload);
        });
        subscribed = channel;
    } catch (e) {
        console.warn('Failed to subscribe to the notification channel:', e);
    }
}

/** Close it — on sign-out, or when the member changes. */
export function stopNotificationChannel() {
    if ( ! subscribed) return;

    try {
        if (window.Echo) window.Echo.leave(subscribed);
    } catch (e) {
        // no-op: nothing useful to do if the socket is already gone
    }
    subscribed = null;
}

/**
 * Be told when a notification arrives, for as long as the calling
 * component is mounted.
 */
export function onNotification(handler) {
    eventBus.on(NOTIFICATION_EVENT, handler);

    onUnmounted(() => {
        eventBus.off(NOTIFICATION_EVENT, handler);
    });
}
