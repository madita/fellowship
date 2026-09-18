import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import TicketHistory from '@/components/ticket/TicketHistory.vue';
import en from '@/translations/en.js';
import { createI18n } from 'vue-i18n';

vi.mock('axios');
// Dates are not what these tests are about
vi.mock('@/plugins/formatDate.js', () => ({
    formatDate: (value) => String(value),
    formatDateDistanceToNow: () => 'just now',
}));

const i18n = createI18n({ legacy: false, locale: 'en', messages: { en }, missingWarn: false, fallbackWarn: false });

const stubs = {
    'v-timeline': { template: '<ol class="timeline"><slot /></ol>' },
    'v-timeline-item': { template: '<li class="timeline-item" :data-icon="icon"><slot /></li>', props: ['icon', 'dotColor'] },
    'v-chip': { template: '<span class="chip"><slot /></span>' },
    'v-icon': { template: '<i />' },
    'v-btn': { template: '<button class="btn" @click="$emit(\'click\')"><slot /></button>' },
    'v-dialog': { template: '<div class="dialog" v-if="modelValue"><slot /></div>', props: ['modelValue'] },
    'v-card': { template: '<div><slot /></div>' },
    'v-card-title': { template: '<div><slot /></div>' },
    'v-card-text': { template: '<div><slot /></div>' },
    'v-divider': { template: '<hr />' },
    'v-spacer': { template: '<span />' },
    'diff-view': { template: '<div class="diff" :data-old="oldValue" :data-new="newValue" />', props: ['oldValue', 'newValue'] },
    'empty-state': { template: '<div class="empty">{{ title }}<slot name="actions" /></div>', props: ['title'] },
    'loading-state': { template: '<div class="loading" />' },
};

const history = [
    {
        id: 3,
        action: 'updated',
        created_at: '2026-09-17T10:00:00Z',
        user: { id: 1, username: 'boss' },
        changes: [
            { field: 'status', old: 'open', new: 'in_progress', old_display: null, new_display: null },
            { field: 'assigned_to_user_id', old: '', new: '1', old_display: null, new_display: 'boss' },
        ],
    },
    {
        id: 2,
        action: 'updated',
        created_at: '2026-09-17T09:00:00Z',
        user: { id: 1, username: 'boss' },
        changes: [{ field: 'description', old: '<p>First</p>', new: '<p>Second</p>' }],
    },
    {
        id: 'comment-4',
        action: 'commented',
        created_at: '2026-09-17T08:00:00Z',
        user: { id: 2, username: 'alice' },
        is_internal: true,
        excerpt: 'Checked the logs',
        changes: [],
    },
    { id: 'created', action: 'created', created_at: '2026-09-16T09:00:00Z', user: null, changes: [] },
];

const render = async () => {
    const wrapper = mount(TicketHistory, { props: { ticketId: 7 }, global: { plugins: [i18n], stubs } });
    await flushPromises();
    return wrapper;
};

describe('TicketHistory', () => {
    beforeEach(() => {
        axios.get.mockResolvedValue({ data: { data: history } });
    });

    it('loads the history of the ticket', async () => {
        await render();
        expect(axios.get).toHaveBeenCalledWith('/api/tickets/7/history');
    });

    it('shows who changed the status and the assignee', async () => {
        const wrapper = await render();
        const first = wrapper.findAll('.timeline-item')[0];

        expect(first.text()).toContain('boss made changes');
        expect(first.findAll('.chip').map(chip => chip.text())).toEqual(['Open', 'In Progress']);
        expect(first.text()).toContain('Unassigned');
    });

    it('opens the description changes as a diff', async () => {
        const wrapper = await render();
        await wrapper.findAll('.timeline-item')[1].find('.btn').trigger('click');

        const diff = wrapper.find('.diff');
        expect(diff.attributes('data-old')).toBe('<p>First</p>');
        expect(diff.attributes('data-new')).toBe('<p>Second</p>');
    });

    it('shows comments as activity, internal ones marked', async () => {
        const wrapper = await render();
        const comment = wrapper.findAll('.timeline-item')[2];

        expect(comment.text()).toContain('alice commented');
        expect(comment.text()).toContain('Checked the logs');
        expect(comment.find('.chip').text()).toBe('Internal');
    });

    it('opens the comments tab from a comment entry', async () => {
        const wrapper = await render();
        await wrapper.findAll('.timeline-item')[2].find('.comment-excerpt').trigger('click');

        expect(wrapper.emitted('open-comments')).toHaveLength(1);
    });

    it('names changes without a member as the system', async () => {
        const wrapper = await render();
        expect(wrapper.findAll('.timeline-item')[3].text()).toContain('System created the ticket');
    });
});
