import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { createPinia, setActivePinia } from 'pinia';
import axios from 'axios';
import en from '@/translations/en.js';
import HelpToursPage from '@/pages/admin/settings/community/HelpToursPage.vue';
import { useHelpStore } from '@/store/helpStore.js';
import { useSettingsStore } from '@/store/settingStore.js';

vi.mock('axios');

const dialog = {
    requestError: vi.fn(),
    confirmDelete: vi.fn(() => Promise.resolve(true)),
    success: vi.fn(),
    warning: vi.fn(),
    error: vi.fn(),
};

vi.mock('@/composables/useDialog.js', () => ({ useDialog: () => dialog }));

const i18n = createI18n({ legacy: false, locale: 'en', messages: { en }, missingWarn: false, fallbackWarn: false });

const push = vi.fn();
const routes = [
    { name: 'dashboard', path: '/dashboard' },
    { name: 'forum', path: '/forum' },
    { name: 'wiki-show', path: '/wiki/:slug' },
];

vi.mock('vue-router', () => ({
    useRouter: () => ({ push, getRoutes: () => routes }),
    useRoute: () => ({ params: { category: 'community', setting: 'tours' }, path: '/admin/settings/community/tours', query: {} }),
}));

const passthrough = (tag = 'div') => ({ template: `<${tag}><slot /></${tag}>` });

const stubs = {
    'settings-page-layout': { template: '<div class="page"><slot /></div>' },
    'settings-card': { template: '<section><slot /></section>' },
    'empty-state': { template: '<div class="empty">{{ title }}</div>', props: ['title', 'text'] },
    'loading-state': { template: '<div class="loading" />' },
    'v-table': { template: '<table><slot /></table>' },
    'v-dialog': { template: '<div class="dialog" v-if="modelValue"><slot /></div>', props: ['modelValue'] },
    'v-card': passthrough(),
    'v-card-title': passthrough(),
    'v-card-text': passthrough(),
    'v-card-actions': passthrough(),
    'v-form': { template: '<form><slot /></form>' },
    'v-row': passthrough(),
    'v-col': passthrough(),
    'v-spacer': { template: '<span />' },
    'v-divider': { template: '<hr />' },
    'v-avatar': passthrough('span'),
    'v-icon': { template: '<i><slot /></i>' },
    'v-chip': passthrough('span'),
    'v-switch': {
        template: '<input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', !modelValue)" />',
        props: ['modelValue', 'label'],
    },
    'v-btn': {
        template: '<button :data-title="title" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
        props: ['title', 'disabled', 'loading'],
    },
    'v-text-field': { template: '<input :value="modelValue" />', props: ['modelValue', 'label'] },
    'v-textarea': { template: '<textarea :value="modelValue" />', props: ['modelValue', 'label'] },
    'v-select': { template: '<select />', props: ['modelValue', 'items', 'label'] },
    'v-combobox': { template: '<input class="combobox" :value="modelValue" />', props: ['modelValue', 'items', 'label'] },
    'v-list-item': passthrough('li'),
    'v-list-item-subtitle': passthrough('span'),
    'v-tabs': passthrough(),
    'v-tab': passthrough('span'),
    'v-window': passthrough(),
    'v-window-item': passthrough(),
    'v-expansion-panels': passthrough(),
    'v-expansion-panel': passthrough(),
    'v-expansion-panel-title': passthrough(),
    'v-expansion-panel-text': passthrough(),
};

const tour = (overrides = {}) => ({
    id: 1,
    key: 'welcome',
    route_pattern: '/dashboard',
    mode: 'tour',
    icon: 'mdi-hand-wave-outline',
    color: 'primary',
    audience: 'members',
    roles: [],
    auto_start: true,
    show_launcher: true,
    is_enabled: true,
    sort_order: 0,
    translations: { en: { title: 'Welcome', description: 'A look around' }, de: { title: '', description: '' } },
    steps: [
        {
            id: 10,
            target: '[data-help="toolbar-notifications"]',
            placement: 'bottom',
            sort_order: 0,
            translations: { en: { title: 'Your notifications', content: 'Here' }, de: { title: '', content: '' } },
        },
    ],
    ...overrides,
});

