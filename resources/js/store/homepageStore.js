import { defineStore } from 'pinia';
import axios from 'axios';

export const useHomepageStore = defineStore('homepage', {
    state: () => ({
        sections: [],
        widgets: [],
        menuItems: [],
        isLoading: false,
        error: null,
    }),

    getters: {
        enabledWidgets: (state) => state.widgets.filter(w => w.enabled),
        orderedWidgets: (state) => [...state.widgets].sort((a, b) => a.order - b.order),
        activeWidgets: (state) => {
            return state.widgets
                .filter(w => w.enabled)
                .sort((a, b) => a.order - b.order);
        },
        enabledMenuItems: (state) => state.menuItems.filter(m => m.enabled),
        orderedMenuItems: (state) => [...state.menuItems].sort((a, b) => a.order - b.order),
        activeMenuItems: (state) => {
            return state.menuItems
                .filter(m => m.enabled)
                .sort((a, b) => a.order - b.order);
        },
        enabledSections: (state) => state.sections.filter(s => s.enabled),
        orderedSections: (state) => [...state.sections].sort((a, b) => a.order - b.order),
        activeSections: (state) => {
            return state.sections
                .filter(s => s.enabled)
                .sort((a, b) => a.order - b.order);
        },
    },

    actions: {
        /**
         * Fetch all widgets (admin)
         */
        async fetchWidgets() {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/admin/homepage/widgets');
                this.widgets = response.data.widgets;
            } catch (error) {
                this.error = error.message;
                console.error('Failed to fetch widgets:', error);
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Fetch public widgets for homepage rendering
         */
        async fetchPublicWidgets() {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/homepage/widgets');
                this.widgets = response.data.widgets;
            } catch (error) {
                this.error = error.message;
                console.error('Failed to fetch public widgets:', error);
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Create a new widget
         */
        async createWidget(widgetData) {
            try {
                const response = await axios.post('/api/admin/homepage/widgets', widgetData);
                this.widgets.push(response.data.widget);
                return response.data.widget;
            } catch (error) {
                console.error('Failed to create widget:', error);
                throw error;
            }
        },

        /**
         * Update a widget
         */
        async updateWidget(id, widgetData) {
            try {
                const response = await axios.patch(`/api/admin/homepage/widgets/${id}`, widgetData);
                const index = this.widgets.findIndex(w => w.id === id);
                if (index !== -1) {
                    this.widgets[index] = response.data.widget;
                }
                return response.data.widget;
            } catch (error) {
                console.error('Failed to update widget:', error);
                throw error;
            }
        },

        /**
         * Delete a widget
         */
        async deleteWidget(id) {
            try {
                await axios.delete(`/api/admin/homepage/widgets/${id}`);
                this.widgets = this.widgets.filter(w => w.id !== id);
            } catch (error) {
                console.error('Failed to delete widget:', error);
                throw error;
            }
        },

        /**
         * Toggle widget enabled status
         */
        async toggleWidget(id) {
            try {
                const response = await axios.post(`/api/admin/homepage/widgets/${id}/toggle`);
                const index = this.widgets.findIndex(w => w.id === id);
                if (index !== -1) {
                    this.widgets[index] = response.data.widget;
                }
                return response.data.widget;
            } catch (error) {
                console.error('Failed to toggle widget:', error);
                throw error;
            }
        },

        /**
         * Reorder widgets
         */
        async reorderWidgets(newOrder) {
            try {
                await axios.post('/api/admin/homepage/widgets/reorder', { widgets: newOrder });
                await this.fetchWidgets();
            } catch (error) {
                console.error('Failed to reorder widgets:', error);
                throw error;
            }
        },

        /**
         * Duplicate a widget
         */
        async duplicateWidget(id) {
            try {
                const response = await axios.post(`/api/admin/homepage/widgets/${id}/duplicate`);
                this.widgets.push(response.data.widget);
                return response.data.widget;
            } catch (error) {
                console.error('Failed to duplicate widget:', error);
                throw error;
            }
        },

        /**
         * Fetch all menu items (admin)
         */
        async fetchMenuItems() {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/admin/homepage/menu');
                this.menuItems = response.data.items;
            } catch (error) {
                this.error = error.message;
                console.error('Failed to fetch menu items:', error);
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Fetch public menu items for homepage rendering
         */
        async fetchPublicMenu() {
            try {
                const response = await axios.get('/api/homepage/menu');
                this.menuItems = response.data.items;
            } catch (error) {
                console.error('Failed to fetch public menu:', error);
                throw error;
            }
        },

        /**
         * Create a new menu item
         */
        async createMenuItem(itemData) {
            try {
                const response = await axios.post('/api/admin/homepage/menu', itemData);
                this.menuItems.push(response.data.item);
                return response.data.item;
            } catch (error) {
                console.error('Failed to create menu item:', error);
                throw error;
            }
        },

        /**
         * Update a menu item
         */
        async updateMenuItem(id, itemData) {
            try {
                const response = await axios.patch(`/api/admin/homepage/menu/${id}`, itemData);
                const index = this.menuItems.findIndex(i => i.id === id);
                if (index !== -1) {
                    this.menuItems[index] = response.data.item;
                }
                return response.data.item;
            } catch (error) {
                console.error('Failed to update menu item:', error);
                throw error;
            }
        },

        /**
         * Delete a menu item
         */
        async deleteMenuItem(id) {
            try {
                await axios.delete(`/api/admin/homepage/menu/${id}`);
                this.menuItems = this.menuItems.filter(i => i.id !== id);
            } catch (error) {
                console.error('Failed to delete menu item:', error);
                throw error;
            }
        },

        /**
         * Reorder menu items
         */
        async reorderMenu(newOrder) {
            try {
                await axios.post('/api/admin/homepage/menu/reorder', { items: newOrder });
                await this.fetchMenuItems();
            } catch (error) {
                console.error('Failed to reorder menu:', error);
                throw error;
            }
        },

        /**
         * Fetch all sections (admin)
         */
        async fetchSections() {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/admin/homepage/sections');
                this.sections = response.data;
            } catch (error) {
                this.error = error.message;
                console.error('Failed to fetch sections:', error);
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Fetch public sections for homepage rendering
         */
        async fetchPublicSections() {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/homepage/sections');
                this.sections = response.data;
            } catch (error) {
                this.error = error.message;
                console.error('Failed to fetch public sections:', error);
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Create a new section
         */
        async createSection(sectionData) {
            try {
                const response = await axios.post('/api/admin/homepage/sections', sectionData);
                this.sections.push(response.data);
                return response.data;
            } catch (error) {
                console.error('Failed to create section:', error);
                throw error;
            }
        },

        /**
         * Update a section
         */
        async updateSection(id, sectionData) {
            try {
                const response = await axios.patch(`/api/admin/homepage/sections/${id}`, sectionData);
                const index = this.sections.findIndex(s => s.id === id);
                if (index !== -1) {
                    this.sections[index] = response.data;
                }
                return response.data;
            } catch (error) {
                console.error('Failed to update section:', error);
                throw error;
            }
        },

        /**
         * Delete a section
         */
        async deleteSection(id) {
            try {
                await axios.delete(`/api/admin/homepage/sections/${id}`);
                this.sections = this.sections.filter(s => s.id !== id);
            } catch (error) {
                console.error('Failed to delete section:', error);
                throw error;
            }
        },

        /**
         * Toggle section enabled status
         */
        async toggleSection(id) {
            try {
                const response = await axios.post(`/api/admin/homepage/sections/${id}/toggle`);
                const index = this.sections.findIndex(s => s.id === id);
                if (index !== -1) {
                    this.sections[index] = response.data;
                }
                return response.data;
            } catch (error) {
                console.error('Failed to toggle section:', error);
                throw error;
            }
        },

        /**
         * Reorder sections
         */
        async reorderSections(newOrder) {
            try {
                await axios.post('/api/admin/homepage/sections/reorder', { sections: newOrder });
                await this.fetchSections();
            } catch (error) {
                console.error('Failed to reorder sections:', error);
                throw error;
            }
        },
    },
});
