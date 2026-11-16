<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import TicketTrendChart from '@/components/ticket/TicketTrendChart.vue';
import { useTicketHelpers } from '@/composables/useTicketHelpers.js';
import { useDialog } from '@/composables/useDialog.js';
import { formatDateDistanceToNow } from '@/plugins/formatDate.js';

/**
 * Overview of the ticket admin. Every count that maps to a list filter is
 * clickable: `filter` hands the filters to the list view, `open-ticket`
 * opens a ticket (by id).
 */

const emit = defineEmits(['filter', 'open-ticket']);

const { t } = useI18n();
const dialog = useDialog();
const { statusOptions, getStatusColor, getStatusLabel, priorityOptions, getPriorityLabel } = useTicketHelpers();

const stats = ref(null);
const loading = ref(false);

const loadStats = async () => {
    if (loading.value) return;
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/tickets/stats');
        stats.value = data.data;
    } catch (err) {
        console.error('Failed to load ticket stats:', err);
        await dialog.requestError(err, t('tickets.overview.loadFailed'));
    } finally {
        loading.value = false;
    }
};

const queueTiles = computed(() => [
    { key: 'open', icon: 'mdi-ticket-outline', color: 'primary', filter: { status: 'open' } },
    { key: 'unassigned', icon: 'mdi-account-question-outline', color: 'secondary', filter: { status: 'open', assigned_to: 'unassigned' } },
    { key: 'overdue', icon: 'mdi-clock-alert-outline', color: 'error', filter: { status: 'open', due: 'overdue' } },
    { key: 'urgent', icon: 'mdi-alert-outline', color: 'warning', filter: { status: 'open', priority: 'urgent' } },
]);

const activityTiles = computed(() => [
    { key: 'created_7d', previous: stats.value.created_prev_7d, icon: 'mdi-plus-circle-outline' },
    { key: 'resolved_7d', previous: stats.value.resolved_prev_7d, icon: 'mdi-check-circle-outline' },
]);

const avgResolution = computed(() => {
    const hours = stats.value?.avg_resolution_hours;
    if (hours === null || hours === undefined) return '–';
    if (hours < 1) return t('tickets.overview.lessThanHour');
    if (hours < 48) return t('tickets.overview.hours', { count: Math.round(hours) });
    return t('tickets.overview.days', { count: Math.round(hours / 24) });
});

const statusTotal = computed(() => Object.values(stats.value?.by_status || {}).reduce((sum, n) => sum + n, 0));

const maxOf = (items, key = 'open') => Math.max(...items.map(item => item[key]), 1);

const priorityRows = computed(() => priorityOptions.map(p => ({
    ...p,
    open: stats.value.by_priority[p.value] ?? 0,
})));

const openFilter = (filter) => emit('filter', filter);

onMounted(loadStats);

defineExpose({ loadStats });
</script>

