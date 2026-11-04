<template>
    <div>
        <!-- Where the content came from. Without this the report cannot
             tell a link to the old site from a link to the wider web,
             and those are the two halves worth telling apart. -->
        <v-card class="mb-4">
            <v-card-title class="text-subtitle-1 font-weight-medium">{{ $t('migrationTool.linksTitle') }}</v-card-title>
            <v-card-text>
                <div class="text-body-2 text-medium-emphasis mb-4">{{ $t('migrationTool.linksIntro') }}</div>

                <v-row align="start">
                    <v-col cols="12" md="7">
                        <v-text-field
                            v-model="hostsInput"
                            :label="$t('migrationTool.linksOldSite')"
                            :hint="$t('migrationTool.linksOldSiteHint')"
                            placeholder="oldsite.example, forum.oldsite.example"
                            prepend-inner-icon="mdi-history"
                            persistent-hint
                            density="comfortable"
                            variant="outlined"
                        />
                    </v-col>
                    <v-col cols="12" md="5" class="d-flex flex-column ga-2">
                        <v-btn
                            color="primary"
                            variant="elevated"
                            prepend-icon="mdi-text-search"
                            :loading="scanning"
                            :disabled="scanning || checking"
                            @click="scan"
                        >
                            {{ summary.links ? $t('migrationTool.linksRescan') : $t('migrationTool.linksScan') }}
                        </v-btn>
                        <div v-if="scannedAt" class="text-caption text-medium-emphasis">
                            {{ $t('migrationTool.linksScannedAt', { links: summary.links, date: scannedAt }) }}
                        </div>
                        <div v-else class="text-caption text-medium-emphasis">
                            {{ $t('migrationTool.linksNeverScanned') }}
                        </div>
                    </v-col>
                </v-row>
            </v-card-text>
        </v-card>

        <template v-if="summary.links">
            <!-- What the content points at, and how much of it answers -->
            <v-card class="mb-4">
                <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-medium">
                    <span>{{ $t('migrationTool.linksSummary') }}</span>
                    <div class="d-flex align-center ga-2">
                        <v-btn
                            v-if="checking"
                            color="error"
                            variant="text"
                            size="small"
                            prepend-icon="mdi-stop"
                            @click="stopping = true"
                        >
                            {{ $t('common.cancel') }}
                        </v-btn>
                        <v-btn
                            color="primary"
                            variant="tonal"
                            size="small"
                            prepend-icon="mdi-radar"
                            :loading="checking"
                            :disabled="scanning || checking || (!waiting && !recheck)"
                            @click="check"
                        >
                            {{ recheck
                                ? $t('migrationTool.linksCheckAgain')
                                : $t('migrationTool.linksCheck', { count: waiting }) }}
                        </v-btn>
                    </div>
                </v-card-title>
                <v-card-text>
                    <v-table density="comfortable">
                        <thead>
                            <tr>
                                <th>{{ $t('migrationTool.linksPointsAt') }}</th>
                                <th class="text-end">{{ $t('migrationTool.linksCount') }}</th>
                                <th class="text-end">{{ $t('migrationTool.linksUses') }}</th>
                                <th class="text-end">{{ $t('migrationTool.linkStatus.broken') }}</th>
                                <th class="text-end">{{ $t('migrationTool.linkStatus.unsure') }}</th>
                                <th class="text-end">{{ $t('migrationTool.linkStatus.ok') }}</th>
                                <th class="text-end">{{ $t('migrationTool.linkStatus.pending') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in scopeRows" :key="row.scope">
                                <td>
                                    <v-icon :icon="SCOPE_ICONS[row.scope]" size="small" class="me-2" />
                                    {{ $t('migrationTool.linkScope.' + row.scope) }}
                                </td>
                                <td class="text-end">{{ row.links }}</td>
                                <td class="text-end text-medium-emphasis">{{ row.uses }}</td>
                                <td class="text-end">
                                    <span :class="row.broken ? 'text-error font-weight-bold' : 'text-medium-emphasis'">{{ row.broken }}</span>
                                </td>
                                <td class="text-end">
                                    <span :class="row.unsure ? 'text-warning' : 'text-medium-emphasis'">{{ row.unsure }}</span>
                                </td>
                                <td class="text-end">
                                    <span :class="row.ok ? 'text-success' : 'text-medium-emphasis'">{{ row.ok }}</span>
                                </td>
                                <td class="text-end text-medium-emphasis">{{ row.pending }}</td>
                            </tr>
                        </tbody>
                    </v-table>

                    <v-progress-linear
                        v-if="checking"
                        :model-value="progress === null ? 0 : progress"
                        :indeterminate="progress === null"
                        color="primary"
                        height="6"
                        rounded
                        class="mt-4"
                    />
                    <div v-if="checking" class="text-caption text-medium-emphasis mt-2">
                        {{ $t('migrationTool.linksChecking', { done: checked, total: startedWith }) }}
                    </div>

                    <!-- The report is only as good as the admin's patience
                         allows, so it says out loud what it has not asked
                         about yet rather than implying everything is fine. -->
                    <div v-else-if="waiting" class="text-caption text-medium-emphasis mt-2">
                        {{ $t('migrationTool.linksWaiting', { count: waiting }) }}
                    </div>

                    <v-checkbox
                        v-model="recheck"
                        :label="$t('migrationTool.linksRecheck')"
                        :disabled="checking"
                        density="compact"
                        hide-details
                        class="mt-2"
                    />
                    <v-checkbox
                        v-model="includeInternal"
                        :label="$t('migrationTool.linksIncludeInternal')"
                        :disabled="checking"
                        density="compact"
                        hide-details
                    />
                    <div class="text-caption text-medium-emphasis mt-1">{{ $t('migrationTool.linksCliHint') }}</div>

                    <div v-if="worst.length" class="mt-4">
                        <div class="text-subtitle-2 mb-2">{{ $t('migrationTool.linksWorstHosts') }}</div>
                        <v-chip
                            v-for="host in worst"
                            :key="host.host"
                            size="small"
                            color="error"
                            variant="tonal"
                            class="me-2 mb-2"
                            @click="filterHost(host.host)"
                        >
                            {{ host.host }} · {{ $t('migrationTool.linksHostBroken', { broken: host.broken, links: host.links }) }}
                        </v-chip>
                    </div>
                </v-card-text>
            </v-card>

            <!-- The report itself -->
            <v-card>
                <v-card-title class="d-flex align-center justify-space-between flex-wrap ga-2 text-subtitle-1 font-weight-medium">
                    <span>{{ $t('migrationTool.linksReport') }}</span>
                    <div class="d-flex align-center flex-wrap ga-2">
                        <v-select
                            v-model="scopeFilter"
                            :items="scopeOptions"
                            :label="$t('migrationTool.linksPointsAt')"
                            density="compact"
                            hide-details
                            clearable
                            style="min-width: 180px;"
                        />
                        <v-select
                            v-model="statusFilter"
                            :items="statusOptions"
                            :label="$t('migrationTool.linksVerdict')"
                            density="compact"
                            hide-details
                            clearable
                            style="min-width: 170px;"
                        />
                        <v-text-field
                            v-model="search"
                            :label="$t('common.search')"
                            prepend-inner-icon="mdi-magnify"
                            density="compact"
                            hide-details
                            clearable
                            style="max-width: 220px;"
                        />
                        <v-btn
                            variant="text"
                            size="small"
                            prepend-icon="mdi-download"
                            :disabled="!links.length"
                            @click="exportCsv"
                        >
                            {{ $t('migrationTool.linksExport') }}
                        </v-btn>
                        <v-btn
                            variant="text"
                            size="small"
                            color="error"
                            prepend-icon="mdi-delete"
                            :loading="clearing"
                            :disabled="clearing"
                            @click="clear"
                        >
                            {{ $t('migrationTool.linksClear') }}
                        </v-btn>
                    </div>
                </v-card-title>
                <v-card-text class="pa-0">
                    <div v-if="hostFilter" class="px-4 pb-2">
                        <v-chip size="small" closable @click:close="filterHost(null)">
                            {{ hostFilter }}
                        </v-chip>
                    </div>
                    <div v-if="truncated" class="text-caption text-medium-emphasis px-4 pb-2">
                        {{ $t('migrationTool.linksTruncated', { shown: links.length, total: matching }) }}
                    </div>

                    <v-table v-if="links.length" density="comfortable">
                        <thead>
                            <tr>
                                <th>{{ $t('migrationTool.linksVerdict') }}</th>
                                <th>{{ $t('migrationTool.linksAddress') }}</th>
                                <th class="text-end">{{ $t('migrationTool.linksUses') }}</th>
                                <th>{{ $t('migrationTool.linksFoundOn') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="link in links" :key="link.id">
                                <td style="width: 150px;">
                                    <v-chip
                                        :color="STATUS_COLOURS[link.status] || undefined"
                                        size="small"
                                        variant="tonal"
                                        label
                                    >
                                        {{ $t('migrationTool.linkStatus.' + link.status) }}
                                        <template v-if="link.http_status"> · {{ link.http_status }}</template>
                                    </v-chip>
                                </td>
                                <td>
                                    <div class="d-flex align-center ga-1">
                                        <v-icon
                                            :icon="link.kind === 'image' ? 'mdi-image-outline' : SCOPE_ICONS[link.scope]"
                                            size="x-small"
                                            class="text-medium-emphasis"
                                        />
                                        <a
                                            :href="link.url"
                                            target="_blank"
                                            rel="noopener noreferrer nofollow"
                                            class="text-body-2 link-address"
                                        >{{ link.url }}</a>
                                    </div>
                                    <div v-if="link.redirect_url" class="text-caption text-medium-emphasis">
                                        → {{ link.redirect_url }}
                                    </div>
                                    <div v-else-if="link.reason" class="text-caption text-medium-emphasis">
                                        {{ link.reason }}
                                    </div>
                                </td>
                                <td class="text-end">{{ link.uses }}</td>
                                <td>
                                    <template v-for="(place, index) in link.occurrences" :key="index">
                                        <v-chip
                                            :href="place.url"
                                            :target="place.url ? '_blank' : undefined"
                                            size="x-small"
                                            variant="tonal"
                                            class="me-1 mb-1"
                                        >
                                            {{ place.title || place.label }}
                                        </v-chip>
                                    </template>
                                    <span v-if="link.uses > link.occurrences.length" class="text-caption text-medium-emphasis">
                                        {{ $t('migrationTool.linksMorePlaces', { count: link.uses - link.occurrences.length }) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </v-table>
                    <empty-state
                        v-else
                        compact
                        icon="mdi-link-variant-off"
                        :title="$t('migrationTool.linksNoneMatching')"
                    />
                </v-card-text>
            </v-card>
        </template>

        <empty-state
            v-else-if="!scanning && loaded"
            icon="mdi-link-variant"
            :title="$t('migrationTool.linksNothingYet')"
            :text="$t('migrationTool.linksNothingYetHint')"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useDialog } from '@/composables/useDialog.js';
import EmptyState from '../../common/EmptyState.vue';
import {
    DEFAULT_SCOPES,
    SCOPES,
    SCOPE_ICONS,
    STATUSES,
    STATUS_COLOURS,
    checkProgress,
    presentScopes,
    scopeRow,
    toCsv,
    waitingIn,
    worstHosts,
} from '@/utils/migrationLinks.js';

const { t } = useI18n();
const feedback = useDialog();
const emit = defineEmits(['notify']);

const summary = ref({});
const links = ref([]);
const hosts = ref([]);
const matching = ref(0);
const loaded = ref(false);

const hostsInput = ref('');
const scanning = ref(false);
const clearing = ref(false);

const checking = ref(false);
const stopping = ref(false);
const checked = ref(0);
const startedWith = ref(0);
const recheck = ref(false);
const includeInternal = ref(false);

const scopeFilter = ref(null);
const statusFilter = ref(null);
const hostFilter = ref(null);
const search = ref('');

// One request per typed letter would be one request too many.
const SEARCH_DEBOUNCE_MS = 350;
let searchTimer = null;

const scopes = computed(() => includeInternal.value ? [...DEFAULT_SCOPES, 'internal'] : DEFAULT_SCOPES);
const waiting = computed(() => waitingIn(summary.value, scopes.value));
const progress = computed(() => checkProgress(summary.value, scopes.value, startedWith.value));
const scopeRows = computed(() => presentScopes(summary.value).map(scope => scopeRow(summary.value, scope)));
const worst = computed(() => worstHosts(hosts.value));
const truncated = computed(() => matching.value > links.value.length);

const scannedAt = computed(() => {
    const when = summary.value?.scanned_at;
    if (!when) return null;

    const date = new Date(when);
    return Number.isNaN(date.getTime()) ? null : date.toLocaleString();
});

const scopeOptions = computed(() => SCOPES.map(scope => ({
    value: scope,
    title: t('migrationTool.linkScope.' + scope),
})));

const statusOptions = computed(() => STATUSES.map(status => ({
    value: status,
    title: t('migrationTool.linkStatus.' + status),
})));

const fetchReport = async () => {
    try {
        const { data } = await axios.get('/api/admin/migrations/links', {
            params: {
                scope: scopeFilter.value || undefined,
                status: statusFilter.value || undefined,
                host: hostFilter.value || undefined,
                search: search.value || undefined,
            },
        });

        summary.value = data.summary || {};
        links.value = data.links || [];
        hosts.value = data.hosts || [];
        matching.value = data.matching || 0;

        // Only ever filled from the backend while the field is idle, so
        // a half-typed domain is not overwritten underneath the cursor
        if (!hostsInput.value) {
            hostsInput.value = (data.legacy_hosts || []).join(', ');
        }
    } catch (e) {
        emit('notify', { text: t('migrationTool.loadFailed'), color: 'error' });
    } finally {
        loaded.value = true;
    }
};

const scan = async () => {
    if (scanning.value) return;
    scanning.value = true;

    try {
        const { data } = await axios.post('/api/admin/migrations/links/scan', {
            legacy_hosts: hostsInput.value.split(/[\s,;]+/).filter(Boolean),
        });

        hostsInput.value = (data.legacy_hosts || []).join(', ');
        startedWith.value = 0;
        checked.value = 0;
        await fetchReport();

        emit('notify', { text: data.message, color: 'success' });

        if (data.result?.truncated) {
            await feedback.warning(t('migrationTool.linksTruncatedScan'));
        }
    } catch (e) {
        await feedback.requestError(e);
    } finally {
        scanning.value = false;
    }
};

const check = async () => {
    if (checking.value) return;

    checking.value = true;
    stopping.value = false;
    checked.value = 0;

    // Asking again means putting the verdicts back first — and only on
    // the first call, or the run would keep resetting what it just did
    // and never finish.
    let reset = recheck.value;

    try {
        for (;;) {
            const { data } = await axios.post('/api/admin/migrations/links/check', {
                limit: 10,
                scopes: scopes.value,
                reset,
            });

            reset = false;
            summary.value = data.summary || summary.value;
            checked.value += data.result?.checked || 0;

            if (!startedWith.value) {
                startedWith.value = checked.value + (data.result?.remaining || 0);
            }

            if (stopping.value || !data.result?.checked || !data.result?.remaining) break;
        }

        recheck.value = false;
        await fetchReport();
    } catch (e) {
        await feedback.requestError(e);
    } finally {
        checking.value = false;
        stopping.value = false;
        startedWith.value = 0;
    }
};

const clear = async () => {
    if (!(await feedback.confirmDelete(t('migrationTool.linksConfirmClear')))) return;

    clearing.value = true;

    try {
        const { data } = await axios.delete('/api/admin/migrations/links');
        emit('notify', { text: data.message, color: 'success' });
        await fetchReport();
    } catch (e) {
        await feedback.requestError(e);
    } finally {
        clearing.value = false;
    }
};

const filterHost = async (host) => {
    hostFilter.value = hostFilter.value === host ? null : host;
    await fetchReport();
};

const exportCsv = () => {
    const csv = toCsv(links.value, {
        url: t('migrationTool.linksAddress'),
        host: t('migrationTool.linksHost'),
        scope: t('migrationTool.linksPointsAt'),
        status: t('migrationTool.linksVerdict'),
        uses: t('migrationTool.linksUses'),
        where: t('migrationTool.linksFoundOn'),
    });

    // Byte order mark: without it a spreadsheet opens the umlauts wrong
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'migration-links.csv';
    link.click();
    URL.revokeObjectURL(link.href);
};

watch([scopeFilter, statusFilter], fetchReport);

watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(fetchReport, SEARCH_DEBOUNCE_MS);
});

onMounted(fetchReport);
</script>

<style scoped>
.link-address {
    color: inherit;
    word-break: break-all;
}
</style>
