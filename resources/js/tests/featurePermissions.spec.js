import { describe, it, expect } from 'vitest';
import { ADMIN_PERMISSIONS } from '@/router/middleware/permission.js';
import adminRoutes from '@/router/admin.routes.js';
import wikiRoutes from '@/router/wiki.routes.js';
import navigation from '@/configs/navigation.js';

// Every guarded page names one permission, and the router refuses the
// page outright when it names none - so a route added behind the guard
// without a line in the map blanks the screen rather than refusing it.
// That is what this file is here to catch.

const guarded = (routes) => routes.filter(route =>
    (route.meta?.middleware || []).some(middleware => middleware?.name === 'permission')
);

const names = (routes) => guarded(routes).map(route => route.name);

// A page names one permission, or a list of them
const accepted = (route) => {
    const wanted = ADMIN_PERMISSIONS[route];

    return Array.isArray(wanted) ? wanted : [wanted].filter(Boolean);
};

describe('the router guard', () => {
    it('has a permission for every page it guards', () => {
        const missing = [...names(adminRoutes), ...names(wikiRoutes)]
            .filter(name => !ADMIN_PERMISSIONS[name]);

        expect(missing).toEqual([]);
    });

    it('guards every admin page', () => {
        // An admin route with no guard is a page anybody can open. A
        // redirect is not a page: whatever it points at is guarded.
        const ungated = adminRoutes
            .filter(route => route.component && !route.redirect)
            .filter(route => !(route.meta?.middleware || []).some(m => m?.name === 'permission'))
            .map(route => route.name || route.path);

        expect(ungated).toEqual([]);
    });

    it('names a permission, not a role', () => {
        for (const [route, permission] of Object.entries(ADMIN_PERMISSIONS)) {
            // A page names one permission, or any of several: the
            // settings overview is the way in to a dozen features and
            // belongs to all of them
            for (const name of accepted(route)) {
                expect(name, route).toMatch(/^(manage|award)-/);
            }

            expect(accepted(route).length, route).toBeGreaterThan(0);
        }
    });

    it('gives each feature its own permission rather than one for all of them', () => {
        // The thing this set exists to undo: manage-page and manage-post
        // standing in for a dozen features between them
        expect(ADMIN_PERMISSIONS['admin-migrations']).toBe('manage-migration');
        expect(ADMIN_PERMISSIONS['admin-translations']).toBe('manage-translation');
        expect(ADMIN_PERMISSIONS['admin-forums']).toBe('manage-forum');
        expect(ADMIN_PERMISSIONS['admin-gallery']).toBe('manage-gallery');
        expect(ADMIN_PERMISSIONS['admin-tickets']).toBe('manage-ticket');

        // …and the two of them are left with the feature they are named
        // after
        expect(ADMIN_PERMISSIONS['admin-pages']).toBe('manage-page');
        expect(ADMIN_PERMISSIONS['admin-posts']).toBe('manage-post');
    });

    it('asks for the same permission on every page of a feature', () => {
        const wiki = names(wikiRoutes).map(name => ADMIN_PERMISSIONS[name]);

        expect([...new Set(wiki)]).toEqual(['manage-wiki']);
    });
});

describe('the administration menu', () => {
    const admin = navigation.menu.find(section => section.key === 'menu.admin');

    it('is still an admin section', () => {
        expect(admin.role).toBe('admin');
    });

    it('gates every entry on a permission, so a delegate sees only theirs', () => {
        const ungated = admin.items.filter(item => !item.permission).map(item => item.key);

        expect(ungated).toEqual([]);
    });

    it('gates the entries under an entry as well', () => {
        const events = admin.items.find(item => item.key === 'menu.adminEvents');

        expect(events.permission).toBe('manage-event');
        expect(events.items.every(item => item.permission === 'manage-event')).toBe(true);
    });

    it('points each entry at the permission its page asks for', () => {
        // A menu entry that offers a page the router then refuses is
        // worse than no entry
        const byLink = {
            '/admin/users': 'admin-users',
            '/admin/pages': 'admin-pages',
            '/admin/posts': 'admin-posts',
            '/admin/media': 'admin-media',
            '/admin/tickets': 'admin-tickets',
            '/admin/polls': 'admin-polls',
            '/admin/podcast': 'admin-podcast',
            '/admin/mailings': 'admin-mailings',
            '/admin/related-content': 'admin-relations',
            '/admin/announcements': 'admin-announcements',
            '/admin/settings': 'admin-settings',
        };

        for (const [link, route] of Object.entries(byLink)) {
            const item = admin.items.find(entry => entry.link === link);

            expect(item, link).toBeTruthy();
            expect(accepted(route), link).toContain(item.permission);
        }
    });

    it('opens the settings overview to everyone it is the way in for', () => {
        // The translations, the menus, the forum categories and the
        // migration tool are only reachable through it, so gating it on
        // manage-setting alone would shut them out of their own screens
        const settings = accepted('admin-settings');

        expect(settings).toContain('manage-setting');
        expect(settings).toContain('manage-translation');
        expect(settings).toContain('manage-migration');
        expect(settings).toContain('manage-forum');
    });
});
