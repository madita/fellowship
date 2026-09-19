import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import axios from 'axios';
import en from '@/translations/en.js';
import NotificationsPage from '@/pages/users/Notifications.vue';

vi.mock('axios');
vi.mock('@/composables/useDialog.js', () => ({
    useDialog: () => ({ requestError: vi.fn(), confirmDelete: vi.fn(() => Promise.resolve(true)) }),
}));

const i18n = createI18n({ legacy: false, locale: 'en', messages: { en }, missingWarn: false, fallbackWarn: false });

const stubs = {
    'page-header': { template: '<header><slot name="actions" /></header>' },
    'empty-state': { template: '<div class="empty">{{ title }}</div>', props: ['title'] },
    'loading-state': { template: '<div class="loading" />' },
    'v-container': { template: '<div><slot /></div>' },
    'v-card': { template: '<div><slot /></div>' },
    'v-list': { template: '<ul><slot /></ul>' },
    'v-list-item': { template: '<li class="row" @click="$emit(\'click\')"><slot name="prepend" /><slot /><slot name="append" /></li>' },
    'v-list-item-title': { template: '<div class="title"><slot /></div>' },
    'v-list-item-subtitle': { template: '<div class="subtitle"><slot /></div>' },
    'v-avatar': { template: '<span><slot /></span>' },
    'v-icon': { template: '<i :data-icon="icon" />', props: ['icon'] },
    'v-chip': { template: '<span class="chip"><slot /></span>' },
    'v-btn': { template: '<button :data-title="title" @click="$emit(\'click\')"><slot /></button>', props: ['title'] },
    'v-divider': { template: '<hr />' },
};

const notifications = [
    {
        id: 'n1',
        read_at: null,
        created_at: '2026-09-18T10:00:00Z',
        data: { type: 'mention', mentioned_by: 'alice', subject: 'The Fellowship', url: '/wiki/the-fellowship' },
    },
    {
        id: 'n2',
        read_at: '2026-09-18T09:00:00Z',
        created_at: '2026-09-18T08:00:00Z',
        data: { subject: 'Server maintenance', body: '<p>Tonight</p><script>alert(1)</script>', notifier: { username: 'admin' } },
    },
];

const push = vi.fn();

const render = async (data = notifications) => {
    axios.get.mockResolvedValue({ data });
    const wrapper = mount(NotificationsPage, {
        global: {
            plugins: [i18n],
            stubs,
            mocks: { $formatDate: () => 'date', $formatDistanceToNow: () => 'just now', $router: { push } },
        },
    });
    await flushPromises();
    return wrapper;
};

describe('Notifications page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('lists notifications of every kind, not only announcements', async () => {
        const wrapper = await render();
        const rows = wrapper.findAll('.row');

        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('alice mentioned you in "The Fellowship"');
        expect(rows[1].text()).toContain('Server maintenance');
    });

    it('marks an unread notification and opens what it is about', async () => {
        const wrapper = await render();
        axios.get.mockResolvedValue({ data: {} });

        await wrapper.findAll('.row')[0].trigger('click');
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/account/notification/markasread/n1');
        expect(push).toHaveBeenCalledWith('/wiki/the-fellowship');
    });

    it('shows the announcement text without its scripts', async () => {
        const wrapper = await render();

        expect(wrapper.html()).toContain('<p>Tonight</p>');
        expect(wrapper.html()).not.toContain('alert(1)');
    });

    it('says when there is nothing', async () => {
        const wrapper = await render([]);

        expect(wrapper.find('.empty').text()).toBe('No notifications');
    });
});
