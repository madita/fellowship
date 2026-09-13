<template>
    <div>
        <page-header
            :title="$t('migrationDashboard.title')"
            :subtitle="$t('migrationDashboard.description')"
            icon="mdi-database-arrow-right-outline"
            :back-to="{ name: 'admin-settings-category', params: { category: 'tools' } }"
            fluid
        >
            <template #actions>
                <template v-if="tab === 'runs'">
                    <v-btn
                        color="primary"
                        variant="elevated"
                        prepend-icon="mdi-play"
                        :disabled="runBlocksControls || selectedMigrations.length === 0"
                        :loading="starting"
                        @click="startMigrations"
                    >
                        {{ $t('migrationDashboard.runSelected', { count: selectedMigrations.length }) }}
                    </v-btn>
                    <v-btn
                        color="warning"
                        variant="tonal"
                        prepend-icon="mdi-play-circle"
                        :disabled="runBlocksControls"
                        :loading="starting"
                        @click="runAll"
                    >
                        {{ $t('migrationDashboard.runAll') }}
                    </v-btn>
                </template>
            </template>

            <!-- Tab order follows the real migration order. The tab *values*
                 stay as they are — other screens deep-link to them. -->
            <v-tabs v-model="tab" color="primary">
                <v-tab value="sources">
                    <v-icon start>mdi-database-outline</v-icon>
                    <span class="font-weight-bold me-1">1.</span>{{ $t('migrationTool.tabSources') }}
                </v-tab>
                <v-tab value="mappings">
                    <v-icon start>mdi-swap-horizontal</v-icon>
                    <span class="font-weight-bold me-1">2.</span>{{ $t('migrationTool.tabMappings') }}
                </v-tab>
                <v-tab value="runs">
                    <v-icon start>mdi-auto-fix</v-icon>
                    <span class="font-weight-bold me-1">3.</span>{{ $t('migrationTool.tabRuns') }}
                    <v-badge
                        v-if="isRunning"
                        dot
                        inline
                        :color="runStalled ? 'warning' : 'primary'"
                    />
                </v-tab>
                <v-tab value="legacyUsers">
                    <v-icon start>mdi-account-convert</v-icon>
                    <span class="font-weight-bold me-1">4.</span>{{ $t('migrationTool.tabLegacyUsers') }}
                </v-tab>
            </v-tabs>
        </page-header>

        <v-container fluid>
        <!-- An import keeps running while this page is closed, so after a
             reload this banner is what says so. It sits above the tabs'
             content on purpose: the run view is only one of four tabs. -->
        <v-alert
            v-if="currentBatchId && isRunning"
            :type="runStalled ? 'warning' : 'info'"
            variant="tonal"
            border="start"
            class="mb-4"
        >
            <div class="d-flex flex-wrap align-center ga-3">
                <v-progress-circular v-if="!runStalled" indeterminate size="22" width="2" />
                <div class="flex-grow-1" style="min-width: 220px;">
                    <div class="text-subtitle-2">
                        {{ runStalled ? $t('migrationDashboard.stalledTitle') : $t('migrationDashboard.runningTitle') }}
                    </div>
                    <div class="text-body-2">{{ runningLine }}</div>
                    <div v-if="runProgress.currentItem && !runStalled" class="text-caption text-medium-emphasis">
                        {{ $t('migrationDashboard.runningItem', { item: runProgress.currentItem }) }}
                    </div>
                    <div v-if="runStalled" class="text-body-2 mt-1">
                        {{ $t('migrationDashboard.stalledText', { count: runStalledMinutes }) }}
                    </div>
                    <div v-else class="text-caption text-medium-emphasis">
                        {{ $t('migrationDashboard.runningKeepOpen') }}
                    </div>
                </div>
                <div class="d-flex align-center ga-2">
                    <v-btn
                        v-if="tab !== 'runs'"
                        size="small"
                        variant="tonal"
                        append-icon="mdi-arrow-right"
                        @click="tab = 'runs'"
                    >
                        {{ $t('migrationDashboard.runningOpen') }}
                    </v-btn>
                    <v-btn
                        size="small"
                        color="error"
                        variant="text"
                        :loading="cancelling"
                        :disabled="cancelling"
                        @click="cancelBatch"
                    >
                        {{ $t('common.cancel') }}
                    </v-btn>
                </div>
            </div>
            <v-progress-linear
                v-if="runProgress.percentage !== null"
                :model-value="runProgress.percentage"
                :color="runStalled ? 'warning' : 'primary'"
                height="6"
                rounded
                class="mt-3"
            />
        </v-alert>

        <!-- The order of the whole migration, with live state per step. -->
        <v-card class="mb-4">
            <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-medium">
                <span class="d-flex align-center">
                    <v-icon icon="mdi-map-marker-path" class="me-2" />
                    {{ $t('migrationDashboard.guideTitle') }}
                </span>
                <v-btn
                    variant="text"
                    size="small"
                    :prepend-icon="guideOpen ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                    @click="toggleGuide"
                >
                    {{ guideOpen ? $t('migrationDashboard.guideHide') : $t('migrationDashboard.guideShow') }}
                </v-btn>
            </v-card-title>
            <v-expand-transition>
                <div v-show="guideOpen">
                    <v-card-text class="pt-0">
                        <div class="text-body-2 text-medium-emphasis mb-4">{{ $t('migrationDashboard.guideIntro') }}</div>
                        <div class="d-flex flex-wrap ga-4">
                            <div
                                v-for="step in guideSteps"
                                :key="step.key"
                                class="guide-step"
                                :class="{
                                    'guide-step--done': step.done,
                                    'guide-step--current': step.current && !step.done,
                                }"
                            >
                                <div class="d-flex align-center ga-2 mb-1">
                                    <v-avatar
                                        size="28"
                                        :color="step.done ? 'success' : (step.current ? 'primary' : undefined)"
                                        :variant="step.done || step.current ? 'flat' : 'tonal'"
                                    >
                                        <v-icon v-if="step.done" icon="mdi-check" size="small" />
                                        <span v-else class="text-caption font-weight-bold">{{ step.number }}</span>
                                    </v-avatar>
                                    <span class="text-subtitle-2">{{ $t('migrationDashboard.' + stepTitleKeys[step.key]) }}</span>
                                </div>
                                <div class="text-caption text-medium-emphasis mb-2 guide-step__state">{{ stepState(step) }}</div>
                                <v-btn
                                    size="small"
                                    :variant="step.current && !step.done ? 'tonal' : 'text'"
                                    :color="step.current && !step.done ? 'primary' : undefined"
                                    append-icon="mdi-arrow-right"
                                    @click="tab = step.tab"
                                >
                                    {{ $t('migrationDashboard.guideOpen') }}
                                </v-btn>
                            </div>
                        </div>
                    </v-card-text>
                </div>
            </v-expand-transition>
        </v-card>

        <v-skeleton-loader v-if="!tab" type="card" />
        <v-window v-else v-model="tab">
        <v-window-item value="sources">
            <migration-sources
                @notify="onNotify"
                @changed="fetchOverview"
                @tested="sourceTested = true"
            />
        </v-window-item>

        <v-window-item value="mappings">
            <migration-mappings @notify="onNotify" @run="onImportRun" @changed="fetchOverview" />
        </v-window-item>

        <v-window-item value="legacyUsers">
            <migration-legacy-users :initial-search="legacyUsersSearch" @notify="onNotify" @changed="fetchOverview" />
        </v-window-item>

        <v-window-item value="runs">
        <v-row>
            <!-- Post-import steps -->
            <v-col cols="12" md="5">
                <v-card>
                    <v-card-title class="text-subtitle-1 font-weight-medium">{{ $t('migrationDashboard.postStepsTitle') }}</v-card-title>
                    <v-card-text>
                        <div class="text-body-2 text-medium-emphasis mb-3">{{ $t('migrationDashboard.postStepsIntro') }}</div>
                        <v-alert
                            v-if="!anyImportRun"
                            type="warning"
                            density="compact"
                            class="mb-3"
                        >
                            {{ $t('migrationDashboard.postStepsNoImports') }}
                        </v-alert>

                        <div v-for="group in groups" :key="group.key" class="mb-4">
                            <div v-if="groups.length > 1" class="d-flex align-center mb-2">
                                <v-checkbox
                                    :model-value="isGroupSelected(group.key)"
                                    :indeterminate="isGroupPartiallySelected(group.key)"
                                    :disabled="runBlocksControls"
                                    hide-details
                                    density="compact"
                                    @update:model-value="toggleGroup(group.key, $event)"
                                />
                                <span class="text-subtitle-1 font-weight-medium">{{ group.name }}</span>
                            </div>

                            <v-list density="compact">
                                <v-list-item
                                    v-for="(migration, index) in getMigrationsForGroup(group.key)"
                                    :key="migration.key"
                                >
                                    <template #prepend>
                                        <v-checkbox
                                            v-model="selectedMigrations"
                                            :value="migration.key"
                                            :disabled="runBlocksControls"
                                            hide-details
                                            density="compact"
                                        />
                                    </template>
                                    <v-list-item-title>
                                        <span class="text-medium-emphasis me-1">{{ migration.order || index + 1 }}.</span>
                                        {{ migration.name }}
                                    </v-list-item-title>
                                    <v-list-item-subtitle>{{ migration.description }}</v-list-item-subtitle>
                                    <template #append>
                                        <migration-status-chip
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
                    <v-card-title class="text-subtitle-1 font-weight-medium">{{ $t('migrationTool.forumArchive') }}</v-card-title>
                    <v-card-text>
                        <div class="text-caption text-medium-emphasis mb-3">{{ $t('migrationTool.forumArchiveHint') }}</div>
                        <v-text-field
                            v-model="archiveCategory"
                            :label="$t('migrationTool.forumArchiveCategory')"
                            placeholder="Archiv"
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
                            :disabled="!archiveCategory || archiving"
                            @click="archiveForum"
                        >
                            {{ $t('migrationTool.forumArchiveRun') }}
                        </v-btn>
                    </v-card-text>
                </v-card>

                <!-- Current Batch Progress -->
                <v-card v-if="currentBatchId" class="mt-4">
                    <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-medium">
                        <span class="d-flex align-center">
                            <!-- Spin only while the run is really writing: a
                                 spinner on a dead run is what made a stopped
                                 import look like it was still working. -->
                            <v-progress-circular
                                v-if="runBlocksControls"
                                indeterminate
                                size="20"
                                width="2"
                                class="mr-2"
                            />
                            <v-icon
                                v-else-if="runStalled"
                                icon="mdi-alert-circle-outline"
                                color="warning"
                                class="mr-2"
                            />
                            {{ runStalled ? $t('migrationDashboard.stalledTitle') : $t('migrationDashboard.batchProgress') }}
                        </span>
                        <v-btn
                            v-if="isRunning"
                            :color="runStalled ? 'warning' : 'error'"
                            variant="text"
                            size="small"
                            :loading="cancelling"
                            :disabled="cancelling"
                            @click="cancelBatch"
                        >
                            {{ runStalled ? $t('migrationDashboard.markStopped') : $t('common.cancel') }}
                        </v-btn>
                    </v-card-title>
                    <v-card-text>
                        <v-alert v-if="runStalled" type="warning" density="compact" class="mb-3">
                            {{ $t('migrationDashboard.stalledText', { count: runStalledMinutes }) }}
                        </v-alert>
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
                                :class="{ 'migration-row--running': migration.status === 'running' && !runStalled }"
                            >
                                <template #prepend>
                                    <v-icon
                                        :icon="rowStatusIcon(migration)"
                                        :color="rowStatusColor(migration)"
                                        size="small"
                                    />
                                </template>
                                <v-list-item-title class="text-body-2">
                                    {{ migration.name }}
                                    <span v-if="migration.status === 'running' && migration.currentItem" class="text-caption text-medium-emphasis">
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
                                            :color="runStalled ? 'warning' : 'primary'"
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
                    <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-medium">
                        <span>
                            <v-icon icon="mdi-console" class="mr-2" />
                            {{ $t('migrationDashboard.output') }}
                        </span>
                        <div class="d-flex align-center ga-2">
                            <v-select
                                v-if="batchStatus?.migrations?.length > 1"
                                v-model="selectedLogMigration"
                                :items="logMigrationOptions"
                                item-title="name"
                                item-value="key"
                                density="compact"
                                hide-details
                                style="width: 200px;"
                            />
                            <v-btn
                                icon="mdi-delete"
                                size="small"
                                variant="text"
                                :disabled="logs.length === 0"
                                @click="clearLogs"
                            />
                        </div>
                    </v-card-title>
                    <v-card-text class="pa-0">
                        <div ref="terminalRef" class="terminal">
                            <empty-state
                                v-if="logs.length === 0"
                                icon="mdi-console-line"
                                :title="$t('migrationDashboard.output')"
                                :text="$t('migrationDashboard.noOutputYet')"
                                compact
                                class="terminal-empty"
                            />
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
                    <v-card-title class="text-subtitle-1 font-weight-medium">{{ $t('migrationDashboard.recentBatches') }}</v-card-title>
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item
                                v-for="batch in history"
                                :key="batch.batchId"
                                :disabled="loadingBatchId !== null"
                                @click="loadBatch(batch.batchId)"
                            >
                                <template #prepend>
                                    <v-progress-circular
                                        v-if="loadingBatchId === batch.batchId"
                                        indeterminate
                                        size="20"
                                        width="2"
                                    />
                                    <v-icon
                                        v-else
                                        :icon="getStatusIcon(batch.status)"
                                        :color="getStatusColor(batch.status)"
                                    />
                                </template>
                                <v-list-item-title class="text-body-2 text-truncate">
                                    {{ batchTitle(batch) }}
                                </v-list-item-title>
                                <v-list-item-subtitle>
                                    {{ formatDate(batch.startedAt) }}
                                </v-list-item-subtitle>
                                <template #append>
                                    <v-chip
                                        :color="getStatusColor(batch.status)"
                                        variant="tonal"
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
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import axios from 'axios';
import MigrationSources from '@/components/admin/migration/MigrationSources.vue';
import MigrationMappings from '@/components/admin/migration/MigrationMappings.vue';
import MigrationLegacyUsers from '@/components/admin/migration/MigrationLegacyUsers.vue';
import PageHeader from '../../components/common/PageHeader.vue';
import EmptyState from '../../components/common/EmptyState.vue';
import { useDialog } from '@/composables/useDialog.js';
import { buildGuideSteps, postStepsDone, sortByOrder } from '@/utils/migrationGuide.js';
import { isRunStalled, minutesSinceUpdate, runIsLive, runSummary, RUNNING_STATES } from '@/utils/migrationRun.js';

