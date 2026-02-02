<template>
    <settings-page-layout
        title="Social Media"
        description="Configure your social media profile links"
        icon="mdi-share-variant-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'general' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-share-variant" title="Social Media Links">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    Add your social media profiles. These links will appear in your site footer.
                </div>
            </v-alert>

            <v-text-field
                v-model="settings.social_twitter"
                label="Twitter / X URL"
                prepend-inner-icon="mdi-twitter"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.social_twitter"
                hint="Full URL to your Twitter/X profile"
                persistent-hint
                placeholder="https://twitter.com/yourusername"
            ></v-text-field>

            <v-text-field
                v-model="settings.social_facebook"
                label="Facebook URL"
                prepend-inner-icon="mdi-facebook"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.social_facebook"
                hint="Full URL to your Facebook page"
                persistent-hint
                placeholder="https://facebook.com/yourpage"
            ></v-text-field>

            <v-text-field
                v-model="settings.social_instagram"
                label="Instagram URL"
                prepend-inner-icon="mdi-instagram"
                variant="outlined"
                :error-messages="errors.social_instagram"
                hint="Full URL to your Instagram profile"
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
            Save Settings
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
