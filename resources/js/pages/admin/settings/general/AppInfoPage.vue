<template>
    <settings-page-layout
        :title="$t('settings.appInfo.title')"
        :description="$t('settings.appInfo.description')"
        icon="mdi-information-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'general' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-application" :title="$t('settings.appInfo.cardTitle')">
            <v-text-field
                v-model="settings.app_name"
                :label="$t('settings.appInfo.appName')"
                prepend-inner-icon="mdi-application"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.app_name"
            ></v-text-field>

            <v-text-field
                v-model="settings.app_copyright"
                :label="$t('settings.appInfo.copyright')"
                prepend-inner-icon="mdi-copyright"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.app_copyright"
                :hint="$t('settings.appInfo.copyrightHint')"
                persistent-hint
            ></v-text-field>

            <v-text-field
                v-model="settings.site_tagline"
                :label="$t('settings.appInfo.tagline')"
                prepend-inner-icon="mdi-format-quote-close"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.site_tagline"
                :hint="$t('settings.appInfo.taglineHint')"
                persistent-hint
            ></v-text-field>

            <v-text-field
                v-model="settings.site_url"
                :label="$t('settings.appInfo.siteUrl')"
                prepend-inner-icon="mdi-link-variant"
                variant="outlined"
                :error-messages="errors.site_url"
                :hint="$t('settings.appInfo.siteUrlHint')"
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
