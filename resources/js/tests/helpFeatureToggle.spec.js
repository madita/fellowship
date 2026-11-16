import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { createPinia, setActivePinia } from 'pinia';
import axios from 'axios';
import en from '@/translations/en.js';
import ToolbarHelp from '@/components/toolbar/ToolbarHelp.vue';
import HelpHost from '@/components/help/HelpHost.vue';
import { useSettingsStore } from '@/store/settingStore.js';
import { useAuthStore } from '@/store/authStore.js';
import { useHelpStore } from '@/store/helpStore.js';

vi.mock('axios');

vi.mock('vue-router', () => ({
    useRouter: () => ({ push: vi.fn(), getRoutes: () => [] }),
    useRoute: () => ({ path: '/dashboard', query: {} }),
}));

const i18n = createI18n({ legacy: false, locale: 'en', messages: { en }, missingWarn: false, fallbackWarn: false });

const stubs = {
    // Renders the activator, which is where the icon lives
    'v-menu': {
        template: '<div class="menu"><slot name="activator" :props="{}" /><slot /></div>',
        props: ['modelValue'],
    },
    'v-btn': {
        template: '<button class="btn" :data-help="$attrs[\'data-help\']" @click="$emit(\'click\')"><slot /></button>',
    },
    'v-badge': { template: '<span class="badge" :data-on="modelValue"><slot /></span>', props: ['modelValue'] },
    'v-icon': { template: '<i><slot /></i>' },
    'v-card': { template: '<div><slot /></div>' },
    'v-card-title': { template: '<div><slot /></div>' },
    'v-divider': { template: '<hr />' },
    'v-list': { template: '<ul><slot /></ul>' },
    'v-list-item': { template: '<li class="tour" @click="$emit(\'click\')"><slot name="prepend" /><slot /><slot name="append" /></li>' },
    'v-list-item-title': { template: '<div><slot /></div>' },
    'v-list-item-subtitle': { template: '<div><slot /></div>' },
    'v-avatar': { template: '<span><slot /></span>' },
    'empty-state': { template: '<div class="empty">{{ title }}</div>', props: ['title'] },
    'help-tour-overlay': { template: '<div class="overlay" />' },
    'help-hints': { template: '<div class="hints" />' },
    'help-element-picker': { template: '<div class="picker" />' },
};

const tour = (overrides = {}) => ({
    id: 1,
    key: 'welcome',
    mode: 'tour',
    icon: 'mdi-hand-wave-outline',
    color: 'primary',
    title: 'Welcome',
    description: 'A quick look around',
    auto_start: false,
    show_launcher: true,
    seen: false,
    completed: false,
    steps: [{ id: 10, target: '#one', placement: 'auto', title: 'One', content: 'First' }],
    ...overrides,
});

const setFeature = (enabled) => {
    const settings = useSettingsStore();

    settings.appSettings = { ...settings.appSettings, feature_help_enabled: enabled };
};

/**
 * The feature can be switched off site-wide, and then nothing about help
 * is on screen — the toolbar icon least of all, since it is the only part
 * of it that is visible when there is nothing to show.
 */
describe('turning the help feature off', () => {
    beforeEach(() => {
        localStorage.clear();
        setActivePinia(createPinia());
        vi.clearAllMocks();
        axios.get = vi.fn().mockResolvedValue({ data: { data: [tour()] } });
        useAuthStore().isLoggedIn = true;
    });

    const renderToolbar = async () => {
        const wrapper = mount(ToolbarHelp, { global: { plugins: [i18n], stubs } });
        await flushPromises();

        return wrapper;
    };

    it('shows the icon while the feature is on', async () => {
        setFeature(true);
        useHelpStore().tours = [tour()];

        const wrapper = await renderToolbar();

        expect(wrapper.find('button[data-help="toolbar-help"]').exists()).toBe(true);
    });

    it('shows no icon at all once it is off', async () => {
        setFeature(false);
        useHelpStore().tours = [tour()];

        const wrapper = await renderToolbar();

        expect(wrapper.find('button[data-help="toolbar-help"]').exists()).toBe(false);
        expect(wrapper.find('.menu').exists()).toBe(false);
        expect(wrapper.text()).toBe('');
    });

    it('takes the icon away the moment the setting changes', async () => {
        setFeature(true);
        useHelpStore().tours = [tour()];

        const wrapper = await renderToolbar();
        expect(wrapper.find('button[data-help="toolbar-help"]').exists()).toBe(true);

        setFeature(false);
        await flushPromises();

        expect(wrapper.find('button[data-help="toolbar-help"]').exists()).toBe(false);
    });

    it('still offers the icon on a page with no help, so it can be found', async () => {
        // Unset means on, which is what an install that predates the
        // setting looks like
        useHelpStore().tours = [];

        const wrapper = await renderToolbar();

        expect(wrapper.find('button[data-help="toolbar-help"]').exists()).toBe(true);
        expect(wrapper.find('.empty').text()).toContain('No help has been written for this page yet');
    });

    it('marks the icon while there is help nobody has read', async () => {
        setFeature(true);
        useHelpStore().tours = [tour({ seen: false })];

        let wrapper = await renderToolbar();
        expect(wrapper.find('.badge').attributes('data-on')).toBe('true');

        useHelpStore().tours = [tour({ seen: true })];
        wrapper = await renderToolbar();
        expect(wrapper.find('.badge').attributes('data-on')).toBe('false');
    });
});

describe('the help host', () => {
    beforeEach(() => {
        localStorage.clear();
        setActivePinia(createPinia());
        vi.clearAllMocks();
        axios.get = vi.fn().mockResolvedValue({ data: { data: [tour()] } });
    });

    const render = async () => {
        const wrapper = mount(HelpHost, { global: { plugins: [i18n], stubs } });
        await flushPromises();

        return wrapper;
    };

    it('puts the overlay and the hints on the page while the feature is on', async () => {
        setFeature(true);

        const wrapper = await render();

        expect(wrapper.find('.overlay').exists()).toBe(true);
        expect(wrapper.find('.hints').exists()).toBe(true);
        expect(axios.get).toHaveBeenCalled();
    });

    it('renders nothing and asks for nothing once it is off', async () => {
        setFeature(false);

        const wrapper = await render();

        expect(wrapper.find('.overlay').exists()).toBe(false);
        expect(wrapper.find('.hints').exists()).toBe(false);
        expect(wrapper.find('.picker').exists()).toBe(false);
        // No point asking the server for help it will not show
        expect(axios.get).not.toHaveBeenCalled();
        expect(useHelpStore().tours).toEqual([]);
    });

    it('drops what it was holding when the feature is switched off', async () => {
        setFeature(true);

        const wrapper = await render();
        expect(useHelpStore().tours).toHaveLength(1);

        setFeature(false);
        await flushPromises();

        expect(useHelpStore().tours).toEqual([]);
        expect(wrapper.find('.overlay').exists()).toBe(false);
    });
});
