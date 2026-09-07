<template>
    <div v-if="loading && empty" class="text-center py-6">
        <v-progress-circular indeterminate size="24" color="primary" />
    </div>
    <v-alert v-else-if="error" type="error" variant="tonal" density="compact" class="text-caption">
        {{ error }}
    </v-alert>
    <div v-else-if="empty" class="text-center py-4 text-medium-emphasis">
        <v-icon size="32" class="mb-2">{{ emptyIcon }}</v-icon>
        <div class="text-caption">{{ emptyText }}</div>
    </div>
    <div v-else :class="{ 'widget-refreshing': loading }">
        <slot />
    </div>
</template>

<script>
/**
 * Loading / error / empty states shared by the dashboard widgets; renders
 * the slot once there is data (dimmed while a refresh is in flight).
 */
export default {
    name: 'WidgetState',
    props: {
        loading: { type: Boolean, default: false },
        error: { type: String, default: null },
        empty: { type: Boolean, default: false },
        emptyIcon: { type: String, default: 'mdi-tray-remove' },
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
