import { useDialogStore, createDialogApi } from '@/store/dialogStore.js';

/**
 * Registers `this.$dialog` on every component. The store is resolved
 * lazily so the plugin can be installed before Pinia is active.
 */
export default {
    install(app) {
        let api = null;
        Object.defineProperty(app.config.globalProperties, '$dialog', {
            get() {
                if (!api) api = createDialogApi(useDialogStore());
                return api;
            },
        });
    },
};
