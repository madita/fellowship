<template>
    <settings-page-layout
        title="Theme Mode"
        description="Configure the default theme mode for your application"
        icon="mdi-brightness-6"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'theme' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-theme-light-dark" title="Theme Mode">
            <v-select
                v-model="settings.theme_mode"
                label="Default Theme Mode"
                :items="themeModes"
                item-title="label"
                item-value="value"
                prepend-inner-icon="mdi-theme-light-dark"
                variant="outlined"
                :error-messages="errors.theme_mode"
                hint="Default theme appearance for users"
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
import { themeModes } from '@/composables/settingsConstants';

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
