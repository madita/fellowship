import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const get = vi.fn();
const post = vi.fn();

vi.mock('axios', () => ({
    default: {
        get: (...args) => get(...args),
        post: (...args) => post(...args),
    },
}));

const success = vi.fn();
const requestError = vi.fn();

vi.mock('@/composables/useDialog.js', () => ({
    useDialog: () => ({ success, requestError }),
}));

import TaxonomyCreateDialog from '@/components/taxonomy/TaxonomyCreateDialog.vue';

const OPTIONS = {
    data: {
        data: [
            { id: 1, title: 'News', parent_id: null, depth: 0, path: ['News'] },
            { id: 2, title: 'Releases', parent_id: 1, depth: 1, path: ['News', 'Releases'] },
        ],
    },
};

const stubs = {
    VDialog: { template: '<div><slot /></div>' },
    VCard: { template: '<div><slot /></div>' },
    VCardTitle: { template: '<div><slot /></div>' },
    VCardText: { template: '<div><slot /></div>' },
    VCardActions: { template: '<div><slot /></div>' },
    VDivider: { template: '<hr />' },
    VSpacer: { template: '<span />' },
    VIcon: { template: '<i><slot /></i>' },
    VBtn: { template: '<button><slot /></button>' },
    VTextField: { props: ['modelValue'], template: '<input />' },
    VTextarea: { props: ['modelValue'], template: '<textarea />' },
    // The one thing a test can read without a real Vuetify: the warning
    // carries its message in a prop.
    VAlert: { props: ['text'], template: '<div class="alert">{{ text }}</div>' },
    VAutocomplete: { props: ['modelValue', 'items'], template: '<div class="parent-field" />' },
    // Reached from the parent field's item slot, which the stub above does
    // not render; stubbed so the slot still compiles quietly.
    VListItem: { template: '<div><slot /></div>' },
    VListItemTitle: { template: '<div><slot /></div>' },
    VListItemSubtitle: { template: '<div><slot /></div>' },
};

const flush = async () => {
    await Promise.resolve();
    await Promise.resolve();
    await new Promise(resolve => setTimeout(resolve, 0));
};

const render = async (props = {}) => {
    const wrapper = mount(TaxonomyCreateDialog, {
        props: { modelValue: true, vocabulary: 'category', label: 'Category', ...props },
        global: { stubs },
    });
    await flush();

    return wrapper;
};

const save = async (wrapper) => {
    const buttons = wrapper.findAll('button');
    await buttons[buttons.length - 1].trigger('click');
    await flush();
};

describe('TaxonomyCreateDialog', () => {
    beforeEach(() => {
        get.mockReset();
        post.mockReset();
        success.mockReset();
        requestError.mockReset();
        get.mockResolvedValue(OPTIONS);
        post.mockResolvedValue({
            data: { category: { id: 3, title: 'Guides', parent_id: 1, depth: 1, path: ['News', 'Guides'] } },
        });
    });

    it('reads the tree of the vocabulary it was given', async () => {
        await render();

        expect(get).toHaveBeenCalledWith('/api/tag/options/category');
    });

    it('reads the endpoints a screen hands it instead of the generic ones', async () => {
        const wrapper = await render({
            vocabulary: 'wiki',
            optionsEndpoint: '/api/wiki/category-options',
            createEndpoint: '/api/wiki/category',
            name: 'Characters',
        });

        expect(get).toHaveBeenCalledWith('/api/wiki/category-options');

        await save(wrapper);

        expect(post).toHaveBeenCalledWith('/api/wiki/category', {
            term: 'Characters',
            taxonomy: 'wiki',
            parent_id: null,
            content: null,
        });
    });

    it('posts the chosen parent and hands the created entry back', async () => {
        const wrapper = await render({ name: 'Guides', parentId: 1 });

        await save(wrapper);

        expect(post).toHaveBeenCalledWith('/api/tag/terms', {
            term: 'Guides',
            taxonomy: 'category',
            parent_id: 1,
            content: null,
        });

        const created = wrapper.emitted('created');
        expect(created).toHaveLength(1);
        expect(created[0][0]).toMatchObject({ id: 3, path: ['News', 'Guides'] });
        // Closed by itself: the form behind it is waiting for the answer.
        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([false]);
        expect(success).toHaveBeenCalled();
    });

    it('says a name is taken before the server has to refuse it', async () => {
        const wrapper = await render({ name: 'releases' });

        expect(wrapper.find('.alert').exists()).toBe(true);

        const clean = await render({ name: 'Guides' });

        expect(clean.find('.alert').exists()).toBe(false);
    });

    it('offers no parent for a flat vocabulary', async () => {
        const wrapper = await render({ vocabulary: 'tags', label: 'Tag', name: 'lore', withParent: false });

        expect(wrapper.find('.parent-field').exists()).toBe(false);

        await save(wrapper);

        expect(post).toHaveBeenCalledWith('/api/tag/terms', {
            term: 'lore',
            taxonomy: 'tags',
            parent_id: null,
            content: null,
        });
    });

    it('stays open when the server refuses what was typed', async () => {
        post.mockRejectedValue({ response: { status: 422, data: { errors: { term: ['Already used'] } } } });

        const wrapper = await render({ name: 'Releases' });

        await save(wrapper);

        expect(wrapper.emitted('created')).toBeUndefined();
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        expect(requestError).not.toHaveBeenCalled();
    });

    it('asks for a name rather than posting an empty one', async () => {
        const wrapper = await render();

        await save(wrapper);

        expect(post).not.toHaveBeenCalled();
    });
});
