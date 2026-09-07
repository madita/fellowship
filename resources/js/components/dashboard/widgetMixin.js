/**
 * Shared behaviour of dashboard widgets: each widget implements `fetch()`
 * and gets loading/error handling, reloading on the page's refresh
 * counter or a changed item limit, and a helper to report its subtitle
 * (e.g. "3 upcoming") to the widget header.
 */
export default {
    props: {
        widgetConfig: { type: Object, default: () => ({}) },
        // Incremented by the dashboard's "Refresh" action.
        refreshKey: { type: Number, default: 0 },
    },
    emits: ['update-meta'],
    data() {
        return {
            loading: false,
            error: null,
        };
    },
    computed: {
        limit() {
            const value = parseInt(this.widgetConfig?.limit, 10);
            return value > 0 ? Math.min(value, 20) : 5;
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
