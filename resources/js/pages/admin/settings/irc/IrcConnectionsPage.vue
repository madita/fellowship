<template>
    <settings-page-layout
        :title="$t('irc.admin.connectionsTitle')"
        :description="$t('irc.admin.connectionsDescription')"
        icon="mdi-connection"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'irc' } }"
        :show-save-button="false"
    >
        <settings-card icon="mdi-connection" :title="$t('irc.admin.activeConnections')">
            <div class="d-flex justify-end mb-4">
                <v-btn
                    color="primary"
                    size="small"
                    variant="tonal"
                    prepend-icon="mdi-refresh"
                    @click="fetchConnections"
                    :loading="loading"
                >
                    {{ $t('common.refresh') }}
                </v-btn>
            </div>

            <loading-state v-if="loading" compact />

            <v-table v-else density="comfortable">
                <thead>
                    <tr>
                        <th>{{ $t('irc.admin.table.user') }}</th>
                        <th>{{ $t('irc.admin.table.server') }}</th>
                        <th>{{ $t('irc.admin.table.nickname') }}</th>
                        <th>{{ $t('irc.admin.table.status') }}</th>
                        <th>{{ $t('irc.admin.table.channels') }}</th>
                        <th>{{ $t('irc.admin.table.connectedSince') }}</th>
                        <th class="text-right">{{ $t('irc.admin.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="conn in connections" :key="conn.id">
                        <td>
                            <div class="d-flex align-center ga-2">
                                <v-avatar size="24" color="primary">
                                    <span class="text-caption">{{ (conn.user?.name || '?')[0].toUpperCase() }}</span>
                                </v-avatar>
                                <span>{{ conn.user?.name || conn.user?.username || `User #${conn.user_id}` }}</span>
                            </div>
                        </td>
                        <td>{{ conn.server?.name || $t('irc.admin.unknown') }}</td>
                        <td class="font-weight-medium">{{ conn.nickname }}</td>
                        <td>
                            <v-chip
                                :color="statusColor(conn.status)"
                                size="x-small"
                                variant="tonal"
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
                                <v-tooltip activator="parent" location="top">{{ $t('irc.admin.disconnect') }}</v-tooltip>
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
                                <v-tooltip activator="parent" location="top">{{ $t('common.delete') }}</v-tooltip>
                            </v-btn>
                        </td>
                    </tr>
                    <tr v-if="!connections.length && !loading">
                        <td colspan="7">
                            <empty-state compact icon="mdi-connection" :title="$t('irc.admin.noConnections')" />
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
    return { connected: 'success', connecting: 'warning' }[status];
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
