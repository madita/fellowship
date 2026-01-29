<template>
    <settings-page-layout
        title="Basic Metadata"
        description="Configure your site's default SEO metadata"
        icon="mdi-tag-text-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'seo' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-text-search" title="Basic SEO Metadata">
            <v-text-field
                v-model="settings.meta_title"
                label="Meta Title"
                prepend-inner-icon="mdi-format-title"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.meta_title"
                hint="Default page title for SEO (50-60 characters recommended)"
                persistent-hint
                counter="60"
            ></v-text-field>

            <v-textarea
                v-model="settings.meta_description"
                label="Meta Description"
                prepend-inner-icon="mdi-text"
                variant="outlined"
                class="mb-4"
                rows="3"
                :error-messages="errors.meta_description"
                hint="Default meta description (150-160 characters recommended)"
                persistent-hint
                counter="160"
            ></v-textarea>

            <v-text-field
                v-model="settings.meta_keywords"
                label="Meta Keywords"
                prepend-inner-icon="mdi-key-variant"
                variant="outlined"
                :error-messages="errors.meta_keywords"
                hint="Comma-separated keywords (e.g., gaming, community, rpg)"
                persistent-hint
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

const props = defineProps({
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
