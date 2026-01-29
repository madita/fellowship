<template>
    <settings-page-layout
        title="Search Engines"
        description="Configure search engine indexing, sitemap, and robots.txt"
        icon="mdi-robot-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'seo' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-search-web" title="Search Engine Settings">
            <v-text-field
                v-model="settings.canonical_url"
                label="Canonical URL"
                prepend-inner-icon="mdi-link-variant"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.canonical_url"
                hint="Preferred full URL for search engines (e.g., https://example.com)"
                persistent-hint
                placeholder="https://example.com"
            ></v-text-field>

            <v-switch
                v-model="settings.indexing_enabled"
                label="Allow Search Engine Indexing"
                color="primary"
                class="mb-4"
                hint="Allow search engines to index your site"
                persistent-hint
            ></v-switch>

            <v-switch
                v-model="settings.sitemap_enabled"
                label="Enable Sitemap"
                color="primary"
                class="mb-4"
                hint="Generate and maintain sitemap.xml"
                persistent-hint
            ></v-switch>

            <v-textarea
                v-model="settings.robots_txt_custom"
                label="Custom Robots.txt Rules"
                prepend-inner-icon="mdi-robot"
                variant="outlined"
                rows="4"
                :error-messages="errors.robots_txt_custom"
                hint="Custom rules for robots.txt (advanced users only)"
                persistent-hint
                placeholder="User-agent: *&#10;Disallow: /admin/"
            ></v-textarea>
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
