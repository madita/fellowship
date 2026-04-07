<template>
    <div>
        <!-- Yjs WebSocket Server Status -->
        <settings-card icon="mdi-server-network" title="Yjs WebSocket Server">
            <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    The Yjs WebSocket server enables real-time collaborative editing in sandboxes.
                    It must be running for users to see each other's cursors and edits in real time.
                </div>
            </v-alert>

            <div class="d-flex align-center justify-space-between mb-4">
                <div>
                    <div class="text-subtitle-1 font-weight-medium">Server Status</div>
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

        <!-- Sandbox Statistics -->
        <settings-card icon="mdi-chart-bar" title="Sandbox Statistics">
            <v-row dense>
                <v-col cols="12" sm="4">
                    <v-card variant="tonal" color="primary" class="pa-3 text-center">
                        <div class="text-h5 font-weight-bold">{{ stats.total_sandboxes }}</div>
                        <div class="text-caption">Total Sandboxes</div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="4">
                    <v-card variant="tonal" color="success" class="pa-3 text-center">
                        <div class="text-h5 font-weight-bold">{{ stats.active_last_24h }}</div>
                        <div class="text-caption">Active (24h)</div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="4">
                    <v-card variant="tonal" color="info" class="pa-3 text-center">
                        <div class="text-h5 font-weight-bold">{{ stats.total_versions }}</div>
                        <div class="text-caption">Total Versions</div>
                    </v-card>
                </v-col>
            </v-row>
        </settings-card>

        <!-- Sandbox Feature Settings -->
        <settings-card icon="mdi-notebook-edit" title="Sandbox Settings">
            <v-switch
                v-model="settings.sandbox_enabled"
                label="Enable Sandbox Feature"
                color="primary"
                class="mb-4"
                hint="Allow users to create and use collaborative sandboxes"
                persistent-hint
            ></v-switch>

            <v-switch
                v-model="settings.sandbox_public_enabled"
                label="Allow Public Sandboxes"
                color="primary"
                class="mb-4"
                hint="Allow users to make their sandboxes publicly accessible"
                persistent-hint
                :disabled="!settings.sandbox_enabled"
            ></v-switch>

            <v-switch
                v-model="settings.sandbox_collaboration_enabled"
                label="Enable Real-time Collaboration"
                color="primary"
                class="mb-4"
                hint="Enable real-time collaborative editing via Yjs WebSocket server"
                persistent-hint
                :disabled="!settings.sandbox_enabled"
            ></v-switch>

            <v-text-field
                v-model.number="settings.sandbox_autosave_interval"
                label="Auto-save Interval (seconds)"
                prepend-inner-icon="mdi-timer-outline"
                variant="outlined"
                type="number"
                class="mb-4"
                :error-messages="errors.sandbox_autosave_interval"
                hint="How often the sandbox content auto-saves (in seconds)"
                persistent-hint
                :disabled="!settings.sandbox_enabled"
            ></v-text-field>
        </settings-card>

        <!-- Role-based Limits -->
        <settings-card icon="mdi-account-cog" title="Role-based Limits">
            <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    Configure sandbox limits per role. A value of <strong>0</strong> means unlimited.
                </div>
            </v-alert>

            <div v-if="loadingRoles" class="text-center py-4">
                <v-progress-circular indeterminate size="24"></v-progress-circular>
            </div>

            <template v-else>
                <v-card
                    v-for="role in roles"
                    :key="role"
                    variant="outlined"
                    class="mb-4 pa-4"
                >
                    <div class="d-flex align-center mb-3">
                        <v-avatar :color="role === 'admin' ? 'error' : 'primary'" size="28" class="mr-2">
                            <v-icon size="16" color="white">
                                {{ role === 'admin' ? 'mdi-shield-crown' : 'mdi-account' }}
                            </v-icon>
                        </v-avatar>
                        <span class="text-subtitle-1 font-weight-medium text-capitalize">{{ role }}</span>
                        <v-chip v-if="role === 'admin'" size="x-small" color="warning" variant="tonal" class="ml-2">
                            Typically unlimited
                        </v-chip>
                    </div>

                    <v-row dense>
                        <v-col cols="12" sm="4">
                            <v-text-field
                                :model-value="getRoleLimit(role, 'max_sandboxes')"
                                @update:model-value="setRoleLimit(role, 'max_sandboxes', $event)"
                                label="Max Sandboxes"
                                prepend-inner-icon="mdi-notebook-multiple"
                                variant="outlined"
                                type="number"
                                density="compact"
                                hint="0 = unlimited"
                                persistent-hint
                                :disabled="!settings.sandbox_enabled"
                            ></v-text-field>
                        </v-col>
                        <v-col cols="12" sm="4">
                            <v-text-field
                                :model-value="getRoleLimit(role, 'max_collaborators')"
                                @update:model-value="setRoleLimit(role, 'max_collaborators', $event)"
                                label="Max Collaborators"
                                prepend-inner-icon="mdi-account-group"
                                variant="outlined"
                                type="number"
                                density="compact"
                                hint="0 = unlimited"
                                persistent-hint
                                :disabled="!settings.sandbox_enabled"
                            ></v-text-field>
                        </v-col>
                        <v-col cols="12" sm="4">
                            <v-text-field
                                :model-value="getRoleLimit(role, 'max_versions')"
                                @update:model-value="setRoleLimit(role, 'max_versions', $event)"
                                label="Max Versions"
                                prepend-inner-icon="mdi-history"
                                variant="outlined"
                                type="number"
                                density="compact"
                                hint="0 = unlimited"
                                persistent-hint
                                :disabled="!settings.sandbox_enabled"
                            ></v-text-field>
                        </v-col>
                    </v-row>
                </v-card>
            </template>
        </settings-card>

        <!-- Yjs Server Configuration -->
        <settings-card icon="mdi-cog-outline" title="WebSocket Server Configuration">
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

        <v-btn
            :loading="isSaving"
            block
            size="large"
            color="primary"
            @click="$emit('save')"
            prepend-icon="mdi-content-save"
        >
            Save Sandbox Settings
        </v-btn>
    </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from 'vue';
