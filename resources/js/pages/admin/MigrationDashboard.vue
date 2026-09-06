<template>
    <v-container fluid class="pa-4">
        <!-- Header -->
        <div class="d-flex align-center justify-space-between mb-4">
            <div>
                <h1 class="text-h4 font-weight-bold">{{ $t('migrationDashboard.title') }}</h1>
                <p class="text-body-2 text-grey">{{ $t('migrationDashboard.description') }}</p>
            </div>
            <div class="d-flex ga-2">
                <v-btn
                    color="primary"
                    :disabled="isRunning || selectedMigrations.length === 0"
                    :loading="starting"
                    @click="startMigrations"
                >
                    <v-icon icon="mdi-play" start />
                    {{ $t('migrationDashboard.runSelected', { count: selectedMigrations.length }) }}
                </v-btn>
                <v-btn
                    color="warning"
                    :disabled="isRunning"
                    :loading="starting"
                    @click="runAll"
                >
                    <v-icon icon="mdi-play-circle" start />
                    {{ $t('migrationDashboard.runAll') }}
                </v-btn>
            </div>
        </div>

        <v-tabs v-model="tab" color="primary" class="mb-4">
            <v-tab value="runs">
                <v-icon start>mdi-play-box-multiple-outline</v-icon>{{ $t('migrationTool.tabRuns') }}
            </v-tab>
            <v-tab value="sources">
                <v-icon start>mdi-database-outline</v-icon>{{ $t('migrationTool.tabSources') }}
            </v-tab>
            <v-tab value="mappings">
                <v-icon start>mdi-swap-horizontal</v-icon>{{ $t('migrationTool.tabMappings') }}
            </v-tab>
            <v-tab value="legacyUsers">
                <v-icon start>mdi-account-convert</v-icon>{{ $t('migrationTool.tabLegacyUsers') }}
            </v-tab>
        </v-tabs>

        <v-window v-model="tab">
        <v-window-item value="sources">
            <migration-sources @notify="onNotify" />
        </v-window-item>

        <v-window-item value="mappings">
            <migration-mappings @notify="onNotify" @run="onImportRun" />
        </v-window-item>

        <v-window-item value="legacyUsers">
            <migration-legacy-users @notify="onNotify" />
        </v-window-item>

        <v-window-item value="runs">
        <v-row>
            <!-- Migration Selection -->
            <v-col cols="12" md="5">
                <v-card>
                    <v-card-title>{{ $t('migrationDashboard.availableMigrations') }}</v-card-title>
                    <v-card-text>
                        <div v-for="group in groups" :key="group.key" class="mb-4">
                            <div class="d-flex align-center mb-2">
                                <v-checkbox
                                    :model-value="isGroupSelected(group.key)"
                                    :indeterminate="isGroupPartiallySelected(group.key)"
                                    :disabled="isRunning"
                                    hide-details
                                    density="compact"
                                    @update:model-value="toggleGroup(group.key, $event)"
                                />
                                <span class="text-subtitle-1 font-weight-medium">{{ group.name }}</span>
                            </div>

                            <v-list density="compact" class="ml-6">
                                <v-list-item
                                    v-for="migration in getMigrationsForGroup(group.key)"
                                    :key="migration.key"
                                >
                                    <template #prepend>
                                        <v-checkbox
                                            v-model="selectedMigrations"
                                            :value="migration.key"
                                            :disabled="isRunning"
                                            hide-details
                                            density="compact"
                                        />
                                    </template>
                                    <v-list-item-title>{{ migration.name }}</v-list-item-title>
                                    <v-list-item-subtitle>{{ migration.description }}</v-list-item-subtitle>
                                    <template #append>
                                        <MigrationStatusChip
                                            v-if="getMigrationStatus(migration.key)"
                                            :status="getMigrationStatus(migration.key)"
                                        />
                                    </template>
                                </v-list-item>
                            </v-list>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Archive imported forum -->
                <v-card class="mt-4">
                    <v-card-title>{{ $t('migrationTool.forumArchive') }}</v-card-title>
                    <v-card-text>
                        <div class="text-caption text-medium-emphasis mb-3">{{ $t('migrationTool.forumArchiveHint') }}</div>
                        <v-text-field
                            v-model="archiveCategory"
                            :label="$t('migrationTool.forumArchiveCategory')"
                            placeholder="Archiv"
                            variant="outlined"
                            density="compact"
                        />
                        <v-checkbox
                            v-model="archiveLockThreads"
                            :label="$t('migrationTool.forumArchiveLock')"
                            density="compact"
                            hide-details
                            class="mb-2"
                        />
                        <v-btn
                            color="primary"
                            variant="tonal"
                            :loading="archiving"
                            :disabled="!archiveCategory"
                            @click="archiveForum"
                        >
                            {{ $t('migrationTool.forumArchiveRun') }}
                        </v-btn>
                    </v-card-text>
                </v-card>

                <!-- Current Batch Progress -->
                <v-card v-if="currentBatchId" class="mt-4">
                    <v-card-title class="d-flex align-center justify-space-between">
                        <span>
                            <v-progress-circular
                                v-if="isRunning"
                                indeterminate
                                size="20"
                                width="2"
                                class="mr-2"
                            />
                            {{ $t('migrationDashboard.batchProgress') }}
                        </span>
                        <v-btn
                            v-if="isRunning"
                            color="error"
                            variant="text"
                            size="small"
                            @click="cancelBatch"
                        >
                            {{ $t('common.cancel') }}
                        </v-btn>
                    </v-card-title>
                    <v-card-text>
                        <div class="text-body-2 mb-2">
                            {{ $t('migrationDashboard.progressStatus', { completed: batchStatus?.summary?.completed || 0, total: batchStatus?.summary?.total || 0 }) }}
                            <span v-if="batchStatus?.summary?.failed" class="text-error">
                                ({{ $t('migrationDashboard.failedCount', { count: batchStatus.summary.failed }) }})
                            </span>
                        </div>

                        <v-list density="compact">
                            <v-list-item
                                v-for="migration in batchStatus?.migrations || []"
                                :key="migration.key"
                                :class="{ 'bg-primary-lighten-5': migration.status === 'running' }"
                            >
                                <template #prepend>
                                    <v-icon
                                        :icon="getStatusIcon(migration.status)"
                                        :color="getStatusColor(migration.status)"
                                        size="small"
                                    />
                                </template>
                                <v-list-item-title class="text-body-2">
                                    {{ migration.name }}
                                    <span v-if="migration.status === 'running' && migration.currentItem" class="text-caption text-grey">
                                        - {{ migration.currentItem }}
                                    </span>
                                </v-list-item-title>
                                <template #append>
                                    <div class="d-flex align-center ga-2">
                                        <span v-if="migration.total > 0" class="text-caption">
                                            {{ migration.processed }}/{{ migration.total }}
                                        </span>
                                        <v-progress-linear
                                            v-if="migration.status === 'running'"
                                            :model-value="migration.percentage"
                                            color="primary"
                                            style="width: 60px;"
                                            height="6"
                                            rounded
                                        />
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- Terminal Output -->
            <v-col cols="12" md="7">
                <v-card class="terminal-card">
                    <v-card-title class="d-flex align-center justify-space-between">
                        <span>
                            <v-icon icon="mdi-console" class="mr-2" />
                            {{ $t('migrationDashboard.output') }}
                        </span>
                        <div>
                            <v-select
                                v-if="batchStatus?.migrations?.length > 1"
                                v-model="selectedLogMigration"
                                :items="logMigrationOptions"
                                item-title="name"
                                item-value="key"
                                density="compact"
                                variant="outlined"
                                hide-details
                                style="width: 200px;"
                                class="mr-2"
                            />
                            <v-btn
                                icon
                                size="small"
                                variant="text"
                                :disabled="logs.length === 0"
                                @click="clearLogs"
                            >
                                <v-icon icon="mdi-delete" />
                            </v-btn>
                        </div>
                    </v-card-title>
                    <v-card-text class="pa-0">
                        <div ref="terminalRef" class="terminal">
                            <div v-if="logs.length === 0" class="terminal-empty">
                                <v-icon icon="mdi-console-line" size="48" color="grey-darken-1" />
                                <div class="mt-2">{{ $t('migrationDashboard.noOutputYet') }}</div>
                            </div>
                            <div
                                v-for="(log, index) in logs"
                                :key="index"
                                class="terminal-line"
                                :class="'terminal-line--' + log.type"
                            >
                                <span class="terminal-timestamp">{{ formatTimestamp(log.timestamp) }}</span>
                                <span class="terminal-type">[{{ log.type.toUpperCase() }}]</span>
                                <span class="terminal-message">{{ log.message }}</span>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- History -->
                <v-card v-if="history.length > 0" class="mt-4">
                    <v-card-title>{{ $t('migrationDashboard.recentBatches') }}</v-card-title>
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item
                                v-for="batch in history"
                                :key="batch.batchId"
                                @click="loadBatch(batch.batchId)"
                            >
                                <template #prepend>
                                    <v-icon
                                        :icon="getStatusIcon(batch.status)"
                                        :color="getStatusColor(batch.status)"
                                    />
                                </template>
                                <v-list-item-title class="text-body-2">
                                    {{ $t('migrationDashboard.migrationsCount', { count: batch.totalMigrations }) }}
                                </v-list-item-title>
                                <v-list-item-subtitle>
                                    {{ formatDate(batch.startedAt) }}
                                </v-list-item-subtitle>
                                <template #append>
                                    <v-chip
                                        :color="getStatusColor(batch.status)"
                                        size="x-small"
                                    >
                                        {{ batch.completed }}/{{ batch.totalMigrations }}
                                    </v-chip>
                                </template>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
        </v-window-item>
        </v-window>

        <!-- Snackbar -->
        <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="3000">
            {{ snackbar.text }}
        </v-snackbar>
    </v-container>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import MigrationSources from '@/components/admin/migration/MigrationSources.vue';
