import { defineStore } from 'pinia';

export const useSettingsStore = defineStore({
    id: 'settings',

    state: () => ({
        locale: 'de', // default locale
    }),

    actions: {
        setLocale(locale) {
            this.locale = locale;
        }
    }
});