<template>
    <div class="ticket-overview">
        <loading-state v-if="loading && !stats" />

        <template v-else-if="stats">
            <div class="d-flex align-center mb-4">
                <h2 class="text-h6 font-weight-medium">{{ t('tickets.overview.title') }}</h2>
                <v-spacer />
                <v-btn variant="tonal" size="small" prepend-icon="mdi-refresh" :loading="loading" @click="loadStats">
                    {{ t('tickets.overview.refresh') }}
                </v-btn>
            </div>

            <!-- Queues and activity -->
            <v-row dense class="mb-2">
                <v-col v-for="tile in queueTiles" :key="tile.key" cols="6" sm="4" lg="2">
                    <v-card
                        variant="tonal"
                        :color="tile.color"
                        class="h-100"
                        :aria-label="t('tickets.overview.showInList', { label: t(`tickets.overview.stats.${tile.key}`) })"
                        @click="openFilter(tile.filter)"
                    >
                        <v-card-text class="d-flex align-center pa-3">
                            <v-icon size="28" class="mr-3" :icon="tile.icon" />
                            <div>
                                <div class="text-h5 font-weight-bold">{{ stats[tile.key] }}</div>
                                <div class="text-caption">{{ t(`tickets.overview.stats.${tile.key}`) }}</div>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col v-for="tile in activityTiles" :key="tile.key" cols="6" sm="4" lg="2">
                    <v-card variant="outlined" class="h-100">
                        <v-card-text class="d-flex align-center pa-3">
                            <v-icon size="28" class="mr-3" color="medium-emphasis" :icon="tile.icon" />
                            <div>
                                <div class="text-h5 font-weight-bold">{{ stats[tile.key] }}</div>
                                <div class="text-caption">{{ t(`tickets.overview.stats.${tile.key}`) }}</div>
                                <div class="text-caption text-medium-emphasis">
                                    {{ t('tickets.overview.previousWeek', { count: tile.previous }) }}
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <v-row class="mb-2">
                <!-- Created vs resolved -->
                <v-col cols="12" lg="8">
                    <v-card variant="outlined" class="h-100">
                        <v-card-title class="text-subtitle-1 font-weight-medium">
                            {{ t('tickets.overview.trend.title', { days: stats.trend.length }) }}
                        </v-card-title>
                        <v-card-text>
                            <ticket-trend-chart :points="stats.trend" />
                        </v-card-text>
                    </v-card>
                </v-col>

                <!-- Status and resolution time -->
                <v-col cols="12" lg="4">
                    <v-card variant="outlined" class="h-100">
                        <v-card-title class="text-subtitle-1 font-weight-medium">
                            {{ t('tickets.overview.byStatus') }}
                        </v-card-title>
                        <v-card-text>
                            <div v-if="statusTotal" class="status-bar d-flex mb-3" role="img" :aria-label="t('tickets.overview.byStatus')">
                                <div
                                    v-for="status in statusOptions.filter(s => stats.by_status[s.value])"
                                    :key="status.value"
                                    :class="`bg-${status.color}`"
                                    :style="{ flexGrow: stats.by_status[status.value] }"
                                    :title="`${getStatusLabel(status.value)}: ${stats.by_status[status.value]}`"
                                />
                            </div>
                            <v-list density="compact" class="pa-0">
                                <v-list-item
                                    v-for="status in statusOptions"
                                    :key="status.value"
                                    class="px-0"
                                    @click="openFilter({ status: status.value })"
                                >
                                    <template #prepend>
                                        <v-icon size="12" :color="getStatusColor(status.value)" icon="mdi-circle" class="mr-2" />
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ getStatusLabel(status.value) }}</v-list-item-title>
                                    <template #append>
                                        <span class="font-weight-bold">{{ stats.by_status[status.value] }}</span>
                                    </template>
                                </v-list-item>
                            </v-list>
                            <v-divider class="my-3" />
                            <div class="d-flex align-center">
                                <v-icon icon="mdi-timer-outline" class="mr-3" color="medium-emphasis" />
                                <div>
                                    <div class="text-h6 font-weight-bold">{{ avgResolution }}</div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ t('tickets.overview.avgResolution', { days: stats.trend.length }) }}
                                    </div>
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <v-row class="mb-2">
                <!-- Open by type -->
                <v-col cols="12" md="4">
                    <v-card variant="outlined" class="h-100">
                        <v-card-title class="text-subtitle-1 font-weight-medium">{{ t('tickets.overview.byType') }}</v-card-title>
                        <v-card-text>
                            <v-list density="compact" class="pa-0">
                                <v-list-item
                                    v-for="type in stats.by_type"
                                    :key="type.slug"
                                    class="px-0"
                                    @click="openFilter({ status: 'open', type: type.slug })"
                                >
                                    <template #prepend>
                                        <v-icon size="18" :color="type.color" :icon="type.icon || 'mdi-ticket-outline'" class="mr-2" />
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ type.name }}</v-list-item-title>
                                    <v-progress-linear
                                        :model-value="(type.open / maxOf(stats.by_type)) * 100"
                                        color="primary"
                                        rounded
                                        height="4"
                                        class="mt-1"
                                    />
                                    <template #append>
                                        <span class="font-weight-bold ml-3">{{ type.open }}</span>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </v-card-text>
                    </v-card>
                </v-col>

                <!-- Open by priority -->
                <v-col cols="12" md="4">
                    <v-card variant="outlined" class="h-100">
                        <v-card-title class="text-subtitle-1 font-weight-medium">{{ t('tickets.overview.byPriority') }}</v-card-title>
                        <v-card-text>
                            <v-list density="compact" class="pa-0">
                                <v-list-item
                                    v-for="priority in priorityRows"
                                    :key="priority.value"
                                    class="px-0"
                                    @click="openFilter({ status: 'open', priority: priority.value })"
                                >
                                    <template #prepend>
                                        <v-icon size="18" :color="priority.color" :icon="priority.icon" class="mr-2" />
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ getPriorityLabel(priority.value) }}</v-list-item-title>
                                    <v-progress-linear
                                        :model-value="(priority.open / maxOf(priorityRows)) * 100"
                                        color="primary"
                                        rounded
                                        height="4"
                                        class="mt-1"
                                    />
                                    <template #append>
                                        <span class="font-weight-bold ml-3">{{ priority.open }}</span>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </v-card-text>
                    </v-card>
                </v-col>

                <!-- Workload -->
                <v-col cols="12" md="4">
                    <v-card variant="outlined" class="h-100">
                        <v-card-title class="text-subtitle-1 font-weight-medium">{{ t('tickets.overview.byAssignee') }}</v-card-title>
                        <v-card-text>
                            <v-list density="compact" class="pa-0">
                                <v-list-item
                                    v-for="assignee in stats.by_assignee"
                                    :key="assignee.id"
                                    class="px-0"
                                >
                                    <template #prepend>
                                        <v-avatar size="24" color="primary" class="mr-2">
                                            <span class="text-caption">{{ (assignee.username || '?').charAt(0).toUpperCase() }}</span>
                                        </v-avatar>
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ assignee.username || t('tickets.unknown') }}</v-list-item-title>
                                    <v-progress-linear
                                        :model-value="(assignee.open / maxOf([...stats.by_assignee, { open: stats.unassigned }])) * 100"
                                        color="primary"
                                        rounded
                                        height="4"
                                        class="mt-1"
                                    />
                                    <template #append>
                                        <span class="font-weight-bold ml-3">{{ assignee.open }}</span>
                                    </template>
                                </v-list-item>
                                <v-list-item class="px-0" @click="openFilter({ status: 'open', assigned_to: 'unassigned' })">
                                    <template #prepend>
                                        <v-avatar size="24" variant="tonal" class="mr-2">
                                            <v-icon size="16" icon="mdi-account-question-outline" />
                                        </v-avatar>
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ t('tickets.unassigned') }}</v-list-item-title>
                                    <v-progress-linear
                                        :model-value="(stats.unassigned / maxOf([...stats.by_assignee, { open: stats.unassigned }])) * 100"
                                        color="primary"
                                        rounded
                                        height="4"
                                        class="mt-1"
                                    />
                                    <template #append>
                                        <span class="font-weight-bold ml-3">{{ stats.unassigned }}</span>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <v-row>
                <!-- Needs attention -->
                <v-col cols="12" lg="7">
                    <v-card variant="outlined" class="h-100">
                        <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                            <v-icon class="mr-2" color="error" icon="mdi-alert-circle-outline" />
                            {{ t('tickets.overview.attention') }}
                        </v-card-title>
                        <v-card-text>
                            <empty-state
                                v-if="!stats.attention.length"
                                compact
                                icon="mdi-check-all"
                                :title="t('tickets.overview.attentionEmpty')"
                            />
                            <v-list v-else density="compact" class="pa-0">
                                <v-list-item
                                    v-for="ticket in stats.attention"
                                    :key="ticket.id"
                                    class="px-0"
                                    @click="emit('open-ticket', ticket.id)"
                                >
                                    <template #prepend>
                                        <v-icon size="18" :color="ticket.type?.color" :icon="ticket.type?.icon || 'mdi-ticket-outline'" class="mr-2" />
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ ticket.title }}</v-list-item-title>
                                    <v-list-item-subtitle class="text-caption">
                                        #{{ ticket.id }} · {{ ticket.assignee || t('tickets.unassigned') }} · {{ formatDateDistanceToNow(ticket.created_at) }}
                                    </v-list-item-subtitle>
                                    <template #append>
                                        <div class="d-flex ga-1 ml-2">
                                            <v-chip v-if="ticket.overdue" size="x-small" variant="tonal" color="error" prepend-icon="mdi-clock-alert-outline">
                                                {{ t('tickets.overview.overdue') }}
                                            </v-chip>
                                            <v-chip
                                                v-if="['urgent', 'high'].includes(ticket.priority)"
                                                size="x-small"
                                                variant="tonal"
                                                :color="ticket.priority === 'urgent' ? 'error' : 'warning'"
                                                :prepend-icon="ticket.priority === 'urgent' ? 'mdi-alert' : 'mdi-arrow-up'"
                                            >
                                                {{ getPriorityLabel(ticket.priority) }}
                                            </v-chip>
                                        </div>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </v-card-text>
                    </v-card>
                </v-col>

                <!-- Most voted feedback -->
                <v-col cols="12" lg="5">
                    <v-card variant="outlined" class="h-100">
                        <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                            <v-icon class="mr-2" color="primary" icon="mdi-arrow-up-bold-outline" />
                            {{ t('tickets.overview.topFeedback') }}
                        </v-card-title>
                        <v-card-text>
                            <empty-state
                                v-if="!stats.top_feedback.length"
                                compact
                                icon="mdi-message-alert-outline"
                                :title="t('tickets.overview.topFeedbackEmpty')"
                            />
                            <v-list v-else density="compact" class="pa-0">
                                <v-list-item
                                    v-for="ticket in stats.top_feedback"
                                    :key="ticket.id"
                                    class="px-0"
                                    @click="emit('open-ticket', ticket.id)"
                                >
                                    <template #prepend>
                                        <v-icon size="18" :color="ticket.type?.color" :icon="ticket.type?.icon || 'mdi-ticket-outline'" class="mr-2" />
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ ticket.title }}</v-list-item-title>
                                    <v-list-item-subtitle class="text-caption">
                                        #{{ ticket.id }} · {{ getStatusLabel(ticket.status) }}
                                    </v-list-item-subtitle>
                                    <template #append>
                                        <v-btn
                                            icon="mdi-open-in-new"
                                            size="x-small"
                                            variant="text"
                                            :to="`/feedback/${ticket.id}`"
                                            :title="t('tickets.overview.viewPublic')"
                                            :aria-label="t('tickets.overview.viewPublic')"
                                            @click.stop
                                        />
                                        <v-chip size="small" variant="tonal" prepend-icon="mdi-arrow-up-bold" class="ml-1">
                                            {{ ticket.votes_count }}
                                        </v-chip>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </template>
    </div>
</template>

<style scoped>
.status-bar {
    height: 10px;
    gap: 2px;
}

.status-bar > div {
    flex-basis: 0;
    min-width: 4px;
}

.status-bar > div:first-child {
    border-radius: 4px 0 0 4px;
}

.status-bar > div:last-child {
    border-radius: 0 4px 4px 0;
}
</style>
