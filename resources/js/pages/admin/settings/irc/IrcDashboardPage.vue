<template>
    <settings-page-layout
        title="IRC Dashboard"
        description="Monitor IRC daemon, servers, and activity"
        icon="mdi-monitor-dashboard"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'irc' } }"
        :show-save-button="false"
    >
        <!-- Daemon Status -->
        <settings-card icon="mdi-cog-sync" title="Daemon Status">
            <div v-if="loading" class="text-center py-4">
                <v-progress-circular indeterminate size="32" />
            </div>
            <template v-else>
                <v-row>
                    <v-col cols="12" sm="6" md="3">
                        <v-card variant="tonal" :color="daemonStatus.daemon_running ? 'success' : 'error'" class="pa-4 text-center">
                            <v-icon size="36">{{ daemonStatus.daemon_running ? 'mdi-check-circle' : 'mdi-alert-circle' }}</v-icon>
                            <div class="text-h6 mt-2">{{ daemonStatus.daemon_running ? 'Running' : 'Stopped' }}</div>
                            <div class="text-caption">Daemon</div>
                        </v-card>
                    </v-col>
                    <v-col cols="12" sm="6" md="3">
                        <v-card variant="tonal" color="primary" class="pa-4 text-center">
                            <div class="text-h4">{{ daemonStatus.active_connections }}</div>
                            <div class="text-caption">Active Connections</div>
                        </v-card>
                    </v-col>
                    <v-col cols="12" sm="6" md="3">
                        <v-card variant="tonal" color="info" class="pa-4 text-center">
                            <div class="text-h4">{{ daemonStatus.joined_channels }}</div>
                            <div class="text-caption">Joined Channels</div>
                        </v-card>
                    </v-col>
                    <v-col cols="12" sm="6" md="3">
                        <v-card variant="tonal" color="warning" class="pa-4 text-center">
                            <div class="text-h4">{{ daemonStatus.pending_commands }}</div>
                            <div class="text-caption">Pending Commands</div>
                        </v-card>
                    </v-col>
                </v-row>

                <v-row class="mt-2">
                    <v-col cols="12" sm="6">
                        <div class="text-body-2 text-medium-emphasis">
                            <v-icon size="small" class="mr-1">mdi-clock-outline</v-icon>
                            Last heartbeat: {{ daemonStatus.last_heartbeat || 'Never' }}
                        </div>
                    </v-col>
                    <v-col cols="12" sm="6">
                        <div class="text-body-2 text-medium-emphasis">
                            <v-icon size="small" class="mr-1">mdi-server</v-icon>
                            Active servers: {{ daemonStatus.active_servers }}
                        </div>
                    </v-col>
                </v-row>

                <div class="mt-4">
                    <v-btn
                        color="primary"
                        variant="outlined"
                        size="small"
                        prepend-icon="mdi-refresh"
                        @click="fetchAll"
                        :loading="loading"
                    >
                        Refresh
                    </v-btn>
                    <v-alert v-if="!daemonStatus.daemon_running" type="warning" variant="tonal" class="mt-3" density="compact">
                        The IRC daemon is not running. Start it with: <code>php artisan irc:daemon</code>
                    </v-alert>
                </div>
            </template>
        </settings-card>

        <!-- Activity Stats -->
        <settings-card icon="mdi-chart-bar" title="Activity">
            <div v-if="loading" class="text-center py-4">
                <v-progress-circular indeterminate size="32" />
            </div>
            <template v-else>
                <v-row>
                    <v-col cols="12" sm="6" md="3">
                        <div class="text-h5">{{ stats.total_messages }}</div>
                        <div class="text-caption text-medium-emphasis">Total Messages</div>
                    </v-col>
                    <v-col cols="12" sm="6" md="3">
                        <div class="text-h5">{{ stats.today_messages }}</div>
                        <div class="text-caption text-medium-emphasis">Messages Today</div>
                    </v-col>
                    <v-col cols="12" sm="6" md="3">
                        <div class="text-h5">{{ stats.unique_users }}</div>
                        <div class="text-caption text-medium-emphasis">Unique Users</div>
                    </v-col>
                    <v-col cols="12" sm="6" md="3">
                        <div class="text-h5">{{ daemonStatus.total_connections }}</div>
                        <div class="text-caption text-medium-emphasis">Total Connections</div>
                    </v-col>
                </v-row>
            </template>
        </settings-card>

        <!-- Server Status -->
        <settings-card icon="mdi-server-network" title="Server Status">
            <div v-if="loading" class="text-center py-4">
                <v-progress-circular indeterminate size="32" />
            </div>
            <v-table v-else density="compact">
                <thead>
                    <tr>
                        <th>Server</th>
                        <th>Host</th>
                        <th>Port</th>
                        <th>SSL</th>
                        <th>Status</th>
                        <th>Connections</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="server in servers" :key="server.id">
                        <td>{{ server.name }}</td>
                        <td class="text-caption">{{ server.host }}</td>
                        <td>{{ server.port }}</td>
                        <td>
                            <v-icon size="small" :color="server.use_ssl ? 'success' : 'grey'">
                                {{ server.use_ssl ? 'mdi-lock' : 'mdi-lock-open-variant' }}
                            </v-icon>
                        </td>
                        <td>
                            <v-chip
                                :color="server.is_reachable == null ? 'grey' : (server.is_reachable ? 'success' : 'error')"
                                size="x-small"
                                variant="flat"
                            >
                                {{ server.is_reachable == null ? 'Unknown' : (server.is_reachable ? 'Online' : 'Offline') }}
                            </v-chip>
                        </td>
                        <td>{{ server.connections_count }}</td>
                    </tr>
                    <tr v-if="!servers.length">
                        <td colspan="6" class="text-center text-medium-emphasis">No servers configured</td>
                    </tr>
                </tbody>
            </v-table>
        </settings-card>
    </settings-page-layout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';

defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

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
    } finally {
        loading.value = false;
    }
}

onMounted(fetchAll);
</script>
