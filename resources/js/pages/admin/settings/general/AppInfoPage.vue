<template>
    <settings-page-layout
        title="Application Info"
        description="Configure your application's basic information"
        icon="mdi-information-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'general' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-application" title="Application Info">
            <v-text-field
                v-model="settings.app_name"
                label="Application Name"
                prepend-inner-icon="mdi-application"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.app_name"
            ></v-text-field>

            <v-text-field
                v-model="settings.app_copyright"
                label="Copyright Text"
                prepend-inner-icon="mdi-copyright"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.app_copyright"
                hint="Text displayed in the footer (e.g., © Fellowship 2021)"
                persistent-hint
            ></v-text-field>

            <v-text-field
                v-model="settings.site_tagline"
                label="Site Tagline"
                prepend-inner-icon="mdi-format-quote-close"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.site_tagline"
                hint="A short tagline or slogan (e.g., 'Your Adventure Awaits')"
                persistent-hint
            ></v-text-field>

            <v-text-field
                v-model="settings.site_url"
                label="Site URL"
                prepend-inner-icon="mdi-link-variant"
                variant="outlined"
                :error-messages="errors.site_url"
                hint="The base URL of your site (e.g., https://fellowship.com)"
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
