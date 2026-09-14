<template>
    <widget-state :loading="loading" :error="error" :empty="!counts">
        <!-- Queues: each tile opens the ticket list filtered accordingly -->
        <div class="d-flex flex-wrap justify-space-around text-center mb-3">
            <router-link
                v-for="tile in tiles"
                :key="tile.key"
                :to="tile.to"
                class="queue-tile text-decoration-none text-high-emphasis pa-2"
                :class="{ 'queue-tile--alert': tile.alert && counts[tile.key] > 0 }"
            >
                <v-icon size="18" :color="tile.color" class="mb-1">{{ tile.icon }}</v-icon>
                <div class="text-h5 font-weight-bold">{{ counts[tile.key] }}</div>
                <div class="text-caption text-medium-emphasis">{{ $t(`dashboard.widgets.ticketOverview.${tile.key}`) }}</div>
            </router-link>
        </div>

        <!-- Open tickets by status -->
        <template v-if="counts.open > 0">
            <div class="d-flex rounded overflow-hidden mb-1" style="height: 8px;">
                <div
                    v-for="status in statuses"
                    :key="status.value"
                    :class="`bg-${status.color}`"
                    :style="{ width: `${(counts.by_status[status.value] / counts.open) * 100}%` }"
                />
            </div>
            <div class="d-flex flex-wrap text-caption mb-3 ga-1">
                <router-link
                    v-for="status in statuses"
                    :key="status.value"
                    :to="ticketLink({ status: status.value })"
                    class="text-decoration-none text-medium-emphasis"
                >
                    <v-icon size="10" :color="status.color" class="mr-1">mdi-circle</v-icon>
                    {{ $t(`tickets.status.${status.value}`) }}: {{ counts.by_status[status.value] }}
                </router-link>
            </div>
        </template>

        <!-- Urgent / high priority and recently resolved -->
        <div class="d-flex flex-wrap align-center ga-2">
            <v-chip
                v-for="priority in urgentPriorities"
                :key="priority.value"
                size="x-small"
                variant="tonal"
                :color="priority.color"
                :prepend-icon="priority.icon"
                :to="ticketLink({ priority: priority.value })"
            >
                {{ $t(`tickets.priority.${priority.value}`) }}: {{ counts.by_priority[priority.value] }}
            </v-chip>
            <v-chip size="x-small" variant="tonal" color="success" prepend-icon="mdi-check-circle-outline">
                {{ $t('dashboard.widgets.ticketOverview.resolved7Days', { count: counts.resolved_7_days }) }}
            </v-chip>
        </div>
    </widget-state>
</template>

<script>
import axios from 'axios';
import widgetMixin from './widgetMixin.js';
import WidgetState from './WidgetState.vue';
import { useUserStore } from '@/store/userStore.js';
import { useTicketHelpers } from '@/composables/useTicketHelpers.js';

/**
 * Ticket queue counters (assigned to me, created by me, unassigned,
 * overdue, due this week) with status and priority breakdowns, from
 * /api/account/dashboard/tickets. Every number links to the filtered list.
 */
export default {
    name: 'TicketOverviewWidget',
    components: { WidgetState },
    mixins: [widgetMixin],
    data() {
        return {
            counts: null,
        };
    },
    computed: {
        isAdmin() {
            return useUserStore().hasRole('admin');
        },
        listPath() {
            return this.isAdmin ? '/admin/tickets' : '/account/tickets';
        },
        tiles() {
            const tiles = [
                { key: 'assigned_to_me', icon: 'mdi-account-check', color: 'primary', to: this.ticketLink({ assigned_to: 'me' }) },
                { key: 'created_by_me', icon: 'mdi-account-edit', color: 'info', to: this.ticketLink({ created_by: 'me' }) },
                { key: 'unassigned', icon: 'mdi-account-question', color: 'warning', to: this.ticketLink({ assigned_to: 'unassigned' }), adminOnly: true },
                { key: 'overdue', icon: 'mdi-clock-alert', color: 'error', to: this.ticketLink({ due: 'overdue' }), alert: true },
                { key: 'due_this_week', icon: 'mdi-calendar-clock', color: 'orange', to: this.ticketLink({ due: 'week' }) },
            ];
            return tiles.filter(tile => !tile.adminOnly || this.counts?.is_admin);
        },
        statuses() {
            return this.statusOptions.filter(s => ['open', 'in_progress', 'pending'].includes(s.value));
        },
        urgentPriorities() {
            return this.priorityOptions.filter(p => ['urgent', 'high'].includes(p.value));
        },
    },
    // Status/priority colours (needs the i18n setup context).
    setup() {
        const { statusOptions, priorityOptions } = useTicketHelpers();

        return { statusOptions, priorityOptions };
    },
    methods: {
        async fetch() {
            const { data } = await axios.get('/api/account/dashboard/tickets');
            this.counts = data.data || null;
            this.setSubtitle(this.$t('dashboard.widgets.ticketOverview.subtitle', { count: this.counts?.open || 0 }));
        },
        ticketLink(query) {
            return { path: this.listPath, query: { status: 'open', ...query } };
        },
    },
};
</script>

<style scoped>
.queue-tile {
    min-width: 84px;
    border-radius: 8px;
}
.queue-tile:hover {
    background-color: rgba(var(--v-theme-on-surface), 0.04);
}
.queue-tile--alert {
    background-color: rgba(var(--v-theme-error), 0.08);
}
</style>
