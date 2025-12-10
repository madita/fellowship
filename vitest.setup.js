import { vi } from 'vitest';

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

