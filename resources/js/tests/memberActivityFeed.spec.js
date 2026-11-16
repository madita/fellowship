import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import axios from 'axios';
import en from '@/translations/en.js';
import ActivityFeed from '@/components/common/ActivityFeed.vue';

/**
 * The feed as a member's profile page asks for it: one member's log.
 *
 * The same component renders the site-wide feed, a feature's feed and one
 * page's history, so the thing worth pinning down is that narrowing it to
 * a member reaches the backend as a filter — and that the rows then stop
 * repeating the name and face the profile header already shows.
 */
vi.mock('axios');

vi.mock('@/composables/useDialog.js', () => ({
    useDialog: () => ({ requestError: vi.fn(() => Promise.resolve()) }),
}));

const i18n = createI18n({ legacy: false, locale: 'en', messages: { en }, missingWarn: false, fallbackWarn: false });

const slot = (tag = 'div') => ({ template: `<${tag}><slot /></${tag}>` });

const stubs = {
    LoadingState: { template: '<div class="loading" />' },
    EmptyState: {
        props: ['title', 'text', 'icon', 'compact'],
        template: '<div class="empty">{{ title }}|{{ text }}</div>',
    },
    UserAvatar: { props: ['user'], template: '<span class="face">{{ user?.username }}</span>' },
    'v-list': slot(),
    'v-list-item': { props: ['to'], template: '<div class="row"><slot name="prepend" /><slot /></div>' },
    'v-list-item-title': slot(),
    'v-list-item-subtitle': slot(),
    'v-divider': { template: '<hr />' },
    'v-avatar': slot('span'),
    'v-icon': slot('i'),
    'v-btn': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
};

const row = (overrides = {}) => ({
    id: 1,
    log: 'forum',
    event: 'thread_created',
    description: 'thread created',
    title: 'Council of Elrond',
    url: '/forum/general/council-of-elrond',
    properties: {},
    causer: { id: 7, username: 'frodo' },
    created_at: '2026-10-01T10:00:00Z',
    ...overrides,
});

const render = async (props = {}, data = { data: [row()], meta: { current_page: 1, has_more: false } }) => {
    axios.get.mockResolvedValue({ data });

    const wrapper = mount(ActivityFeed, {
        props,
        global: {
            plugins: [i18n],
            stubs,
            mocks: { $formatDistanceToNow: () => 'two weeks ago' },
        },
    });
    await flushPromises();

    return wrapper;
};

describe('ActivityFeed, narrowed to one member', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('asks the backend for that member only', async () => {
        await render({ causerId: 7 });

        expect(axios.get).toHaveBeenCalledWith('/api/activity', expect.objectContaining({
            params: expect.objectContaining({ causer_id: 7 }),
        }));
    });

    it('leaves the name and face off the rows when the page already says whose log it is', async () => {
        const wrapper = await render({ causerId: 7, showCauser: false });

        expect(wrapper.find('.face').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('frodo');
        // The entry itself still reads as a sentence
        expect(wrapper.text()).toContain('Council of Elrond');
    });

    it('still names whoever did it in the site-wide feed', async () => {
        const wrapper = await render();

        expect(wrapper.find('.face').exists()).toBe(true);
        expect(wrapper.text()).toContain('frodo');
    });

    it('says whose log is empty rather than that nothing has happened anywhere', async () => {
        const wrapper = await render(
            { causerId: 7, emptyTitle: 'Nothing from frodo yet', emptyText: 'Have a look later.' },
            { data: [], meta: { current_page: 1, has_more: false } }
        );

        expect(wrapper.find('.empty').text()).toContain('Nothing from frodo yet');
        expect(wrapper.find('.empty').text()).not.toContain(en.activity.empty);
    });

    it('falls back to the site-wide wording when no feed-specific one is given', async () => {
        const wrapper = await render({}, { data: [], meta: { current_page: 1, has_more: false } });

        expect(wrapper.find('.empty').text()).toContain(en.activity.empty);
    });
});
