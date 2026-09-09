<template>
    <v-empty-state
        class="empty-state"
        :icon="icon"
        :title="title"
        :text="text"
        :size="compact ? 48 : 72"
        color="primary"
        :class="{ 'empty-state--compact': compact }"
    >
        <template v-if="$slots.actions" #actions>
            <slot name="actions" />
        </template>
        <template v-if="$slots.default" #default>
            <slot />
        </template>
    </v-empty-state>
</template>

<script>
/**
 * "Nothing here" placeholder for lists, grids and search results.
 *
 *   <empty-state icon="mdi-forum-outline" :title="$t('forum.noThreads')" :text="$t('forum.noThreadsHint')">
 *       <template #actions><v-btn color="primary" variant="flat">…</v-btn></template>
 *   </empty-state>
 */
export default {
    name: 'EmptyState',
    props: {
        icon: { type: String, default: 'mdi-tray-remove-outline' },
        title: { type: String, required: true },
        text: { type: String, default: '' },
        // Smaller icon and padding inside cards/widgets
        compact: { type: Boolean, default: false },
    },
};
</script>

<style scoped>
.empty-state {
    padding: 48px 16px;
}

.empty-state--compact {
    padding: 24px 16px;
}

.empty-state :deep(.v-empty-state__media) {
    opacity: 0.45;
}

.empty-state :deep(.v-empty-state__title) {
    font-size: 1.25rem;
    font-weight: 600;
}

.empty-state :deep(.v-empty-state__text) {
    max-width: 440px;
}
</style>
