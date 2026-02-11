<template>
    <settings-page-layout
        :title="$t('settings.advanced.caching.title')"
        :description="$t('settings.advanced.caching.description')"
        icon="mdi-cached"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'advanced' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-speedometer" :title="$t('settings.advanced.caching.cardTitle')">
            <v-switch
                v-model="settings.cache_enabled"
                :label="$t('settings.advanced.caching.enableCaching')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.caching.enableCachingHint')"
                persistent-hint
            ></v-switch>

            <v-text-field
                v-model.number="settings.cache_lifetime_minutes"
                :label="$t('settings.advanced.caching.cacheLifetime')"
                prepend-inner-icon="mdi-clock"
                variant="outlined"
                type="number"
                class="mb-4"
                :error-messages="errors.cache_lifetime_minutes"
                :hint="$t('settings.advanced.caching.cacheLifetimeHint')"
                persistent-hint
            ></v-text-field>

            <v-switch
                v-model="settings.cdn_enabled"
                :label="$t('settings.advanced.caching.enableCdn')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.caching.enableCdnHint')"
                persistent-hint
            ></v-switch>

            <v-text-field
                v-if="settings.cdn_enabled"
                v-model="settings.cdn_url"
                :label="$t('settings.advanced.caching.cdnUrl')"
                prepend-inner-icon="mdi-server-network"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.cdn_url"
                :hint="$t('settings.advanced.caching.cdnUrlHint')"
                persistent-hint
            ></v-text-field>

            <v-switch
                v-model="settings.image_optimization_enabled"
                :label="$t('settings.advanced.caching.enableImageOptimization')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.caching.enableImageOptimizationHint')"
                persistent-hint
            ></v-switch>

            <v-switch
                v-model="settings.lazy_loading_enabled"
                :label="$t('settings.advanced.caching.enableLazyLoading')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.caching.enableLazyLoadingHint')"
                persistent-hint
            ></v-switch>

            <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>{{ $t('settings.advanced.caching.performanceTips') }}</strong>
                    <ul class="mt-1 ml-4">
                        <li><strong>{{ $t('settings.advanced.caching.imageOptimization') }}</strong> {{ $t('settings.advanced.caching.imageOptimizationDesc') }}</li>
                        <li><strong>{{ $t('settings.advanced.caching.lazyLoading') }}</strong> {{ $t('settings.advanced.caching.lazyLoadingDesc') }}</li>
                    </ul>
                </div>
            </v-alert>

            <v-divider class="my-6"></v-divider>

            <!-- Cache Management -->
            <div class="d-flex align-center justify-space-between mb-4">
                <div>
                    <div class="text-subtitle-1 font-weight-medium">{{ $t('settings.advanced.caching.cacheManagement') }}</div>
                    <div class="text-caption text-medium-emphasis">
                        {{ $t('settings.advanced.caching.cacheManagementDesc') }}
                    </div>
                </div>
                <div class="d-flex align-center ga-2">
                    <v-chip
                        v-if="cacheStatus"
                        :color="cacheStatus.enabled ? 'success' : 'warning'"
                        size="small"
                        variant="tonal"
                    >
                        {{ cacheStatus.enabled ? $t('settings.advanced.caching.cacheEnabled') : $t('settings.advanced.caching.cacheDisabled') }}
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
            <div class="text-caption text-medium-emphasis mb-2 mt-4">{{ $t('settings.advanced.caching.systemCache') }}</div>
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
                        {{ $t('settings.advanced.caching.settings') }}
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
                        {{ $t('settings.advanced.caching.views') }}
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
                        {{ $t('settings.advanced.caching.routes') }}
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
                        {{ $t('settings.advanced.caching.config') }}
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
                        {{ $t('settings.advanced.caching.app') }}
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
            <div class="text-caption text-medium-emphasis mb-2 mt-4">{{ $t('settings.advanced.caching.contentCache') }}</div>
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
                        {{ $t('settings.advanced.caching.pages') }}
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
                        {{ $t('settings.advanced.caching.posts') }}
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
                        {{ $t('settings.advanced.caching.clearAllCaches') }}
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
            {{ $t('settings.saveSettings') }}
        </v-btn>
    </settings-page-layout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/api/useAPI.js';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';

const { t } = useI18n();
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
            text: t('settings.advanced.caching.cacheCleared', { types: response.data.cleared.join(', ') }),
            type: 'success'
        });
        await fetchCacheStatus();
    } catch (error) {
        console.error('Failed to clear cache:', error);
        emit('message', {
            text: t('settings.advanced.caching.cacheClearFailed') + ': ' + (error.response?.data?.message || error.message),
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
