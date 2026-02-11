<template>
    <settings-page-layout
        :title="$t('settings.advanced.api.title')"
        :description="$t('settings.advanced.api.description')"
        icon="mdi-api"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'advanced' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <!-- Server Environment (Read-only from .env) -->
        <settings-card icon="mdi-server" :title="$t('settings.advanced.api.serverEnvironment')" class="mb-4">
            <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                {{ $t('settings.advanced.api.serverEnvironmentInfo') }}
            </v-alert>

            <v-text-field
                :model-value="serverEnvironment"
                :label="$t('settings.advanced.api.environment')"
                prepend-inner-icon="mdi-monitor"
                variant="outlined"
                class="mb-4"
                readonly
                disabled
            >
                <template #append-inner>
                    <v-chip
                        :color="environmentColor"
                        size="small"
                    >
                        {{ serverEnvironment }}
                    </v-chip>
                </template>
            </v-text-field>

            <v-text-field
                :model-value="serverDebugMode ? $t('settings.advanced.api.enabled') : $t('settings.advanced.api.disabled')"
                :label="$t('settings.advanced.api.debugMode')"
                prepend-inner-icon="mdi-bug"
                variant="outlined"
                readonly
                disabled
            >
                <template #append-inner>
                    <v-chip
                        :color="serverDebugMode ? 'warning' : 'success'"
                        size="small"
                    >
                        {{ serverDebugMode ? 'ON' : 'OFF' }}
                    </v-chip>
                </template>
            </v-text-field>

            <v-alert v-if="serverDebugMode && serverEnvironment === 'production'" type="warning" variant="tonal" density="compact" class="mt-4">
                <strong>{{ $t('settings.advanced.api.warning') }}</strong> {{ $t('settings.advanced.api.debugWarning') }}
            </v-alert>
        </settings-card>

        <!-- API Settings -->
        <settings-card icon="mdi-api" :title="$t('settings.advanced.api.apiSettings')">
            <v-text-field
                v-model.number="settings.api_rate_limit_per_minute"
                :label="$t('settings.advanced.api.rateLimit')"
                prepend-inner-icon="mdi-speedometer"
                variant="outlined"
                type="number"
                class="mb-4"
                :error-messages="errors.api_rate_limit_per_minute"
                :hint="$t('settings.advanced.api.rateLimitHint')"
                persistent-hint
            ></v-text-field>

            <v-switch
                v-model="settings.api_keys_enabled"
                :label="$t('settings.advanced.api.enableApiKeys')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.api.enableApiKeysHint')"
                persistent-hint
            ></v-switch>

            <v-switch
                v-model="settings.background_jobs_enabled"
                :label="$t('settings.advanced.api.enableBackgroundJobs')"
                color="primary"
                :hint="$t('settings.advanced.api.enableBackgroundJobsHint')"
                persistent-hint
            ></v-switch>

            <v-alert v-if="settings.background_jobs_enabled" type="info" variant="tonal" density="compact" class="mt-4">
                {{ $t('settings.advanced.api.queueWorkerInfo') }}
            </v-alert>
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
import { ref, computed, onMounted } from 'vue';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';
import axios from 'axios';

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

// Server environment info (fetched from backend)
const serverEnvironment = ref('unknown');
const serverDebugMode = ref(false);

const environmentColor = computed(() => {
    switch (serverEnvironment.value) {
        case 'production': return 'success';
        case 'staging': return 'warning';
        case 'development': return 'info';
        case 'local': return 'info';
        default: return 'grey';
    }
});

// Fetch server environment info
async function fetchServerInfo() {
    try {
        const response = await axios.get('/api/admin/settings/server-info');
        serverEnvironment.value = response.data.environment || 'unknown';
        serverDebugMode.value = response.data.debug_mode || false;
    } catch (error) {
        console.error('Failed to fetch server info:', error);
    }
}

onMounted(() => {
    fetchServerInfo();
});
</script>
