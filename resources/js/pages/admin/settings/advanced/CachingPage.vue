<template>
    <settings-page-layout
        title="Performance & Caching"
        description="Configure cache settings and management"
        icon="mdi-cached"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'advanced' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-speedometer" title="Performance & Caching">
            <v-switch
                v-model="settings.cache_enabled"
                label="Enable Caching"
                color="primary"
                class="mb-4"
                hint="Enable application-wide caching"
                persistent-hint
            ></v-switch>

            <v-text-field
                v-model.number="settings.cache_lifetime_minutes"
                label="Cache Lifetime (minutes)"
                prepend-inner-icon="mdi-clock"
                variant="outlined"
                type="number"
                class="mb-4"
                :error-messages="errors.cache_lifetime_minutes"
                hint="How long to cache data (in minutes)"
                persistent-hint
            ></v-text-field>

            <v-switch
                v-model="settings.cdn_enabled"
                label="Enable CDN"
                color="primary"
                class="mb-4"
                hint="Use CDN for static assets"
                persistent-hint
            ></v-switch>

            <v-text-field
                v-if="settings.cdn_enabled"
                v-model="settings.cdn_url"
                label="CDN URL"
                prepend-inner-icon="mdi-server-network"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.cdn_url"
                hint="CDN base URL for assets"
                persistent-hint
            ></v-text-field>

            <v-switch
                v-model="settings.image_optimization_enabled"
                label="Enable Image Optimization"
                color="primary"
                class="mb-4"
                hint="Compress and optimize uploaded images (JPEG: 85% quality, PNG: optimized, backgrounds: max 1920x1080)"
                persistent-hint
            ></v-switch>

            <v-switch
                v-model="settings.lazy_loading_enabled"
                label="Enable Lazy Loading"
                color="primary"
                class="mb-4"
                hint="Defer loading of images and iframes until they enter the viewport (improves initial page load)"
                persistent-hint
            ></v-switch>

            <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>Performance Tips:</strong>
                    <ul class="mt-1 ml-4">
                        <li><strong>Image Optimization:</strong> Reduces file size while maintaining quality. Uses Jpegoptim, Pngquant, and WebP conversion.</li>
                        <li><strong>Lazy Loading:</strong> Uses native browser lazy loading (<code>loading="lazy"</code>) and IntersectionObserver for better performance.</li>
                    </ul>
                </div>
            </v-alert>

            <v-divider class="my-6"></v-divider>

            <!-- Cache Management -->
            <div class="d-flex align-center justify-space-between mb-4">
                <div>
                    <div class="text-subtitle-1 font-weight-medium">Cache Management</div>
                    <div class="text-caption text-medium-emphasis">
                        Clear application cache to refresh data. Cached content types: settings, pages, wiki, posts, widgets, and HTTP responses.
                    </div>
                </div>
                <div class="d-flex align-center ga-2">
                    <v-chip
                        v-if="cacheStatus"
                        :color="cacheStatus.enabled ? 'success' : 'warning'"
                        size="small"
                        variant="tonal"
                    >
                        {{ cacheStatus.enabled ? 'Cache Enabled' : 'Cache Disabled' }}
                    </v-chip>
                    <v-chip
                        v-if="cacheStatus"
                        size="small"
                        variant="outlined"
                    >
                        {{ cacheStatus.lifetime_minutes }} min TTL
                    </v-chip>
                    <v-chip
                        v-if="cacheStatus"
                        size="small"
                        variant="outlined"
                        color="info"
                    >
                        {{ cacheStatus.driver }}
                    </v-chip>
                </div>
            </div>

            <!-- System Cache -->
            <div class="text-caption text-medium-emphasis mb-2 mt-4">System Cache</div>
            <v-row dense>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'settings'"
                        @click="clearCache('settings')"
                    >
                        <v-icon start size="small">mdi-cog</v-icon>
                        Settings
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'views'"
                        @click="clearCache('views')"
                    >
                        <v-icon start size="small">mdi-file-document</v-icon>
                        Views
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'routes'"
                        @click="clearCache('routes')"
                    >
                        <v-icon start size="small">mdi-routes</v-icon>
                        Routes
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'config'"
                        @click="clearCache('config')"
                    >
                        <v-icon start size="small">mdi-wrench</v-icon>
                        Config
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'application'"
                        @click="clearCache('application')"
                    >
                        <v-icon start size="small">mdi-database</v-icon>
                        App
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'http'"
                        @click="clearCache('http')"
                    >
                        <v-icon start size="small">mdi-web</v-icon>
                        HTTP
                    </v-btn>
                </v-col>
            </v-row>

            <!-- Content Cache -->
            <div class="text-caption text-medium-emphasis mb-2 mt-4">Content Cache</div>
            <v-row dense>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'pages'"
                        @click="clearCache('pages')"
                    >
                        <v-icon start size="small">mdi-file-multiple</v-icon>
                        Pages
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'wiki'"
                        @click="clearCache('wiki')"
                    >
                        <v-icon start size="small">mdi-book-open-page-variant</v-icon>
                        Wiki
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'posts'"
                        @click="clearCache('posts')"
                    >
                        <v-icon start size="small">mdi-post</v-icon>
                        Posts
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'widgets'"
                        @click="clearCache('widgets')"
                    >
                        <v-icon start size="small">mdi-widgets</v-icon>
                        Widgets
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="4">
                    <v-btn
                        block
                        size="small"
                        color="error"
                        variant="tonal"
                        :loading="clearingCache === 'all'"
                        @click="clearCache('all')"
                    >
                        <v-icon start size="small">mdi-delete-sweep</v-icon>
                        Clear All Caches
                    </v-btn>
                </v-col>
            </v-row>
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
import { ref, onMounted } from 'vue';
import { useApi } from '@/api/useAPI.js';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';

const api = useApi('api');

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
const cacheStatus = ref(null);
const clearingCache = ref(null);

async function fetchCacheStatus() {
    try {
        const response = await api.get('/admin/settings/cache-status');
        cacheStatus.value = response.data;
    } catch (error) {
        console.error('Failed to fetch cache status:', error);
    }
}

async function clearCache(type) {
    clearingCache.value = type;
    try {
        const response = await api.post('/admin/settings/clear-cache', { type });
        emit('message', {
            text: `Cache cleared successfully: ${response.data.cleared.join(', ')}`,
            type: 'success'
        });
        await fetchCacheStatus();
    } catch (error) {
        console.error('Failed to clear cache:', error);
        emit('message', {
            text: 'Failed to clear cache: ' + (error.response?.data?.message || error.message),
            type: 'error'
        });
    } finally {
        clearingCache.value = null;
    }
}

onMounted(() => {
    fetchCacheStatus();
});
</script>
