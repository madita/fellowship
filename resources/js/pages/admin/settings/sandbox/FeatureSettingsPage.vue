<template>
    <settings-page-layout
        title="Feature Settings"
        description="Enable or disable sandbox and collaboration features"
        icon="mdi-toggle-switch-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'sandbox' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-notebook-edit" title="Sandbox Features">
            <v-switch
                v-model="settings.sandbox_enabled"
                label="Enable Sandbox Feature"
                color="primary"
                class="mb-4"
                hint="Allow users to create and use collaborative sandboxes"
                persistent-hint
            ></v-switch>

            <v-switch
                v-model="settings.sandbox_public_enabled"
                label="Allow Public Sandboxes"
                color="primary"
                class="mb-4"
                hint="Allow users to make their sandboxes publicly accessible"
                persistent-hint
                :disabled="!settings.sandbox_enabled"
            ></v-switch>

            <v-switch
                v-model="settings.sandbox_collaboration_enabled"
                label="Enable Real-time Collaboration"
                color="primary"
                class="mb-4"
                hint="Enable real-time collaborative editing via Yjs WebSocket server"
                persistent-hint
                :disabled="!settings.sandbox_enabled"
            ></v-switch>

            <v-text-field
                v-model.number="settings.sandbox_autosave_interval"
                label="Auto-save Interval (seconds)"
                prepend-inner-icon="mdi-timer-outline"
                variant="outlined"
                type="number"
                :error-messages="errors.sandbox_autosave_interval"
                hint="How often the sandbox content auto-saves (in seconds)"
                persistent-hint
                :disabled="!settings.sandbox_enabled"
            ></v-text-field>
        </settings-card>

        <v-btn
            :loading="isSaving"
            block
            size="large"
            color="primary"
            @click="$emit('save')"
            prepend-icon="mdi-content-save"
            class="d-sm-none"
        >
            Save Settings
        </v-btn>
    </settings-page-layout>
</template>

<script setup>
import { ref } from 'vue';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';

defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

defineEmits(['save', 'message']);

const message = ref('');
const alertType = ref('success');
</script>
