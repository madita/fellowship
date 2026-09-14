import { describe, it, expect, beforeEach, vi } from 'vitest';

// Create a shared mock API instance that will be returned every time useApi is called
const mockGet = vi.fn(() => Promise.resolve({ data: {} }));
const mockPost = vi.fn(() => Promise.resolve({ data: {} }));

// Override the global useAPI mock with our singleton
vi.mock('@/api/useAPI.js', () => ({
    useApi: vi.fn(() => ({
        get: mockGet,
        post: mockPost,
    })),
}));

// Mock the settings store (used by saveSettings)
vi.mock('@/store/settingStore.js', () => ({
    useSettingsStore: vi.fn(() => ({
        fetchAppSettings: vi.fn().mockResolvedValue(undefined),
    })),
}));

// Translations are looked up through the i18n instance; return the key
vi.mock('@/plugins/vue-i18n.js', () => ({
    i18n: { global: { t: (key) => key } },
}));

describe('useSettings Composable', () => {
    let useSettings;
    let dialogStore;

    beforeEach(async () => {
        vi.clearAllMocks();
        // Reset module registry so each test gets a fresh composable
        vi.resetModules();

        // Re-mock after resetModules
        vi.doMock('@/api/useAPI.js', () => ({
            useApi: vi.fn(() => ({
                get: mockGet,
                post: mockPost,
            })),
        }));

        vi.doMock('@/store/settingStore.js', () => ({
            useSettingsStore: vi.fn(() => ({
                fetchAppSettings: vi.fn().mockResolvedValue(undefined),
            })),
        }));

        vi.doMock('@/plugins/vue-i18n.js', () => ({
            i18n: { global: { t: (key) => key } },
        }));

        // Feedback is reported through the app-wide dialog store
        const { setActivePinia, createPinia } = await import('pinia');
        setActivePinia(createPinia());
        const { useDialogStore } = await import('@/store/dialogStore.js');
        dialogStore = useDialogStore();
        vi.spyOn(dialogStore, 'error').mockResolvedValue(undefined);
        vi.spyOn(dialogStore, 'success').mockResolvedValue(undefined);

        const mod = await import('@/composables/useSettings.js');
        useSettings = mod.useSettings;
    });

    describe('Initial State', () => {
        it('has auto_approve_roles_wiki as empty array by default', () => {
            const { settings } = useSettings();
            expect(settings.auto_approve_roles_wiki).toEqual([]);
        });

        it('has isSaving as false initially', () => {
            const { isSaving } = useSettings();
            expect(isSaving.value).toBe(false);
        });
    });

    describe('fetchSettings', () => {
        it('fetches settings and assigns them to reactive state', async () => {
            mockGet.mockResolvedValueOnce({
                data: {
                    settings: {
                        app_name: 'Test App',
                        auto_approve_roles_wiki: ['admin', 'editor'],
                    },
                },
            });

            const { settings, fetchSettings } = useSettings();
            await fetchSettings();

            expect(mockGet).toHaveBeenCalledWith('/admin/settings');
            expect(settings.app_name).toBe('Test App');
            expect(settings.auto_approve_roles_wiki).toEqual(['admin', 'editor']);
        });

        it('reports a fetch error through the dialog service', async () => {
            const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
            mockGet.mockRejectedValueOnce(new Error('Network error'));

            const { fetchSettings } = useSettings();
            await fetchSettings();

            expect(dialogStore.error).toHaveBeenCalledWith('settings.overview.loadError');
            expect(dialogStore.success).not.toHaveBeenCalled();
            consoleSpy.mockRestore();
        });
    });

    describe('saveSettings - feedback', () => {
        it('shows a success dialog after saving', async () => {
            mockPost.mockResolvedValueOnce({ data: {} });

            const { saveSettings, isSaving } = useSettings();
            await saveSettings();

            expect(dialogStore.success).toHaveBeenCalledWith('settings.overview.saved');
            expect(isSaving.value).toBe(false);
        });

        it('shows the server validation errors in an error dialog', async () => {
            const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
            mockPost.mockRejectedValueOnce({
                response: { data: { errors: { app_name: ['The app name is required.'] } } },
            });

            const { saveSettings, errors } = useSettings();
            await saveSettings();

            expect(dialogStore.error).toHaveBeenCalledWith(
                'settings.overview.saveError:\n• The app name is required.'
            );
            expect(errors.app_name).toEqual(['The app name is required.']);
            consoleSpy.mockRestore();
        });

        it('ignores a second save while one is in flight', async () => {
            let resolvePost;
            mockPost.mockReturnValueOnce(new Promise(resolve => { resolvePost = resolve; }));

            const { saveSettings, isSaving } = useSettings();
            const first = saveSettings();
            expect(isSaving.value).toBe(true);
            await saveSettings();
            expect(mockPost).toHaveBeenCalledTimes(1);

            resolvePost({ data: {} });
            await first;
            expect(isSaving.value).toBe(false);
        });
    });

    describe('saveSettings - JSON type detection', () => {
        it('serializes arrays as JSON type', async () => {
            mockPost.mockResolvedValueOnce({ data: {} });

            const { settings, saveSettings } = useSettings();
            settings.auto_approve_roles_wiki = ['admin', 'editor'];
            settings.maintenance_mode = false;
            settings.posts_per_page = 10;
            settings.app_name = 'Test';

            await saveSettings();

            expect(mockPost).toHaveBeenCalled();
            const savedSettings = mockPost.mock.calls[0][1].settings;

            // Find the auto_approve_roles_wiki entry
            const wikiRolesSetting = savedSettings.find(s => s.key === 'auto_approve_roles_wiki');
            expect(wikiRolesSetting).toBeDefined();
            expect(wikiRolesSetting.type).toBe('json');
            expect(wikiRolesSetting.value).toBe(JSON.stringify(['admin', 'editor']));

            // Verify other types are still correct
            const boolSetting = savedSettings.find(s => s.key === 'maintenance_mode');
            expect(boolSetting.type).toBe('boolean');

            const intSetting = savedSettings.find(s => s.key === 'posts_per_page');
            expect(intSetting.type).toBe('integer');

            const stringSetting = savedSettings.find(s => s.key === 'app_name');
            expect(stringSetting.type).toBe('string');
        });

        it('serializes empty arrays as JSON type', async () => {
            mockPost.mockResolvedValueOnce({ data: {} });

            const { settings, saveSettings } = useSettings();
            settings.auto_approve_roles_wiki = [];

            await saveSettings();

            const savedSettings = mockPost.mock.calls[0][1].settings;
            const wikiRolesSetting = savedSettings.find(s => s.key === 'auto_approve_roles_wiki');
            expect(wikiRolesSetting).toBeDefined();
            expect(wikiRolesSetting.type).toBe('json');
            expect(wikiRolesSetting.value).toBe('[]');
        });

        it('filters out null and undefined values', async () => {
            mockPost.mockResolvedValueOnce({ data: {} });

            const { settings, saveSettings } = useSettings();
            settings.logo_light = null;
            settings.logo_dark = undefined;

            await saveSettings();

            const savedSettings = mockPost.mock.calls[0][1].settings;
            const nullSetting = savedSettings.find(s => s.key === 'logo_light');
            const undefinedSetting = savedSettings.find(s => s.key === 'logo_dark');
            expect(nullSetting).toBeUndefined();
            expect(undefinedSetting).toBeUndefined();
        });

        it('excludes app_logo from saved settings', async () => {
            mockPost.mockResolvedValueOnce({ data: {} });

            const { settings, saveSettings } = useSettings();
            settings.app_logo = 'some-logo.png';

            await saveSettings();

            const savedSettings = mockPost.mock.calls[0][1].settings;
            const logoSetting = savedSettings.find(s => s.key === 'app_logo');
            expect(logoSetting).toBeUndefined();
        });
    });
});
