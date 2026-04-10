<template>
    <settings-page-layout
        :title="$t('settings.seo.twitterCard.title')"
        :description="$t('settings.seo.twitterCard.description')"
        icon="mdi-twitter"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'seo' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-twitter" :title="$t('settings.seo.twitterCard.cardTitle')">
            <v-select
                v-model="settings.twitter_card_type"
                :label="$t('settings.seo.twitterCard.cardType')"
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
                :label="$t('settings.seo.twitterCard.siteHandle')"
                prepend-inner-icon="mdi-at"
                variant="outlined"
                :error-messages="errors.twitter_site"
                :hint="$t('settings.seo.twitterCard.siteHandleHint')"
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
            {{ $t('settings.saveSettings') }}
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
