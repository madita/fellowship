<template>
    <div class="map-picker">
        <div v-if="!readonly" class="d-flex align-center ga-2 mb-2">
            <v-text-field
                v-model="search"
                :label="$t('events.map.search')"
                :loading="searching"
                variant="outlined"
                density="compact"
                hide-details
                prepend-inner-icon="mdi-magnify"
                @keydown.enter.prevent="lookUp"
            />
            <v-btn variant="tonal" :loading="searching" @click="lookUp">
                {{ $t('events.map.find') }}
            </v-btn>
        </div>

        <div ref="canvas" class="map-canvas" />

        <div v-if="!readonly" class="d-flex align-center justify-space-between mt-2">
            <span class="text-caption text-medium-emphasis">
                <template v-if="hasPoint">{{ readablePoint }}</template>
                <template v-else>{{ $t('events.map.hint') }}</template>
                <span class="d-block">{{ $t('events.map.zoomHint') }}</span>
            </span>
            <v-btn
                v-if="hasPoint"
                size="x-small"
                variant="text"
                color="error"
                prepend-icon="mdi-close"
                @click="clear"
            >
                {{ $t('events.map.clear') }}
            </v-btn>
        </div>
    </div>
</template>

<script>
import { useSettingsStore } from '@/store/settingStore.js';

// Somewhere to open when nothing has been picked yet
const DEFAULT_VIEW = { lat: 51.1657, lng: 10.4515, zoom: 5 };

