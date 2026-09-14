<template>
    <loading-state v-if="loading && empty" compact />
    <v-alert v-else-if="error" type="error" variant="tonal" density="compact" class="text-caption">
        {{ error }}
    </v-alert>
    <empty-state v-else-if="empty" compact :icon="emptyIcon" :title="emptyText" />
    <div v-else class="widget-state" :class="[{ 'widget-refreshing': loading }, 'widget-state--cols-' + columns]">
        <slot />
    </div>
</template>

<script>
import LoadingState from '@/components/common/LoadingState.vue';
import EmptyState from '@/components/common/EmptyState.vue';

/**
 * Loading / error / empty states shared by the dashboard widgets; renders
 * the slot once there is data (dimmed while a refresh is in flight).
 *
 * Wide widgets (see `columns`, provided by widgetMixin) lay a direct
 * child `v-list` or `.widget-list` out as a grid, so a short list sits
 * side by side instead of leaving the extra width empty.
 */
export default {
    name: 'WidgetState',
    components: { LoadingState, EmptyState },
    inject: {
        widgetColumns: { default: () => ({ value: 1 }) },
    },
    props: {
        loading: { type: Boolean, default: false },
        error: { type: String, default: null },
        empty: { type: Boolean, default: false },
        emptyIcon: { type: String, default: 'mdi-tray-remove-outline' },
        emptyText: { type: String, default: '' },
    },
    computed: {
        columns() {
            return Math.min(Math.max(this.widgetColumns?.value || 1, 1), 3);
        },
    },
};
</script>

<style scoped>
.widget-refreshing {
    opacity: 0.6;
    transition: opacity 0.2s ease;
}

/* Side-by-side lists in wide widgets */
.widget-state--cols-2 > :deep(.v-list),
.widget-state--cols-2 > :deep(.widget-list),
.widget-state--cols-3 > :deep(.v-list),
.widget-state--cols-3 > :deep(.widget-list) {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 16px;
    align-items: start;
}

.widget-state--cols-3 > :deep(.v-list),
.widget-state--cols-3 > :deep(.widget-list) {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
</style>