import MigrationMappings from '@/components/admin/migration/MigrationMappings.vue';
import MigrationLegacyUsers from '@/components/admin/migration/MigrationLegacyUsers.vue';

const { t } = useI18n();

// Components
const MigrationStatusChip = {
    props: ['status'],
    template: `
        <v-chip :color="color" size="small">{{ label }}</v-chip>
    `,
    computed: {
        color() {
            const colors = {
                pending: 'grey',
                running: 'primary',
                completed: 'success',
                failed: 'error',
            };
            return colors[this.status] || 'grey';
        },
        label() {
            return this.status?.charAt(0).toUpperCase() + this.status?.slice(1) || '';
        },
    },
};

// State
const tab = ref('runs');
const migrations = ref([]);
const groups = ref([]);
const selectedMigrations = ref([]);
const starting = ref(false);
const currentBatchId = ref(null);
const batchStatus = ref(null);
const logs = ref([]);
const history = ref([]);
const terminalRef = ref(null);
const selectedLogMigration = ref(null);
const pollingInterval = ref(null);

const snackbar = reactive({
    show: false,
    text: '',
    color: 'success',
});

// Computed
const isRunning = computed(() => {
    return batchStatus.value?.status === 'running' || batchStatus.value?.status === 'pending';
});

const logMigrationOptions = computed(() => {
    if (!batchStatus.value?.migrations) return [];
    return [
        { key: null, name: t('migrationDashboard.allLogs') },
        ...batchStatus.value.migrations.map(m => ({ key: m.key, name: m.name })),
    ];
});

