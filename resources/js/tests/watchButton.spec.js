import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import axios from 'axios';
import en from '@/translations/en.js';
import WatchButton from '@/components/common/WatchButton.vue';

vi.mock('axios');

const requestError = vi.fn(() => Promise.resolve());
vi.mock('@/composables/useDialog.js', () => ({
    useDialog: () => ({ requestError: (...args) => requestError(...args) }),
}));

const i18n = createI18n({ legacy: false, locale: 'en', messages: { en }, missingWarn: false, fallbackWarn: false });

const stubs = {
    'v-btn': {
        props: ['prependIcon', 'color', 'variant', 'loading', 'title'],
        template: '<button :data-icon="prependIcon" :data-color="color" :data-loading="String(loading)" @click="$emit(\'click\')"><slot /></button>',
    },
};

const render = (props = {}) => mount(WatchButton, {
    props: { kind: 'ticket', id: 7, ...props },
    global: { plugins: [i18n], stubs },
});

describe('WatchButton', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('offers to watch something that is not being watched', () => {
        const w = render({ modelValue: false });

        expect(w.text()).toContain(en.watch.watch);
        expect(w.find('button').attributes('data-icon')).toBe('mdi-eye-outline');
    });

    it('shows that something is already being watched', () => {
        const w = render({ modelValue: true });

        expect(w.text()).toContain(en.watch.watching);
        expect(w.find('button').attributes('data-icon')).toBe('mdi-eye-check');
    });

    // The kind is a registry slug; the API refuses class names
    it('posts to the shared endpoint for its kind', async () => {
        axios.post.mockResolvedValue({ data: { watching: true, watchers_count: 3 } });

        const w = render({ kind: 'forum-thread', id: 42 });
        await w.find('button').trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/watch/forum-thread/42');
    });

    it('hands the new state back to the page', async () => {
        axios.post.mockResolvedValue({ data: { watching: true, watchers_count: 3 } });

        const w = render({ modelValue: false });
        await w.find('button').trigger('click');
        await flushPromises();

        expect(w.emitted('update:modelValue')).toEqual([[true]]);
        expect(w.emitted('update:watchersCount')).toEqual([[3]]);
    });

    it('shows the count only when asked to', async () => {
        const plain = render({ watchersCount: 5 });
        expect(plain.text()).not.toContain('5');

        const counted = render({ watchersCount: 5, showCount: true });
        expect(counted.text()).toContain('(5)');
    });

    it('says nothing about a count of nobody', () => {
        const w = render({ watchersCount: 0, showCount: true });

        expect(w.text()).not.toContain('(0)');
    });

    it('reports a failure and leaves the state alone', async () => {
        axios.post.mockRejectedValue(new Error('nope'));

        const w = render({ modelValue: false });
        await w.find('button').trigger('click');
        await flushPromises();

        expect(requestError).toHaveBeenCalled();
        expect(w.emitted('update:modelValue')).toBeUndefined();
    });

    // A second click before the first answers would post twice
    it('ignores a click while a request is in flight', async () => {
        let release;
        axios.post.mockReturnValue(new Promise(resolve => { release = resolve; }));

        const w = render();
        await w.find('button').trigger('click');
        await w.find('button').trigger('click');

        expect(axios.post).toHaveBeenCalledTimes(1);

        release({ data: { watching: true, watchers_count: 1 } });
        await flushPromises();
    });
});