const { t } = useI18n();
const dialog = useDialog();

// Components
const MigrationStatusChip = {
    props: ['status'],
    template: `
        <v-chip :color="color" variant="tonal" size="small">{{ label }}</v-chip>
    `,
    computed: {
        color() {
            const colors = {
                running: 'primary',
                completed: 'success',
                failed: 'error',
            };
            return colors[this.status];
        },
        label() {
            return this.status?.charAt(0).toUpperCase() + this.status?.slice(1) || '';
        },
    },
};

// State — deep-linkable: /admin/migrations?tab=legacyUsers&search=Name
const route = useRoute();
const deepLinkedTab = ['runs', 'sources', 'mappings', 'legacyUsers'].includes(route.query.tab)
    ? route.query.tab
    : null;
// Without a deep link the tab is decided once the overview is in: sources
// first when there is nothing to import from yet, mappings otherwise.
const tab = ref(deepLinkedTab);
const legacyUsersSearch = ref(typeof route.query.search === 'string' ? route.query.search : '');
const migrations = ref([]);
const groups = ref([]);
const selectedMigrations = ref([]);
const starting = ref(false);
const cancelling = ref(false);
const loadingBatchId = ref(null);
const currentBatchId = ref(null);
const batchStatus = ref(null);
const logs = ref([]);
const history = ref([]);
const terminalRef = ref(null);
const selectedLogMigration = ref(null);
const pollingInterval = ref(null);
const OVERVIEW_REFRESH_EVERY = 5;
let pollTick = 0;

