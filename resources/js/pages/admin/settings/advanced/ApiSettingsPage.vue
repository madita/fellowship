<template>
    <settings-page-layout
        title="API & Developer"
        description="Configure environment, debug, and rate limit settings"
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
        <settings-card icon="mdi-server" title="Server Environment" class="mb-4">
            <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                These settings are read from your server's <code>.env</code> file and cannot be changed from the admin panel.
            </v-alert>

            <v-text-field
                :model-value="serverEnvironment"
                label="Environment"
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
                :model-value="serverDebugMode ? 'Enabled' : 'Disabled'"
                label="Debug Mode"
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
                <strong>Warning:</strong> Debug mode is enabled in production. This may expose sensitive information.
            </v-alert>
        </settings-card>

        <!-- API Settings -->
        <settings-card icon="mdi-api" title="API Settings">
            <v-text-field
                v-model.number="settings.api_rate_limit_per_minute"
                label="API Rate Limit (per minute)"
                prepend-inner-icon="mdi-speedometer"
                variant="outlined"
                type="number"
                class="mb-4"
                :error-messages="errors.api_rate_limit_per_minute"
                hint="Maximum API requests per minute per user/key"
                persistent-hint
            ></v-text-field>

            <v-switch
                v-model="settings.api_keys_enabled"
                label="Enable API Keys"
                color="primary"
                class="mb-4"
                hint="Allow users to generate API keys for external integrations"
                persistent-hint
            ></v-switch>

            <v-switch
                v-model="settings.background_jobs_enabled"
                label="Enable Background Jobs"
                color="primary"
                hint="Process tasks in background queue (requires queue worker running)"
                persistent-hint
            ></v-switch>

            <v-alert v-if="settings.background_jobs_enabled" type="info" variant="tonal" density="compact" class="mt-4">
                Make sure you have a queue worker running: <code>php artisan queue:work</code>
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
            Save Settings
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
