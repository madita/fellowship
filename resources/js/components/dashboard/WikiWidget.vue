<template>
    <widget-state
        :loading="loading"
        :error="error"
        :empty="changes.length === 0"
        empty-icon="mdi-book-open-page-variant-outline"
        :empty-text="$t('dashboard.widgets.wiki.empty')"
    >
        <router-link
            v-for="change in changes"
            :key="change.id"
            :to="change.url"
            class="d-block mb-3 text-decoration-none text-high-emphasis"
        >
            <div class="d-flex align-center mb-1">
                <v-chip
                    :color="change.action === 'created' ? 'success' : 'warning'"
                    size="x-small"
                    class="mr-2"
                >
                    {{ $t(`dashboard.widgets.wiki.${change.action === 'created' ? 'created' : 'edited'}`) }}
                </v-chip>
                <div class="text-caption text-medium-emphasis">{{ relative(change.date) }}</div>
            </div>
            <div class="text-body-2 font-weight-medium">{{ change.title }}</div>
            <div v-if="change.author" class="text-caption text-medium-emphasis">
                {{ $t('dashboard.widgets.wiki.by', { name: change.author.username }) }}
            </div>
        </router-link>
    </widget-state>
</template>

<script>
import axios from 'axios';
import widgetMixin from './widgetMixin.js';
import WidgetState from './WidgetState.vue';
import { formatDateDistanceToNow } from '@/plugins/formatDate.js';

/**
 * Recently created or edited wiki pages, from /api/wiki/recent-changes.
 */
export default {
    name: 'WikiWidget',
    components: { WidgetState },
    mixins: [widgetMixin],
    data() {
        return {
            changes: [],
        };
    },
    methods: {
        async fetch() {
            const { data } = await axios.get('/api/wiki/recent-changes', { params: { limit: this.limit } });
            this.changes = data.data || [];
            this.setSubtitle(this.$t('dashboard.widgets.wiki.subtitle', { count: this.changes.length }));
        },
        relative(date) {
            return formatDateDistanceToNow(date);
        },
    },
};
</script>
