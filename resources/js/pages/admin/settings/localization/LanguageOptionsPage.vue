<template>
    <settings-page-layout
        title="Language Options"
        description="Configure user language preferences and fallback settings"
        icon="mdi-translate"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'localization' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-translate" title="Language Options">
            <v-switch
                v-model="settings.language_change_enabled"
                label="Allow users to change language"
                color="primary"
                :hint="settings.language_change_enabled ? 'Users can switch between available languages' : 'Language selection is disabled for users'"
                persistent-hint
                class="mb-4"
            ></v-switch>

            <v-switch
                v-model="settings.locale_auto_detect"
                label="Auto-detect user locale"
                color="primary"
                :hint="settings.locale_auto_detect ? 'Automatically detect user language from browser' : 'Use default language for all users'"
                persistent-hint
                class="mb-4"
            ></v-switch>

            <v-select
                v-model="settings.translation_fallback_language"
                label="Translation Fallback Language"
                :items="languages"
                item-title="name"
                item-value="code"
                prepend-inner-icon="mdi-translate-variant"
                variant="outlined"
                :error-messages="errors.translation_fallback_language"
                hint="Language to use when translation is missing"
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
            Save Settings
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
