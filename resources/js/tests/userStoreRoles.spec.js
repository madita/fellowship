import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useUserStore } from '@/store/userStore.js';

describe('userStore role and permission checks', () => {
    let store;

    beforeEach(() => {
        // Node's own global localStorage can shadow jsdom's; use a plain in-memory one
        const data = new Map();
        vi.stubGlobal('localStorage', {
            getItem: key => (data.has(key) ? data.get(key) : null),
            setItem: (key, value) => data.set(key, String(value)),
            removeItem: key => data.delete(key),
            clear: () => data.clear(),
        });
        setActivePinia(createPinia());
        store = useUserStore();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('matches plain role and permission names as sent by /api/user', () => {
        store.roles = ['admin', 'editor'];
        store.permissions = ['manage-page'];

        expect(store.hasRole('admin')).toBe(true);
        expect(store.hasRole('moderator')).toBe(false);
        expect(store.hasPermission('manage-page')).toBe(true);
        expect(store.hasPermission('manage-post')).toBe(false);
    });

    it('still matches { name } objects from older cached state', () => {
        store.roles = [{ name: 'admin' }];
        store.permissions = [{ name: 'manage-post' }];

        expect(store.hasRole('admin')).toBe(true);
        expect(store.hasPermission('manage-post')).toBe(true);
    });

    it('is false when nothing is loaded', () => {
        store.roles = null;
        store.permissions = null;

        expect(store.hasRole('admin')).toBe(false);
        expect(store.hasPermission('manage-page')).toBe(false);
    });
});
