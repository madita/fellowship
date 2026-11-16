import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useHomepageStore } from '@/store/homepageStore';
import axios from 'axios';

vi.mock('axios');

describe('Homepage Store', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useHomepageStore();
        vi.clearAllMocks();
    });

    describe('Initial State', () => {
        it('initializes with empty arrays', () => {
            expect(store.sections).toEqual([]);
            expect(store.widgets).toEqual([]);
            expect(store.menuItems).toEqual([]);
            expect(store.isLoading).toBe(false);
            expect(store.error).toBeNull();
        });
    });

    describe('Getters', () => {
        beforeEach(() => {
            store.widgets = [
                { id: 1, enabled: true, order: 2 },
                { id: 2, enabled: false, order: 1 },
                { id: 3, enabled: true, order: 3 },
            ];
        });

        it('enabledWidgets returns only enabled widgets', () => {
            expect(store.enabledWidgets).toHaveLength(2);
            expect(store.enabledWidgets.map(w => w.id)).toEqual([1, 3]);
        });

        it('orderedWidgets returns widgets sorted by order', () => {
            const ordered = store.orderedWidgets;
            expect(ordered.map(w => w.order)).toEqual([1, 2, 3]);
        });

        it('activeWidgets returns enabled widgets sorted by order', () => {
            const active = store.activeWidgets;
            expect(active).toHaveLength(2);
            expect(active.map(w => w.id)).toEqual([1, 3]);
        });
    });

    describe('Widget Actions', () => {
        it('fetchWidgets sets widgets from API', async () => {
            const mockWidgets = [
                { id: 1, type: 'hero', enabled: true, order: 1 },
                { id: 2, type: 'stats', enabled: false, order: 2 },
            ];

            axios.get.mockResolvedValueOnce({ data: { widgets: mockWidgets } });

            await store.fetchWidgets();

            expect(axios.get).toHaveBeenCalledWith('/api/admin/homepage/widgets');
            expect(store.widgets).toEqual(mockWidgets);
            expect(store.isLoading).toBe(false);
        });

        it('fetchWidgets handles errors', async () => {
            axios.get.mockRejectedValueOnce(new Error('Network error'));

            await expect(store.fetchWidgets()).rejects.toThrow('Network error');
            expect(store.error).toBe('Network error');
        });

        it('createWidget adds new widget to store', async () => {
            const newWidget = { type: 'hero', enabled: true, order: 1, content: {} };
            const createdWidget = { id: 1, ...newWidget };

            axios.post.mockResolvedValueOnce({ data: { widget: createdWidget } });

            const result = await store.createWidget(newWidget);

            expect(axios.post).toHaveBeenCalledWith('/api/admin/homepage/widgets', newWidget);
            expect(store.widgets).toContainEqual(createdWidget);
            expect(result).toEqual(createdWidget);
        });

        it('updateWidget updates existing widget in store', async () => {
            store.widgets = [{ id: 1, type: 'hero', title: 'Old' }];

            const updatedWidget = { id: 1, type: 'hero', title: 'New' };
            axios.patch.mockResolvedValueOnce({ data: { widget: updatedWidget } });

            await store.updateWidget(1, updatedWidget);

            expect(axios.patch).toHaveBeenCalledWith('/api/admin/homepage/widgets/1', updatedWidget);
            expect(store.widgets[0].title).toBe('New');
        });

        it('deleteWidget removes widget from store', async () => {
            store.widgets = [
                { id: 1, type: 'hero' },
                { id: 2, type: 'stats' },
            ];

            axios.delete.mockResolvedValueOnce({});

            await store.deleteWidget(1);

            expect(axios.delete).toHaveBeenCalledWith('/api/admin/homepage/widgets/1');
            expect(store.widgets).toHaveLength(1);
            expect(store.widgets[0].id).toBe(2);
        });

        it('toggleWidget updates widget enabled status', async () => {
            store.widgets = [{ id: 1, enabled: true }];

            const toggled = { id: 1, enabled: false };
            axios.post.mockResolvedValueOnce({ data: { widget: toggled } });

            await store.toggleWidget(1);

            expect(axios.post).toHaveBeenCalledWith('/api/admin/homepage/widgets/1/toggle');
            expect(store.widgets[0].enabled).toBe(false);
        });

        it('duplicateWidget adds duplicated widget to store', async () => {
            store.widgets = [{ id: 1, type: 'hero' }];

            const duplicated = { id: 2, type: 'hero', title: 'Hero (Copy)' };
            axios.post.mockResolvedValueOnce({ data: { widget: duplicated } });

            const result = await store.duplicateWidget(1);

            expect(axios.post).toHaveBeenCalledWith('/api/admin/homepage/widgets/1/duplicate');
            expect(store.widgets).toHaveLength(2);
            expect(result).toEqual(duplicated);
        });

        it('reorderWidgets updates order and refreshes', async () => {
            axios.post.mockResolvedValueOnce({});
            axios.get.mockResolvedValueOnce({ data: { widgets: [] } });

            const newOrder = [
                { id: 1, order: 2 },
                { id: 2, order: 1 },
            ];

            await store.reorderWidgets(newOrder);

            expect(axios.post).toHaveBeenCalledWith('/api/admin/homepage/widgets/reorder', {
                widgets: newOrder,
            });
            expect(axios.get).toHaveBeenCalledWith('/api/admin/homepage/widgets');
        });
    });

    describe('Section Actions', () => {
        it('fetchSections sets sections from API', async () => {
            const mockSections = [
                { id: 1, title: 'Hero Section', enabled: true, order: 1 },
                { id: 2, title: 'Features', enabled: true, order: 2 },
            ];

            axios.get.mockResolvedValueOnce({ data: mockSections });

            await store.fetchSections();

            expect(axios.get).toHaveBeenCalledWith('/api/admin/homepage/sections');
            expect(store.sections).toEqual(mockSections);
        });

        it('createSection adds new section to store', async () => {
            const newSection = { title: 'New Section', layout: '2-col', enabled: true };
            const createdSection = { id: 1, ...newSection };

            axios.post.mockResolvedValueOnce({ data: createdSection });

            const result = await store.createSection(newSection);

            expect(axios.post).toHaveBeenCalledWith('/api/admin/homepage/sections', newSection);
            expect(store.sections).toContainEqual(createdSection);
            expect(result).toEqual(createdSection);
        });

        it('updateSection updates existing section', async () => {
            store.sections = [{ id: 1, title: 'Old' }];

            const updatedSection = { id: 1, title: 'New' };
            axios.patch.mockResolvedValueOnce({ data: updatedSection });

            await store.updateSection(1, updatedSection);

            expect(axios.patch).toHaveBeenCalledWith('/api/admin/homepage/sections/1', updatedSection);
            expect(store.sections[0].title).toBe('New');
        });

        it('deleteSection removes section from store', async () => {
            store.sections = [
                { id: 1, title: 'Section 1' },
                { id: 2, title: 'Section 2' },
            ];

            axios.delete.mockResolvedValueOnce({});

            await store.deleteSection(1);

            expect(axios.delete).toHaveBeenCalledWith('/api/admin/homepage/sections/1');
            expect(store.sections).toHaveLength(1);
            expect(store.sections[0].id).toBe(2);
        });

        it('toggleSection updates section enabled status', async () => {
            store.sections = [{ id: 1, enabled: true }];

            const toggled = { id: 1, enabled: false };
            axios.post.mockResolvedValueOnce({ data: toggled });

            await store.toggleSection(1);

            expect(axios.post).toHaveBeenCalledWith('/api/admin/homepage/sections/1/toggle');
            expect(store.sections[0].enabled).toBe(false);
        });
    });

    describe('Menu Item Actions', () => {
        it('fetchMenuItems sets menu items from API', async () => {
            const mockItems = [
                { id: 1, label: 'Home', anchor_target: '#home', order: 1 },
                { id: 2, label: 'About', anchor_target: '#about', order: 2 },
            ];

            axios.get.mockResolvedValueOnce({ data: { items: mockItems } });

            await store.fetchMenuItems();

            expect(axios.get).toHaveBeenCalledWith('/api/admin/homepage/menu');
            expect(store.menuItems).toEqual(mockItems);
        });

        it('createMenuItem adds new item to store', async () => {
            const newItem = { label: 'Contact', anchor_target: '#contact', order: 1 };
            const createdItem = { id: 1, ...newItem };

            axios.post.mockResolvedValueOnce({ data: { item: createdItem } });

            const result = await store.createMenuItem(newItem);

            expect(axios.post).toHaveBeenCalledWith('/api/admin/homepage/menu', newItem);
            expect(store.menuItems).toContainEqual(createdItem);
            expect(result).toEqual(createdItem);
        });

        it('updateMenuItem updates existing item', async () => {
            store.menuItems = [{ id: 1, label: 'Old' }];

            const updatedItem = { id: 1, label: 'New' };
            axios.patch.mockResolvedValueOnce({ data: { item: updatedItem } });

            await store.updateMenuItem(1, updatedItem);

            expect(axios.patch).toHaveBeenCalledWith('/api/admin/homepage/menu/1', updatedItem);
            expect(store.menuItems[0].label).toBe('New');
        });

        it('deleteMenuItem removes item from store', async () => {
            store.menuItems = [
                { id: 1, label: 'Home' },
                { id: 2, label: 'About' },
            ];

            axios.delete.mockResolvedValueOnce({});

            await store.deleteMenuItem(1);

            expect(axios.delete).toHaveBeenCalledWith('/api/admin/homepage/menu/1');
            expect(store.menuItems).toHaveLength(1);
            expect(store.menuItems[0].id).toBe(2);
        });

        it('reorderMenu updates order and refreshes', async () => {
            axios.post.mockResolvedValueOnce({});
            axios.get.mockResolvedValueOnce({ data: { items: [] } });

            const newOrder = [
                { id: 1, order: 2 },
                { id: 2, order: 1 },
            ];

            await store.reorderMenu(newOrder);

            expect(axios.post).toHaveBeenCalledWith('/api/admin/homepage/menu/reorder', {
                items: newOrder,
            });
        });
    });

    describe('Ordered Getters for Sections and Menu', () => {
        it('orderedSections returns sections sorted by order', () => {
            store.sections = [
                { id: 1, order: 3 },
                { id: 2, order: 1 },
                { id: 3, order: 2 },
            ];

            const ordered = store.orderedSections;
            expect(ordered.map(s => s.order)).toEqual([1, 2, 3]);
        });

        it('activeSections returns only enabled sections in order', () => {
            store.sections = [
                { id: 1, enabled: true, order: 2 },
                { id: 2, enabled: false, order: 1 },
                { id: 3, enabled: true, order: 3 },
            ];

            const active = store.activeSections;
            expect(active).toHaveLength(2);
            expect(active.map(s => s.id)).toEqual([1, 3]);
        });

        it('orderedMenuItems returns menu items sorted by order', () => {
            store.menuItems = [
                { id: 1, order: 3 },
                { id: 2, order: 1 },
                { id: 3, order: 2 },
            ];

            const ordered = store.orderedMenuItems;
            expect(ordered.map(m => m.order)).toEqual([1, 2, 3]);
        });
    });
});
