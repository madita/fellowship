<template>
    <settings-page-layout
        :title="$t('irc.admin.dashboardTitle')"
        :description="$t('irc.admin.dashboardDescription')"
        icon="mdi-monitor-dashboard"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'irc' } }"
        :show-save-button="false"
    >
        <!-- Daemon Status -->
        <settings-card icon="mdi-cog-sync" :title="$t('irc.admin.daemonStatus')">
            <loading-state v-if="loading" compact />
            <template v-else>
                <v-row>
                    <v-col cols="12" sm="6" md="3">
                        <v-card variant="tonal" :color="daemonStatus.daemon_running ? 'success' : 'error'" class="pa-4 text-center">
                            <v-icon size="36">{{ daemonStatus.daemon_running ? 'mdi-check-circle' : 'mdi-alert-circle' }}</v-icon>
                            <div class="text-h6 mt-2">{{ daemonStatus.daemon_running ? $t('irc.admin.running') : $t('irc.admin.stopped') }}</div>
                            <div class="text-caption">{{ $t('irc.admin.daemon') }}</div>
                        </v-card>
                    </v-col>
                    <v-col cols="12" sm="6" md="3">
                        <v-card variant="tonal" color="primary" class="pa-4 text-center">
                            <div class="text-h4">{{ daemonStatus.active_connections }}</div>
                            <div class="text-caption">{{ $t('irc.admin.activeConnections') }}</div>
                        </v-card>
                    </v-col>
                    <v-col cols="12" sm="6" md="3">
                        <v-card variant="tonal" color="info" class="pa-4 text-center">
                            <div class="text-h4">{{ daemonStatus.joined_channels }}</div>
                            <div class="text-caption">{{ $t('irc.admin.joinedChannels') }}</div>
                        </v-card>
                    </v-col>
                    <v-col cols="12" sm="6" md="3">
                        <v-card variant="tonal" color="warning" class="pa-4 text-center">
                            <div class="text-h4">{{ daemonStatus.pending_commands }}</div>
                            <div class="text-caption">{{ $t('irc.admin.pendingCommands') }}</div>
                        </v-card>
                    </v-col>
                </v-row>

                <v-row class="mt-2">
                    <v-col cols="12" sm="6">
                        <div class="text-body-2 text-medium-emphasis">
                            <v-icon size="small" class="mr-1">mdi-clock-outline</v-icon>
                            {{ $t('irc.admin.lastHeartbeat', { time: daemonStatus.last_heartbeat || $t('irc.admin.never') }) }}
                        </div>
                    </v-col>
                    <v-col cols="12" sm="6">
                        <div class="text-body-2 text-medium-emphasis">
                            <v-icon size="small" class="mr-1">mdi-server</v-icon>
                            {{ $t('irc.admin.activeServers', { count: daemonStatus.active_servers }) }}
                        </div>
                    </v-col>
                </v-row>

                <div class="mt-4">
                    <v-btn
                        color="primary"
                        variant="tonal"
                        size="small"
                        prepend-icon="mdi-refresh"
                        @click="fetchAll"
                        :loading="loading"
                    >
                        {{ $t('common.refresh') }}
                    </v-btn>
                    <v-alert v-if="!daemonStatus.daemon_running" type="warning" variant="tonal" class="mt-3" density="compact">
                        {{ $t('irc.admin.daemonNotRunning') }} <code>php artisan irc:daemon</code>
                    </v-alert>
                </div>
            </template>
        </settings-card>

        <!-- Activity Stats -->
        <settings-card icon="mdi-chart-bar" :title="$t('irc.admin.activity')">
            <loading-state v-if="loading" compact />
            <template v-else>
                <v-row>
                    <v-col cols="12" sm="6" md="3">
                        <div class="text-h5">{{ stats.total_messages }}</div>
                        <div class="text-caption text-medium-emphasis">{{ $t('irc.admin.totalMessages') }}</div>
                    </v-col>
                    <v-col cols="12" sm="6" md="3">
                        <div class="text-h5">{{ stats.today_messages }}</div>
                        <div class="text-caption text-medium-emphasis">{{ $t('irc.admin.messagesToday') }}</div>
                    </v-col>
                    <v-col cols="12" sm="6" md="3">
                        <div class="text-h5">{{ stats.unique_users }}</div>
                        <div class="text-caption text-medium-emphasis">{{ $t('irc.admin.uniqueUsers') }}</div>
                    </v-col>
                    <v-col cols="12" sm="6" md="3">
                        <div class="text-h5">{{ daemonStatus.total_connections }}</div>
                        <div class="text-caption text-medium-emphasis">{{ $t('irc.admin.totalConnections') }}</div>
                    </v-col>
                </v-row>
            </template>
        </settings-card>

        <!-- Server Status -->
        <settings-card icon="mdi-server-network" :title="$t('irc.admin.serverStatus')">
            <loading-state v-if="loading" compact />
            <v-table v-else density="compact">
                <thead>
                    <tr>
                        <th>{{ $t('irc.admin.table.server') }}</th>
                        <th>{{ $t('irc.admin.table.host') }}</th>
                        <th>{{ $t('irc.admin.table.port') }}</th>
                        <th>{{ $t('irc.admin.table.ssl') }}</th>
                        <th>{{ $t('irc.admin.table.status') }}</th>
                        <th>{{ $t('irc.admin.table.connections') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="server in servers" :key="server.id">
                        <td>{{ server.name }}</td>
                        <td class="text-caption">{{ server.host }}</td>
                        <td>{{ server.port }}</td>
                        <td>
                            <v-icon size="small" :color="server.use_ssl ? 'success' : 'medium-emphasis'">
                                {{ server.use_ssl ? 'mdi-lock' : 'mdi-lock-open-variant' }}
                            </v-icon>
                        </td>
                        <td>
                            <v-chip
                                :color="server.is_reachable == null ? undefined : (server.is_reachable ? 'success' : 'error')"
                                size="x-small"
                                variant="tonal"
                            >
                                {{ server.is_reachable == null ? $t('irc.admin.unknown') : (server.is_reachable ? $t('irc.admin.online') : $t('irc.admin.offline')) }}
                            </v-chip>
                        </td>
                        <td>{{ server.connections_count }}</td>
                    </tr>
                    <tr v-if="!servers.length">
                        <td colspan="6">
                            <empty-state compact icon="mdi-server-network-off" :title="$t('irc.admin.noServers')" />
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </settings-card>
    </settings-page-layout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import { useDialog } from '@/composables/useDialog.js';

defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

const { t } = useI18n();
const dialog = useDialog();
const loading = ref(true);
const daemonStatus = ref({});
const stats = ref({});
const servers = ref([]);

async function fetchAll() {
    loading.value = true;
    try {
        const [statusRes, statsRes, serversRes] = await Promise.all([
            axios.get('/api/admin/irc/daemon/status'),
            axios.get('/api/admin/irc/stats'),
            axios.get('/api/admin/irc/servers'),
        ]);
        daemonStatus.value = statusRes.data;
        stats.value = statsRes.data;
        servers.value = serversRes.data;
    } catch (error) {
        console.error('Error fetching IRC dashboard:', error);
        await dialog.requestError(error, t('irc.admin.loadFailed'));
    } finally {
        loading.value = false;
    }
}

onMounted(fetchAll);
</script>
