import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import permission from '@/router/middleware/permission.js';
import { middlewarePipeline } from '@/router/middlewarePipeline.js';
import { useUserStore } from '@/store/userStore.js';

// Hoisted: the store calls useApi() at module scope, which happens before
// a plain const in this file would have been initialised
const { api } = vi.hoisted(() => ({ api: { get: () => {} } }));

vi.mock('@/api/useAPI.js', () => ({
    useApi: () => api,
}));

/**
 * Opening a guarded page directly races app start-up: the router's first
 * navigation runs while the member's permissions are still being fetched.
 * Not knowing yet must not read as not being allowed, and must never blank
 * the page.
 */
describe('the permission middleware', () => {
    let store;

    beforeEach(() => {
        localStorage.clear();
        setActivePinia(createPinia());
        store = useUserStore();
        api.get = vi.fn();
        vi.spyOn(console, 'warn').mockImplementation(() => {});
        vi.spyOn(console, 'error').mockImplementation(() => {});
    });

    const to = (name = 'admin-settings-page') => ({ name });

    it('lets a member through who holds the permission', async () => {
        // The settings pages ask for manage-setting now, not for
        // manage-post standing in for half the admin area
        store.updateState({ permissions: ['manage-setting'] });

        const next = vi.fn();
        await permission({ to: to(), next });

        expect(next).toHaveBeenCalledWith();
    });

    it('turns away a member who does not', async () => {
        store.updateState({ permissions: ['manage-user'] });

        const next = vi.fn();
        await permission({ to: to(), next });

        expect(next).toHaveBeenCalledWith({ name: 'access-denied' });
    });

    it('waits for the permissions rather than crashing on their absence', async () => {
        // What a hard reload looks like: signed in, nothing loaded yet
        expect(store.permissions).toBeNull();

        api.get.mockResolvedValue({
            data: { user: { id: 1 }, roles: ['admin'], permissions: ['manage-setting'] },
        });

        const next = vi.fn();
        await permission({ to: to(), next });

        expect(api.get).toHaveBeenCalledWith('/user');
        expect(next).toHaveBeenCalledWith();
    });

    it('refuses rather than blanking the page when they cannot be loaded', async () => {
        api.get.mockRejectedValue(new Error('offline'));

        const next = vi.fn();

        await expect(permission({ to: to(), next })).resolves.not.toThrow();
        expect(next).toHaveBeenCalledWith({ name: 'access-denied' });
    });

    it('says which route is missing from the map', async () => {
        store.updateState({ permissions: ['manage-post'] });

        await expect(permission({ to: to('a-route-nobody-mapped'), next: vi.fn() }))
            .rejects.toThrow('No permission defined for the route');
    });
});

describe('the middleware pipeline', () => {
    it('hands back what a middleware that has to wait returns', async () => {
        const order = [];

        const first = ({ next }) => {
            order.push('first');

            return next();
        };

        const second = async ({ next }) => {
            await Promise.resolve();
            order.push('second');

            return next();
        };

        const middleware = [first, second];
        const context = { to: {}, from: {}, next: () => order.push('done') };

        await middleware[0]({
            ...context,
            next: middlewarePipeline(context, middleware, 1),
        });

        expect(order).toEqual(['first', 'second', 'done']);
    });

    it('propagates a failure instead of losing it', async () => {
        const boom = async () => {
            throw new Error('boom');
        };

        const context = { to: {}, from: {}, next: vi.fn() };
        const run = middlewarePipeline(context, [() => {}, boom], 1);

        await expect(run()).rejects.toThrow('boom');
    });

    it('goes straight to the destination when a middleware redirects', () => {
        const next = vi.fn();
        const context = { to: {}, from: {}, next };

        middlewarePipeline(context, [() => {}, () => {}], 1)({ name: 'access-denied' });

        expect(next).toHaveBeenCalledWith({ name: 'access-denied' });
    });
});

describe('loading who the member is', () => {
    let store;

    beforeEach(() => {
        localStorage.clear();
        setActivePinia(createPinia());
        store = useUserStore();
        api.get = vi.fn();
        vi.spyOn(console, 'error').mockImplementation(() => {});
    });

    it('asks once and keeps the answer', async () => {
        api.get.mockResolvedValue({
            data: { user: { id: 1, username: 'ada' }, roles: ['admin'], permissions: ['manage-post'] },
        });

        await store.storeInfo();

        // Twice was the bug: it emptied the store between the two
        expect(api.get).toHaveBeenCalledTimes(1);
        expect(store.permissions).toEqual(['manage-post']);
        expect(JSON.parse(localStorage.getItem('USER_INFO')).permissions).toEqual(['manage-post']);
    });

    it('is never empty part way through', async () => {
        let seen = null;

        api.get.mockImplementation(async () => {
            // Whatever looks at the store while the request is in flight —
            // the router's first navigation, for one — must not find it
            // wiped
            seen = store.permissions;

            return { data: { user: { id: 1 }, roles: [], permissions: ['manage-post'] } };
        });

        store.updateState({ user: { id: 1 }, roles: [], permissions: ['manage-post'] });
        await store.storeInfo();

        expect(seen).toEqual(['manage-post']);
    });

    it('keeps what it knew when the network is the problem', async () => {
        store.updateState({ user: { id: 1 }, roles: ['admin'], permissions: ['manage-post'] });

        api.get.mockRejectedValue(new Error('offline'));

        await expect(store.storeInfo()).rejects.toThrow('offline');

        // Signing someone out of a working session over a dropped request
        // is the wrong answer
        expect(store.permissions).toEqual(['manage-post']);
    });

    it('forgets them when the session is actually gone', async () => {
        store.updateState({ user: { id: 1 }, roles: ['admin'], permissions: ['manage-post'] });

        api.get.mockRejectedValue({ response: { status: 401 } });

        await expect(store.storeInfo()).rejects.toBeTruthy();

        expect(store.permissions).toBeNull();
        expect(localStorage.getItem('USER_INFO')).toBeNull();
    });
});
