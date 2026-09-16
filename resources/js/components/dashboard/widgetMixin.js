/**
 * Shared behaviour of dashboard widgets: each widget implements `fetch()`
 * and gets loading/error handling, reloading on the page's refresh
 * counter or a changed item limit, and a helper to report its subtitle
 * (e.g. "3 upcoming") to the widget header.
 */
import { computed } from 'vue';

export default {
    props: {
        widgetConfig: { type: Object, default: () => ({}) },
        // Grid columns the widget currently spans (1-3); WidgetState lays
        // lists out side by side when there is room.
        columns: { type: Number, default: 1 },
        // Grid rows the widget spans (1-2); a taller widget shows more items.
        rows: { type: Number, default: 1 },
        // Incremented by the dashboard's "Refresh" action.
        refreshKey: { type: Number, default: 0 },
    },
    emits: ['update-meta'],
    provide() {
        return { widgetColumns: computed(() => this.columns) };
    },
    data() {
        return {
            loading: false,
            error: null,
        };
    },
    computed: {
        // Configured items per row of height, so a double-height widget
        // fills its space instead of leaving the bottom half empty.
        limit() {
            const value = parseInt(this.widgetConfig?.limit, 10);
            const perRow = value > 0 ? Math.min(value, 20) : 5;
            return Math.min(perRow * Math.max(this.rows, 1), 40);
        },
    },
    watch: {
        refreshKey() {
            this.load();
        },
        limit() {
            this.load();
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        async load() {
            this.loading = true;
            this.error = null;
            try {
                await this.fetch();
            } catch (e) {
                this.error = e.response?.data?.message || e.message || this.$t('dashboard.loadFailed');
            } finally {
                this.loading = false;
            }
        },
        setSubtitle(subtitle) {
            this.$emit('update-meta', { subtitle });
        },
    },
};