// Guide state — counts only, fetched alongside the tabs so the guide is
// right even for tabs that were never opened.
const sources = ref([]);
const mappings = ref([]);
const legacyUsers = ref([]);
const sourceTested = ref(false);
const importsRunFlag = ref(null);
const ranPostKeys = ref([]);

const GUIDE_STORAGE_KEY = 'migrationGuideOpen';
const guideOpen = ref(true);

const stepTitleKeys = {
    source: 'stepSourceTitle',
    mappings: 'stepMappingsTitle',
    imports: 'stepImportsTitle',
    post: 'stepPostTitle',
    legacy: 'stepLegacyTitle',
};

const snackbar = reactive({
    show: false,
    text: '',
    color: 'success',
});

// Computed
const isRunning = computed(() => {
    return batchStatus.value?.status === 'running' || batchStatus.value?.status === 'pending';
});

// Re-read on every poll so "no progress for N minutes" keeps counting up.
const runNow = ref(Date.now());
const runProgress = computed(() => runSummary(batchStatus.value));
const runStalled = computed(() => isRunStalled(batchStatus.value, runNow.value));
const runStalledMinutes = computed(() => minutesSinceUpdate(batchStatus.value, runNow.value));

// Only a run that is actually writing may hold the post-import controls shut.
// A dead import would otherwise block the next step with no way out.
const runBlocksControls = computed(() => runIsLive(batchStatus.value, runNow.value));

