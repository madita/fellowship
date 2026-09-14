<template>
    <settings-page-layout
        :title="$t('sandbox.admin.featuresTitle')"
        :description="$t('sandbox.admin.featuresDescription')"
        icon="mdi-toggle-switch-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'sandbox' } }"
        :is-saving="isSaving"
        @save="$emit('save')"
    >
        <settings-card icon="mdi-notebook-edit" :title="$t('sandbox.admin.featuresCard')">
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
            variant="elevated"
            @click="$emit('save')"
            prepend-icon="mdi-content-save"
            class="d-sm-none"
        >
            {{ $t('sandbox.admin.saveSettings') }}
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

defineEmits(['save']);

</script>
