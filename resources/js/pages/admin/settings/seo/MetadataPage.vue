<template>
    <settings-page-layout
        :title="$t('settings.seo.metadata.title')"
        :description="$t('settings.seo.metadata.description')"
        icon="mdi-tag-text-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'seo' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-text-search" :title="$t('settings.seo.metadata.cardTitle')">
            <v-text-field
                v-model="settings.meta_title"
                :label="$t('settings.seo.metadata.metaTitle')"
                prepend-inner-icon="mdi-format-title"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.meta_title"
                :hint="$t('settings.seo.metadata.metaTitleHint')"
                persistent-hint
                counter="60"
            ></v-text-field>

            <v-textarea
                v-model="settings.meta_description"
                :label="$t('settings.seo.metadata.metaDescription')"
                prepend-inner-icon="mdi-text"
                variant="outlined"
                class="mb-4"
                rows="3"
                :error-messages="errors.meta_description"
                :hint="$t('settings.seo.metadata.metaDescriptionHint')"
                persistent-hint
                counter="160"
            ></v-textarea>

            <v-text-field
                v-model="settings.meta_keywords"
                :label="$t('settings.seo.metadata.metaKeywords')"
                prepend-inner-icon="mdi-key-variant"
                variant="outlined"
                :error-messages="errors.meta_keywords"
                :hint="$t('settings.seo.metadata.metaKeywordsHint')"
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