const runningLine = computed(() => {
    const run = runProgress.value;
    if (!run.name || run.status === 'pending') return t('migrationDashboard.runningQueued');

    const rows = run.total > 0
        ? t('migrationDashboard.runningRows', { name: run.name, processed: run.processed, total: run.total })
        : t('migrationDashboard.runningRowsUnknown', { name: run.name, processed: run.processed });

    // A batch of post-import steps runs several migrations in a row.
    return run.steps > 1
        ? rows + ' · ' + t('migrationDashboard.runningSteps', { done: run.stepsDone, total: run.steps })
        : rows;
});

const importedMappingCount = computed(() =>
    mappings.value.filter(mapping => mapping.last_run?.status === 'completed').length
);

// The backend tells us whether anything was imported; fall back to what
// the mappings report.
const anyImportRun = computed(() =>
    importsRunFlag.value === null ? importedMappingCount.value > 0 : !!importsRunFlag.value
);

const guideSteps = computed(() => buildGuideSteps({
    sourceCount: sources.value.length,
    sourceTested: sourceTested.value,
    mappingCount: mappings.value.length,
    importedMappingCount: importedMappingCount.value,
    importsRun: anyImportRun.value,
    postStepCount: migrations.value.length,
    postDone: postStepsDone(migrations.value, ranPostKeys.value),
    legacyUserCount: legacyUsers.value.length,
    unassignedLegacyUsers: legacyUsers.value.filter(row => !row.assigned_user).length,
}));