const render = async (data = [tour()]) => {
    axios.get.mockResolvedValue({
        data: { data, locales: ['en', 'de'], default: 'en', roles: ['admin', 'moderator'] },
    });

    const wrapper = mount(HelpToursPage, {
        props: { category: { title: 'Help & Tours' }, setting: { title: 'Tours & Hints' } },
        global: { plugins: [i18n], stubs },
    });

    await flushPromises();

    return wrapper;
};

/**
 * The page the admin writes help on. It went white once, so the first
 * thing asked of it here is simply that it renders.
 */
describe('the help tours settings page', () => {
    beforeEach(() => {
        sessionStorage.clear();
        localStorage.clear();
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('renders the tours that have been written', async () => {
        const wrapper = await render();

        expect(axios.get).toHaveBeenCalledWith('/api/admin/help-tours');
        expect(wrapper.find('.page').exists()).toBe(true);
        expect(wrapper.text()).toContain('Welcome');
        // The pattern is shown as written, so a wildcard is visible
        expect(wrapper.text()).toContain('/dashboard');
        expect(wrapper.text()).toContain('Guided tour');
    });

    it('says so when nothing has been written yet', async () => {
        const wrapper = await render([]);

        expect(wrapper.find('.empty').text()).toContain('No tours yet');
    });

    it('names a tour by whichever language it has a title in', async () => {
        const wrapper = await render([tour({
            translations: { en: { title: '', description: '' }, de: { title: 'Willkommen', description: '' } },
        })]);

        expect(wrapper.text()).toContain('Willkommen');
    });

    it('will not offer to run a tour that is not live', async () => {
        const wrapper = await render([tour({ is_enabled: false })]);

        const preview = wrapper.findAll('button').find(
            button => button.attributes('data-title') === 'Make it live to try it out'
        );

        expect(preview?.attributes('disabled')).toBeDefined();
    });

    it('opens the editor with the tour loaded into every language tab', async () => {
        const wrapper = await render();

        const edit = wrapper.findAll('button').find(button => button.attributes('data-title') === 'Edit');
        await edit.trigger('click');
        await flushPromises();

        expect(wrapper.find('.dialog').exists()).toBe(true);
        expect(wrapper.vm.form.translations.en.title).toBe('Welcome');
        // A language the tour says nothing in still gets a field to fill
        expect(wrapper.vm.form.translations.de.title).toBe('');
        expect(wrapper.vm.form.steps).toHaveLength(1);
        expect(wrapper.vm.form.steps[0].target).toBe('[data-help="toolbar-notifications"]');
    });

    it('starts a new tour with one empty step, since a tour needs one', async () => {
        const wrapper = await render([]);

        const add = wrapper.findAll('button').find(button => button.text().includes('New tour'));
        await add.trigger('click');

        expect(wrapper.vm.form.steps).toHaveLength(1);
        expect(wrapper.vm.form.route_pattern).toBe('');
    });

    it('will not go off picking before the page is known', async () => {
        const wrapper = await render([]);

        await wrapper.vm.pickOnPage(0);

        expect(dialog.warning).toHaveBeenCalled();
        expect(push).not.toHaveBeenCalled();
    });

    it('parks the draft and opens the page in picking mode', async () => {
        const wrapper = await render();

        wrapper.vm.form.route_pattern = '/wiki/*';
        await wrapper.vm.pickOnPage(0);

        // The wildcard is not a page, so the section it covers is opened
        expect(push).toHaveBeenCalledWith({ path: '/wiki', query: { help_pick: 1 } });
        expect(useHelpStore().pendingPick()).not.toBeNull();
    });

    it('comes back from picking with the editor as it was', async () => {
        const store = useHelpStore();

        store.beginPicking({
            draft: {
                editingId: 1,
                form: {
                    route_pattern: '/dashboard',
                    mode: 'tour',
                    icon: 'mdi-help-circle-outline',
                    color: 'primary',
                    audience: 'everyone',
                    roles: [],
                    auto_start: false,
                    show_launcher: true,
                    is_enabled: true,
                    sort_order: 0,
                    translations: { en: { title: 'Half written', description: '' }, de: { title: '', description: '' } },
                    steps: [{ uid: 1, id: null, target: '', placement: 'auto', translations: { en: { title: 'A step', content: '' }, de: { title: '', content: '' } } }],
                },
            },
            stepIndex: 0,
            returnTo: { name: 'admin-settings-page' },
        });
        store.finishPicking('[data-help="forum-new-thread"]');

        const wrapper = await render();
        await flushPromises();

        expect(wrapper.find('.dialog').exists()).toBe(true);
        expect(wrapper.vm.form.translations.en.title).toBe('Half written');
        expect(wrapper.vm.form.steps[0].target).toBe('[data-help="forum-new-thread"]');
        // Taken once, so a reload does not apply it again
        expect(store.pendingPick()).toBeNull();
    });

    it('turns the whole feature off from here, without a save button', async () => {
        const wrapper = await render();
        const settings = useSettingsStore();

        axios.post.mockResolvedValue({ data: { success: true } });
        const refetch = vi.spyOn(settings, 'fetchAppSettings').mockImplementation(async () => {
            settings.appSettings = { ...settings.appSettings, feature_help_enabled: false };
        });

        await wrapper.vm.setFeatureEnabled(false);

        expect(axios.post).toHaveBeenCalledWith('/api/admin/settings', {
            settings: [{ key: 'feature_help_enabled', value: false, type: 'boolean' }],
        });
        // Re-read, because the store is what the toolbar consults
        expect(refetch).toHaveBeenCalled();
        expect(wrapper.vm.featureEnabled).toBe(false);
    });

    it('puts the switch back if the setting could not be saved', async () => {
        const wrapper = await render();
        const settings = useSettingsStore();

        settings.appSettings = { ...settings.appSettings, feature_help_enabled: true };
        axios.post.mockRejectedValue(new Error('nope'));

        await wrapper.vm.setFeatureEnabled(false);

        expect(dialog.requestError).toHaveBeenCalled();
        // Never shows an answer that was not saved
        expect(wrapper.vm.featureEnabled).toBe(true);
    });

    it('keeps the tours editable while the feature is off', async () => {
        const settings = useSettingsStore();
        settings.appSettings = { ...settings.appSettings, feature_help_enabled: false };

        const wrapper = await render();

        expect(wrapper.vm.featureEnabled).toBe(false);
        // Writing them with the feature off is the point: switch it on
        // when they are ready
        expect(wrapper.text()).toContain('Welcome');
        expect(wrapper.findAll('button').some(b => b.attributes('data-title') === 'Edit')).toBe(true);
    });

    it('sends the tour and its steps together, without its own bookkeeping', async () => {
        axios.patch.mockResolvedValue({ data: { data: tour() } });

        const wrapper = await render();

        const edit = wrapper.findAll('button').find(button => button.attributes('data-title') === 'Edit');
        await edit.trigger('click');

        wrapper.vm.formRef = { validate: () => Promise.resolve({ valid: true }) };
        await wrapper.vm.save();
        await flushPromises();

        const [url, payload] = axios.patch.mock.calls[0];

        expect(url).toBe('/api/admin/help-tours/1');
        expect(payload.steps).toHaveLength(1);
        expect(payload.steps[0]).not.toHaveProperty('uid');
        expect(payload.steps[0].id).toBe(10);
        expect(dialog.success).toHaveBeenCalled();
    });
});
