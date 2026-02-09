<template>
    <settings-page-layout
        :title="$t('settings.seo.openGraph.title')"
        :description="$t('settings.seo.openGraph.description')"
        icon="mdi-share-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'seo' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-share-variant" :title="$t('settings.seo.openGraph.cardTitle')">
            <v-text-field
                v-model="settings.og_title"
                :label="$t('settings.seo.openGraph.ogTitle')"
                prepend-inner-icon="mdi-format-title"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.og_title"
                :hint="$t('settings.seo.openGraph.ogTitleHint')"
                persistent-hint
            ></v-text-field>

            <v-textarea
                v-model="settings.og_description"
                :label="$t('settings.seo.openGraph.ogDescription')"
                prepend-inner-icon="mdi-text"
                variant="outlined"
                class="mb-4"
                rows="2"
                :error-messages="errors.og_description"
                :hint="$t('settings.seo.openGraph.ogDescriptionHint')"
                persistent-hint
            ></v-textarea>

            <div class="text-subtitle-2 mb-2">{{ $t('settings.seo.openGraph.ogImage') }}</div>
            <image-upload
                image-key="og_image"
                :label="$t('settings.seo.openGraph.uploadOgImage')"
                :current-image="settings.og_image"
                :hint="$t('settings.seo.openGraph.ogImageHint')"
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
            {{ $t('settings.saveSettings') }}
        </v-btn>
    </settings-page-layout>
</template>

<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';
import ImageUpload from '@/components/settings/ImageUpload.vue';

const { t } = useI18n();

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
    emit('message', { text: t('settings.seo.openGraph.uploadSuccess'), type: 'success' });
}

function handleImageDeleted(key) {
    props.settings[key] = null;
    emit('message', { text: t('settings.seo.openGraph.deleteSuccess'), type: 'success' });
}

function handleImageError(errorMessage) {
    emit('message', { text: errorMessage, type: 'error' });
}
</script>
