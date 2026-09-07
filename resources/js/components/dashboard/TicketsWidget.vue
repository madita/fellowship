<template>
    <widget-state
        :loading="loading"
        :error="error"
        :empty="tickets.length === 0"
        empty-icon="mdi-ticket-confirmation-outline"
        :empty-text="$t('dashboard.widgets.tickets.empty')"
    >
        <v-list density="compact" class="pa-0">
            <v-list-item
                v-for="ticket in tickets"
                :key="ticket.id"
                :to="ticketLink"
                class="px-0 mb-1"
            >
                <template v-slot:prepend>
                    <v-avatar :color="priorityColor(ticket.priority)" size="24">
                        <v-icon size="12" color="white">{{ ticket.ticket_type?.icon || 'mdi-ticket' }}</v-icon>
                    </v-avatar>
                </template>
                <v-list-item-title class="text-body-2">{{ ticket.title }}</v-list-item-title>
                <v-list-item-subtitle class="text-caption">
                    <v-chip size="x-small" variant="tonal" :color="statusColor(ticket.status)" class="mr-1">
                        {{ ticket.status_label }}
                    </v-chip>
                    {{ relative(ticket.updated_at) }}
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
import { formatDateDistanceToNow } from '@/plugins/formatDate.js';

/**
 * The user's open tickets (all open tickets for admins), from /api/tickets.
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
        ticketLink() {
            return useUserStore().hasRole('admin') ? '/admin/tickets' : '/account/tickets';
        },
    },
    methods: {
        async fetch() {
            const { data } = await axios.get('/api/tickets', {
                params: { status: 'open', sort: 'updated_at', direction: 'desc', per_page: this.limit },
            });
            this.tickets = data.data || [];
            this.total = data.total || this.tickets.length;
            this.setSubtitle(this.$t('dashboard.widgets.tickets.subtitle', { count: this.total }));
        },
        priorityColor(priority) {
            return { urgent: 'error', high: 'warning', low: 'grey' }[priority] || 'purple';
        },
        statusColor(status) {
            return { open: 'primary', in_progress: 'info', pending: 'warning' }[status] || 'secondary';
        },
        relative(date) {
            return formatDateDistanceToNow(date);
        },
    },
};
</script>
