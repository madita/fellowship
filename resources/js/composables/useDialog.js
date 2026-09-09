import { useDialogStore, createDialogApi } from '@/store/dialogStore.js';

/**
 * Modal feedback for `<script setup>` components — the same API as
 * `this.$dialog` in Options API components.
 *
 *   const dialog = useDialog();
 *   await dialog.error(e.response?.data?.message || e.message);
 *   if (!(await dialog.confirmDelete(t('…')))) return;
 */
export function useDialog() {
    return createDialogApi(useDialogStore());
}
