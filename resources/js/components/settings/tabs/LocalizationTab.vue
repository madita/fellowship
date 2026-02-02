<template>
    <div>
        <!-- Localization Settings -->
        <settings-card icon="mdi-earth" title="Localization Settings">
            <v-select
                v-model="settings.default_language"
                label="Default Language"
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
                label="Default Timezone"
                :items="timezones"
                prepend-inner-icon="mdi-clock-outline"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.default_timezone"
            ></v-autocomplete>

            <v-select
                v-model="settings.date_format"
                label="Date Format"
                :items="dateFormats"
                item-title="label"
                item-value="value"
                prepend-inner-icon="mdi-calendar"
                variant="outlined"
                class="mb-4"
            ></v-select>

            <v-select
                v-model="settings.time_format"
                label="Time Format"
                :items="timeFormats"
                item-title="label"
                item-value="value"
                prepend-inner-icon="mdi-clock"
                variant="outlined"
                class="mb-4"
            ></v-select>

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
        >
            Save Settings
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
