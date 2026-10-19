<template>
    <div class="flex-grow-1">
        <page-header
            :title="$t('podcast.title')"
            :subtitle="$t('podcast.subtitle')"
            icon="mdi-podcast"
        >
            <template v-if="canManage" #actions>
                <v-btn
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-plus"
                    :to="{ name: 'podcast-create' }"
                >
                    {{ $t('podcast.manage.newShow') }}
                </v-btn>
            </template>
        </page-header>

        <v-container>
            <loading-state v-if="loading && !podcasts.length" />

            <empty-state
                v-else-if="!podcasts.length"
                icon="mdi-podcast"
                :title="$t('podcast.empty.title')"
                :text="$t('podcast.empty.text')"
            />

            <template v-else>
                <!-- The newest episodes across every show, so a listener
                     landing here has something to press play on without
                     first picking a show. -->
                <section v-if="latest.length" class="mb-8">
                    <h2 class="text-h6 font-weight-medium mb-3">{{ $t('podcast.latestEpisodes') }}</h2>
                    <v-row dense>
                        <v-col v-for="episode in latest" :key="episode.id" cols="12" md="6">
                            <episode-list-item
                                :episode="episode"
                                :show-slug="episode.podcast?.slug"
                                :is-current="store.isCurrent(episode)"
                                :is-playing="store.isCurrent(episode) && store.playing"
                                @toggle="store.toggle(episode, episode.podcast)"
                            />
                        </v-col>
                    </v-row>
                </section>

                <section>
                    <h2 class="text-h6 font-weight-medium mb-3">{{ $t('podcast.shows') }}</h2>
                    <v-row>
                        <v-col v-for="podcast in podcasts" :key="podcast.id" cols="12" sm="6" md="4">
                            <v-card
                                class="h-100 d-flex flex-column"
                                variant="outlined"
                                :to="{ name: 'podcast-show', params: { slug: podcast.slug } }"
                            >
                                <v-img
                                    v-if="podcast.cover_url"
                                    :src="podcast.cover_url"
                                    :alt="podcast.title"
                                    aspect-ratio="1"
                                    cover
                                />
                                <div v-else class="podcast-card__placeholder d-flex align-center justify-center">
                                    <v-icon size="64" color="primary">mdi-podcast</v-icon>
                                </div>

                                <v-card-title class="text-subtitle-1 font-weight-medium">
                                    {{ podcast.title }}
                                </v-card-title>

                                <v-card-subtitle v-if="podcast.subtitle">{{ podcast.subtitle }}</v-card-subtitle>

                                <v-card-text class="flex-grow-1">
                                    <div class="d-flex align-center flex-wrap ga-2">
                                        <v-chip size="small" variant="tonal" prepend-icon="mdi-playlist-music">
                                            {{ $t('podcast.episodeCount', { count: podcast.episodes_count ?? 0 }) }}
                                        </v-chip>
                                        <v-chip v-if="podcast.explicit" size="small" color="warning" variant="tonal">
                                            {{ $t('podcast.explicit') }}
                                        </v-chip>
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>
                </section>
            </template>
        </v-container>
    </div>
</template>

<script>
import axios from 'axios';
import { usePodcastStore } from '@/store/podcastStore.js';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import EpisodeListItem from '@/components/podcast/EpisodeListItem.vue';
import { canManagePodcasts } from '@/utils/podcast.js';

/**
 * Every published show, with the newest episodes across all of them on
 * top so there is something to play straight away.
 */
export default {
    name: 'PodcastList',
    components: { PageHeader, EmptyState, LoadingState, EpisodeListItem },
    setup() {
        return { store: usePodcastStore() };
    },
    data() {
        return {
            latest: [],
        };
    },
    computed: {
        podcasts() {
            return this.store.podcasts;
        },
        loading() {
            return this.store.loadingPodcasts;
        },
        canManage() {
            return canManagePodcasts();
        },
    },
    async mounted() {
        await this.store.fetchPodcasts();
        await this.fetchLatest();
    },
    methods: {
        /**
         * Not in the store: nothing else needs it, and a failure here is
         * no reason to leave the page without its list of shows.
         */
        async fetchLatest() {
            try {
                const { data } = await axios.get('/api/podcasts/latest-episodes', { params: { limit: 4 } });
                this.latest = data.episodes || [];
            } catch (error) {
                this.latest = [];
            }
        },
    },
};
</script>

<style scoped>
.podcast-card__placeholder {
    aspect-ratio: 1;
    background: linear-gradient(
        135deg,
        rgba(var(--v-theme-primary), 0.12) 0%,
        rgba(var(--v-theme-secondary), 0.12) 100%
    );
}
</style>
