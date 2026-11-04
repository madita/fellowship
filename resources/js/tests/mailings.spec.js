import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import axios from 'axios';
import { useMailingStore } from '@/store/mailingStore.js';
import { useUserStore } from '@/store/userStore.js';
import permission from '@/utils/permission.js';
import navigation from '@/configs/navigation.js';
import en from '@/translations/en.js';
import de from '@/translations/de.js';

vi.mock('axios');

const mailing = (overrides = {}) => ({
    id: 1,
    subject: 'What has been happening',
    status: 'draft',
    mail_category_id: 2,
    audience: { type: 'all' },
    recipient_count: 0,
    sent_count: 0,
    progress: 0,
    ...overrides,
});

describe('the mailing store', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        store = useMailingStore();
    });

    it('offers only the kinds of mail somebody may be written to about', () => {
        store.categories = [
            { id: 1, key: 'account', name: 'Account & security', is_mandatory: true },
            { id: 2, key: 'newsletter', name: 'Newsletter', is_mandatory: false },
        ];

        // A mandatory category is the mail that gets a member back into
        // their account — not a thing to send a newsletter under
        expect(store.sendableCategories.map(category => category.key)).toEqual(['newsletter']);
    });

    it('leaves a template that is switched off out of the ones to start from', () => {
        store.templates = [
            { id: 1, key: 'newsletter', is_enabled: true, mail_category_id: 2 },
            { id: 2, key: 'welcome', is_enabled: false, mail_category_id: 1 },
        ];

        expect(store.usableTemplates.map(template => template.key)).toEqual(['newsletter']);
        expect(store.templatesByCategory(2).map(template => template.key)).toEqual(['newsletter']);
    });

    it('reports both numbers of a head count, so the gap between them shows', async () => {
        axios.post.mockResolvedValue({ data: { total: 412, will_receive: 380, opted_out: 32 } });

        await store.countAudience(2, { type: 'all' });

        expect(axios.post).toHaveBeenCalledWith('/api/admin/mailings/estimate', {
            mail_category_id: 2,
            audience: { type: 'all' },
        });
        expect(store.estimate).toEqual({ total: 412, will_receive: 380, opted_out: 32 });
    });

    it('stops counting as busy once a head count fails', async () => {
        axios.post.mockRejectedValue(new Error('network'));

        await expect(store.countAudience(2, { type: 'all' })).rejects.toThrow();

        // Rethrown rather than swallowed, and the flag released either way
        expect(store.estimating).toBe(false);
    });

    it('updates the row in place when a send changes its status', async () => {
        store.mailings = [mailing({ category: { id: 2, name: 'Newsletter' } })];

        axios.post.mockResolvedValue({
            data: { message: 'On its way', mailing: mailing({ status: 'sent', sent_count: 380, recipient_count: 412 }) },
        });

        await store.send(1);

        const row = store.mailings[0];

        expect(row.status).toBe('sent');
        expect(row.sent_count).toBe(380);
        // Merged rather than replaced: the list carries the category that
        // a send response leaves out
        expect(row.category.name).toBe('Newsletter');
    });

    it('sends a schedule only when one was given', async () => {
        axios.post.mockResolvedValue({ data: { message: 'ok', mailing: mailing() } });

        await store.send(1);
        expect(axios.post).toHaveBeenLastCalledWith('/api/admin/mailings/1/send', {});

        await store.send(1, '2026-12-24T18:00:00.000Z');
        expect(axios.post).toHaveBeenLastCalledWith('/api/admin/mailings/1/send', {
            scheduled_for: '2026-12-24T18:00:00.000Z',
        });
    });

    it('takes a deleted mailing out of the list', async () => {
        store.mailings = [mailing({ id: 1 }), mailing({ id: 2 })];
        axios.delete.mockResolvedValue({ data: { message: 'gone' } });

        await store.deleteMailing(1);

        expect(store.mailings.map(row => row.id)).toEqual([2]);
    });
});

describe('who can see the mailing screens', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    const administration = navigation.menu.find(section => section.role === 'admin');
    const mailings = administration.items.find(item => item.link === '/admin/mailings');

    it('is in the administration section, gated by its own permission', () => {
        expect(mailings).toBeDefined();
        expect(mailings.permission).toBe('manage-mailings');
    });

    it('is hidden from a member who holds neither the role nor the permission', () => {
        const users = useUserStore();
        users.roles = ['user'];
        users.permissions = [];

        expect(permission.sectionVisible(administration)).toBe(false);
        expect(permission.applyPermissions(mailings)).toBe(false);
    });

    it('opens the section for somebody given the newsletter to write', () => {
        const users = useUserStore();
        users.roles = ['user'];
        users.permissions = ['manage-mailings'];

        // The whole of Administration is admin-only, but one item in it
        // names a permission of its own — so its holder sees the section,
        // and in it only that item
        expect(permission.sectionVisible(administration)).toBe(true);
        expect(permission.applyPermissions(mailings)).toBe(true);
        expect(permission.applyPermissions({ link: '/admin/users' })).toBe(true);
    });

    it('does not let an item without a gate of its own open the section', () => {
        const users = useUserStore();
        users.roles = ['user'];
        users.permissions = [];

        // Every other Administration item has no permission of its own,
        // and none of them may open an admin-only section. Carrying the
        // section's own gate across is the point: without it there is
        // nothing to get past.
        const ungated = administration.items.filter(item => !item.permission);

        expect(ungated.length).toBeGreaterThan(0);
        expect(
            ungated.some(item => permission.sectionVisible({ role: 'admin', items: [item] }))
        ).toBe(false);
    });
});

describe('the wording of the mailing screens', () => {
    const keys = (object, prefix = '') => Object.entries(object).flatMap(([key, value]) => (
        value && typeof value === 'object'
            ? keys(value, `${prefix}${key}.`)
            : [`${prefix}${key}`]
    ));

    it('says the same things in both languages', () => {
        for (const block of ['mailings', 'mailPreferences', 'unsubscribe']) {
            expect(en[block], `en.${block}`).toBeDefined();
            expect(de[block], `de.${block}`).toBeDefined();
            expect(keys(de[block]).sort()).toEqual(keys(en[block]).sort());
        }
    });

    it('has a word for every state a mailing can be in', () => {
        // The list page reads these by status, so a missing one shows as
        // a raw key
        for (const status of ['draft', 'scheduled', 'sending', 'sent', 'failed', 'cancelled']) {
            expect(en.mailings.status[status]).toBeTruthy();
            expect(de.mailings.status[status]).toBeTruthy();
        }
    });

    it('has a word for every reason a member was skipped', () => {
        for (const reason of ['opted_out', 'unsubscribed_all', 'cancelled']) {
            expect(en.mailings.recipients.reasons[reason]).toBeTruthy();
            expect(de.mailings.recipients.reasons[reason]).toBeTruthy();
        }
    });
});