const stepState = (step) => {
    const key = (name, params) => t(`migrationDashboard.${name}`, params || {});

    switch (step.key) {
        case 'source':
            if (!step.stat.count) return key('stepSourceTodo');
            return step.stat.tested
                ? key('stepSourceTested', { count: step.stat.count })
                : key('stepSourceDone', { count: step.stat.count });
        case 'mappings':
            return step.stat.count
                ? key('stepMappingsDone', { count: step.stat.count })
                : key('stepMappingsTodo');
        case 'imports':
            if (!step.stat.total) return key('stepImportsTodo');
            return key('stepImportsProgress', { done: step.stat.done, total: step.stat.total });
        case 'post':
            if (step.blocked) return key('stepPostBlocked');
            return step.done ? key('stepPostDone') : key('stepPostTodo', { count: step.stat.count });
        case 'legacy':
            if (!step.stat.total) return key('stepLegacyEmpty');
            return step.stat.open
                ? key('stepLegacyTodo', { count: step.stat.open })
                : key('stepLegacyDone', { count: step.stat.total });
        default:
            return '';
    }
};

const toggleGuide = () => {
    guideOpen.value = !guideOpen.value;
    try {
        localStorage.setItem(GUIDE_STORAGE_KEY, guideOpen.value ? '1' : '0');
    } catch (e) {
        // Private mode / blocked storage — the guide simply reopens next time.
    }
};

