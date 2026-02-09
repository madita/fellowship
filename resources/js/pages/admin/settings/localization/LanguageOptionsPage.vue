<template>
    <settings-page-layout
        :title="$t('settings.localization.languageOptions.title')"
        :description="$t('settings.localization.languageOptions.description')"
        icon="mdi-translate"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'localization' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-translate" :title="$t('settings.localization.languageOptions.cardTitle')">
            <v-switch
                v-model="settings.language_change_enabled"
                :label="$t('settings.localization.languageOptions.allowChange')"
                color="primary"
                :hint="settings.language_change_enabled ? $t('settings.localization.languageOptions.allowChangeHintEnabled') : $t('settings.localization.languageOptions.allowChangeHintDisabled')"
                persistent-hint
                class="mb-4"
            ></v-switch>

            <v-switch
                v-model="settings.locale_auto_detect"
                :label="$t('settings.localization.languageOptions.autoDetect')"
                color="primary"
                :hint="settings.locale_auto_detect ? $t('settings.localization.languageOptions.autoDetectHintEnabled') : $t('settings.localization.languageOptions.autoDetectHintDisabled')"
                persistent-hint
                class="mb-4"
            ></v-switch>

            <v-select
                v-model="settings.translation_fallback_language"
                :label="$t('settings.localization.languageOptions.fallbackLanguage')"
                :items="languages"
                item-title="name"
                item-value="code"
                prepend-inner-icon="mdi-translate-variant"
                variant="outlined"
                :error-messages="errors.translation_fallback_language"
                :hint="$t('settings.localization.languageOptions.fallbackLanguageHint')"
                persistent-hint
            ></v-select>
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
import { languages } from '@/composables/settingsConstants';

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
