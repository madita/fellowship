import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const get = vi.fn();
vi.mock('axios', () => ({ default: { get: (...args) => get(...args) } }));

import DataTableRelation from '@/components/common/DataTable/DataTableRelation.vue';

const LIST = {
    data: {
        data: {
            columns: [
                { key: 'change', title: 'Change', type: 'text' },
                { key: 'author', title: 'By', type: 'text' },
            ],
            rows: [
                {
                    id: 7,
                    values: { change: 'content', author: 'madita' },
                    details_url: '/datatable/pages/3/history/7',
                },
            ],
        },
    },
};

const REVISION = {
    data: {
        data: {
            id: 7,
            changes: [
                { field: 'content', label: 'Content', old: '<p>Before</p>', new: '<p>After</p>', html: true },
            ],
        },
    },
};

const stubs = {
    'v-icon': { template: '<i />' },
    'v-chip': { template: '<span><slot /></span>' },
    'v-spacer': { template: '<span />' },
    'v-btn': { template: '<button><slot /></button>' },
    'v-list': { template: '<div><slot /></div>' },
    'v-list-item': { template: '<div><slot /></div>' },
    'v-list-item-title': { template: '<div><slot /></div>' },
    'v-list-item-subtitle': { template: '<div><slot /></div>' },
    'v-expansion-panels': { template: '<div><slot /></div>' },
    'v-expansion-panel': { template: '<div><slot /></div>' },
    'v-expansion-panel-title': { template: '<div><slot /></div>' },
    'v-expansion-panel-text': { template: '<div><slot /></div>' },
    // Reached through LoadingState and DiffView's own toolbar
    'v-progress-circular': { template: '<span />' },
    'v-btn-toggle': { template: '<div><slot /></div>' },
};

const relation = { key: 'history', title: 'History', endpoint: '/datatable/pages/{id}/history' };

const render = async () => {
    const wrapper = mount(DataTableRelation, {
        props: { relation, itemId: 3 },
        global: { stubs },
    });
    await flush();

    return wrapper;
};

const flush = async () => {
    await Promise.resolve();
    await Promise.resolve();
    await new Promise(resolve => setTimeout(resolve, 0));
};

describe('DataTableRelation: revision history', () => {
    beforeEach(() => {
        get.mockReset();
        get.mockImplementation((url) => Promise.resolve(url.includes('/history/') ? REVISION : LIST));
    });

    it('lists the revisions without fetching any of their text', async () => {
        const wrapper = await render();

        expect(get).toHaveBeenCalledTimes(1);
        expect(get).toHaveBeenCalledWith('/api/datatable/pages/3/history');
        expect(wrapper.text()).toContain('content');
        expect(wrapper.findComponent({ name: 'DiffView' }).exists()).toBe(false);
    });

    it('reads a revision in full the first time its row is opened', async () => {
        const wrapper = await render();

        wrapper.vm.openRows = [7];
        await flush();

        expect(get).toHaveBeenCalledWith('/api/datatable/pages/3/history/7');

        const diff = wrapper.findComponent({ name: 'DiffView' });
        expect(diff.exists()).toBe(true);
        expect(diff.props('oldValue')).toBe('<p>Before</p>');
        expect(diff.props('newValue')).toBe('<p>After</p>');
        expect(diff.props('html')).toBe(true);
    });

    it('does not fetch the same revision twice', async () => {
        const wrapper = await render();

        wrapper.vm.openRows = [7];
        await flush();
        wrapper.vm.openRows = [];
        await flush();
        wrapper.vm.openRows = [7];
        await flush();

        const detailCalls = get.mock.calls.filter(([url]) => url.includes('/history/7'));
        expect(detailCalls).toHaveLength(1);
    });

    it('offers a retry when the revision cannot be read', async () => {
        get.mockImplementation((url) => (
            url.includes('/history/') ? Promise.reject(new Error('nope')) : Promise.resolve(LIST)
        ));

        const wrapper = await render();
        wrapper.vm.openRows = [7];
        await flush();

        expect(wrapper.vm.detailOf({ id: 7 }).error).toBe(true);

        get.mockImplementation((url) => Promise.resolve(url.includes('/history/') ? REVISION : LIST));
        await wrapper.vm.loadDetail(7, true);
        await flush();

        expect(wrapper.findComponent({ name: 'DiffView' }).exists()).toBe(true);
    });
});
