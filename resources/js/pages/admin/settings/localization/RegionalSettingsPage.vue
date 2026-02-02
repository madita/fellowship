<template>
    <settings-page-layout
        title="Regional Settings"
        description="Configure language, timezone, and date/time formats"
        icon="mdi-map-clock-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'localization' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-earth" title="Regional Settings">
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
import {
    languages,
    timezones,
    dateFormats,
    timeFormats
} from '@/composables/settingsConstants';

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
