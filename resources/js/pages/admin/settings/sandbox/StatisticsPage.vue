<template>
    <settings-page-layout
        :title="$t('sandbox.admin.statisticsTitle')"
        :description="$t('sandbox.admin.statisticsDescription')"
        icon="mdi-chart-bar"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'sandbox' } }"
        :is-saving="isSaving"
        @save="$emit('save')"
    >
        <settings-card icon="mdi-chart-bar" :title="$t('sandbox.admin.statisticsCard')">
            <div class="d-flex align-center justify-space-between mb-4">
                <div class="text-subtitle-1 font-weight-medium">{{ $t('sandbox.admin.usageOverview') }}</div>
                <v-btn
                    icon="mdi-refresh"
                    size="x-small"
                    variant="text"
                    :title="$t('common.refresh')"
                    :loading="loadingStats"
                    @click="fetchStats"
                ></v-btn>
            </div>

            <v-row dense class="mb-4">
                <v-col cols="12" sm="6" md="3">
                    <v-card variant="tonal" color="primary" class="pa-3 text-center">
                        <div class="text-h5 font-weight-bold">{{ stats.total_sandboxes }}</div>
                        <div class="text-caption">{{ $t('sandbox.admin.totalSandboxes') }}</div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <v-card variant="tonal" color="success" class="pa-3 text-center">
                        <div class="text-h5 font-weight-bold">{{ stats.active_last_24h }}</div>
                        <div class="text-caption">{{ $t('sandbox.admin.active24h') }}</div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <v-card variant="tonal" color="info" class="pa-3 text-center">
                        <div class="text-h5 font-weight-bold">{{ stats.total_versions }}</div>
                        <div class="text-caption">{{ $t('sandbox.admin.totalVersions') }}</div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <v-card variant="tonal" color="warning" class="pa-3 text-center">
                        <div class="text-h5 font-weight-bold">{{ stats.total_collaborators }}</div>
                        <div class="text-caption">{{ $t('sandbox.admin.totalCollaborators') }}</div>
                    </v-card>
                </v-col>
            </v-row>
        </settings-card>

        <settings-card icon="mdi-server-network" :title="$t('sandbox.admin.websocketServer')">
            <v-card variant="outlined" class="pa-3">
                <div class="d-flex justify-space-between align-center mb-2">
                    <span class="text-body-2">{{ $t('sandbox.admin.status') }}:</span>
                    <v-chip
                        :color="wsStatus.connected ? 'success' : 'error'"
                        size="small"
                        variant="tonal"
                    >
                        <v-icon start size="small">
                            {{ wsStatus.connected ? 'mdi-check-circle' : 'mdi-alert-circle' }}
                        </v-icon>
                        {{ wsStatus.connected ? $t('sandbox.admin.running') : $t('sandbox.admin.notRunning') }}
                    </v-chip>
                </div>
                <v-divider class="my-2"></v-divider>
                <div class="d-flex justify-space-between align-center mb-2">
                    <span class="text-body-2">{{ $t('sandbox.admin.host') }}:</span>
                    <code class="text-body-2">{{ wsStatus.host || '—' }}:{{ wsStatus.port || '—' }}</code>
                </div>
                <v-divider class="my-2"></v-divider>
                <div class="d-flex justify-space-between align-center">
                    <span class="text-body-2">{{ $t('sandbox.admin.latency') }}:</span>
                    <span class="text-body-2">{{ wsStatus.latency_ms !== null ? wsStatus.latency_ms + 'ms' : '—' }}</span>
                </div>
            </v-card>
        </settings-card>
    </settings-page-layout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/api/useAPI.js';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';
import { useDialog } from '@/composables/useDialog.js';

const api = useApi('api');

defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

defineEmits(['save']);

const { t } = useI18n();
const dialog = useDialog();
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
    if (loadingStats.value) return;

    loadingStats.value = true;
    try {
        const response = await api.get('/sandbox/status');
        Object.assign(wsStatus, response.data.websocket);
        Object.assign(stats, response.data.stats);
    } catch (error) {
        console.error('Failed to fetch sandbox stats:', error);
        await dialog.requestError(error, t('sandbox.admin.statsLoadFailed'));
    } finally {
        loadingStats.value = false;
    }
}

onMounted(() => {
    fetchStats();
});
</script>