const getMigrationsForGroup = (groupKey) => {
    return migrations.value.filter(m => m.group === groupKey);
};

const isGroupSelected = (groupKey) => {
    const groupMigrations = getMigrationsForGroup(groupKey);
    return groupMigrations.every(m => selectedMigrations.value.includes(m.key));
};

const isGroupPartiallySelected = (groupKey) => {
    const groupMigrations = getMigrationsForGroup(groupKey);
    const selectedCount = groupMigrations.filter(m => selectedMigrations.value.includes(m.key)).length;
    return selectedCount > 0 && selectedCount < groupMigrations.length;
};

const getMigrationStatus = (key) => {
    if (!batchStatus.value?.migrations) return null;
    const migration = batchStatus.value.migrations.find(m => m.key === key);
    return migration?.status || null;
};

// Methods
const fetchMigrations = async () => {
    try {
        const response = await axios.get('/api/admin/migrations');
        migrations.value = response.data.migrations;
        groups.value = response.data.groups;

        // If there's an active batch, resume polling
        if (response.data.activeBatches?.length > 0) {
            currentBatchId.value = response.data.activeBatches[0];
            startPolling();
        }
    } catch (error) {
        showSnackbar(t('migrationDashboard.failedToLoadMigrations'), 'error');
    }
};

