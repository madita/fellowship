<template>
    <div
        class="loading-state d-flex flex-column align-center justify-center text-center"
        :class="compact ? 'py-6' : 'py-12'"
        role="status"
        aria-live="polite"
    >
        <v-progress-circular indeterminate color="primary" :size="compact ? 32 : 48" :width="compact ? 3 : 4" />
        <p v-if="text" class="text-body-2 text-medium-emphasis mt-4 mb-0">{{ text }}</p>
    </div>
</template>

<script>
/**
 * Centered spinner for a page or card whose data is still loading.
 * Use v-skeleton-loader instead when the layout of the content is known.
 */
export default {
    name: 'LoadingState',
    props: {
        text: { type: String, default: '' },
        compact: { type: Boolean, default: false },
    },
};
</script>
