<template>
    <widget-state
        :loading="loading"
        :error="error"
        :empty="tickets.length === 0"
        empty-icon="mdi-ticket-confirmation-outline"
        :empty-text="$t(`dashboard.widgets.tickets.empty.${scope}`)"
    >
        <v-list density="compact" class="pa-0">
            <v-list-item
                v-for="ticket in tickets"
                :key="ticket.id"
                :to="ticketLink(ticket)"
                class="px-0 mb-1"
            >
                <template v-slot:prepend>
                    <v-avatar :color="getPriorityColor(ticket.priority)" size="24" :title="getPriorityLabel(ticket.priority)">
                        <v-icon size="12" color="white">{{ getPriorityIcon(ticket.priority) }}</v-icon>
                    </v-avatar>
                </template>
                <v-list-item-title class="text-body-2">{{ ticket.title }}</v-list-item-title>
                <v-list-item-subtitle class="text-caption">
                    <v-chip size="x-small" variant="tonal" :color="getStatusColor(ticket.status)" class="mr-1">
                        {{ getStatusLabel(ticket.status) }}
                    </v-chip>
                    <span v-if="ticket.due_date" :class="isOverdue(ticket) ? 'text-error font-weight-medium' : ''">
                        <v-icon size="12" class="mr-1">{{ isOverdue(ticket) ? 'mdi-clock-alert-outline' : 'mdi-calendar-clock' }}</v-icon>
                        {{ relative(ticket.due_date) }}
                    </span>
                    <span v-else>{{ relative(ticket.updated_at) }}</span>
                    <span v-if="scope !== 'assigned' && ticket.assignee" class="text-medium-emphasis">
                        · {{ ticket.assignee.username }}
                    </span>
                </v-list-item-subtitle>
            </v-list-item>
        </v-list>
    </widget-state>
</template>

<script>
import axios from 'axios';
import widgetMixin from './widgetMixin.js';
import WidgetState from './WidgetState.vue';
import { useUserStore } from '@/store/userStore.js';
import { useTicketHelpers } from '@/composables/useTicketHelpers.js';
import { formatDateDistanceToNow } from '@/plugins/formatDate.js';

/**
 * A ticket queue from /api/tickets. The widget settings choose the queue
 * (my tickets, assigned to me, unassigned, all open) and the ordering.
 */
export default {
    name: 'TicketsWidget',
    components: { WidgetState },
    mixins: [widgetMixin],
    data() {
        return {
            tickets: [],
            total: 0,
        };
    },
    computed: {
        isAdmin() {
            return useUserStore().hasRole('admin');
        },
        scope() {
            const scope = this.widgetConfig?.scope || 'mine';
            // Admin-only queues fall back for everyone else.
            return !this.isAdmin && ['unassigned', 'all'].includes(scope) ? 'mine' : scope;
        },
        sort() {
            return this.widgetConfig?.sort || 'updated_at';
        },
        listPath() {
            return this.isAdmin ? '/admin/tickets' : '/account/tickets';
        },
    },
    watch: {
        scope() {
            this.load();
        },
        sort() {
            this.load();
        },
    },
    methods: {
        async fetch() {
            const params = {
                status: 'open',
                per_page: this.limit,
                sort: this.sort,
                direction: this.sort === 'due_date' ? 'asc' : 'desc',
                ...this.scopeParams(),
            };
            const { data } = await axios.get('/api/tickets', { params });
            this.tickets = data.data || [];
            this.total = data.total ?? this.tickets.length;
            this.setSubtitle(this.$t(`dashboard.widgets.tickets.subtitle.${this.scope}`, { count: this.total }));
        },
        scopeParams() {
            return {
                mine: { mine: 1 },
                assigned: { assigned_to: 'me' },
                unassigned: { assigned_to: 'unassigned' },
                all: {},
            }[this.scope];
        },
        ticketLink(ticket) {
            return { path: this.listPath, query: { ...this.scopeParams(), status: 'open', search: ticket.title } };
        },
        isOverdue(ticket) {
            return ticket.due_date && new Date(ticket.due_date) < new Date();
        },
        relative(date) {
            return formatDateDistanceToNow(date);
        },
    },
    // Status/priority colours and labels (needs the i18n setup context).
    setup() {
        const { getStatusColor, getStatusLabel, getPriorityColor, getPriorityIcon, getPriorityLabel } = useTicketHelpers();

        return { getStatusColor, getStatusLabel, getPriorityColor, getPriorityIcon, getPriorityLabel };
    },
};
</script>
