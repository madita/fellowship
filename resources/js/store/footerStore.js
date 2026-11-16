import { defineStore } from 'pinia';
import axios from 'axios';

export const useFooterStore = defineStore('footer', {
    state: () => ({
        widgets: [],
        sections: [],
        widgetsLoaded: false,
        sectionsLoaded: false,
        isLoading: false,
        error: null,
    }),

    getters: {
        activeWidgets: (state) => {
            if (!Array.isArray(state.widgets)) return [];
            return state.widgets
                .filter(widget => widget && widget.enabled)
                .sort((a, b) => (a.order || 0) - (b.order || 0));
        },
        activeSections: (state) => {
            if (!Array.isArray(state.sections)) return [];
            return state.sections
                .filter(section => section && section.enabled)
                .sort((a, b) => (a.order || 0) - (b.order || 0));
        },
        enabledWidgets: (state) => state.widgets.filter(w => w.enabled),
        orderedWidgets: (state) => [...state.widgets].sort((a, b) => a.order - b.order),
        enabledSections: (state) => state.sections.filter(s => s.enabled),
        orderedSections: (state) => [...state.sections].sort((a, b) => a.order - b.order),
    },

    actions: {
        /**
         * Fetch all widgets (admin)
         */
        async fetchWidgets() {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/admin/footer/widgets');
                this.widgets = response.data.widgets || response.data;
                this.widgetsLoaded = true;
            } catch (error) {
                this.error = error.message;
                console.error('Failed to fetch widgets:', error);
                this.widgets = [];
                this.widgetsLoaded = true;
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Fetch public widgets for footer rendering
         */
        async fetchPublicWidgets() {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/footer/widgets');
                this.widgets = response.data.widgets || response.data;
                this.widgetsLoaded = true;
            } catch (error) {
                this.error = error.message;
                console.error('Failed to fetch public widgets:', error);
                this.widgets = [];
                this.widgetsLoaded = true;
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
                const response = await axios.post('/api/admin/footer/widgets', widgetData);
                this.widgets.push(response.data.widget || response.data);
                return response.data.widget || response.data;
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
                const response = await axios.patch(`/api/admin/footer/widgets/${id}`, widgetData);
                const index = this.widgets.findIndex(w => w.id === id);
                if (index !== -1) {
                    this.widgets[index] = response.data.widget || response.data;
                }
                return response.data.widget || response.data;
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
                await axios.delete(`/api/admin/footer/widgets/${id}`);
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
                const response = await axios.post(`/api/admin/footer/widgets/${id}/toggle`);
                const index = this.widgets.findIndex(w => w.id === id);
                if (index !== -1) {
                    this.widgets[index] = response.data.widget || response.data;
                }
                return response.data.widget || response.data;
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
                await axios.post('/api/admin/footer/widgets/reorder', { widgets: newOrder });
                await this.fetchWidgets();
            } catch (error) {
                console.error('Failed to reorder widgets:', error);
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
                const response = await axios.get('/api/admin/footer/sections');
                this.sections = response.data;
                this.sectionsLoaded = true;
            } catch (error) {
                this.error = error.message;
                console.error('Failed to fetch sections:', error);
                this.sections = [];
                this.sectionsLoaded = true;
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Fetch public sections for footer rendering
         */
        async fetchPublicSections() {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/footer/sections');
                this.sections = response.data;
                this.sectionsLoaded = true;
            } catch (error) {
                this.error = error.message;
                console.error('Failed to fetch public sections:', error);
                this.sections = [];
                this.sectionsLoaded = true;
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
                const response = await axios.post('/api/admin/footer/sections', sectionData);
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
                const response = await axios.patch(`/api/admin/footer/sections/${id}`, sectionData);
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
                await axios.delete(`/api/admin/footer/sections/${id}`);
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
                const response = await axios.post(`/api/admin/footer/sections/${id}/toggle`);
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
                await axios.post('/api/admin/footer/sections/reorder', { sections: newOrder });
                await this.fetchSections();
            } catch (error) {
                console.error('Failed to reorder sections:', error);
                throw error;
            }
        },
    },
});
