<template>
    <settings-page-layout
        title="IRC Connections"
        description="View and manage active IRC connections"
        icon="mdi-connection"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'irc' } }"
        :show-save-button="false"
    >
        <settings-card icon="mdi-connection" title="Active Connections">
            <div class="d-flex justify-end mb-4">
                <v-btn
                    color="primary"
                    size="small"
                    variant="outlined"
                    prepend-icon="mdi-refresh"
                    @click="fetchConnections"
                    :loading="loading"
                >
                    Refresh
                </v-btn>
            </div>

            <div v-if="loading" class="text-center py-4">
                <v-progress-circular indeterminate size="32" />
            </div>

            <v-table v-else density="comfortable">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Server</th>
                        <th>Nickname</th>
                        <th>Status</th>
                        <th>Channels</th>
                        <th>Connected Since</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="conn in connections" :key="conn.id">
                        <td>
                            <div class="d-flex align-center gap-2">
                                <v-avatar size="24" color="primary">
                                    <span class="text-caption">{{ (conn.user?.name || '?')[0].toUpperCase() }}</span>
                                </v-avatar>
                                <span>{{ conn.user?.name || conn.user?.username || `User #${conn.user_id}` }}</span>
                            </div>
                        </td>
                        <td>{{ conn.server?.name || 'Unknown' }}</td>
                        <td class="font-weight-medium">{{ conn.nickname }}</td>
                        <td>
                            <v-chip
                                :color="statusColor(conn.status)"
                                size="x-small"
                                variant="flat"
                            >
                                {{ conn.status }}
                            </v-chip>
                        </td>
                        <td>{{ conn.channels_count }}</td>
                        <td class="text-caption">
                            {{ conn.connected_at ? formatDate(conn.connected_at) : '-' }}
                        </td>
                        <td class="text-right">
                            <v-btn
                                v-if="conn.status === 'connected'"
                                icon
                                size="small"
                                variant="text"
                                color="warning"
                                :loading="busy[conn.id] === 'disconnect'"
                                :disabled="!!busy[conn.id]"
                                @click="disconnectConnection(conn)"
                            >
                                <v-icon size="small">mdi-power-plug-off</v-icon>
                                <v-tooltip activator="parent" location="top">Disconnect</v-tooltip>
                            </v-btn>
                            <v-btn
                                icon
                                size="small"
                                variant="text"
                                color="error"
                                :loading="busy[conn.id] === 'delete'"
                                :disabled="!!busy[conn.id]"
                                @click="deleteConnection(conn)"
                            >
                                <v-icon size="small">mdi-delete</v-icon>
                                <v-tooltip activator="parent" location="top">Delete</v-tooltip>
                            </v-btn>
                        </td>
                    </tr>
                    <tr v-if="!connections.length && !loading">
                        <td colspan="7" class="text-center text-medium-emphasis py-8">
                            No connections found
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
const connections = ref([]);
// In-flight action per connection id: 'disconnect' | 'delete'
const busy = ref({});

function setBusy(id, action) {
    const next = { ...busy.value };
    if (action) next[id] = action;
    else delete next[id];
    busy.value = next;
}

function statusColor(status) {
    return { connected: 'success', connecting: 'warning', disconnected: 'grey' }[status] || 'grey';
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleString();
}

async function fetchConnections() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/irc/connections');
        connections.value = data;
    } catch (error) {
        console.error('Error fetching connections:', error);
        await dialog.requestError(error, t('irc.admin.loadFailed'));
    } finally {
        loading.value = false;
    }
}

async function disconnectConnection(conn) {
    if (busy.value[conn.id]) return;

    const confirmed = await dialog.confirm({
        title: t('irc.admin.disconnectTitle'),
        content: t('irc.admin.disconnectConfirm', { nickname: conn.nickname, server: conn.server?.name }),
        confirmationText: t('irc.admin.disconnect'),
        color: 'warning',
    });
    if (!confirmed) return;

    setBusy(conn.id, 'disconnect');
    try {
        await axios.post(`/api/admin/irc/connections/${conn.id}/disconnect`);
        conn.status = 'disconnected';
    } catch (error) {
        console.error('Error disconnecting:', error);
        await dialog.requestError(error, t('irc.admin.disconnectFailed'));
    } finally {
        setBusy(conn.id, null);
    }
}

async function deleteConnection(conn) {
    if (busy.value[conn.id]) return;

    const confirmed = await dialog.confirmDelete(
        t('irc.admin.deleteConnectionConfirm', { nickname: conn.nickname }),
        { title: t('irc.admin.deleteConnectionTitle') }
    );
    if (!confirmed) return;

    setBusy(conn.id, 'delete');
    try {
        await axios.delete(`/api/admin/irc/connections/${conn.id}`);
        await fetchConnections();
    } catch (error) {
        console.error('Error deleting connection:', error);
        await dialog.requestError(error, t('irc.admin.deleteConnectionFailed'));
    } finally {
        setBusy(conn.id, null);
    }
}

onMounted(fetchConnections);
</script>
