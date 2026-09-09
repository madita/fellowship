<template>
    <div>
        <message-dialog
            :model-value="message.show"
            :type="message.type"
            :title="message.title"
            :message="message.message"
            :ok-text="message.okText"
            @update:model-value="onMessageToggle"
        />
        <confirm-dialog
            :model-value="confirm.show"
            :title="confirm.title"
            :content="confirm.content"
            :confirmation-text="confirm.confirmationText || (confirm.destructive ? $t('dialogs.confirm.delete') : '')"
            :cancellation-text="confirm.cancellationText"
            :confirmation-keyword="confirm.confirmationKeyword"
            :color="confirm.color"
            :resolve="onConfirmAnswer"
            @update:model-value="onConfirmToggle"
        />
    </div>
</template>

<script>
import { mapState } from 'pinia';
import { useDialogStore } from '@/store/dialogStore.js';
import MessageDialog from '@/components/common/MessageDialog.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';

/**
 * Renders the app-wide message and confirm dialogs driven by
 * `store/dialogStore.js`. Mounted once in App.vue.
 */
export default {
    name: 'DialogHost',
    components: { MessageDialog, ConfirmDialog },
    computed: {
        ...mapState(useDialogStore, ['message', 'confirm']),
    },
    methods: {
        onMessageToggle(open) {
            if (!open) useDialogStore().closeMessage();
        },
        onConfirmAnswer(answer) {
            useDialogStore().closeConfirm(answer);
        },
        onConfirmToggle(open) {
            // Closed by clicking outside / Escape: counts as "no".
            if (!open && this.confirm.show) useDialogStore().closeConfirm(false);
        },
    },
};
</script>
