<template>
    <div>
        <!-- Basic SEO Metadata -->
        <settings-card icon="mdi-text-search" title="Basic SEO Metadata">
            <v-text-field
                v-model="settings.meta_title"
                label="Meta Title"
                prepend-inner-icon="mdi-format-title"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.meta_title"
                hint="Default page title for SEO (50-60 characters recommended)"
                persistent-hint
                counter="60"
            ></v-text-field>

            <v-textarea
                v-model="settings.meta_description"
                label="Meta Description"
                prepend-inner-icon="mdi-text"
                variant="outlined"
                class="mb-4"
                rows="3"
                :error-messages="errors.meta_description"
                hint="Default meta description (150-160 characters recommended)"
                persistent-hint
                counter="160"
            ></v-textarea>

            <v-text-field
                v-model="settings.meta_keywords"
                label="Meta Keywords"
                prepend-inner-icon="mdi-key-variant"
                variant="outlined"
                :error-messages="errors.meta_keywords"
                hint="Comma-separated keywords (e.g., gaming, community, rpg)"
                persistent-hint
            ></v-text-field>
        </settings-card>

        <!-- Open Graph -->
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

        <!-- Twitter Card -->
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

        <!-- Search Engine Settings -->
        <settings-card icon="mdi-search-web" title="Search Engine Settings">
            <v-text-field
                v-model="settings.canonical_url"
                label="Canonical URL"
                prepend-inner-icon="mdi-link-variant"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.canonical_url"
                hint="Preferred domain for search engines"
                persistent-hint
            ></v-text-field>

            <v-switch
                v-model="settings.indexing_enabled"
                label="Allow Search Engine Indexing"
                color="primary"
                class="mb-4"
                hint="Allow search engines to index your site"
                persistent-hint
            ></v-switch>

            <v-switch
                v-model="settings.sitemap_enabled"
                label="Enable Sitemap"
                color="primary"
                class="mb-4"
                hint="Generate and maintain sitemap.xml"
                persistent-hint
            ></v-switch>

            <v-textarea
                v-model="settings.robots_txt_custom"
                label="Custom Robots.txt Rules"
                prepend-inner-icon="mdi-robot"
                variant="outlined"
                rows="4"
                :error-messages="errors.robots_txt_custom"
                hint="Custom rules for robots.txt (advanced users only)"
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
            Save Settings
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