const logMigrationOptions = computed(() => {
    if (!batchStatus.value?.migrations) return [];
    return [
        { key: null, name: t('migrationDashboard.allLogs') },
        ...batchStatus.value.migrations.map(m => ({ key: m.key, name: m.name })),
    ];
});

const getMigrationsForGroup = (groupKey) => {
    return sortByOrder(migrations.value.filter(m => m.group === groupKey));
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
        importsRunFlag.value = response.data.imports_run ?? null;

        // If there's an active batch, resume polling
        if (response.data.activeBatches?.length > 0) {
            currentBatchId.value = response.data.activeBatches[0];
            startPolling();
        }
    } catch (error) {
        showSnackbar(t('migrationDashboard.failedToLoadMigrations'), 'error');
    }
};

// Counts behind the guide. Errors stay silent — a failing count must not
// bury the tab that could fix it.
const fetchOverview = async () => {
    const [sourcesRes, mappingsRes, legacyRes] = await Promise.allSettled([
        axios.get('/api/admin/migrations/sources'),
        axios.get('/api/admin/migrations/mappings'),
        axios.get('/api/admin/migrations/legacy-users'),
    ]);

    if (sourcesRes.status === 'fulfilled') sources.value = sourcesRes.value.data || [];
    if (mappingsRes.status === 'fulfilled') mappings.value = mappingsRes.value.data || [];
    if (legacyRes.status === 'fulfilled') legacyUsers.value = legacyRes.value.data?.legacyUsers || [];

    if (!tab.value) tab.value = sources.value.length ? 'mappings' : 'sources';
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
    if (selectedMigrations.value.length === 0 || starting.value) return;

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
    if (starting.value) return;
    selectedMigrations.value = migrations.value.map(m => m.key);
    startMigrations();
};

const cancelBatch = async () => {
    if (!currentBatchId.value || cancelling.value) return;

    const ok = await dialog.confirm({
        title: runStalled.value ? t('migrationDashboard.markStopped') : t('migrationDashboard.cancelBatchTitle'),
        content: runStalled.value
            ? t('migrationDashboard.markStoppedConfirm', { count: runStalledMinutes.value })
            : t('migrationDashboard.cancelBatchConfirm'),
        confirmationText: runStalled.value ? t('migrationDashboard.markStopped') : t('migrationDashboard.cancelBatchTitle'),
        cancellationText: t('dialogs.confirm.close'),
        color: 'warning',
    });
    if (!ok) return;

    cancelling.value = true;
    try {
        await axios.post(`/api/admin/migrations/cancel/${currentBatchId.value}`);
        showSnackbar(t('migrationDashboard.batchCancelled'));
        addLog('warning', t('migrationDashboard.batchCancelledByUser'));
        // Don't wait for the next poll to clear the banner and free the buttons.
        await fetchBatchStatus();
    } catch (error) {
        showSnackbar(t('migrationDashboard.failedToCancelBatch'), 'error');
    } finally {
        cancelling.value = false;
    }
};

