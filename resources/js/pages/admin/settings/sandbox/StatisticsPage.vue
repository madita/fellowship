<template>
    <settings-page-layout
        title="Statistics"
        description="Sandbox usage and activity statistics"
        icon="mdi-chart-bar"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'sandbox' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-chart-bar" title="Sandbox Statistics">
            <div class="d-flex align-center justify-space-between mb-4">
                <div class="text-subtitle-1 font-weight-medium">Usage Overview</div>
                <v-btn
                    icon="mdi-refresh"
                    size="x-small"
                    variant="text"
                    :loading="loadingStats"
                    @click="fetchStats"
                ></v-btn>
            </div>

            <v-row dense class="mb-4">
                <v-col cols="12" sm="6" md="3">
                    <v-card variant="tonal" color="primary" class="pa-3 text-center">
                        <div class="text-h5 font-weight-bold">{{ stats.total_sandboxes }}</div>
                        <div class="text-caption">Total Sandboxes</div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <v-card variant="tonal" color="success" class="pa-3 text-center">
                        <div class="text-h5 font-weight-bold">{{ stats.active_last_24h }}</div>
                        <div class="text-caption">Active (24h)</div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <v-card variant="tonal" color="info" class="pa-3 text-center">
                        <div class="text-h5 font-weight-bold">{{ stats.total_versions }}</div>
                        <div class="text-caption">Total Versions</div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <v-card variant="tonal" color="warning" class="pa-3 text-center">
                        <div class="text-h5 font-weight-bold">{{ stats.total_collaborators }}</div>
                        <div class="text-caption">Total Collaborators</div>
                    </v-card>
                </v-col>
            </v-row>
        </settings-card>

        <settings-card icon="mdi-server-network" title="WebSocket Server">
            <v-card variant="outlined" class="pa-3">
                <div class="d-flex justify-space-between align-center mb-2">
                    <span class="text-body-2">Status:</span>
                    <v-chip
                        :color="wsStatus.connected ? 'success' : 'error'"
                        size="small"
                        variant="tonal"
                    >
                        <v-icon start size="small">
                            {{ wsStatus.connected ? 'mdi-check-circle' : 'mdi-alert-circle' }}
                        </v-icon>
                        {{ wsStatus.connected ? 'Running' : 'Not Running' }}
                    </v-chip>
                </div>
                <v-divider class="my-2"></v-divider>
                <div class="d-flex justify-space-between align-center mb-2">
                    <span class="text-body-2">Host:</span>
                    <code class="text-body-2">{{ wsStatus.host || '—' }}:{{ wsStatus.port || '—' }}</code>
                </div>
                <v-divider class="my-2"></v-divider>
                <div class="d-flex justify-space-between align-center">
                    <span class="text-body-2">Latency:</span>
                    <span class="text-body-2">{{ wsStatus.latency_ms !== null ? wsStatus.latency_ms + 'ms' : '—' }}</span>
                </div>
            </v-card>
        </settings-card>
    </settings-page-layout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useApi } from '@/api/useAPI.js';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';

const api = useApi('api');

defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

defineEmits(['save', 'message']);

const message = ref('');
const alertType = ref('success');
const loadingStats = ref(false);

const stats = reactive({
    total_sandboxes: 0,
    active_last_24h: 0,
    total_versions: 0,
    total_collaborators: 0,
});

const wsStatus = reactive({
    host: null,
    port: null,
    connected: false,
    latency_ms: null,
    error: null,
});

async function fetchStats() {
    loadingStats.value = true;
    try {
        const response = await api.get('/sandbox/status');
        Object.assign(wsStatus, response.data.websocket);
        Object.assign(stats, response.data.stats);
    } catch (error) {
        console.error('Failed to fetch sandbox stats:', error);
    } finally {
        loadingStats.value = false;
    }
}

onMounted(() => {
    fetchStats();
});
</script>
