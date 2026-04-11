import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ModerationTab from '@/components/settings/tabs/ModerationTab.vue';

// Shared mock for API calls
const mockGet = vi.fn(() => Promise.resolve({ data: { data: { records: [] } } }));

vi.mock('@/api/useAPI.js', () => ({
    useApi: vi.fn(() => ({
        get: mockGet,
        post: vi.fn(() => Promise.resolve({ data: {} })),
    })),
}));

// Minimal i18n with the moderation keys
const i18n = createI18n({
    legacy: false,
    locale: 'en',
    messages: {
        en: {
            settings: {
                moderation: {
                    contentAutoApproval: 'Content Auto-Approval',
                    autoApprovalDesc: 'Configure which roles can bypass the approval process.',
                    autoApproveRolesWiki: 'Auto-approve roles for Wiki pages',
                    autoApproveRolesWikiHint: 'Users with any of these roles will have their wiki pages auto-approved.',
                    noRolesSelected: 'All wiki pages require manual approval',
                    saveSettings: 'Save Settings',
                },
            },
        },
    },
});

// Simple stubs that render content for assertions
const stubs = {
    'settings-card': {
        template: '<div class="settings-card-stub" :data-icon="icon" :data-title="title"><slot /></div>',
        props: ['icon', 'title'],
    },
    'v-select': {
        template: '<div class="v-select-stub" :data-label="label" :data-multiple="multiple" :data-loading="loading"></div>',
        props: ['modelValue', 'items', 'label', 'itemTitle', 'itemValue', 'multiple', 'chips', 'closableChips', 'prependInnerIcon', 'variant', 'hint', 'persistentHint', 'loading'],
    },
    'v-alert': {
        template: '<div class="v-alert-stub" :data-type="type"><slot /></div>',
        props: ['type', 'variant', 'density'],
    },
    'v-btn': {
        template: '<button class="v-btn-stub" :data-loading="loading" @click="$emit(\'click\')"><slot /></button>',
        props: ['loading', 'block', 'size', 'color', 'prependIcon'],
    },
    'v-icon': { template: '<i class="v-icon-stub"></i>' },
};

function createWrapper(props = {}) {
    return mount(ModerationTab, {
        props: {
            settings: {
                auto_approve_roles_wiki: [],
                ...props.settings,
            },
            errors: props.errors || {},
            isSaving: props.isSaving || false,
        },
        global: {
            plugins: [i18n],
            stubs,
        },
    });
}

describe('ModerationTab', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        // Default: return empty roles array
        mockGet.mockResolvedValue({ data: { data: { records: [] } } });
    });

    describe('Rendering', () => {
        it('renders the settings card with correct title and icon', () => {
            const wrapper = createWrapper();
            const card = wrapper.find('.settings-card-stub');
            expect(card.exists()).toBe(true);
            expect(card.attributes('data-title')).toBe('Content Auto-Approval');
            expect(card.attributes('data-icon')).toBe('mdi-shield-check');
        });

        it('renders the info alert with description', () => {
            const wrapper = createWrapper();
            const infoAlert = wrapper.find('.v-alert-stub[data-type="info"]');
            expect(infoAlert.exists()).toBe(true);
            expect(infoAlert.text()).toContain('Configure which roles can bypass');
        });

        it('renders the role selector with correct label', () => {
            const wrapper = createWrapper();
            const select = wrapper.find('.v-select-stub');
            expect(select.exists()).toBe(true);
            expect(select.attributes('data-label')).toBe('Auto-approve roles for Wiki pages');
            expect(select.attributes('data-multiple')).toBeDefined();
        });

        it('shows warning alert when no roles are selected', () => {
            const wrapper = createWrapper({
                settings: { auto_approve_roles_wiki: [] },
            });
            const warningAlert = wrapper.find('.v-alert-stub[data-type="warning"]');
            expect(warningAlert.exists()).toBe(true);
            expect(warningAlert.text()).toContain('All wiki pages require manual approval');
        });

        it('hides warning alert when roles are selected', () => {
            const wrapper = createWrapper({
                settings: { auto_approve_roles_wiki: ['admin'] },
            });
            const warningAlert = wrapper.find('.v-alert-stub[data-type="warning"]');
            expect(warningAlert.exists()).toBe(false);
        });

        it('renders the save button', () => {
            const wrapper = createWrapper();
            const btn = wrapper.find('.v-btn-stub');
            expect(btn.exists()).toBe(true);
            expect(btn.text()).toContain('Save Settings');
        });

        it('passes isSaving to the save button', () => {
            const wrapper = createWrapper({ isSaving: true });
            const btn = wrapper.find('.v-btn-stub');
            expect(btn.attributes('data-loading')).toBe('true');
        });
    });

    describe('Role Fetching', () => {
        it('fetches roles on mount', async () => {
            mockGet.mockResolvedValueOnce({
                data: {
                    data: {
                        records: [
                            { id: 1, name: 'admin' },
                            { id: 2, name: 'editor' },
                        ],
                    },
                },
            });

            createWrapper();

            // Wait for the async onMounted fetchRoles to complete
            await vi.waitFor(() => {
                expect(mockGet).toHaveBeenCalledWith('/datatable/roles');
            });
        });

        it('handles fetch roles error gracefully', async () => {
            const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
            mockGet.mockRejectedValueOnce(new Error('Network error'));

            createWrapper();

            await vi.waitFor(() => {
                expect(consoleSpy).toHaveBeenCalledWith(
                    'Failed to fetch roles:',
                    expect.any(Error)
                );
            });

            consoleSpy.mockRestore();
        });
    });

    describe('Events', () => {
        it('emits save event when save button is clicked', async () => {
            const wrapper = createWrapper();
            const btn = wrapper.find('.v-btn-stub');
            await btn.trigger('click');

            expect(wrapper.emitted('save')).toBeTruthy();
            expect(wrapper.emitted('save').length).toBeGreaterThanOrEqual(1);
        });
    });
});
