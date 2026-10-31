<template>
    <widget-state
        :loading="loading"
        :error="error"
        :empty="episodes.length === 0"
        empty-icon="mdi-podcast"
        :empty-text="$t('dashboard.widgets.podcast.empty')"
    >
        <v-list density="compact" class="pa-0 bg-transparent">
            <v-list-item
                v-for="episode in episodes"
                :key="episode.id"
                :to="routeFor(episode)"
                class="px-0"
            >
                <template #prepend>
                    <!-- Play without leaving the dashboard: the player bar
                         in the layout picks it up and keeps playing. -->
                    <v-btn
                        :icon="isPlaying(episode) ? 'mdi-pause' : 'mdi-play'"
                        :color="store.isCurrent(episode) ? 'primary' : undefined"
                        :variant="store.isCurrent(episode) ? 'elevated' : 'tonal'"
                        :disabled="!episode.audio_url"
                        :title="playTitle(episode)"
                        size="x-small"
                        class="mr-3"
                        @click.prevent.stop="toggle(episode)"
                    />
                </template>

                <v-list-item-title class="text-body-2 font-weight-medium">
                    {{ episode.title }}
                </v-list-item-title>

                <v-list-item-subtitle class="text-caption">
                    {{ episode.podcast?.title }}
                    <template v-if="episode.duration"> · {{ formatDuration(episode.duration) }}</template>
                </v-list-item-subtitle>
            </v-list-item>
        </v-list>
    </widget-state>
</template>

<script>
import axios from 'axios';
import widgetMixin from './widgetMixin.js';
import WidgetState from './WidgetState.vue';
import { usePodcastStore } from '@/store/podcastStore.js';
import { formatDuration } from '@/utils/podcast.js';

/**
 * The newest podcast episodes across every published show, from
 * /api/podcasts/latest-episodes.
 *
 * Pressing play here hands the episode to the podcast store, which the
 * player bar in DefaultLayout acts on — so the episode keeps playing
 * while the listener carries on using the dashboard.
 */
export default {
    name: 'PodcastWidget',
    components: { WidgetState },
    mixins: [widgetMixin],
    setup() {
        return { store: usePodcastStore() };
    },
    data() {
        return {
            episodes: [],
        };
    },
    methods: {
        formatDuration,

        async fetch() {
            const { data } = await axios.get('/api/podcasts/latest-episodes', {
                params: { limit: this.limit },
            });

            this.episodes = data.episodes || [];
            this.setSubtitle(this.$t('dashboard.widgets.podcast.subtitle', { count: this.episodes.length }));
        },

        routeFor(episode) {
            if ( ! episode.podcast?.slug) {
                return undefined;
            }

            return {
                name: 'podcast-episode',
                params: { slug: episode.podcast.slug, episodeSlug: episode.slug },
            };
        },

        isPlaying(episode) {
            return this.store.isCurrent(episode) && this.store.playing;
        },

        playTitle(episode) {
            if ( ! episode.audio_url) {
                return this.$t('podcast.noAudio');
            }

            return this.isPlaying(episode) ? this.$t('podcast.player.pause') : this.$t('podcast.player.play');
        },

        toggle(episode) {
            this.store.toggle(episode, episode.podcast);
        },
    },
};
</script>
