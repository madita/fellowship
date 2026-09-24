<template>
    <template v-if="view">
        <a
            v-if="view.external"
            :href="view.href"
            target="_blank"
            rel="noopener noreferrer"
            class="d-inline-flex align-center location-link"
        >
            <v-icon size="18" class="mr-1">{{ view.icon }}</v-icon>{{ view.label }}
        </a>
        <router-link
            v-else-if="view.to"
            :to="view.to"
            class="d-inline-flex align-center location-link"
        >
            <v-icon size="18" class="mr-1">{{ view.icon }}</v-icon>{{ view.label }}
        </router-link>
        <span v-else class="d-inline-flex align-center">
            <v-icon size="18" class="mr-1">{{ view.icon }}</v-icon>{{ view.label }}
        </span>
    </template>
    <template v-else>{{ $t('events.noLocationSpecified') }}</template>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { buildViewLocation, normalizeLocation } from '@/utils/eventLocation.js';
import { useSettingsStore } from '@/store/settingStore.js';

/**
 * Where an event happens, read-only: an icon and a label that links to the
 * map, the IRC window or the meeting URL when there is one to link to.
 *
 * Shared by the calendar drawer and the event's own page.
 */
const props = defineProps({
    location: { type: [Object, String], default: null },
});

const { t } = useI18n();

// Older events stored the location as plain text, so normalise first
const view = computed(() => buildViewLocation(
    normalizeLocation(props.location),
    t,
    useSettingsStore().mapProvider
));
</script>

<style scoped>
.location-link {
    color: rgb(var(--v-theme-primary));
    text-decoration: none;
}

.location-link:hover {
    text-decoration: underline;
}
</style>
