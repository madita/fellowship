import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import axios from 'axios';
import en from '@/translations/en.js';
import CommentThread from '@/components/common/CommentThread.vue';

vi.mock('axios');

vi.mock('@/composables/useDialog.js', () => ({
    useDialog: () => ({
        requestError: vi.fn(),
        confirmDelete: vi.fn(() => Promise.resolve(true)),
    }),
}));

const i18n = createI18n({ legacy: false, locale: 'en', messages: { en }, missingWarn: false, fallbackWarn: false });

const stubs = {
    'loading-state': { template: '<div class="loading" />' },
    'empty-state': { template: '<div class="empty" />', props: ['icon', 'title', 'text'] },
    'simple-editor': { template: '<textarea />', props: ['modelValue', 'limit', 'placeholder'] },
    'user-avatar': { template: '<span />', props: ['user', 'size', 'linkToProfile'] },
    'v-divider': { template: '<hr />' },
    'v-icon': { template: '<i><slot /></i>' },
    'v-chip': {
        template: '<span class="chip"><slot /></span>',
        props: ['color', 'variant', 'size', 'prependIcon'],
    },
    'v-btn': {
        template: '<button :data-loading="loading" @click="$emit(\'click\')"><slot /></button>',
        props: ['color', 'variant', 'size', 'density', 'prependIcon', 'loading', 'disabled', 'block'],
    },
};

const comment = (id, overrides = {}) => ({
    id,
    user_id: 50,
    user: { id: 50, username: 'Sam' },
    content: `<p>Answer ${id}</p>`,
    time_ago: 'just now',
    is_helpful: false,
    parent_id: null,
    replies: [],
    ...overrides,
});

const render = async ({ topics = [comment(1)], canMark = true, currentUser = { id: 7 } } = {}) => {
    axios.get.mockResolvedValue({
        data: { data: topics, count: topics.length, can_mark_helpful: canMark },
    });

    const wrapper = mount(CommentThread, {
        props: { kind: 'status', id: 9, currentUser },
        global: { plugins: [i18n], stubs },
    });

    await flushPromises();

    return wrapper;
};

const buttonSaying = (wrapper, text) => wrapper.findAll('button').find(b => b.text().includes(text));

/**
 * Marking the comment that answered a discussion. The forum has had this
 * for its replies all along; the comments on everything else did not, and
 * the one trait behind both is what these cover from the client's side.
 */
describe('marking a comment as the helpful one', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.post = vi.fn();
    });

    it('offers nothing to a reader who does not get to decide', async () => {
        const wrapper = await render({ canMark: false });

        expect(buttonSaying(wrapper, 'Mark as helpful')).toBeUndefined();
    });

    it('offers it to the reader the server says may decide', async () => {
        const wrapper = await render();

        expect(buttonSaying(wrapper, 'Mark as helpful')).toBeDefined();
    });

    it('marks the comment and says so', async () => {
        axios.post.mockResolvedValue({ data: { helpful: true, comment: comment(1, { is_helpful: true }) } });

        const wrapper = await render();

        await buttonSaying(wrapper, 'Mark as helpful').trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/comments/1/helpful');
        expect(wrapper.find('.chip').text()).toContain('Helpful');
        // The same button now takes it away, which is the whole of the fix
        expect(buttonSaying(wrapper, 'Unmark helpful')).toBeDefined();
    });

    it('takes the mark away again', async () => {
        axios.post.mockResolvedValue({ data: { helpful: false, comment: comment(1) } });

        const wrapper = await render({ topics: [comment(1, { is_helpful: true })] });
        expect(wrapper.find('.chip').exists()).toBe(true);

        await buttonSaying(wrapper, 'Unmark helpful').trigger('click');
        await flushPromises();

        expect(wrapper.find('.chip').exists()).toBe(false);
    });

    it('moves the mark rather than adding a second one', async () => {
        axios.post.mockResolvedValue({ data: { helpful: true, comment: comment(2) } });

        const wrapper = await render({
            topics: [comment(1, { is_helpful: true }), comment(2)],
        });

        // The second topic's button, the first one already being marked
        const buttons = wrapper.findAll('button').filter(b => b.text().includes('Mark as helpful'));
        await buttons[0].trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/comments/2/helpful');
        expect(wrapper.findAll('.chip')).toHaveLength(1);
    });

    /**
     * The answer is as likely to be under a comment as beside it, so a
     * reply can hold the mark — and has to lose it when one moves.
     */
    it('reaches the replies too', async () => {
        axios.post.mockResolvedValue({ data: { helpful: true, comment: comment(1) } });

        const wrapper = await render({
            topics: [comment(1, { replies: [comment(2, { parent_id: 1, is_helpful: true })] })],
        });

        expect(wrapper.findAll('.chip')).toHaveLength(1);

        await buttonSaying(wrapper, 'Mark as helpful').trigger('click');
        await flushPromises();

        const marked = wrapper.vm.topics.flatMap(t => [t, ...(t.replies ?? [])]).filter(c => c.is_helpful);

        expect(marked.map(c => c.id)).toEqual([1]);
    });

    it('asks for a discussion only once and remembers who may mark', async () => {
        const wrapper = await render({ canMark: false });

        expect(axios.get).toHaveBeenCalledWith('/api/comments/status/9');
        expect(wrapper.vm.canMarkHelpful).toBe(false);
    });
});
