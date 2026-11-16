<template>
    <widget-state
        :loading="loading"
        :error="error"
        :empty="albums.length === 0"
        empty-icon="mdi-image-off-outline"
        :empty-text="$t('dashboard.widgets.gallery.empty')"
    >
        <div class="album-grid">
            <router-link
                v-for="album in albums"
                :key="album.id"
                :to="{ name: 'gallery-album', params: { album: album.slug } }"
                class="album-tile text-decoration-none"
            >
                <v-img
                    :src="album.cover || undefined"
                    :aspect-ratio="16 / 10"
                    cover
                    class="rounded"
                >
                    <div v-if="!album.cover" class="d-flex align-center justify-center fill-height bg-surface-variant">
                        <v-icon size="28" color="medium-emphasis">mdi-image-outline</v-icon>
                    </div>
                    <div class="album-caption text-caption px-2 py-1">
                        <div class="text-truncate font-weight-medium">{{ album.name }}</div>
                        <div>{{ $t('dashboard.widgets.gallery.images', { count: album.media_count }) }}</div>
                    </div>
                </v-img>
            </router-link>
        </div>
    </widget-state>
</template>

<script>
import axios from 'axios';
import widgetMixin from './widgetMixin.js';
import WidgetState from './WidgetState.vue';

/**
 * The newest gallery albums with their covers, from /api/collections/recent.
 */
export default {
    name: 'GalleryWidget',
    components: { WidgetState },
    mixins: [widgetMixin],
    data() {
        return {
            albums: [],
            total: 0,
        };
    },
    methods: {
        async fetch() {
            const { data } = await axios.get('/api/collections/recent', { params: { limit: this.limit } });
            this.albums = data.data || [];
            this.total = data.total ?? this.albums.length;
            this.setSubtitle(this.$t('dashboard.widgets.gallery.subtitle', { count: this.total }));
        },
    },
};
</script>

<style scoped>
.album-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 8px;
}
.album-tile {
    position: relative;
    display: block;
    color: inherit;
}
.album-caption {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    color: #fff;
    background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
}
</style>
