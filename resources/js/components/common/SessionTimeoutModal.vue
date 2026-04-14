<template>
    <VDialog
        v-model="isVisible"
        persistent
        max-width="450"
        :scrim="true"
        scrim-opacity="0.7"
    >
        <VCard class="session-timeout-card">
            <VCardTitle class="d-flex align-center ga-3 py-4">
                <VIcon icon="mdi-clock-alert" size="32" color="warning" />
                <span class="text-h5">{{ $t('session.timeout.title') }}</span>
            </VCardTitle>

            <VCardText class="pb-2">
                <p class="text-body-1 mb-4">
                    {{ $t('session.timeout.message') }}
                </p>
                <p class="text-body-2 text-medium-emphasis">
                    {{ $t('session.timeout.explanation') }}
                </p>
            </VCardText>

            <VCardActions class="pa-4 pt-2">
                <VSpacer />
                <VBtn
                    color="primary"
                    variant="elevated"
                    size="large"
                    prepend-icon="mdi-refresh"
                    @click="refreshPage"
                >
                    {{ $t('session.timeout.refresh') }}
                </VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const isVisible = ref(false);

// Event handler for session timeout
const handleSessionTimeout = () => {
    isVisible.value = true;
};

// Refresh the page
const refreshPage = () => {
    window.location.reload();
};

// Listen for custom session timeout event
onMounted(() => {
    window.addEventListener('session-timeout', handleSessionTimeout);
});

onUnmounted(() => {
    window.removeEventListener('session-timeout', handleSessionTimeout);
});

// Expose method for programmatic triggering
defineExpose({
    show: () => { isVisible.value = true; },
    hide: () => { isVisible.value = false; },
});
</script>

<style scoped>
.session-timeout-card {
    border-top: 4px solid rgb(var(--v-theme-warning));
}
</style>
