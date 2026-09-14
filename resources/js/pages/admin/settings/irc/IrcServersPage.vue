<template>
    <settings-page-layout
        :title="$t('irc.admin.serversTitle')"
        :description="$t('irc.admin.serversDescription')"
        icon="mdi-server-network"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'irc' } }"
        :show-save-button="false"
    >
        <settings-card icon="mdi-server-network" :title="$t('irc.admin.servers')">
            <div class="d-flex justify-end mb-4">
                <v-btn color="primary" variant="flat" size="small" prepend-icon="mdi-plus" @click="openDialog()">
                    {{ $t('irc.admin.addServer') }}
                </v-btn>
            </div>

            <loading-state v-if="loading" compact />

            <v-table v-else density="comfortable">
                <thead>
                    <tr>
                        <th>{{ $t('irc.admin.table.name') }}</th>
                        <th>{{ $t('irc.admin.table.host') }}</th>
                        <th>{{ $t('irc.admin.table.port') }}</th>
                        <th>{{ $t('irc.admin.table.ssl') }}</th>
                        <th>{{ $t('irc.admin.table.active') }}</th>
                        <th>{{ $t('irc.admin.table.status') }}</th>
                        <th>{{ $t('irc.admin.table.connections') }}</th>
                        <th class="text-right">{{ $t('irc.admin.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="server in servers" :key="server.id">
                        <td class="font-weight-medium">{{ server.name }}</td>
                        <td class="text-caption">{{ server.host }}</td>
                        <td>{{ server.port }}</td>
                        <td>
                            <v-icon size="small" :color="server.use_ssl ? 'success' : 'medium-emphasis'">
                                {{ server.use_ssl ? 'mdi-lock' : 'mdi-lock-open-variant' }}
                            </v-icon>
                        </td>
                        <td>
                            <v-switch
                                :model-value="server.is_active"
                                density="compact"
                                hide-details
                                color="success"
                                :loading="togglingId === server.id"
                                :disabled="togglingId !== null"
                                @update:model-value="toggleActive(server, $event)"
                            />
                        </td>
                        <td>
                            <div class="d-flex align-center ga-1">
                                <v-chip
                                    :color="server.is_reachable == null ? undefined : (server.is_reachable ? 'success' : 'error')"
                                    size="x-small"
                                    variant="tonal"
                                >
                                    {{ server.is_reachable == null ? $t('irc.admin.unknown') : (server.is_reachable ? $t('irc.admin.online') : $t('irc.admin.offline')) }}
                                </v-chip>
                                <v-btn
                                    icon
                                    size="x-small"
                                    variant="text"
                                    :title="$t('irc.admin.checkServer')"
                                    :loading="checkingServer === server.id"
                                    @click="checkServer(server)"
                                >
                                    <v-icon size="small">mdi-refresh</v-icon>
                                </v-btn>
                            </div>
                        </td>
                        <td>{{ server.connections_count }}</td>
                        <td class="text-right">
                            <v-btn icon size="small" variant="text" :title="$t('common.edit')" @click="openDialog(server)">
                                <v-icon size="small">mdi-pencil</v-icon>
                            </v-btn>
                            <v-btn
                                icon
                                size="small"
                                variant="text"
                                color="error"
                                :title="$t('common.delete')"
                                @click="deleteServer(server)"
                                :loading="deletingId === server.id"
                                :disabled="server.connections_count > 0 || (deletingId !== null && deletingId !== server.id)"
                            >
                                <v-icon size="small">mdi-delete</v-icon>
                            </v-btn>
                        </td>
                    </tr>
                    <tr v-if="!servers.length && !loading">
                        <td colspan="8">
                            <empty-state
                                compact
                                icon="mdi-server-network-off"
                                :title="$t('irc.admin.noServers')"
                                :text="$t('irc.admin.noServersText')"
                            />
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </settings-card>

        <!-- Add/Edit Server Dialog -->
        <v-dialog v-model="showDialog" max-width="600" persistent>
            <v-card>
                <v-card-title class="text-h6">
                    {{ editingServer ? $t('irc.admin.editServer') : $t('irc.admin.addServer') }}
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <v-form ref="formRef" @submit.prevent="saveServer">
                        <v-text-field
                            v-model="form.name"
                            :label="$t('irc.admin.form.name')"
                            prepend-inner-icon="mdi-label"
                            class="mb-3"
                            :rules="[v => !!v || $t('irc.admin.form.required')]"
                        />
                        <v-text-field
                            v-model="form.host"
                            :label="$t('irc.admin.form.host')"
                            prepend-inner-icon="mdi-server"
                            class="mb-3"
                            :rules="[v => !!v || $t('irc.admin.form.required')]"
                            :hint="$t('irc.admin.form.hostHint')"
                        />
                        <v-row>
                            <v-col cols="6">
                                <v-text-field
                                    v-model.number="form.port"
                                    :label="$t('irc.admin.form.port')"
                                    type="number"
                                    prepend-inner-icon="mdi-ethernet"
                                    :rules="[v => (v > 0 && v <= 65535) || $t('irc.admin.form.invalidPort')]"
                                />
                            </v-col>
                            <v-col cols="6" class="d-flex align-center">
                                <v-switch
                                    v-model="form.use_ssl"
                                    :label="$t('irc.admin.form.useSsl')"
                                    color="success"
                                    hide-details
                                />
                            </v-col>
                        </v-row>
                        <v-text-field
                            v-model="form.password"
                            :label="$t('irc.admin.form.password')"
                            prepend-inner-icon="mdi-lock"
                            class="mb-3"
                            type="password"
                            :hint="$t('irc.admin.form.passwordHint')"
                            persistent-hint
                        />
                        <v-textarea
                            v-model="form.description"
                            :label="$t('irc.admin.form.description')"
                            prepend-inner-icon="mdi-text"
                            rows="2"
                            class="mb-3"
                        />
                        <v-row>
                            <v-col cols="6">
                                <v-text-field
                                    v-model.number="form.order"
                                    :label="$t('irc.admin.form.sortOrder')"
                                    type="number"
                                />
                            </v-col>
                            <v-col cols="6" class="d-flex align-center">
                                <v-switch
                                    v-model="form.is_active"
                                    :label="$t('irc.admin.form.active')"
                                    color="success"
                                    hide-details
                                />
                            </v-col>
                        </v-row>
                    </v-form>
                </v-card-text>
                <v-divider />
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" :disabled="saving" @click="showDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="primary" variant="flat" @click="saveServer" :loading="saving">
                        {{ editingServer ? $t('common.update') : $t('common.create') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
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
const saving = ref(false);
const servers = ref([]);
const showDialog = ref(false);
const editingServer = ref(null);
const checkingServer = ref(null);
const deletingId = ref(null);
const togglingId = ref(null);
const formRef = ref(null);

const defaultForm = {
    name: '',
    host: '',
    port: 6667,
    use_ssl: false,
    password: '',
    description: '',
    is_active: true,
    order: 0,
};

const form = ref({ ...defaultForm });

async function fetchServers() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/irc/servers');
        servers.value = data;
    } catch (error) {
        console.error('Error fetching servers:', error);
        await dialog.requestError(error, t('irc.admin.loadFailed'));
    } finally {
        loading.value = false;
    }
}

function openDialog(server = null) {
    editingServer.value = server;
    form.value = server ? { ...server } : { ...defaultForm };
    showDialog.value = true;
}

async function saveServer() {
    if (saving.value) return;
    const { valid } = await formRef.value.validate();
    if (!valid) return;

    saving.value = true;
    try {
        if (editingServer.value) {
            await axios.patch(`/api/admin/irc/servers/${editingServer.value.id}`, form.value);
        } else {
            await axios.post('/api/admin/irc/servers', form.value);
        }
        showDialog.value = false;
        await fetchServers();
        await dialog.success(t('irc.admin.serverSaved'));
    } catch (error) {
        console.error('Error saving server:', error);
        await dialog.requestError(error, t('irc.admin.serverSaveFailed'));
    } finally {
        saving.value = false;
    }
}

async function deleteServer(server) {
    if (deletingId.value !== null) return;

    const confirmed = await dialog.confirmDelete(
        t('irc.admin.deleteServerConfirm', { name: server.name }),
        { title: t('irc.admin.deleteServerTitle') }
    );
    if (!confirmed) return;

    deletingId.value = server.id;
    try {
        await axios.delete(`/api/admin/irc/servers/${server.id}`);
        await fetchServers();
    } catch (error) {
        console.error('Error deleting server:', error);
        await dialog.requestError(error, t('irc.admin.deleteServerFailed'));
    } finally {
        deletingId.value = null;
    }
}

async function toggleActive(server, value) {
    if (togglingId.value !== null) return;

    togglingId.value = server.id;
    try {
        await axios.patch(`/api/admin/irc/servers/${server.id}`, { is_active: value });
        server.is_active = value;
    } catch (error) {
        console.error('Error toggling server:', error);
        await dialog.requestError(error, t('irc.admin.toggleFailed'));
    } finally {
        togglingId.value = null;
    }
}

async function checkServer(server) {
    if (checkingServer.value !== null) return;

    checkingServer.value = server.id;
    try {
        const { data } = await axios.post(`/api/admin/irc/servers/${server.id}/check`);
        server.is_reachable = data.is_reachable;
    } catch (error) {
        console.error('Error checking server:', error);
        await dialog.requestError(error, t('irc.admin.checkFailed'));
    } finally {
        checkingServer.value = null;
    }
}

onMounted(fetchServers);
</script>
