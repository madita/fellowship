<template>
    <settings-page-layout
        :title="$t('settings.socialMedia.title')"
        :description="$t('settings.socialMedia.description')"
        icon="mdi-share-variant-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'general' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-share-variant" :title="$t('settings.socialMedia.cardTitle')">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    {{ $t('settings.socialMedia.infoText') }}
                </div>
            </v-alert>

            <v-text-field
                v-model="settings.social_twitter"
                :label="$t('settings.socialMedia.twitter')"
                prepend-inner-icon="mdi-twitter"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.social_twitter"
                :hint="$t('settings.socialMedia.twitterHint')"
                persistent-hint
                placeholder="https://twitter.com/yourusername"
            ></v-text-field>

            <v-text-field
                v-model="settings.social_facebook"
                :label="$t('settings.socialMedia.facebook')"
                prepend-inner-icon="mdi-facebook"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.social_facebook"
                :hint="$t('settings.socialMedia.facebookHint')"
                persistent-hint
                placeholder="https://facebook.com/yourpage"
            ></v-text-field>

            <v-text-field
                v-model="settings.social_instagram"
                :label="$t('settings.socialMedia.instagram')"
                prepend-inner-icon="mdi-instagram"
                variant="outlined"
                :error-messages="errors.social_instagram"
                :hint="$t('settings.socialMedia.instagramHint')"
                persistent-hint
                placeholder="https://instagram.com/yourusername"
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
