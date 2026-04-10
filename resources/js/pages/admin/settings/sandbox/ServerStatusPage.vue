<template>
    <settings-page-layout
        title="WebSocket Server"
        description="Yjs WebSocket server status and connection details"
        icon="mdi-server-network"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'sandbox' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-server-network" title="Server Status">
            <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    The Yjs WebSocket server enables real-time collaborative editing in sandboxes.
                    It must be running for users to see each other's cursors and edits in real time.
                </div>
            </v-alert>

            <div class="d-flex align-center justify-space-between mb-4">
                <div>
                    <div class="text-subtitle-1 font-weight-medium">Connection Status</div>
                    <div class="text-caption text-medium-emphasis">
                        Real-time connection status of the Yjs WebSocket server
                    </div>
                </div>
                <div class="d-flex align-center ga-2">
                    <v-chip
                        :color="wsStatus.connected ? 'success' : 'error'"
                        size="small"
                        variant="tonal"
                    >
                        <v-icon start size="small">
                            {{ wsStatus.connected ? 'mdi-check-circle' : 'mdi-alert-circle' }}
                        </v-icon>
                        {{ wsStatus.connected ? 'Connected' : 'Disconnected' }}
                    </v-chip>
                    <v-chip
                        v-if="wsStatus.latency_ms !== null"
                        size="small"
                        variant="outlined"
                        color="info"
                    >
                        {{ wsStatus.latency_ms }}ms
                    </v-chip>
                    <v-btn
                        icon="mdi-refresh"
                        size="x-small"
                        variant="text"
                        :loading="loadingStatus"
                        @click="fetchWsStatus"
                    ></v-btn>
                </div>
            </div>

            <v-card variant="outlined" class="pa-3 mb-4">
                <div class="d-flex justify-space-between align-center mb-2">
                    <span class="text-body-2">Host:</span>
                    <code class="text-body-2 font-weight-medium">{{ wsStatus.host || '—' }}</code>
                </div>
                <v-divider class="my-2"></v-divider>
                <div class="d-flex justify-space-between align-center mb-2">
                    <span class="text-body-2">Port:</span>
                    <code class="text-body-2 font-weight-medium">{{ wsStatus.port || '—' }}</code>
                </div>
                <v-divider class="my-2"></v-divider>
                <div class="d-flex justify-space-between align-center mb-2">
                    <span class="text-body-2">Latency:</span>
                    <span class="text-body-2 font-weight-medium">
                        {{ wsStatus.latency_ms !== null ? wsStatus.latency_ms + 'ms' : '—' }}
                    </span>
                </div>
                <v-divider class="my-2"></v-divider>
                <div class="d-flex justify-space-between align-center">
                    <span class="text-body-2">Status:</span>
                    <v-chip
                        :color="wsStatus.connected ? 'success' : 'error'"
                        size="x-small"
                        variant="tonal"
                    >
                        {{ wsStatus.connected ? 'Running' : 'Not Running' }}
                    </v-chip>
                </div>
            </v-card>

            <v-alert
                v-if="wsStatus.error"
                type="error"
                variant="tonal"
                density="compact"
                class="mb-4"
            >
                <strong>Connection Error:</strong> {{ wsStatus.error }}
            </v-alert>

            <v-alert
                v-if="!wsStatus.connected && !loadingStatus"
                type="warning"
                variant="tonal"
                density="compact"
                class="mb-4"
            >
                <div class="text-caption">
                    <strong>Server is not running.</strong> Start the Yjs server with:
                    <code class="d-block mt-1 pa-1">npm run yjs-server</code>
                    or
                    <code class="d-block mt-1 pa-1">node tools/yjs-server.mjs</code>
                </div>
            </v-alert>
        </settings-card>

        <settings-card icon="mdi-cog-outline" title="Server Configuration">
            <v-alert type="warning" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>Note:</strong> Changing these values requires updating your <code>.env</code> file
                    and restarting the Yjs server. These fields show the current configuration.
                </div>
            </v-alert>

            <v-text-field
                :model-value="wsStatus.host || '127.0.0.1'"
                label="WebSocket Host"
                prepend-inner-icon="mdi-server"
                variant="outlined"
                class="mb-4"
                readonly
                disabled
                hint="Set via YJS_WS_HOST in .env"
                persistent-hint
            ></v-text-field>

            <v-text-field
                :model-value="wsStatus.port || 1234"
                label="WebSocket Port"
                prepend-inner-icon="mdi-ethernet"
                variant="outlined"
                class="mb-4"
                readonly
                disabled
                hint="Set via YJS_WS_PORT in .env"
                persistent-hint
            ></v-text-field>

            <v-alert type="info" variant="tonal" density="compact">
                <div class="text-caption">
                    <strong>Environment Variables:</strong>
                    <ul class="mt-1 ml-4">
                        <li><code>YJS_WS_HOST</code> — Host address for the Yjs server (default: 127.0.0.1)</li>
                        <li><code>YJS_WS_PORT</code> — Port for the Yjs server (default: 1234)</li>
                    </ul>
                    <div class="mt-2">
                        After changing these values, restart the Yjs server:
                        <code class="d-block mt-1">npm run yjs-server</code>
                    </div>
                </div>
            </v-alert>
        </settings-card>
    </settings-page-layout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
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

defineEmits(['save', 'message']);

const message = ref('');
const alertType = ref('success');
const loadingStatus = ref(false);

const wsStatus = reactive({
    host: null,
    port: null,
    connected: false,
    latency_ms: null,
    error: null,
});

async function fetchWsStatus() {
    loadingStatus.value = true;
    try {
        const response = await api.get('/sandbox/status');
        Object.assign(wsStatus, response.data.websocket);
    } catch (error) {
        wsStatus.connected = false;
        wsStatus.error = error.response?.data?.message || 'Failed to check server status';
    } finally {
        loadingStatus.value = false;
    }
}

onMounted(() => {
    fetchWsStatus();
});
</script>
