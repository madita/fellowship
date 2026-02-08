<template>
    <div>
        <!-- Basic SEO Metadata -->
        <settings-card icon="mdi-text-search" :title="$t('settings.seoSettings.basicMetadata')">
            <v-text-field
                v-model="settings.meta_title"
                :label="$t('settings.seoSettings.metaTitle')"
                prepend-inner-icon="mdi-format-title"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.meta_title"
                :hint="$t('settings.seoSettings.metaTitleHint')"
                persistent-hint
                counter="60"
            ></v-text-field>

            <v-textarea
                v-model="settings.meta_description"
                :label="$t('settings.seoSettings.metaDescription')"
                prepend-inner-icon="mdi-text"
                variant="outlined"
                class="mb-4"
                rows="3"
                :error-messages="errors.meta_description"
                :hint="$t('settings.seoSettings.metaDescriptionHint')"
                persistent-hint
                counter="160"
            ></v-textarea>

            <v-text-field
                v-model="settings.meta_keywords"
                :label="$t('settings.seoSettings.metaKeywords')"
                prepend-inner-icon="mdi-key-variant"
                variant="outlined"
                :error-messages="errors.meta_keywords"
                :hint="$t('settings.seoSettings.metaKeywordsHint')"
                persistent-hint
            ></v-text-field>
        </settings-card>

        <!-- Open Graph -->
        <settings-card icon="mdi-share-variant" :title="$t('settings.seoSettings.openGraph')">
            <v-text-field
                v-model="settings.og_title"
                :label="$t('settings.seoSettings.ogTitle')"
                prepend-inner-icon="mdi-format-title"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.og_title"
                :hint="$t('settings.seoSettings.ogTitleHint')"
                persistent-hint
            ></v-text-field>

            <v-textarea
                v-model="settings.og_description"
                :label="$t('settings.seoSettings.ogDescription')"
                prepend-inner-icon="mdi-text"
                variant="outlined"
                class="mb-4"
                rows="2"
                :error-messages="errors.og_description"
                :hint="$t('settings.seoSettings.ogDescriptionHint')"
                persistent-hint
            ></v-textarea>

            <div class="text-subtitle-2 mb-2">{{ $t('settings.seoSettings.ogImage') }}</div>
            <image-upload
                image-key="og_image"
                :label="$t('settings.seoSettings.uploadOgImage')"
                :current-image="settings.og_image"
                :hint="$t('settings.seoSettings.ogImageHint')"
                :max-height="300"
                :max-width="600"
                placeholder-size="large"
                @uploaded="handleImageUploaded"
                @deleted="handleImageDeleted"
                @error="handleImageError"
            />
        </settings-card>

        <!-- Twitter Card -->
        <settings-card icon="mdi-twitter" :title="$t('settings.seoSettings.twitterCard')">
            <v-select
                v-model="settings.twitter_card_type"
                :label="$t('settings.seoSettings.twitterCardType')"
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
                :label="$t('settings.seoSettings.twitterSiteHandle')"
                prepend-inner-icon="mdi-at"
                variant="outlined"
                :error-messages="errors.twitter_site"
                :hint="$t('settings.seoSettings.twitterSiteHint')"
                persistent-hint
                placeholder="@yourhandle"
            ></v-text-field>
        </settings-card>

        <!-- Search Engine Settings -->
        <settings-card icon="mdi-search-web" :title="$t('settings.seoSettings.searchEngineSettings')">
            <v-text-field
                v-model="settings.canonical_url"
                :label="$t('settings.seoSettings.canonicalUrl')"
                prepend-inner-icon="mdi-link-variant"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.canonical_url"
                :hint="$t('settings.seoSettings.canonicalUrlHint')"
                persistent-hint
                placeholder="https://example.com"
            ></v-text-field>

            <v-switch
                v-model="settings.indexing_enabled"
                :label="$t('settings.seoSettings.allowIndexing')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.seoSettings.allowIndexingHint')"
                persistent-hint
            ></v-switch>

            <v-switch
                v-model="settings.sitemap_enabled"
                :label="$t('settings.seoSettings.enableSitemap')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.seoSettings.enableSitemapHint')"
                persistent-hint
            ></v-switch>

            <v-textarea
                v-model="settings.robots_txt_custom"
                :label="$t('settings.seoSettings.customRobots')"
                prepend-inner-icon="mdi-robot"
                variant="outlined"
                rows="4"
                :error-messages="errors.robots_txt_custom"
                :hint="$t('settings.seoSettings.customRobotsHint')"
                persistent-hint
                placeholder="User-agent: *&#10;Disallow: /admin/"
            ></v-textarea>
        </settings-card>

        <v-btn
            :loading="isSaving"
            block
            size="large"
            color="primary"
            @click="$emit('save')"
            prepend-icon="mdi-content-save"
        >
            {{ $t('settings.seoSettings.saveSettings') }}
        </v-btn>
    </div>
</template>

<script setup>
import SettingsCard from '../SettingsCard.vue';
import ImageUpload from '../ImageUpload.vue';
import { twitterCardTypes } from '../../../composables/settingsConstants';

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
});

const emit = defineEmits(['save', 'message']);

function handleImageUploaded({ key, path }) {
    props.settings[key] = path;
    emit('message', { text: 'OG image uploaded successfully', type: 'success' });
}

function handleImageDeleted(key) {
    props.settings[key] = null;
    emit('message', { text: 'OG image deleted successfully', type: 'success' });
}

function handleImageError(message) {
    emit('message', { text: message, type: 'error' });
}
</script>
