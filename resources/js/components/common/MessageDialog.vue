<template>
    <v-dialog v-model="open" max-width="440" :persistent="persistent">
        <v-card>
            <v-card-title class="d-flex align-center text-h6">
                <v-icon :color="color" class="mr-3">{{ icon }}</v-icon>
                {{ title || $t(`dialogs.message.${type}`) }}
            </v-card-title>
            <v-card-text class="text-body-2" style="white-space: pre-line;">{{ message }}</v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn :color="color" variant="tonal" @click="close">{{ okText || $t('dialogs.confirm.ok') }}</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
/**
 * Modal feedback for the outcome of an action (error, warning, success,
 * info) — the app's replacement for inline alerts. Use with v-model:
 *
 *   <message-dialog v-model="dialog.show" :type="dialog.type" :message="dialog.message" />
 */
export default {
    name: 'MessageDialog',
    props: {
        modelValue: { type: Boolean, default: false },
        type: { type: String, default: 'info', validator: v => ['error', 'warning', 'success', 'info'].includes(v) },
        title: { type: String, default: '' },
        message: { type: String, default: '' },
        okText: { type: String, default: '' },
        persistent: { type: Boolean, default: false },
    },
    emits: ['update:modelValue', 'closed'],
    computed: {
        open: {
            get() {
                return this.modelValue;
            },
            set(value) {
                this.$emit('update:modelValue', value);
            },
        },
        color() {
            return { error: 'error', warning: 'warning', success: 'success', info: 'primary' }[this.type];
        },
        icon() {
            return {
                error: 'mdi-alert-circle-outline',
                warning: 'mdi-alert-outline',
                success: 'mdi-check-circle-outline',
                info: 'mdi-information-outline',
            }[this.type];
        },
    },
    methods: {
        close() {
            this.open = false;
            this.$emit('closed');
        },
    },
};
</script>
