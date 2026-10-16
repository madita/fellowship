import { vi, beforeEach } from 'vitest';
import { config } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { createPinia, setActivePinia } from 'pinia';
import { section } from '@/utils/crumbs.js';

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

/**
 * A store is reachable from any mounted component.
 *
 * Shared pieces read stores — the page header asks the settings store
 * whether breadcrumbs are on — so a component test that only cares about
 * a title should not have to know that. A test that cares about store
 * state still calls setActivePinia itself; this only stops the mount from
 * throwing when it does not.
 */
/**
 * And a store that remembers something can read it.
 *
 * Node's own global localStorage shadows jsdom's here and is not a
 * Storage, so the auth store threw on `getItem` the moment any component
 * holding it was mounted. One in-memory store, emptied between tests.
 */
const storage = new Map();

vi.stubGlobal('localStorage', {
  getItem: key => (storage.has(key) ? storage.get(key) : null),
  setItem: (key, value) => storage.set(key, String(value)),
  removeItem: key => storage.delete(key),
  clear: () => storage.clear(),
});

beforeEach(() => {
  storage.clear();
  setActivePinia(createPinia());
});

// $crumb, the breadcrumb section helper that app.js registers globally
config.global.mocks = { ...(config.global.mocks || {}), $crumb: section };

// Mock global objects needed for tests
global.URL.createObjectURL = vi.fn();
global.Blob = vi.fn(() => ({}));

