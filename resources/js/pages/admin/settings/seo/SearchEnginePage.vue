<template>
    <settings-page-layout
        :title="$t('settings.seo.searchEngine.title')"
        :description="$t('settings.seo.searchEngine.description')"
        icon="mdi-robot-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'seo' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-search-web" :title="$t('settings.seo.searchEngine.cardTitle')">
            <v-text-field
                v-model="settings.canonical_url"
                :label="$t('settings.seo.searchEngine.canonicalUrl')"
                prepend-inner-icon="mdi-link-variant"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.canonical_url"
                :hint="$t('settings.seo.searchEngine.canonicalUrlHint')"
                persistent-hint
                placeholder="https://example.com"
            ></v-text-field>

            <v-switch
                v-model="settings.indexing_enabled"
                :label="$t('settings.seo.searchEngine.indexingEnabled')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.seo.searchEngine.indexingEnabledHint')"
                persistent-hint
            ></v-switch>

            <v-switch
                v-model="settings.sitemap_enabled"
                :label="$t('settings.seo.searchEngine.sitemapEnabled')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.seo.searchEngine.sitemapEnabledHint')"
                persistent-hint
            ></v-switch>

            <v-textarea
                v-model="settings.robots_txt_custom"
                :label="$t('settings.seo.searchEngine.robotsTxt')"
                prepend-inner-icon="mdi-robot"
                variant="outlined"
                rows="4"
                :error-messages="errors.robots_txt_custom"
                :hint="$t('settings.seo.searchEngine.robotsTxtHint')"
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
            {{ $t('settings.saveSettings') }}
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
