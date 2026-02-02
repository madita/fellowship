<template>
    <settings-page-layout
        title="Twitter Card"
        description="Configure Twitter/X sharing settings"
        icon="mdi-twitter"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'seo' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-twitter" title="Twitter Card Settings">
            <v-select
                v-model="settings.twitter_card_type"
                label="Twitter Card Type"
                :items="twitterCardTypes"
                item-title="label"
                item-value="value"
                prepend-inner-icon="mdi-card-text"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.twitter_card_type"
            ></v-select>

            <v-text-field
                v-model="settings.twitter_site"
                label="Twitter Site Handle"
                prepend-inner-icon="mdi-at"
                variant="outlined"
                :error-messages="errors.twitter_site"
                hint="Your Twitter username (e.g., @yourhandle)"
                persistent-hint
                placeholder="@yourhandle"
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
import { twitterCardTypes } from '@/composables/settingsConstants';

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
