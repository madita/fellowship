import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import axios from 'axios';
import en from '@/translations/en.js';
import de from '@/translations/de.js';
import WikiSpecial, { SPECIAL_PAGES } from '@/pages/wiki/WikiSpecial.vue';

vi.mock('axios');
vi.mock('@/plugins/formatDate.js', () => ({ formatDateDistanceToNow: () => 'just now' }));

const i18n = createI18n({ legacy: false, locale: 'en', messages: { en, de }, missingWarn: false, fallbackWarn: false });

const stubs = {
    'page-header': { template: '<header>{{ title }}<em>{{ subtitle }}</em></header>', props: ['title', 'subtitle'] },
    'empty-state': { template: '<div class="empty">{{ title }}</div>', props: ['title'] },
    'loading-state': { template: '<div class="loading" />' },
    'v-container': { template: '<div><slot /></div>' },
    'v-row': { template: '<div><slot /></div>' },
    'v-col': { template: '<div><slot /></div>' },
    'v-card': { template: '<div class="card"><slot /></div>' },
    'v-card-text': { template: '<div><slot /></div>' },
    'v-list': { template: '<ul><slot /></ul>' },
    'v-list-item': { template: '<li class="row" :data-to="to"><slot name="prepend" /><slot /><slot name="append" /></li>', props: ['to'] },
    'v-list-item-title': { template: '<span class="title"><slot /></span>' },
    'v-list-item-subtitle': { template: '<span><slot /></span>' },
    'v-avatar': { template: '<span><slot /></span>' },
    'v-icon': { template: '<i />' },
    'v-chip': { template: '<span class="chip" @click="$emit(\'click\')"><slot /></span>' },
    'v-divider': { template: '<hr />' },
    'v-btn': { template: '<button><slot /></button>' },
    'v-btn-toggle': { template: '<div><slot /></div>' },
};

const render = async (page = undefined) => {
    const wrapper = mount(WikiSpecial, {
        global: { plugins: [i18n], stubs, mocks: { $route: { params: page ? { page } : {} }, $router: { push: vi.fn() } } },
    });
    await flushPromises();
    return wrapper;
};

describe('Wiki special pages', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue({ data: { data: [] } });
    });

    it('every special page is named in both languages', () => {
        for (const { key } of SPECIAL_PAGES) {
            for (const messages of [en, de]) {
                expect(messages.wiki.special.pages[key], `${key} missing`).toBeTruthy();
                expect(messages.wiki.special.pages[key].title).toBeTruthy();
                expect(messages.wiki.special.pages[key].description).toBeTruthy();
            }
        }
    });

    it('the hub lists them all without asking the server', async () => {
        const wrapper = await render();

        expect(wrapper.findAll('.card')).toHaveLength(SPECIAL_PAGES.length);
        expect(wrapper.text()).toContain('Wanted pages');
        expect(axios.get).not.toHaveBeenCalled();
    });

    it('a list page loads its own endpoint and links to the pages', async () => {
        axios.get.mockResolvedValue({
            data: { data: [{ title: 'Minas Tirith', slug: 'minas-tirith', count: 2 }] },
        });

        const wrapper = await render('wanted');

        expect(axios.get).toHaveBeenCalledWith('/api/wiki/special/wanted', { params: {} });
        expect(wrapper.find('.row').attributes('data-to')).toBe('/wiki/minas-tirith');
        expect(wrapper.text()).toContain('wanted 2×');
    });

    it('all pages can be narrowed to one letter', async () => {
        axios.get.mockResolvedValue({ data: { data: [], initials: ['A', 'G'] } });
        const wrapper = await render('all-pages');

        await wrapper.findAll('.chip')[1].trigger('click');
        await flushPromises();

        expect(axios.get).toHaveBeenLastCalledWith('/api/wiki/special/all-pages', { params: { letter: 'A' } });
    });

    it('statistics are shown as numbers', async () => {
        axios.get.mockResolvedValue({ data: { data: { pages: 12, categories: 3 } } });
        const wrapper = await render('statistics');

        expect(wrapper.text()).toContain('12');
        expect(wrapper.text()).toContain('Pages');
    });
});