const fetchHistory = async () => {
    try {
        const response = await axios.get('/api/admin/migrations/history');
        history.value = response.data.batches;
    } catch (error) {
        console.error('Failed to load history:', error);
    }
};

const toggleGroup = (groupKey, selected) => {
    const groupMigrations = getMigrationsForGroup(groupKey);
    if (selected) {
        groupMigrations.forEach(m => {
            if (!selectedMigrations.value.includes(m.key)) {
                selectedMigrations.value.push(m.key);
            }
        });
    } else {
        selectedMigrations.value = selectedMigrations.value.filter(
            key => !groupMigrations.find(m => m.key === key)
        );
    }
};

const startMigrations = async () => {
    if (selectedMigrations.value.length === 0) return;

    starting.value = true;
    logs.value = [];

    try {
        const response = await axios.post('/api/admin/migrations/start', {
            migrations: selectedMigrations.value,
        });

        currentBatchId.value = response.data.batchId;
        addLog('info', t('migrationDashboard.startedBatch', { count: response.data.count }));
        startPolling();
        showSnackbar(t('migrationDashboard.migrationsStarted'));
    } catch (error) {
        showSnackbar(t('migrationDashboard.failedToStartMigrations'), 'error');
        addLog('error', error.response?.data?.error || error.message);
    } finally {
        starting.value = false;
    }
};

const runAll = () => {
    selectedMigrations.value = migrations.value.map(m => m.key);
    startMigrations();
};

const cancelBatch = async () => {
    if (!currentBatchId.value) return;

    try {
        await axios.post(`/api/admin/migrations/cancel/${currentBatchId.value}`);
        showSnackbar(t('migrationDashboard.batchCancelled'));
        addLog('warning', t('migrationDashboard.batchCancelledByUser'));
    } catch (error) {
        showSnackbar(t('migrationDashboard.failedToCancelBatch'), 'error');
    }
};

const loadBatch = async (batchId) => {
    currentBatchId.value = batchId;
    logs.value = [];
    await fetchBatchStatus();
    await fetchLogs();
};

const startPolling = () => {
    stopPolling();
    fetchBatchStatus();
    pollingInterval.value = setInterval(fetchBatchStatus, 2000);
};

const stopPolling = () => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
        pollingInterval.value = null;
    }
};

const fetchBatchStatus = async () => {
    if (!currentBatchId.value) return;

    try {
        const response = await axios.get(`/api/admin/migrations/status/${currentBatchId.value}`);
        const prevStatus = batchStatus.value?.status;
        batchStatus.value = response.data;

        // Fetch logs for running/completed migrations
        await fetchLogs();

        // Stop polling if completed
        if (response.data.status === 'completed' || response.data.status === 'completed_with_errors') {
            stopPolling();
            fetchHistory();

            if (prevStatus === 'running') {
                if (response.data.status === 'completed') {
                    addLog('success', t('migrationDashboard.allMigrationsCompletedSuccessfully'));
                    showSnackbar(t('migrationDashboard.migrationsCompleted'), 'success');
                } else {
                    addLog('warning', t('migrationDashboard.migrationsCompletedWithErrors'));
                    showSnackbar(t('migrationDashboard.migrationsCompletedWithErrors'), 'warning');
                }
            }
        }
    } catch (error) {
        console.error('Failed to fetch status:', error);
    }
};

const fetchLogs = async () => {
    if (!currentBatchId.value) return;

    // If a specific migration is selected, fetch its logs
    const migrationKey = selectedLogMigration.value;

    if (migrationKey) {
        try {
            const response = await axios.get(`/api/admin/migrations/logs/${currentBatchId.value}/${migrationKey}`);
            logs.value = response.data.logs || [];
        } catch (error) {
            console.error('Failed to fetch logs:', error);
        }
    } else {
        // Aggregate logs from all migrations
        const allLogs = [];
        for (const migration of batchStatus.value?.migrations || []) {
            try {
                const response = await axios.get(`/api/admin/migrations/logs/${currentBatchId.value}/${migration.key}`);
                const migrationLogs = response.data.logs || [];
                migrationLogs.forEach(log => {
                    allLogs.push({
                        ...log,
                        message: `[${migration.name}] ${log.message}`,
                    });
                });
            } catch (error) {
                // Skip
            }
        }

        // Sort by timestamp
        allLogs.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
        logs.value = allLogs;
    }

    nextTick(() => scrollToBottom());
};

