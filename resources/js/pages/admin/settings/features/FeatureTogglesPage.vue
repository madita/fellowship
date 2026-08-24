<template>
    <settings-page-layout
        title="Feature Toggles"
        description="Activate or deactivate site features. Deactivated features are hidden from the sidebar menu and their pages become unavailable."
        icon="mdi-toggle-switch-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'features' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-puzzle-outline" title="Site Features">
            <p class="text-body-2 text-medium-emphasis mb-4">
                Turning a feature off hides its menu entries for everyone and blocks its pages.
                Existing data is kept — turning the feature back on restores it.
            </p>

            <v-switch
                v-for="feature in features"
                :key="feature.key"
                v-model="settings[settingKey(feature.key)]"
                :label="feature.label"
                color="primary"
                class="mb-2"
                :hint="feature.description"
                persistent-hint
                :prepend-icon="feature.icon"
            ></v-switch>
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
import { FEATURES, featureSettingKey } from '@/configs/features.js';

defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

defineEmits(['save', 'message']);

const features = FEATURES;
const settingKey = featureSettingKey;
const message = ref('');
const alertType = ref('success');
</script>
