<template>
    <div v-if="location">
        <!-- Mode selector: only when the event type allows more than one -->
        <v-btn-toggle
            v-if="allowedModes.length > 1"
            v-model="location.type"
            color="primary"
            density="comfortable"
            mandatory
            class="mb-3 flex-wrap"
        >
            <v-btn
                v-for="mode in allowedModes"
                :key="mode"
                :value="mode"
                :prepend-icon="modeIcon(mode)"
            >
                {{ $t('events.locationModes.' + mode) }}
            </v-btn>
        </v-btn-toggle>

        <!-- real: an address, a point on the map, or both -->
        <template v-if="location.type === 'real'">
            <v-text-field
                v-model="location.address"
                :label="$t('events.locationAddress')"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-map-marker"
            >
                <template #append-inner>
                    <v-btn
                        :icon="showMap ? 'mdi-map-minus' : 'mdi-map-search-outline'"
                        :title="showMap ? $t('events.map.hide') : $t('events.map.show')"
                        variant="text"
                        size="small"
                        density="comfortable"
                        @click="showMap = !showMap"
                    />
                </template>
            </v-text-field>

            <!-- Picking a point is optional: an address on its own is a
                 perfectly good location -->
            <map-picker
                v-if="showMap"
                v-model:lat="location.lat"
                v-model:lng="location.lng"
                class="mb-2"
                @picked="onPointPicked"
            />
        </template>

        <!-- virtual: an internal IRC channel or an external URL -->
        <template v-else-if="location.type === 'virtual'">
            <v-btn-toggle
                v-model="location.virtualMode"
                color="primary"
                density="comfortable"
                mandatory
                class="mb-3"
            >
                <v-btn value="irc" prepend-icon="mdi-pound">{{ $t('events.locationIrc') }}</v-btn>
                <v-btn value="url" prepend-icon="mdi-link-variant">{{ $t('events.locationUrl') }}</v-btn>
            </v-btn-toggle>

            <!-- Any channel may be typed: an event is often held somewhere
                 nobody has joined yet. The list offers the ones set up by an
                 admin and the ones already open. -->
            <v-combobox
                v-if="location.virtualMode === 'irc'"
                v-model="channelChoice"
                :items="channelSuggestions"
                item-title="name"
                :label="$t('events.locationIrcChannel')"
                :hint="$t('events.locationIrcChannelHint')"
                persistent-hint
                :loading="channelsLoading"
                :no-data-text="$t('events.locationTypeChannel')"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-pound"
                clearable
                return-object
            >
                <template #item="{ props: itemProps, item }">
                    <v-list-item
                        v-bind="itemProps"
                        :title="item.raw.name"
                        :subtitle="item.raw.server || $t('events.locationSuggested')"
                    />
                </template>
            </v-combobox>

            <v-text-field
                v-else
                v-model="location.url"
                :label="$t('events.locationUrl')"
                placeholder="https://"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-link-variant"
            />
        </template>

        <!-- custom: free text -->
        <v-text-field
            v-else
            v-model="location.text"
            :label="$t('events.location')"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-map-marker-outline"
        />
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import axios from 'axios';
import MapPicker from '@/components/common/MapPicker.vue';
import { useSettingsStore } from '@/store/settingStore.js';

/**
 * Where an event happens, as an editable field.
 *
 * Shared by the calendar drawer and the event's own page so the two cannot
 * drift apart — the location is fiddly enough written once.
 */
const props = defineProps({
    // The structured location object, edited in place
    location: { type: Object, default: null },
    // Which modes the chosen event type allows
    allowedModes: { type: Array, default: () => ['custom'] },
});

const showMap = ref(false);
const channels = ref([]);
const channelsLoading = ref(false);

const modeIcon = (mode) => ({
    real: 'mdi-map-marker',
    virtual: 'mdi-web',
    custom: 'mdi-map-marker-outline',
}[mode] || 'mdi-map-marker');

// The channels offered: the ones an admin set up, then the ones already
// open that are not already in that list. Anything else can be typed.
const channelSuggestions = computed(() => {
    const preset = useSettingsStore().eventIrcChannels
        .map(name => ({ name: String(name).startsWith('#') ? String(name) : `#${name}`, id: null }));

    const known = new Set(preset.map(channel => channel.name.toLowerCase()));

    return [
        ...preset,
        ...channels.value.filter(channel => !known.has(String(channel.name).toLowerCase())),
    ];
});

/**
 * The combobox hands back either a suggestion or the raw text typed. A
 * suggestion that is a channel the member is in keeps its id, so the link
 * opens that window; anything else travels as a name.
 */
const channelChoice = computed({
    get() {
        if (!props.location) return null;
        if (props.location.irc_channel) {
            return { name: props.location.irc_channel, id: props.location.irc_channel_id ?? null };
        }

        return props.location.irc_channel_id
            ? channels.value.find(channel => channel.id === props.location.irc_channel_id) ?? null
            : null;
    },
    set(value) {
        if (!props.location) return;

        if (!value) {
            props.location.irc_channel_id = null;
            props.location.irc_channel = '';
            return;
        }

        const name = typeof value === 'string' ? value : value.name;

        props.location.irc_channel_id = typeof value === 'object' ? value.id ?? null : null;
        props.location.irc_channel = String(name || '').trim();
    },
});

// A point picked on the map can also fill in an address that was left blank
const onPointPicked = ({ address }) => {
    if (props.location && address && !String(props.location.address || '').trim()) {
        props.location.address = address;
    }
};

const fetchChannels = async () => {
    if (channels.value.length || channelsLoading.value) return;

    channelsLoading.value = true;
    try {
        const { data } = await axios.get('/api/irc/available-channels');
        channels.value = Array.isArray(data) ? data : [];
    } catch (e) {
        // Suggestions are a convenience; a channel can still be typed
        console.error('Failed to load IRC channels:', e);
    } finally {
        channelsLoading.value = false;
    }
};

onMounted(fetchChannels);
</script>
