<template>
    <div class="flex-grow-1">
        <page-header
            :title="episode?.title || $t('podcast.episode')"
            :subtitle="episode?.summary || ''"
            icon="mdi-music-note"
            :breadcrumbs="breadcrumbs"
            :back-to="showRoute"
        >
            <template v-if="episode" #actions>
                <v-btn
                    v-if="canManage"
                    variant="tonal"
                    prepend-icon="mdi-pencil"
                    :to="{ name: 'podcast-episode-edit', params: { slug: $route.params.slug, episodeSlug: episode.slug } }"
                >
                    {{ $t('common.update') }}
                </v-btn>
                <v-btn
                    :prepend-icon="isPlaying ? 'mdi-pause' : 'mdi-play'"
                    color="primary"
                    variant="elevated"
                    :disabled="!episode.audio_url"
                    @click="toggle"
                >
                    {{ isPlaying ? $t('podcast.player.pause') : $t('podcast.player.play') }}
                </v-btn>
            </template>
        </page-header>

        <v-container>
            <loading-state v-if="loading && !episode" />

            <empty-state
                v-else-if="!episode"
                icon="mdi-music-note-off"
                :title="$t('podcast.episodeNotFound.title')"
                :text="$t('podcast.episodeNotFound.text')"
            />

            <v-row v-else>
                <v-col cols="12" md="8">
                    <v-card variant="outlined">
                        <v-card-text>
                            <div class="d-flex align-center flex-wrap ga-2 mb-4">
                                <v-chip v-if="episodeLabel" size="small" variant="tonal">{{ episodeLabel }}</v-chip>
                                <v-chip v-if="episode.duration" size="small" variant="tonal" prepend-icon="mdi-clock-outline">
                                    {{ formatDuration(episode.duration) }}
                                </v-chip>
                                <v-chip v-if="publishedAt" size="small" variant="tonal" prepend-icon="mdi-calendar">
                                    {{ publishedAt }}
                                </v-chip>
                                <v-chip size="small" variant="tonal" prepend-icon="mdi-play-circle-outline">
                                    {{ $t('podcast.playCount', { count: episode.plays || 0 }) }}
                                </v-chip>
                                <v-chip v-if="episode.explicit" size="small" color="warning" variant="tonal">
                                    {{ $t('podcast.explicit') }}
                                </v-chip>
                            </div>

                            <v-alert
                                v-if="!episode.audio_url"
                                type="info"
                                variant="tonal"
                                density="comfortable"
                                class="mb-4"
                            >
                                {{ $t('podcast.noAudioHint') }}
                            </v-alert>

                            <h2 class="text-h6 font-weight-medium mb-2">{{ $t('podcast.showNotes') }}</h2>

                            <!-- Show notes are written in the editor, so
                                 they are HTML and go through the same
                                 sanitiser every other rich text does. -->
                            <div
                                v-if="notes"
                                class="rich-content"
                                v-html="notes"
                            ></div>
                            <p v-else class="text-body-2 text-medium-emphasis mb-0">
                                {{ $t('podcast.noShowNotes') }}
                            </p>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="4">
                    <v-card v-if="show" variant="outlined" :to="showRoute">
                        <v-img v-if="show.cover_url" :src="show.cover_url" :alt="show.title" aspect-ratio="1" cover />
                        <v-card-title class="text-subtitle-1 font-weight-medium">{{ show.title }}</v-card-title>
                        <v-card-subtitle v-if="show.author">{{ show.author }}</v-card-subtitle>
                        <v-card-text class="text-body-2 text-medium-emphasis">
                            {{ $t('podcast.allEpisodes') }}
                        </v-card-text>
                    </v-card>

                    <v-btn
                        v-if="show"
                        variant="tonal"
                        prepend-icon="mdi-rss"
                        :href="show.feed_url"
                        target="_blank"
                        rel="noopener"
                        block
                        class="mt-3"
                    >
                        {{ $t('podcast.subscribe') }}
                    </v-btn>
                </v-col>
            </v-row>
        </v-container>
    </div>
</template>

<script>
import { usePodcastStore } from '@/store/podcastStore.js';
import { renderRichText } from '@/utils/richText.js';
import { formatDuration, canManagePodcasts } from '@/utils/podcast.js';
import { formatDate } from '@/plugins/formatDate.js';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';

/**
 * One episode: the show notes, the facts about it, and a way to play it.
 *
 * Playing is handed to the store, which the player bar in the layout
 * acts on — so the listener can leave this page and keep listening.
 */
export default {
    name: 'EpisodePage',
    components: { PageHeader, EmptyState, LoadingState },
    setup() {
        return { store: usePodcastStore() };
    },
    computed: {
        episode() {
            return this.store.episode;
        },
        loading() {
            return this.store.loadingEpisode;
        },
        show() {
            return this.episode?.podcast || null;
        },
        showRoute() {
            return { name: 'podcast-show', params: { slug: this.$route.params.slug } };
        },
        breadcrumbs() {
            return [
                this.$crumb('podcast'),
                this.show ? { title: this.show.title, to: this.showRoute } : null,
                this.episode?.title || '',
            ].filter(Boolean);
        },
        isPlaying() {
            return this.store.isCurrent(this.episode) && this.store.playing;
        },
        canManage() {
            return canManagePodcasts();
        },
        notes() {
            return this.episode?.show_notes ? renderRichText(this.episode.show_notes) : '';
        },
        episodeLabel() {
            const season = this.episode?.season_number;
            const number = this.episode?.episode_number;

            if ( ! number) {
                return '';
            }

            return season ? `S${season}E${number}` : `E${number}`;
        },
        publishedAt() {
            const date = this.episode?.published_at || this.episode?.created_at;

            return date ? formatDate(date) : '';
        },
    },
    watch: {
        '$route.params.episodeSlug'() {
            this.load();
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        formatDuration,

        load() {
            const { slug, episodeSlug } = this.$route.params;

            return this.store.fetchEpisode(slug, episodeSlug);
        },

        toggle() {
            this.store.toggle(this.episode, this.show);
        },
    },
};
</script>
