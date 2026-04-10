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

describe('useSettings Composable', () => {
    let useSettings;

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

        const mod = await import('@/composables/useSettings.js');
        useSettings = mod.useSettings;

        // Mock window.scrollTo
        window.scrollTo = vi.fn();
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

        it('has empty message initially', () => {
            const { message } = useSettings();
            expect(message.value).toBe('');
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

        it('handles fetch error gracefully', async () => {
            mockGet.mockRejectedValueOnce(new Error('Network error'));

            const { fetchSettings, message, alertType } = useSettings();
            await fetchSettings();

            expect(message.value).toBe('Failed to load settings');
            expect(alertType.value).toBe('error');
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
