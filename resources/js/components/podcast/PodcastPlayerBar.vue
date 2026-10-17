<template>
    <!-- Nothing playing, nothing to show -->
    <v-sheet
        v-if="current"
        class="podcast-player"
        elevation="8"
        role="region"
        :aria-label="$t('podcast.player.label')"
    >
        <audio
            ref="audio"
            :src="current.audio_url"
            preload="metadata"
            @loadedmetadata="onMeta"
            @timeupdate="onTimeUpdate"
            @ended="onEnded"
            @play="store.setPlaying(true)"
            @pause="store.setPlaying(false)"
        ></audio>

        <v-container fluid class="py-2">
            <div class="d-flex align-center ga-2 ga-sm-3">
                <v-avatar v-if="current.cover_url" :image="current.cover_url" rounded="md" size="44" class="d-none d-sm-flex" />
                <v-avatar v-else color="primary" rounded="md" size="44" class="d-none d-sm-flex">
                    <v-icon>mdi-podcast</v-icon>
                </v-avatar>

                <!-- What is playing. min-width:0 so a long title truncates
                     instead of pushing the controls off a phone screen. -->
                <div class="podcast-player__meta">
                    <router-link v-if="episodeRoute" :to="episodeRoute" class="podcast-player__title text-body-2 font-weight-medium">
                        {{ current.title }}
                    </router-link>
                    <div v-else class="podcast-player__title text-body-2 font-weight-medium">{{ current.title }}</div>
                    <div class="text-caption text-medium-emphasis text-truncate">
                        {{ currentShow?.title || $t('podcast.title') }}
                    </div>
                </div>

                <v-btn
                    icon="mdi-rewind-15"
                    variant="text"
                    size="small"
                    class="d-none d-sm-flex"
                    :title="$t('podcast.player.back15')"
                    @click="seekBy(-15)"
                />

                <v-btn
                    :icon="playing ? 'mdi-pause' : 'mdi-play'"
                    color="primary"
                    variant="elevated"
                    size="small"
                    :title="playing ? $t('podcast.player.pause') : $t('podcast.player.play')"
                    @click="togglePlay"
                />

                <v-btn
                    icon="mdi-fast-forward-30"
                    variant="text"
                    size="small"
                    class="d-none d-sm-flex"
                    :title="$t('podcast.player.forward30')"
                    @click="seekBy(30)"
                />

                <!-- The scrubber. Hidden on a phone, where the row has no
                     room for it; the times stay so there is still a sense
                     of where the episode is. -->
                <div class="podcast-player__scrub d-none d-md-flex align-center ga-2">
                    <span class="text-caption text-medium-emphasis">{{ formatTime(position) }}</span>
                    <v-slider
                        :model-value="position"
                        :max="sliderMax"
                        :step="1"
                        hide-details
                        density="compact"
                        color="primary"
                        :aria-label="$t('podcast.player.seek')"
                        @update:model-value="seekTo"
                    />
                    <span class="text-caption text-medium-emphasis">{{ formatTime(sliderMax) }}</span>
                </div>

                <div class="text-caption text-medium-emphasis d-md-none">
                    {{ formatTime(position) }}
                </div>

                <v-menu location="top">
                    <template #activator="{ props }">
                        <v-btn
                            v-bind="props"
                            variant="text"
                            size="small"
                            class="d-none d-sm-flex"
                            :title="$t('podcast.player.speed')"
                        >
                            {{ rate }}×
                        </v-btn>
                    </template>
                    <v-list density="compact">
                        <v-list-item
                            v-for="option in rates"
                            :key="option"
                            :active="option === rate"
                            @click="setRate(option)"
                        >
                            <v-list-item-title>{{ option }}×</v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-menu>

                <v-btn
                    icon="mdi-close"
                    variant="text"
                    size="small"
                    :title="$t('common.close')"
                    @click="close"
                />
            </div>
        </v-container>
    </v-sheet>
</template>

<script>
import { usePodcastStore } from '@/store/podcastStore.js';
import { formatDuration } from '@/utils/podcast.js';