import { useApi } from '@/api/useAPI.js';
import SettingsCard from '../SettingsCard.vue';

const api = useApi('api');

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
});

defineEmits(['save', 'message']);

const loadingStatus = ref(false);
const loadingRoles = ref(true);
const roles = ref([]);

const wsStatus = reactive({
    host: null,
    port: null,
    connected: false,
    latency_ms: null,
    error: null,
});

const stats = reactive({
    total_sandboxes: 0,
    active_last_24h: 0,
    total_versions: 0,
});

const defaultLimits = { max_sandboxes: 0, max_collaborators: 0, max_versions: 0 };

function ensureRoleLimits() {
    // Parse if it's a JSON string
    if (typeof props.settings.sandbox_role_limits === 'string') {
        try {
            props.settings.sandbox_role_limits = JSON.parse(props.settings.sandbox_role_limits);
        } catch {
            props.settings.sandbox_role_limits = {};
        }
    }
    if (!props.settings.sandbox_role_limits || typeof props.settings.sandbox_role_limits !== 'object' || Array.isArray(props.settings.sandbox_role_limits)) {
        props.settings.sandbox_role_limits = {};
    }
}
    for (const role of roles.value) {
        if (!props.settings.sandbox_role_limits[role]) {
            props.settings.sandbox_role_limits[role] = { ...defaultLimits };
        }
    }
}

function getRoleLimit(role, key) {
    return props.settings.sandbox_role_limits?.[role]?.[key] ?? 0;
}

function setRoleLimit(role, key, value) {
    ensureRoleLimits();
    props.settings.sandbox_role_limits[role][key] = Number(value) || 0;
}

async function fetchRoles() {
    loadingRoles.value = true;
    try {
        const response = await api.get('/datatable/permissions/roles');
        roles.value = response.data.data.map(r => r.name);
        ensureRoleLimits();
    } catch (error) {
        console.error('Failed to fetch roles:', error);
        roles.value = ['admin', 'user'];
        ensureRoleLimits();
    } finally {
        loadingRoles.value = false;
    }
}

async function fetchWsStatus() {
    loadingStatus.value = true;
    try {
        const response = await api.get('/sandbox/status');
        Object.assign(wsStatus, response.data.websocket);
        Object.assign(stats, response.data.stats);
    } catch (error) {
        wsStatus.connected = false;
        wsStatus.error = error.response?.data?.message || 'Failed to check server status';
    } finally {
        loadingStatus.value = false;
    }
}

// Re-init role limits when settings are loaded from backend
watch(() => props.settings.sandbox_role_limits, () => {
    if (roles.value.length > 0) {
        ensureRoleLimits();
    }
});

onMounted(() => {
    fetchWsStatus();
    fetchRoles();
});
</script>
