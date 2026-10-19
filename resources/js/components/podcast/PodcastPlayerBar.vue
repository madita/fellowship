<template>
    <!-- Nothing playing, nothing to show -->
    <v-sheet
        v-if="current"
        class="podcast-player"
        elevation="8"
        role="region"
        :aria-label="$t('podcast.player.label')"
    >
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
                    @click="store.seekBy(-15)"
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
                    @click="store.seekBy(30)"
                />

                <!-- The scrubber, which is the reason this bar exists and
                     the toolbar button does not try to be one. Hidden on a
                     phone, where the row has no room for it; the times
                     stay so there is still a sense of where the episode
                     is. -->
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
                        @update:model-value="store.requestSeek"
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
                            {{ store.rate }}×
                        </v-btn>
                    </template>
                    <v-list density="compact">
                        <v-list-item
                            v-for="option in rates"
                            :key="option"
                            :active="option === store.rate"
                            @click="store.setRate(option)"
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
                    @click="store.stop()"
                />
            </div>
        </v-container>
    </v-sheet>
</template>

<script>
import { usePodcastStore } from '@/store/podcastStore.js';
import { formatDuration } from '@/utils/podcast.js';

/**
 * The full player: scrubber, skip, speed.
 *
 * Shown only on the podcast pages — App.vue decides — because that is
 * where someone is actually working with an episode. Everywhere else the
 * single toolbar button is enough, and a bar pinned across the bottom of
 * every page would be a band of chrome the rest of the site has to live
 * around.
 *
 * Fixed to the viewport rather than sticky: it used to sit inside
 * v-main, where `position: sticky` only sticks within the scrolling
 * ancestor and the bar slid away on a short page. Being fixed, it covers
 * what is behind it, so it marks the document while it is up and
 * app.scss gives the page room at the bottom.
 *
 * It owns no audio — PodcastAudioHost does — so this survives being
 * mounted and unmounted as the listener moves on and off the podcast
 * pages without interrupting playback.
 */
export default {
    name: 'PodcastPlayerBar',
    setup() {
        return { store: usePodcastStore() };
    },
    data() {
        return {
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
         * the slider off a nonsense 0.
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
        current: {
            immediate: true,
            handler() {
                this.markDocument();
            },
        },
    },
    mounted() {
        this.markDocument();
    },
    beforeUnmount() {
        // Leaving the podcast pages takes the bar with it, so the page
        // below must not keep the space it was holding open.
        document.documentElement.classList.remove('podcast-bar-visible');
    },
    methods: {
        formatTime: formatDuration,

        markDocument() {
            document.documentElement.classList.toggle('podcast-bar-visible', !! this.current);
        },

        togglePlay() {
            if (this.playing) {
                this.store.pause();

                return;
            }

            this.store.setPlaying(true);
        },
    },
};
</script>

<style scoped>
.podcast-player {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    /* Above the content and the footer, below Vuetify's overlays (2400+)
       so dialogs and menus still come out on top */
    z-index: 1800;
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
