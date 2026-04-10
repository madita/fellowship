<template>
    <div>
        <!-- Localization Settings -->
        <settings-card icon="mdi-earth" :title="$t('settings.localization.title')">
            <v-select
                v-model="settings.default_language"
                :label="$t('settings.localization.defaultLanguage')"
                :items="languages"
                item-title="name"
                item-value="code"
                prepend-inner-icon="mdi-translate"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.default_language"
            ></v-select>

            <v-autocomplete
                v-model="settings.default_timezone"
                :label="$t('settings.localization.defaultTimezone')"
                :items="timezones"
                prepend-inner-icon="mdi-clock-outline"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.default_timezone"
            ></v-autocomplete>

            <v-select
                v-model="settings.date_format"
                :label="$t('settings.localization.dateFormat')"
                :items="dateFormats"
                item-title="label"
                item-value="value"
                prepend-inner-icon="mdi-calendar"
                variant="outlined"
                class="mb-4"
            ></v-select>

            <v-select
                v-model="settings.time_format"
                :label="$t('settings.localization.timeFormat')"
                :items="timeFormats"
                item-title="label"
                item-value="value"
                prepend-inner-icon="mdi-clock"
                variant="outlined"
                class="mb-4"
            ></v-select>

            <v-switch
                v-model="settings.language_change_enabled"
                :label="$t('settings.localization.allowLanguageChange')"
                color="primary"
                :hint="settings.language_change_enabled ? $t('settings.localization.languageChangeEnabledHint') : $t('settings.localization.languageChangeDisabledHint')"
                persistent-hint
                class="mb-4"
            ></v-switch>

            <v-switch
                v-model="settings.locale_auto_detect"
                :label="$t('settings.localization.autoDetectLocale')"
                color="primary"
                :hint="settings.locale_auto_detect ? $t('settings.localization.autoDetectEnabledHint') : $t('settings.localization.autoDetectDisabledHint')"
                persistent-hint
                class="mb-4"
            ></v-switch>

            <v-select
                v-model="settings.translation_fallback_language"
                :label="$t('settings.localization.fallbackLanguage')"
                :items="languages"
                item-title="name"
                item-value="code"
                prepend-inner-icon="mdi-translate-variant"
                variant="outlined"
                :error-messages="errors.translation_fallback_language"
                :hint="$t('settings.localization.fallbackHint')"
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
        >
            {{ $t('settings.localization.saveSettings') }}
        </v-btn>
    </div>
</template>

<script setup>
import SettingsCard from '../SettingsCard.vue';
import {
    languages,
    timezones,
    dateFormats,
    timeFormats
} from '../../../composables/settingsConstants';

defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
});

defineEmits(['save']);
</script>
