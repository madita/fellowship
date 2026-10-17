<template>
    <v-card variant="outlined" class="episode-row">
        <div class="d-flex align-center ga-3 pa-3">
            <!-- The play button doubles as the "this one is playing"
                 indicator, so there is only ever one thing to look at. -->
            <v-btn
                :icon="isPlaying ? 'mdi-pause' : 'mdi-play'"
                :color="isCurrent ? 'primary' : undefined"
                :variant="isCurrent ? 'elevated' : 'tonal'"
                :disabled="!episode.audio_url"
                :title="playTitle"
                size="small"
                @click="$emit('toggle', episode)"
            />

            <div class="episode-row__text">
                <router-link :to="route" class="episode-row__title text-body-1 font-weight-medium">
                    {{ episode.title }}
                </router-link>

                <div class="d-flex align-center flex-wrap ga-2 mt-1">
                    <span v-if="episodeLabel" class="text-caption text-medium-emphasis">{{ episodeLabel }}</span>
                    <span v-if="publishedAt" class="text-caption text-medium-emphasis">{{ publishedAt }}</span>
                    <span v-if="episode.duration" class="text-caption text-medium-emphasis">
                        {{ formatDuration(episode.duration) }}
                    </span>
                    <v-chip v-if="episode.explicit" size="x-small" color="warning" variant="tonal">
                        {{ $t('podcast.explicit') }}
                    </v-chip>
                    <v-chip v-if="!episode.audio_url" size="x-small" color="error" variant="tonal">
                        {{ $t('podcast.noAudio') }}
                    </v-chip>
                </div>

                <p v-if="episode.summary" class="text-body-2 text-medium-emphasis mt-2 mb-0 episode-row__summary">
                    {{ episode.summary }}
                </p>
            </div>
        </div>
    </v-card>
</template>

<script>
import { formatDuration } from '@/utils/podcast.js';
import { formatDateDistanceToNow } from '@/plugins/formatDate.js';

/**
 * One episode in a list: play button, title, the few facts worth showing
 * in a row, and a link to its own page for the show notes.
 *
 * Playback is the parent's business — this emits `toggle` and is told
 * whether it is the current episode, so the same row works on the show
 * page and in the dashboard widget.
 */
export default {
    name: 'EpisodeListItem',
    props: {
        episode: { type: Object, required: true },
        // The show the episode belongs to; needed for its route
        showSlug: { type: String, required: true },
        isCurrent: { type: Boolean, default: false },
        isPlaying: { type: Boolean, default: false },
    },
    emits: ['toggle'],
    computed: {
        route() {
            return {
                name: 'podcast-episode',
                params: { slug: this.showSlug, episodeSlug: this.episode.slug },
            };
        },
        /**
         * "S2E14", or just "E14" for a show that does not do seasons.
         */
        episodeLabel() {
            const { season_number: season, episode_number: number } = this.episode;

            if ( ! number) {
                return '';
            }

            return season ? `S${season}E${number}` : `E${number}`;
        },
        publishedAt() {
            const date = this.episode.published_at || this.episode.created_at;

            return date ? formatDateDistanceToNow(date) : '';
        },
        playTitle() {
            if ( ! this.episode.audio_url) {
                return this.$t('podcast.noAudio');
            }

            return this.isPlaying ? this.$t('podcast.player.pause') : this.$t('podcast.player.play');
        },
    },
    methods: {
        formatDuration,
    },
};
</script>

<style scoped>
.episode-row__text {
    flex: 1 1 auto;
    min-width: 0;
}

.episode-row__title {
    color: inherit;
    text-decoration: none;
}

.episode-row__title:hover {
    text-decoration: underline;
}

/* Two lines of summary: enough to tell episodes apart, not enough to
   turn the list into a wall of text */
.episode-row__summary {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
