<template>
    <widget-state :loading="loading" :error="error" :empty="!stats">
        <div class="d-flex flex-wrap justify-space-around text-center">
            <router-link
                v-for="item in items"
                :key="item.key"
                :to="item.to"
                class="stat-tile text-decoration-none text-high-emphasis pa-2"
            >
                <v-icon size="18" :color="item.color" class="mb-1">{{ item.icon }}</v-icon>
                <div class="text-h5 font-weight-bold">{{ stats[item.key] }}</div>
                <div class="text-caption text-medium-emphasis">{{ $t(`dashboard.widgets.stats.${item.key}`) }}</div>
            </router-link>
        </div>
    </widget-state>
</template>

<script>
import axios from 'axios';
import widgetMixin from './widgetMixin.js';
import WidgetState from './WidgetState.vue';
import { useSettingsStore } from '@/store/settingStore.js';

/**
 * Live community counters, from /api/account/dashboard/stats. Counters
 * of disabled features are hidden.
 */
export default {
    name: 'StatsWidget',
    components: { WidgetState },
    mixins: [widgetMixin],
    data() {
        return {
            stats: null,
        };
    },
    computed: {
        items() {
            const settings = useSettingsStore();
            const enabled = feature => !feature || settings.isFeatureEnabled(feature);

            return [
                { key: 'members', icon: 'mdi-account-group', color: 'primary', to: '/users', feature: null },
                { key: 'upcoming_events', icon: 'mdi-calendar-clock', color: 'primary', to: '/events', feature: 'events' },
                { key: 'wiki_pages', icon: 'mdi-book-open-variant', color: 'warning', to: '/wiki', feature: 'wiki' },
                { key: 'forum_threads', icon: 'mdi-forum', color: 'pink', to: '/forum', feature: 'forum' },
                { key: 'forum_posts', icon: 'mdi-comment-multiple', color: 'pink', to: '/forum', feature: 'forum' },
                { key: 'my_open_tickets', icon: 'mdi-ticket-confirmation', color: 'purple', to: '/account/tickets', feature: 'tickets' },
            ].filter(item => enabled(item.feature));
        },
    },
    methods: {
        async fetch() {
            const { data } = await axios.get('/api/account/dashboard/stats');
            this.stats = data.data || null;
            this.setSubtitle(this.$t('dashboard.widgets.stats.subtitle'));
        },
    },
};
</script>

<style scoped>
.stat-tile {
    min-width: 90px;
    border-radius: 8px;
}
.stat-tile:hover {
    background-color: rgba(var(--v-theme-on-surface), 0.04);
}
</style>
