<template>
    <settings-page-layout
        :title="$t('settings.branding.options.title')"
        :description="$t('settings.branding.options.description')"
        icon="mdi-toggle-switch-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'branding' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-cog-outline" :title="$t('settings.branding.options.cardTitle')">
            <v-switch
                v-model="settings.login_branding_enabled"
                :label="$t('settings.branding.options.enableLoginBranding')"
                color="primary"
                :hint="settings.login_branding_enabled ? $t('settings.branding.options.enableLoginBrandingHintEnabled') : $t('settings.branding.options.enableLoginBrandingHintDisabled')"
                persistent-hint
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
