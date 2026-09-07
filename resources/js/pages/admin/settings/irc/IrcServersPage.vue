<template>
    <settings-page-layout
        title="IRC Servers"
        description="Add, edit, and monitor IRC servers"
        icon="mdi-server-network"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'irc' } }"
        :show-save-button="false"
    >
        <settings-card icon="mdi-server-network" title="Servers">
            <div class="d-flex justify-end mb-4">
                <v-btn color="primary" size="small" prepend-icon="mdi-plus" @click="openDialog()">
                    Add Server
                </v-btn>
            </div>

            <div v-if="loading" class="text-center py-4">
                <v-progress-circular indeterminate size="32" />
            </div>

            <v-table v-else density="comfortable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Host</th>
                        <th>Port</th>
                        <th>SSL</th>
                        <th>Active</th>
                        <th>Status</th>
                        <th>Connections</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="server in servers" :key="server.id">
                        <td class="font-weight-medium">{{ server.name }}</td>
                        <td class="text-caption">{{ server.host }}</td>
                        <td>{{ server.port }}</td>
                        <td>
                            <v-icon size="small" :color="server.use_ssl ? 'success' : 'grey'">
                                {{ server.use_ssl ? 'mdi-lock' : 'mdi-lock-open-variant' }}
                            </v-icon>
                        </td>
                        <td>
                            <v-switch
                                :model-value="server.is_active"
                                density="compact"
                                hide-details
                                color="success"
                                @update:model-value="toggleActive(server, $event)"
                            />
                        </td>
                        <td>
                            <v-chip
                                :color="server.is_reachable == null ? 'grey' : (server.is_reachable ? 'success' : 'error')"
                                size="x-small"
                                variant="flat"
                            >
                                {{ server.is_reachable == null ? 'Unknown' : (server.is_reachable ? 'Online' : 'Offline') }}
                            </v-chip>
                            <v-btn
                                icon
                                size="x-small"
                                variant="text"
                                :loading="checkingServer === server.id"
                                @click="checkServer(server)"
                            >
                                <v-icon size="small">mdi-refresh</v-icon>
                            </v-btn>
                        </td>
                        <td>{{ server.connections_count }}</td>
                        <td class="text-right">
                            <v-btn icon size="small" variant="text" @click="openDialog(server)">
                                <v-icon size="small">mdi-pencil</v-icon>
                            </v-btn>
                            <v-btn
                                icon
                                size="small"
                                variant="text"
                                color="error"
                                @click="deleteServer(server)"
                                :disabled="server.connections_count > 0"
                            >
                                <v-icon size="small">mdi-delete</v-icon>
                            </v-btn>
                        </td>
                    </tr>
                    <tr v-if="!servers.length && !loading">
                        <td colspan="8" class="text-center text-medium-emphasis py-8">
                            No servers configured. Click "Add Server" to get started.
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </settings-card>

        <!-- Add/Edit Server Dialog -->
        <v-dialog v-model="showDialog" max-width="600" persistent>
            <v-card>
                <v-card-title>
                    {{ editingServer ? 'Edit Server' : 'Add Server' }}
                </v-card-title>
                <v-card-text>
                    <v-form ref="formRef" @submit.prevent="saveServer">
                        <v-text-field
                            v-model="form.name"
                            label="Server Name"
                            prepend-inner-icon="mdi-label"
                            variant="outlined"
                            class="mb-3"
                            :rules="[v => !!v || 'Required']"
                        />
                        <v-text-field
                            v-model="form.host"
                            label="Host"
                            prepend-inner-icon="mdi-server"
                            variant="outlined"
                            class="mb-3"
                            :rules="[v => !!v || 'Required']"
                            hint="e.g. irc.libera.chat"
                        />
                        <v-row>
                            <v-col cols="6">
                                <v-text-field
                                    v-model.number="form.port"
                                    label="Port"
                                    type="number"
                                    prepend-inner-icon="mdi-ethernet"
                                    variant="outlined"
                                    :rules="[v => (v > 0 && v <= 65535) || 'Invalid port']"
                                />
                            </v-col>
                            <v-col cols="6" class="d-flex align-center">
                                <v-switch
                                    v-model="form.use_ssl"
                                    label="Use SSL/TLS"
                                    color="success"
                                    hide-details
                                />
                            </v-col>
                        </v-row>
                        <v-text-field
                            v-model="form.password"
                            label="Server Password"
                            prepend-inner-icon="mdi-lock"
                            variant="outlined"
                            class="mb-3"
                            type="password"
                            hint="Leave empty if not required"
                            persistent-hint
                        />
                        <v-textarea
                            v-model="form.description"
                            label="Description"
                            prepend-inner-icon="mdi-text"
                            variant="outlined"
                            rows="2"
                            class="mb-3"
                        />
                        <v-row>
                            <v-col cols="6">
                                <v-text-field
                                    v-model.number="form.order"
                                    label="Sort Order"
                                    type="number"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="6" class="d-flex align-center">
                                <v-switch
                                    v-model="form.is_active"
                                    label="Active"
                                    color="success"
                                    hide-details
                                />
                            </v-col>
                        </v-row>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn @click="showDialog = false">Cancel</v-btn>
                    <v-btn color="primary" variant="flat" @click="saveServer" :loading="saving">
                        {{ editingServer ? 'Update' : 'Create' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
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
const saving = ref(false);
const servers = ref([]);
const showDialog = ref(false);
const editingServer = ref(null);
const checkingServer = ref(null);
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
    } catch (error) {
        console.error('Error saving server:', error);
    } finally {
        saving.value = false;
    }
}

async function deleteServer(server) {
    if (!confirm(`Delete server "${server.name}"? This cannot be undone.`)) return;
    try {
        await axios.delete(`/api/admin/irc/servers/${server.id}`);
        await fetchServers();
    } catch (error) {
        alert(error.response?.data?.message || 'Error deleting server');
    }
}

async function toggleActive(server, value) {
    try {
        await axios.patch(`/api/admin/irc/servers/${server.id}`, { is_active: value });
        server.is_active = value;
    } catch (error) {
        console.error('Error toggling server:', error);
    }
}

async function checkServer(server) {
    checkingServer.value = server.id;
    try {
        const { data } = await axios.post(`/api/admin/irc/servers/${server.id}/check`);
        server.is_reachable = data.is_reachable;
    } catch (error) {
        console.error('Error checking server:', error);
    } finally {
        checkingServer.value = null;
    }
}

onMounted(fetchServers);
</script>
