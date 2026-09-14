<template>
    <widget-state
        :loading="loading"
        :error="error"
        :empty="events.length === 0"
        empty-icon="mdi-calendar-blank-outline"
        :empty-text="$t('dashboard.widgets.events.empty')"
    >
        <v-card v-if="nextEvent" color="primary" variant="tonal" class="mb-3" :to="nextEvent.url">
            <v-card-text class="pa-3">
                <div class="d-flex align-center mb-2">
                    <v-chip color="primary" variant="tonal" size="small" class="mr-2">{{ $t('dashboard.widgets.events.next') }}</v-chip>
                    <div class="text-caption">{{ relative(nextEvent.start) }}</div>
                </div>
                <div class="text-subtitle-2 font-weight-bold mb-1">{{ nextEvent.title }}</div>
                <div class="text-caption">{{ formatWhen(nextEvent) }}</div>
                <div v-if="locationLabel(nextEvent)" class="text-caption text-medium-emphasis">
                    <v-icon size="12" class="mr-1">mdi-map-marker-outline</v-icon>{{ locationLabel(nextEvent) }}
                </div>
            </v-card-text>
        </v-card>
        <v-list density="compact" class="pa-0">
            <v-list-item
                v-for="event in otherEvents"
                :key="event.id"
                :to="event.url"
                class="px-0 mb-1"
            >
                <template v-slot:prepend>
                    <v-avatar :color="event.color || 'surface-variant'" size="24">
                        <v-icon size="12" color="white">mdi-calendar</v-icon>
                    </v-avatar>
                </template>
                <v-list-item-title class="text-body-2">{{ event.title }}</v-list-item-title>
                <v-list-item-subtitle class="text-caption">{{ formatWhen(event) }}</v-list-item-subtitle>
            </v-list-item>
        </v-list>
    </widget-state>
</template>

<script>
import axios from 'axios';
import widgetMixin from './widgetMixin.js';
import WidgetState from './WidgetState.vue';
import { formatDateDistanceToNow } from '@/plugins/formatDate.js';

/**
 * The next upcoming events, from /api/events/upcoming.
 */
export default {
    name: 'EventsWidget',
    components: { WidgetState },
    mixins: [widgetMixin],
    data() {
        return {
            events: [],
            total: 0,
        };
    },
    computed: {
        nextEvent() {
            return this.events[0] || null;
        },
        otherEvents() {
            return this.events.slice(1);
        },
    },
    methods: {
        async fetch() {
            const { data } = await axios.get('/api/events/upcoming', {
                params: { limit: this.limit, days: this.widgetConfig?.days || 90 },
            });
            this.events = data.data?.events || [];
            this.total = data.data?.total || 0;
            this.setSubtitle(this.$t('dashboard.widgets.events.subtitle', { count: this.total }));
        },
        formatWhen(event) {
            const date = this.$formatDate(event.startDate);
            return event.allDay ? date : `${date} · ${event.startTime.slice(0, 5)}`;
        },
        relative(date) {
            return formatDateDistanceToNow(date);
        },
        locationLabel(event) {
            const loc = event.location;
            if (!loc) return '';
            return loc.name || loc.address || loc.irc_channel || loc.url || '';
        },
    },
};
</script>
