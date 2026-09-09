<template>
    <loading-state v-if="loading && empty" compact />
    <v-alert v-else-if="error" type="error" variant="tonal" density="compact" class="text-caption">
        {{ error }}
    </v-alert>
    <empty-state v-else-if="empty" compact :icon="emptyIcon" :title="emptyText" />
    <div v-else :class="{ 'widget-refreshing': loading }">
        <slot />
    </div>
</template>

<script>
import LoadingState from '@/components/common/LoadingState.vue';
import EmptyState from '@/components/common/EmptyState.vue';

/**
 * Loading / error / empty states shared by the dashboard widgets; renders
 * the slot once there is data (dimmed while a refresh is in flight).
 */
export default {
    name: 'WidgetState',
    components: { LoadingState, EmptyState },
    props: {
        loading: { type: Boolean, default: false },
        error: { type: String, default: null },
        empty: { type: Boolean, default: false },
        emptyIcon: { type: String, default: 'mdi-tray-remove-outline' },
        emptyText: { type: String, default: '' },
    },
};
</script>

<style scoped>
.widget-refreshing {
    opacity: 0.6;
    transition: opacity 0.2s ease;
}
</style>
