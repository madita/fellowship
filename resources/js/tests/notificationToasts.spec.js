import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import eventBus from '@/components/common/eventBus.js';
import {
    useNotificationToastStore,
    TOAST_PREFERENCE_KEY,
    TOAST_TIMEOUT,
    MAX_TOASTS,
} from '@/store/notificationToastStore.js';
import {
    NOTIFICATION_EVENT,
    startNotificationChannel,
    stopNotificationChannel,
} from '@/composables/useNotificationChannel.js';

const mention = (overrides = {}) => ({
    id: 'n1',
    type: 'mention',
    mentioned_by: 'alice',
    subject: 'The Fellowship',
    url: '/wiki/the-fellowship',
    ...overrides,
});

describe('notification toasts', () => {
    let store;

    beforeEach(() => {
        localStorage.clear();
        setActivePinia(createPinia());
        vi.useFakeTimers();
        store = useNotificationToastStore();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('is on for anyone who never chose', () => {
        expect(store.enabled).toBe(true);
        expect(store.show(mention())).not.toBeNull();
        expect(store.toasts).toHaveLength(1);
    });

    it('shows nothing once it is switched off', () => {
        store.setEnabled(false);

        expect(store.show(mention())).toBeNull();
        expect(store.toasts).toHaveLength(0);
        expect(localStorage.getItem(TOAST_PREFERENCE_KEY)).toBe('false');
    });

    it('clears what is on screen when it is switched off', () => {
        store.show(mention());
        expect(store.toasts).toHaveLength(1);

        store.setEnabled(false);
        expect(store.toasts).toHaveLength(0);
    });

    it('remembers the answer for the next session', () => {
        store.setEnabled(false);

        setActivePinia(createPinia());
        expect(useNotificationToastStore().enabled).toBe(false);
    });

    it('lets the member record overrule the remembered answer', () => {
        expect(store.enabled).toBe(true);

        store.syncFromUser({ notification_toasts: false });
        expect(store.enabled).toBe(false);

        store.syncFromUser({ notification_toasts: true });
        expect(store.enabled).toBe(true);
    });

    it('leaves the answer alone for a record that does not carry one', () => {
        store.setEnabled(false);

        store.syncFromUser({ id: 1 });
        store.syncFromUser(null);

        expect(store.enabled).toBe(false);
    });

    it('goes away on its own', () => {
        store.show(mention());
        expect(store.toasts).toHaveLength(1);

        vi.advanceTimersByTime(TOAST_TIMEOUT - 1);
        expect(store.toasts).toHaveLength(1);

        vi.advanceTimersByTime(1);
        expect(store.toasts).toHaveLength(0);
    });

    it('can be dismissed before then', () => {
        const toast = store.show(mention());

        store.dismiss(toast.id);
        expect(store.toasts).toHaveLength(0);
    });

    it('shows the same notification once', () => {
        store.show(mention());

        expect(store.show(mention())).toBeNull();
        expect(store.toasts).toHaveLength(1);
    });

    it('keeps a burst down to the newest few', () => {
        for (let i = 0; i < MAX_TOASTS + 3; i++) {
            store.show(mention({ id: `n${i}` }));
        }

        expect(store.toasts).toHaveLength(MAX_TOASTS);
        expect(store.toasts[MAX_TOASTS - 1].data.id).toBe(`n${MAX_TOASTS + 2}`);
    });
});

describe('the notification channel', () => {
    let channels;

    beforeEach(() => {
        channels = {};
        window.Echo = {
            private: (name) => {
                channels[name] = channels[name] || { handlers: [] };

                return {
                    notification: (handler) => {
                        channels[name].handlers.push(handler);
                    },
                };
            },
            leave: vi.fn((name) => { delete channels[name]; }),
        };
    });

    afterEach(() => {
        stopNotificationChannel();
        delete window.Echo;
    });

    it('hands what arrives to everyone listening', () => {
        const seen = [];
        const handler = (payload) => seen.push(payload);
        eventBus.on(NOTIFICATION_EVENT, handler);

        startNotificationChannel(7);
        channels['users.7'].handlers.forEach(h => h(mention()));

        expect(seen).toHaveLength(1);
        expect(seen[0].type).toBe('mention');

        eventBus.off(NOTIFICATION_EVENT, handler);
    });

    it('subscribes once for the same member', () => {
        startNotificationChannel(7);
        startNotificationChannel(7);

        expect(channels['users.7'].handlers).toHaveLength(1);
        expect(window.Echo.leave).not.toHaveBeenCalled();
    });

    it('moves over when somebody else signs in', () => {
        startNotificationChannel(7);
        startNotificationChannel(8);

        expect(window.Echo.leave).toHaveBeenCalledWith('users.7');
        expect(channels['users.8'].handlers).toHaveLength(1);
    });

    it('does nothing for a guest', () => {
        startNotificationChannel(null);

        expect(Object.keys(channels)).toHaveLength(0);
    });
});
