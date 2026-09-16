import { vi } from 'vitest';
import { config } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

// Provide vue-i18n globally for all component tests
const i18n = createI18n({
  legacy: false,
  locale: 'en',
  fallbackLocale: 'en',
  messages: { en: {} },
  missingWarn: false,
  fallbackWarn: false,
});

config.global.plugins = config.global.plugins || [];
config.global.plugins.push(i18n);

// Mock Vuetify components
vi.mock('vuetify/components', () => {
  return {
    VDialog: {},
    VCard: {},
    VCardText: {},
    VCardTitle: {},
    VCardActions: {},
    VRow: {},
    VCol: {},
    VTextField: {},
    VBtn: {},
    VIcon: {},
    VBadge: {},
    VMenu: {},
    VDataTable: {},
    VList: {},
    VListItem: {},
    VSelect: {},
    VCombobox: {},
    VTextarea: {},
    VCheckbox: {},
    VSpacer: {},
    VNavigationDrawer: {},
    VChip: {},
  };
});

// Mock API and stores
vi.mock('@/api/useAPI.js', () => {
  return {
    useApi: vi.fn(() => ({
      get: vi.fn(() => Promise.resolve({ data: {} })),
      post: vi.fn(() => Promise.resolve({ data: {} }))
    }))
  };
});

// Mock global objects needed for tests
global.URL.createObjectURL = vi.fn();
global.Blob = vi.fn(() => ({}));