export default {
    name: 'MapPicker',
    props: {
        lat: { type: [Number, String], default: null },
        lng: { type: [Number, String], default: null },
        // Just show the place: no search, no clearing, no picking
        readonly: { type: Boolean, default: false },
    },
    emits: ['update:lat', 'update:lng', 'picked'],
    data() {
        return {
            search: '',
            searching: false,
            map: null,
            marker: null,
            // Leaflet or the Google Maps API, once it has loaded
            api: null,
        };
    },
    computed: {
        provider() {
            return useSettingsStore().mapProvider;
        },
        googleKey() {
            return useSettingsStore().googleMapsApiKey;
        },
        hasPoint() {
            return this.lat !== null && this.lat !== '' && this.lng !== null && this.lng !== '';
        },
        readablePoint() {
            return this.hasPoint ? `${Number(this.lat).toFixed(5)}, ${Number(this.lng).toFixed(5)}` : '';
        },
    },
    watch: {
        // The address field can move the pin from outside
        lat() {
            this.moveMarker();
        },
        lng() {
            this.moveMarker();
        },
    },
    async mounted() {
        await (this.provider === 'google' ? this.startGoogle() : this.startLeaflet());
    },
    beforeUnmount() {
        // Leaflet holds listeners on the container; Google cleans up with it
        if (this.provider !== 'google') {
            this.$refs.canvas?.removeEventListener('wheel', this.onWheel);
            if (this.map) this.map.remove();
        }
    },
    methods: {
        startPoint() {
            return this.hasPoint
                ? { lat: Number(this.lat), lng: Number(this.lng) }
                : { lat: DEFAULT_VIEW.lat, lng: DEFAULT_VIEW.lng };
        },

        /**
         * OpenStreetMap through Leaflet. The tiles come from the public
         * OSM servers, so the attribution stays on the map.
         */
        async startLeaflet() {
            const L = (await import('leaflet')).default;
            await import('leaflet/dist/leaflet.css');

            this.api = L;

            const start = this.startPoint();

            this.map = L.map(this.$refs.canvas, {
                // The map sits inside a scrolling panel, so the wheel has to
                // keep scrolling that panel. Zooming is the buttons, or the
                // wheel with ctrl held — the same bargain Google calls
                // cooperative gestures.
                scrollWheelZoom: false,
            }).setView(
                [start.lat, start.lng],
                this.hasPoint ? 14 : DEFAULT_VIEW.zoom
            );

            this.$refs.canvas.addEventListener('wheel', this.onWheel, { passive: false });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(this.map);

            if (!this.readonly) {
                this.map.on('click', event => this.pick(event.latlng.lat, event.latlng.lng));
            }

            if (this.hasPoint) this.moveMarker();
        },

        /**
         * Google Maps, which needs a key of its own. Without one there is
         * nothing to draw, so the picker says so rather than sitting blank.
         */
        async startGoogle() {
            if (!this.googleKey) return;

            const { Loader } = await import('@googlemaps/js-api-loader');
            const loader = new Loader({ apiKey: this.googleKey, version: 'weekly' });

            this.api = await loader.importLibrary('maps');
            const { Marker } = await loader.importLibrary('marker');
            this.MarkerClass = Marker;

            const start = this.startPoint();

            this.map = new this.api.Map(this.$refs.canvas, {
                center: start,
                zoom: this.hasPoint ? 14 : DEFAULT_VIEW.zoom,
                streetViewControl: false,
                mapTypeControl: false,
                // Wheel scrolls the panel the map sits in; ctrl+wheel zooms
                gestureHandling: 'cooperative',
            });

            if (!this.readonly) {
                this.map.addListener('click', event => this.pick(event.latLng.lat(), event.latLng.lng()));
            }

            if (this.hasPoint) this.moveMarker();
        },

        /**
         * Ctrl (or cmd) plus the wheel zooms; a plain wheel is left alone so
         * the panel behind the map keeps scrolling.
         */
        onWheel(event) {
            if (!this.map) return;

            if (!(event.ctrlKey || event.metaKey)) return;

            event.preventDefault();
            this.map.setZoom(this.map.getZoom() + (event.deltaY < 0 ? 1 : -1));
        },

        pick(lat, lng) {
            this.$emit('update:lat', Number(lat.toFixed(7)));
            this.$emit('update:lng', Number(lng.toFixed(7)));
            this.$emit('picked', { lat, lng });
        },

        clear() {
            this.$emit('update:lat', null);
            this.$emit('update:lng', null);

            if (this.marker) {
                if (this.provider === 'google') this.marker.setMap(null);
                else this.marker.remove();

                this.marker = null;
            }
        },

        moveMarker() {
            if (!this.map || !this.hasPoint) return;

            const point = { lat: Number(this.lat), lng: Number(this.lng) };

            if (this.provider === 'google') {
                if (this.marker) this.marker.setPosition(point);
                else this.marker = new this.MarkerClass({ position: point, map: this.map });

                this.map.panTo(point);
                return;
            }

            if (this.marker) this.marker.setLatLng(point);
            else this.marker = this.api.marker([point.lat, point.lng]).addTo(this.map);

            this.map.panTo([point.lat, point.lng]);
        },

        /**
         * Turn what was typed into a point. Nominatim is OpenStreetMap's
         * own search and asks for no key; it is used whichever provider
         * draws the map, so searching works before a Google key is set.
         */
        async lookUp() {
            const query = this.search.trim();

            if (!query) return;

            this.searching = true;
            try {
                const url = 'https://nominatim.openstreetmap.org/search'
                    + `?format=json&limit=1&q=${encodeURIComponent(query)}`;

                const found = await fetch(url, { headers: { Accept: 'application/json' } })
                    .then(response => (response.ok ? response.json() : []));

                if (!found.length) return;

                const { lat, lon, display_name: label } = found[0];

                this.pick(Number(lat), Number(lon));
                this.$emit('picked', { lat: Number(lat), lng: Number(lon), address: label });

                if (this.map) {
                    const point = [Number(lat), Number(lon)];
                    if (this.provider === 'google') this.map.setCenter({ lat: point[0], lng: point[1] });
                    else this.map.setView(point, 15);
                }
            } catch {
                // A search that will not answer is not worth an error
                // dialog; the map still takes a click.
            } finally {
                this.searching = false;
            }
        },
    },
};
</script>

<style scoped>
.map-canvas {
    height: 320px;
    width: 100%;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgba(var(--v-theme-surface-variant), 0.35);
}
</style>
