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

        <!-- A place that was pinned on a map is worth drawing, where there
             is room for it — the address alone says little about where. -->
        <map-picker
            v-if="map && point"
            :lat="point.lat"
            :lng="point.lng"
            readonly
            class="mt-3"
        />
    </template>
    <template v-else>{{ $t('events.noLocationSpecified') }}</template>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { buildViewLocation, normalizeLocation } from '@/utils/eventLocation.js';
import { useSettingsStore } from '@/store/settingStore.js';
import MapPicker from '@/components/common/MapPicker.vue';

/**
 * Where an event happens, read-only: an icon and a label that links to the
 * map, the IRC window or the meeting URL when there is one to link to.
 *
 * Shared by the calendar drawer and the event's own page.
 */
const props = defineProps({
    location: { type: [Object, String], default: null },
    // Draw the map under the address when a point was pinned. Off by
    // default: the calendar drawer has no room for one.
    map: { type: Boolean, default: false },
});

const { t } = useI18n();

// Older events stored the location as plain text, so normalise first
const loc = computed(() => normalizeLocation(props.location));

const view = computed(() => buildViewLocation(
    loc.value,
    t,
    useSettingsStore().mapProvider
));

// The pinned point, when there is one to draw. Google without a key draws
// nothing but a grey box, so in that case there is nothing worth showing.
const point = computed(() => {
    const { type, lat, lng } = loc.value;

    if (type !== 'real' || lat == null || lng == null || lat === '' || lng === '') return null;

    const settings = useSettingsStore();
    if (settings.mapProvider === 'google' && !settings.googleMapsApiKey) return null;

    return { lat: Number(lat), lng: Number(lng) };
});
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
