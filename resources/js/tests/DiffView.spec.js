import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import DiffView from '@/components/common/DiffView.vue';

const stubs = {
    'v-icon': { template: '<i><slot /></i>' },
    'v-spacer': { template: '<span />' },
    'v-btn': { template: '<button><slot /></button>' },
    'v-btn-toggle': { template: '<div><slot /></div>' },
};

const render = (props) => mount(DiffView, { props, global: { stubs } });

describe('DiffView.vue', () => {
    it('shows both sides of an edited line, word marked', () => {
        const wrapper = render({
            oldValue: '<p>The quick brown fox</p>',
            newValue: '<p>The slow brown fox</p>',
        });

        expect(wrapper.text()).toContain('quick');
        expect(wrapper.text()).toContain('slow');
        expect(wrapper.find('.diff-word--removed').text()).toBe('quick');
        expect(wrapper.find('.diff-word--added').text()).toBe('slow');
    });

    it('counts what changed', () => {
        const wrapper = render({
            oldValue: '<p>One</p><p>Two</p>',
            newValue: '<p>One</p><p>Two</p><p>Three</p>',
        });

        expect(wrapper.find('.diff-stat--added').text()).toBe('+1');
        expect(wrapper.find('.diff-stat--removed').exists()).toBe(false);
    });

    it('says so when the two versions are the same', () => {
        const wrapper = render({
            oldValue: '<p>Same</p>',
            newValue: '<p><em>Same</em></p>',
            emptyText: 'Nothing changed',
        });

        expect(wrapper.find('.diff-empty').text()).toBe('Nothing changed');
        expect(wrapper.find('.diff-body').exists()).toBe(false);
    });

    it('hides long untouched stretches behind a gap that opens on click', async () => {
        const lines = (extra = '') => Array.from(
            { length: 20 },
            (_, index) => `<p>line ${index}${index === 19 ? extra : ''}</p>`,
        ).join('');

        const wrapper = render({ oldValue: lines(), newValue: lines(' edited') });
        const gap = wrapper.find('.diff-row--gap');

        expect(gap.exists()).toBe(true);
        expect(wrapper.text()).not.toContain('line 0');

        await gap.trigger('click');

        expect(wrapper.text()).toContain('line 0');
        expect(wrapper.find('.diff-row--gap').exists()).toBe(false);
    });

    it('puts the two versions in their own columns in split mode', () => {
        const wrapper = render({
            oldValue: '<p>Before</p>',
            newValue: '<p>After</p>',
            mode: 'split',
        });

        // jsdom reports a window width below the split threshold, so the
        // component falls back to one column; the mode itself is still split.
        expect(wrapper.vm.activeMode).toBe('split');

        wrapper.vm.narrow = false;

        return wrapper.vm.$nextTick().then(() => {
            expect(wrapper.findAll('.diff-side')).toHaveLength(2);
            expect(wrapper.find('.diff-side--removed').text()).toContain('Before');
            expect(wrapper.find('.diff-side--added').text()).toContain('After');
        });
    });

    it('compares plain text without flattening it as html', () => {
        const wrapper = render({
            oldValue: 'Old <title>',
            newValue: 'New <title>',
            html: false,
        });

        expect(wrapper.text()).toContain('Old <title>');
        expect(wrapper.text()).toContain('New <title>');
    });
});