/**
 * The player that keeps playing.
 *
 * Mounted once in DefaultLayout rather than on the podcast pages, because
 * a listener who opens the show notes — or wanders off to the forum —
 * should keep hearing the episode. The <audio> element therefore has to
 * outlive the pages, so the pages only ask the store to play something
 * and this component is the single thing that touches the element.
 *
 * It renders nothing at all until there is something to play.
 */
export default {
    name: 'PodcastPlayerBar',
    setup() {
        return { store: usePodcastStore() };
    },
    data() {
        return {
            rate: 1,
            rates: [0.75, 1, 1.25, 1.5, 2],
        };
    },
    computed: {
        current() {
            return this.store.current;
        },
        currentShow() {
            return this.store.currentShow;
        },
        playing() {
            return this.store.playing;
        },
        position() {
            return this.store.position;
        },
        /**
         * How long the episode is. The file is the authority once it has
         * loaded; until then the figure the episode was saved with keeps
         * the slider from sitting at a nonsense 0.
         */
        sliderMax() {
            return this.store.duration || this.current?.duration || 0;
        },
        episodeRoute() {
            const slug = this.currentShow?.slug;

            if ( ! slug || ! this.current?.slug) {
                return null;
            }

            return { name: 'podcast-episode', params: { slug, episodeSlug: this.current.slug } };
        },
    },
    watch: {
        // A page asked for playback: the element can only be told to play
        // from here, because only this component owns it.
        'store.playRequested'(requested) {
            if (requested) {
                this.$nextTick(() => this.startPlayback());
            }
        },
        'store.playing'(playing) {
            const audio = this.$refs.audio;

            if ( ! audio) {
                return;
            }

            if (playing && audio.paused) {
                audio.play().catch(() => this.store.setPlaying(false));
            } else if ( ! playing && ! audio.paused) {
                audio.pause();
            }
        },
    },
    methods: {
        formatTime: formatDuration,

        startPlayback() {
            const audio = this.$refs.audio;
            this.store.playHandled();

            if ( ! audio) {
                return;
            }

            audio.playbackRate = this.rate;
            // A browser may refuse autoplay; the store has to agree with
            // what actually happened or the button lies about its state.
            audio.play().catch(() => this.store.setPlaying(false));
        },

        togglePlay() {
            if (this.playing) {
                this.store.pause();

                return;
            }

            this.store.setPlaying(true);
        },

        onMeta() {
            const audio = this.$refs.audio;

            if (audio) {
                audio.playbackRate = this.rate;
                audio.volume = this.store.volume;
                this.store.syncProgress(audio.currentTime, audio.duration);
            }
        },

        onTimeUpdate() {
            const audio = this.$refs.audio;

            if (audio) {
                this.store.syncProgress(audio.currentTime, audio.duration);
            }
        },

        onEnded() {
            this.store.setPlaying(false);
            this.store.syncProgress(0, this.store.duration);

            const audio = this.$refs.audio;

            if (audio) {
                audio.currentTime = 0;
            }
        },

        seekTo(seconds) {
            const audio = this.$refs.audio;

            if (audio) {
                audio.currentTime = seconds;
            }

            this.store.syncProgress(seconds, this.store.duration);
        },

        seekBy(delta) {
            const max = this.sliderMax || this.position + Math.abs(delta);

            this.seekTo(Math.min(Math.max(this.position + delta, 0), max));
        },

        setRate(rate) {
            this.rate = rate;

            const audio = this.$refs.audio;

            if (audio) {
                audio.playbackRate = rate;
            }
        },

        close() {
            const audio = this.$refs.audio;

            if (audio) {
                audio.pause();
            }

            this.store.stop();
        },
    },
};
</script>

<style scoped>
.podcast-player {
    position: sticky;
    bottom: 0;
    z-index: 5;
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

/* Truncation only works if the flex child may shrink below its content */
.podcast-player__meta {
    flex: 1 1 auto;
    min-width: 0;
}

.podcast-player__title {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: inherit;
    text-decoration: none;
}

a.podcast-player__title:hover {
    text-decoration: underline;
}

.podcast-player__scrub {
    flex: 2 1 220px;
    min-width: 0;
}
</style>
