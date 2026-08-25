import { defineStore } from 'pinia';
import axios from 'axios';

const emptyEntry = () => ({
    items: [],
    meta: null,
    isLoaded: false,
    isLoading: false,
    error: null,
});

export const useMenuStore = defineStore('menu', {
    state: () => ({
        byLocation: {},
    }),

    getters: {
        entry: (state) => (location) => state.byLocation[location] ?? emptyEntry(),
        items: (state) => (location) => state.byLocation[location]?.items ?? [],
        meta: (state) => (location) => state.byLocation[location]?.meta ?? null,
        isLoaded: (state) => (location) => !!state.byLocation[location]?.isLoaded,
        isLoading: (state) => (location) => !!state.byLocation[location]?.isLoading,
        error: (state) => (location) => state.byLocation[location]?.error ?? null,
    },

    actions: {
        async fetchMenu(location = 'header', force = false) {
            const current = this.byLocation[location];
            if (current?.isLoaded && !force) return;

            this.byLocation[location] = {
                ...(current ?? emptyEntry()),
                isLoading: true,
                error: null,
            };

            try {
                const { data } = await axios.get(`/api/menus/location/${location}`);
                this.byLocation[location] = {
                    items: data.items || [],
                    meta: data.menu || null,
                    isLoaded: true,
                    isLoading: false,
                    error: null,
                };
            } catch (error) {
                this.byLocation[location] = {
                    items: [],
                    meta: null,
                    isLoaded: true,
                    isLoading: false,
                    error: error.message,
                };
                console.error(`Failed to fetch menu "${location}":`, error);
            }
        },

        async fetchMenuBySlug(slug, force = false) {
            if (!slug) return;

            const key = `slug:${slug}`;
            const current = this.byLocation[key];
            if (current?.isLoaded && !force) return;

            this.byLocation[key] = {
                ...(current ?? emptyEntry()),
                isLoading: true,
                error: null,
            };

            try {
                const { data } = await axios.get(`/api/menus/slug/${slug}`);
                this.byLocation[key] = {
                    items: data.items || [],
                    meta: data.menu || null,
                    isLoaded: true,
                    isLoading: false,
                    error: null,
                };
            } catch (error) {
                this.byLocation[key] = {
                    items: [],
                    meta: null,
                    isLoaded: true,
                    isLoading: false,
                    error: error.message,
                };
                console.error(`Failed to fetch menu "${slug}":`, error);
            }
        },

        clearMenu(location = null) {
            if (location) {
                delete this.byLocation[location];
            } else {
                this.byLocation = {};
            }
        },
    },
});
