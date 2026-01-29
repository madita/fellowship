<template>
    <settings-page-layout
        title="Open Graph"
        description="Configure social media sharing preview settings"
        icon="mdi-share-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'seo' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-share-variant" title="Open Graph (Social Media)">
            <v-text-field
                v-model="settings.og_title"
                label="OG Title"
                prepend-inner-icon="mdi-format-title"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.og_title"
                hint="Title shown when shared on social media"
                persistent-hint
            ></v-text-field>

            <v-textarea
                v-model="settings.og_description"
                label="OG Description"
                prepend-inner-icon="mdi-text"
                variant="outlined"
                class="mb-4"
                rows="2"
                :error-messages="errors.og_description"
                hint="Description shown when shared on social media"
                persistent-hint
            ></v-textarea>

            <div class="text-subtitle-2 mb-2">OG Image</div>
            <image-upload
                image-key="og_image"
                label="Upload OG Image"
                :current-image="settings.og_image"
                hint="Recommended: 1200x630px (optimized for social media sharing)"
                :max-height="300"
                :max-width="600"
                placeholder-size="large"
                @uploaded="handleImageUploaded"
                @deleted="handleImageDeleted"
                @error="handleImageError"
            />
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
import ImageUpload from '@/components/settings/ImageUpload.vue';

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

const emit = defineEmits(['save', 'message']);

const message = ref('');
const alertType = ref('success');

function handleImageUploaded({ key, path }) {
    props.settings[key] = path;
    emit('message', { text: 'OG image uploaded successfully', type: 'success' });
}

function handleImageDeleted(key) {
    props.settings[key] = null;
    emit('message', { text: 'OG image deleted successfully', type: 'success' });
}

function handleImageError(errorMessage) {
    emit('message', { text: errorMessage, type: 'error' });
}
</script>