const addLog = (type, message) => {
    logs.value.push({
        type,
        message,
        timestamp: new Date().toISOString(),
    });
    nextTick(() => scrollToBottom());
};

const clearLogs = () => {
    logs.value = [];
};

const scrollToBottom = () => {
    if (terminalRef.value) {
        terminalRef.value.scrollTop = terminalRef.value.scrollHeight;
    }
};

const getStatusIcon = (status) => {
    const icons = {
        pending: 'mdi-clock-outline',
        running: 'mdi-loading mdi-spin',
        completed: 'mdi-check-circle',
        completed_with_errors: 'mdi-alert-circle',
        failed: 'mdi-close-circle',
    };
    return icons[status] || 'mdi-help-circle';
};

const getStatusColor = (status) => {
    const colors = {
        pending: 'grey',
        running: 'primary',
        completed: 'success',
        completed_with_errors: 'warning',
        failed: 'error',
    };
    return colors[status] || 'grey';
};

const formatTimestamp = (timestamp) => {
    if (!timestamp) return '';
    const date = new Date(timestamp);
    return date.toLocaleTimeString();
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString();
};

const showSnackbar = (text, color = 'success') => {
    snackbar.text = text;
    snackbar.color = color;
    snackbar.show = true;
};

const onNotify = ({ text, color = 'success' }) => showSnackbar(text, color);

// Archive imported forum content under a chosen category
const archiveCategory = ref('');
const archiveLockThreads = ref(true);
const archiving = ref(false);

const archiveForum = async () => {
    archiving.value = true;
    try {
        const { data } = await axios.post('/api/admin/migrations/forum/archive', {
            category: archiveCategory.value,
            lock_threads: archiveLockThreads.value,
        });
        showSnackbar(`${data.message} (${data.moved_categories} ${t('migrationTool.forumArchiveMoved')}, ${data.locked_threads} ${t('migrationTool.forumArchiveLocked')})`);
    } catch (e) {
        showSnackbar(e.response?.data?.message || e.message, 'error');
    } finally {
        archiving.value = false;
    }
};

// A mapping import was queued — jump to the runs tab and follow its batch.
const onImportRun = (batchId) => {
    tab.value = 'runs';
    currentBatchId.value = batchId;
    logs.value = [];
    startPolling();
};

// Watch for log filter changes
watch(selectedLogMigration, () => {
    fetchLogs();
});

// Lifecycle
onMounted(() => {
    fetchMigrations();
    fetchHistory();
});

onUnmounted(() => {
    stopPolling();
});
</script>

<style scoped>
.ga-2 {
    gap: 8px;
}

.terminal-card {
    height: calc(100vh - 300px);
    min-height: 300px;
    display: flex;
    flex-direction: column;
}

.terminal-card .v-card-text {
    flex: 1;
    overflow: hidden;
}

.terminal {
    height: 100%;
    background: #1e1e1e;
    color: #d4d4d4;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.4;
    padding: 12px;
    overflow-y: auto;
    overflow-x: hidden;
}

.terminal-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #6b6b6b;
}

.terminal-line {
    padding: 1px 0;
    word-break: break-word;
}

.terminal-timestamp {
    color: #6a9955;
    margin-right: 8px;
}

.terminal-type {
    margin-right: 8px;
    font-weight: bold;
}

.terminal-line--info .terminal-type { color: #569cd6; }
.terminal-line--log .terminal-type { color: #d4d4d4; }
.terminal-line--success .terminal-type { color: #4ec9b0; }
.terminal-line--warning .terminal-type { color: #dcdcaa; }
.terminal-line--error .terminal-type { color: #f14c4c; }
.terminal-line--progress .terminal-type { color: #c586c0; }

.terminal-message { color: inherit; }
.terminal-line--error .terminal-message { color: #f14c4c; }
.terminal-line--warning .terminal-message { color: #dcdcaa; }
.terminal-line--success .terminal-message { color: #4ec9b0; }

.mdi-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
