import { defineStore } from 'pinia';

/**
 * App-wide modal feedback. Components call `this.$dialog.…` (Options API)
 * or `useDialog()` (script setup) and the dialogs are rendered once by
 * `components/common/DialogHost.vue` in App.vue.
 *
 *   await this.$dialog.error(message);          // blocks until closed
 *   const ok = await this.$dialog.confirm({ title, content });
 */
const emptyMessage = () => ({
    show: false,
    type: 'info',
    title: '',
    message: '',
    okText: '',
    resolve: null,
});

const emptyConfirm = () => ({
    show: false,
    title: '',
    content: '',
    confirmationText: '',
    cancellationText: '',
    confirmationKeyword: '',
    color: 'error',
    resolve: null,
});

export const useDialogStore = defineStore('dialog', {
    state: () => ({
        message: emptyMessage(),
        confirm: emptyConfirm(),
    }),

    actions: {
        /**
         * Show a message dialog. Resolves once the user closes it.
         * Accepts either an options object or a plain message string.
         */
        showMessage(options = {}) {
            const opts = typeof options === 'string' ? { message: options } : options;
            this.closeMessage();

            return new Promise(resolve => {
                this.message = {
                    ...emptyMessage(),
                    ...opts,
                    message: String(opts.message ?? ''),
                    show: true,
                    resolve,
                };
            });
        },

        error(message, options = {}) {
            return this.showMessage({ ...options, type: 'error', message });
        },

        warning(message, options = {}) {
            return this.showMessage({ ...options, type: 'warning', message });
        },

        success(message, options = {}) {
            return this.showMessage({ ...options, type: 'success', message });
        },

        info(message, options = {}) {
            return this.showMessage({ ...options, type: 'info', message });
        },

        /**
         * Show an error dialog for a failed request, preferring the
         * server's message over the generic axios one.
         */
        requestError(error, fallback = '') {
            const data = error?.response?.data;
            const message = data?.message || data?.error || fallback || error?.message || '';
            return this.error(message);
        },

        closeMessage() {
            const { resolve } = this.message;
            this.message = emptyMessage();
            if (resolve) resolve();
        },

        /**
         * Ask the user to confirm. Resolves to true/false; closing the
         * dialog any other way counts as "no".
         * Accepts an options object or a plain content string.
         */
        askConfirm(options = {}) {
            const opts = typeof options === 'string' ? { content: options } : options;
            this.closeConfirm(false);

            return new Promise(resolve => {
                this.confirm = {
                    ...emptyConfirm(),
                    ...opts,
                    show: true,
                    resolve,
                };
            });
        },

        /**
         * Confirmation for a destructive action: red button, "Delete" label.
         */
        confirmDelete(content, options = {}) {
            return this.askConfirm({ ...options, content, color: 'error', destructive: true });
        },

        closeConfirm(answer = false) {
            const { resolve } = this.confirm;
            this.confirm = emptyConfirm();
            if (resolve) resolve(!!answer);
        },
    },
});

/**
 * Plain object API around the store, shared by the `$dialog` global
 * property and the `useDialog()` composable.
 */
export function createDialogApi(store) {
    return {
        message: (options) => store.showMessage(options),
        error: (message, options) => store.error(message, options),
        warning: (message, options) => store.warning(message, options),
        success: (message, options) => store.success(message, options),
        info: (message, options) => store.info(message, options),
        requestError: (error, fallback) => store.requestError(error, fallback),
        confirm: (options) => store.askConfirm(options),
        confirmDelete: (content, options) => store.confirmDelete(content, options),
    };
}