const loadBatch = async (batchId) => {
    if (loadingBatchId.value !== null) return;
    loadingBatchId.value = batchId;
    currentBatchId.value = batchId;
    logs.value = [];
    try {
        await fetchBatchStatus();
        await fetchLogs();
    } finally {
        loadingBatchId.value = null;
    }
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
        runNow.value = Date.now();

        // Every fifth poll (~10s): the mapping rows and the guide read their
        // state from the overview, so refresh it while an import is working.
        if (isRunning.value && ++pollTick % OVERVIEW_REFRESH_EVERY === 0) {
            fetchOverview();
        }

        // Remember which post-import steps we watched finish — the guide
        // uses it when the API reports no run state of its own.
        for (const migration of response.data.migrations || []) {
            if (migration.status === 'completed' && !ranPostKeys.value.includes(migration.key)) {
                ranPostKeys.value = [...ranPostKeys.value, migration.key];
            }
        }

        // Fetch logs for running/completed migrations
        await fetchLogs();

        // Stop polling if completed
        if (response.data.status === 'completed' || response.data.status === 'completed_with_errors') {
            stopPolling();
            fetchHistory();
            fetchOverview();

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
        running: 'primary',
        completed: 'success',
        completed_with_errors: 'warning',
        failed: 'error',
    };
    return colors[status];
};

// A stalled batch has no live rows: show the ones it left mid-flight as
// stopped rather than as a spinning icon.
const rowIsStuck = (migration) => runStalled.value && RUNNING_STATES.includes(migration?.status);
const rowStatusIcon = (migration) => (rowIsStuck(migration) ? 'mdi-alert-circle-outline' : getStatusIcon(migration?.status));
const rowStatusColor = (migration) => (rowIsStuck(migration) ? 'warning' : getStatusColor(migration?.status));

// Name what ran instead of counting it. An older payload carries no names,
// so the count stays as the fallback.
const batchTitle = (batch) => (batch?.names?.length
    ? batch.names.join(', ')
    : t('migrationDashboard.migrationsCount', { count: batch?.totalMigrations || 0 }));

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
    if (archiving.value) return;
    const ok = await dialog.confirm({
        title: t('migrationTool.forumArchive'),
        content: t('migrationTool.forumArchiveConfirm', { category: archiveCategory.value }),
        confirmationText: t('migrationTool.forumArchiveRun'),
        color: 'warning',
    });
    if (!ok) return;

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

// A mapping import was queued — jump to the run view and follow its batch.
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
    try {
        const stored = localStorage.getItem(GUIDE_STORAGE_KEY);
        if (stored !== null) guideOpen.value = stored === '1';
    } catch (e) {
        // Storage unavailable — keep the guide open.
    }
    fetchMigrations();
    fetchOverview();
    fetchHistory();
});

onUnmounted(() => {
    stopPolling();
});
</script>

<style scoped>
.guide-step {
    flex: 1 1 200px;
    min-width: 190px;
    padding-left: 12px;
    border-left: 3px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.guide-step--current {
    border-left-color: rgb(var(--v-theme-primary));
}

.guide-step--done {
    border-left-color: rgb(var(--v-theme-success));
}

.guide-step__state {
    min-height: 32px;
}

.migration-row--running {
    background: rgba(var(--v-theme-primary), 0.08);
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
    background: rgba(var(--v-theme-on-surface), 0.04);
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    color: rgb(var(--v-theme-on-surface));
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.4;
    padding: 12px;
    overflow-y: auto;
    overflow-x: hidden;
}

.terminal-empty {
    height: 100%;
}

.terminal-line {
    padding: 1px 0;
    word-break: break-word;
}

.terminal-timestamp {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    margin-right: 8px;
}

.terminal-type {
    margin-right: 8px;
    font-weight: bold;
}

.terminal-line--info .terminal-type { color: rgb(var(--v-theme-info)); }
.terminal-line--log .terminal-type { color: inherit; }
.terminal-line--success .terminal-type { color: rgb(var(--v-theme-success)); }
.terminal-line--warning .terminal-type { color: rgb(var(--v-theme-warning)); }
.terminal-line--error .terminal-type { color: rgb(var(--v-theme-error)); }
.terminal-line--progress .terminal-type { color: rgb(var(--v-theme-secondary)); }

.terminal-message { color: inherit; }
.terminal-line--error .terminal-message { color: rgb(var(--v-theme-error)); }
.terminal-line--warning .terminal-message { color: rgb(var(--v-theme-warning)); }
.terminal-line--success .terminal-message { color: rgb(var(--v-theme-success)); }

.mdi-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
