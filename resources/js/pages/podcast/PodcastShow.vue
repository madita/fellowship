<template>
    <div class="flex-grow-1">
        <page-header
            :title="podcast?.title || $t('podcast.title')"
            :subtitle="podcast?.subtitle || ''"
            icon="mdi-podcast"
            :breadcrumbs="[$crumb('podcast'), podcast?.title || '']"
            :back-to="{ name: 'podcast' }"
        >
            <template v-if="podcast" #actions>
                <v-btn
                    variant="tonal"
                    prepend-icon="mdi-rss"
                    :href="podcast.feed_url"
                    target="_blank"
                    rel="noopener"
                >
                    {{ $t('podcast.subscribe') }}
                </v-btn>
                <template v-if="canManage">
                    <v-btn
                        variant="tonal"
                        prepend-icon="mdi-pencil"
                        :to="{ name: 'podcast-edit', params: { slug: podcast.slug } }"
                    >
                        {{ $t('podcast.manage.editShow') }}
                    </v-btn>
                    <v-btn
                        color="primary"
                        variant="elevated"
                        prepend-icon="mdi-plus"
                        :to="{ name: 'podcast-episode-new', params: { slug: podcast.slug } }"
                    >
                        {{ $t('podcast.manage.newEpisode') }}
                    </v-btn>
                </template>
            </template>
        </page-header>

        <v-container>
            <loading-state v-if="loading && !podcast" />

            <empty-state
                v-else-if="!podcast"
                icon="mdi-podcast-off"
                :title="$t('podcast.notFound.title')"
                :text="$t('podcast.notFound.text')"
            />

            <!-- Only its own author can be looking at a show in this
                 state, so the message is addressed to them. -->
            <v-alert
                v-else-if="podcast && !podcast.is_approved"
                type="info"
                variant="tonal"
                prepend-icon="mdi-clock-outline"
                class="mb-4"
            >
                {{ $t('podcast.awaitingReview') }}
            </v-alert>

            <v-row v-if="podcast">
                <v-col cols="12" md="4" lg="3">
                    <v-card variant="outlined">
                        <v-img v-if="podcast.cover_url" :src="podcast.cover_url" :alt="podcast.title" aspect-ratio="1" cover />
                        <v-card-text>
                            <p v-if="podcast.description" class="text-body-2" style="white-space: pre-line;">
                                {{ podcast.description }}
                            </p>

                            <v-divider class="my-3" />

                            <v-list density="compact" class="pa-0 bg-transparent">
                                <v-list-item v-if="podcast.author_line" class="px-0" prepend-icon="mdi-account-voice">
                                    <v-list-item-title class="text-body-2">{{ podcast.author_line }}</v-list-item-title>
                                </v-list-item>
                                <v-list-item v-if="podcast.categories?.length" class="px-0" prepend-icon="mdi-tag-outline">
                                    <v-list-item-title class="text-body-2">
                                        {{ podcast.categories.map(term => term.title).join(', ') }}
                                    </v-list-item-title>
                                </v-list-item>
                                <v-list-item class="px-0" prepend-icon="mdi-playlist-music">
                                    <v-list-item-title class="text-body-2">
                                        {{ $t('podcast.episodeCount', { count: meta.total ?? episodes.length }) }}
                                    </v-list-item-title>
                                </v-list-item>
                            </v-list>

                            <!-- A feed URL is only useful if it can be
                                 copied into a podcast app by hand too. -->
                            <div class="text-caption text-medium-emphasis mt-3">{{ $t('podcast.feedUrl') }}</div>
                            <copy-label
                                :text="podcast.feed_url"
                                :toast-text="$t('podcast.feedCopied')"
                                class="text-caption"
                            />
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="8" lg="9">
                    <empty-state
                        v-if="!episodes.length"
                        icon="mdi-playlist-remove"
                        :title="$t('podcast.noEpisodes.title')"
                        :text="$t('podcast.noEpisodes.text')"
                    />

                    <template v-else>
                        <episode-list-item
                            v-for="episode in episodes"
                            :key="episode.id"
                            :episode="episode"
                            :show-slug="podcast.slug"
                            :is-current="store.isCurrent(episode)"
                            :is-playing="store.isCurrent(episode) && store.playing"
                            class="mb-3"
                            @toggle="store.toggle(episode, podcast)"
                        />

                        <v-pagination
                            v-if="meta.last_page > 1"
                            v-model="page"
                            :length="meta.last_page"
                            :total-visible="7"
                            class="mt-4"
                            @update:model-value="load"
                        />
                    </template>
                </v-col>
            </v-row>
        </v-container>
    </div>
</template>

<script>
import { usePodcastStore } from '@/store/podcastStore.js';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import CopyLabel from '@/components/common/CopyLabel.vue';
import EpisodeListItem from '@/components/podcast/EpisodeListItem.vue';
import { canManagePodcasts } from '@/utils/podcast.js';

/**
 * One show: its cover and description, the feed to subscribe with, and a
 * page of its episodes.
 */
export default {
    name: 'PodcastShow',
    components: { PageHeader, EmptyState, LoadingState, CopyLabel, EpisodeListItem },
    setup() {
        return { store: usePodcastStore() };
    },
    data() {
        return {
            page: 1,
            podcast: null,
            episodes: [],
            meta: { current_page: 1, last_page: 1, total: 0 },
        };
    },
    computed: {
        loading() {
            return this.store.loadingShow;
        },
        slug() {
            return this.$route.params.slug;
        },
        canManage() {
            return canManagePodcasts();
        },
    },
    watch: {
        // Going from one show straight to another reuses the component
        slug() {
            this.page = 1;
            this.load();
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        async load() {
            const result = await this.store.fetchShow(this.slug, { page: this.page });

            if ( ! result) {
                this.podcast = null;

                return;
            }

            this.podcast = result.podcast;
            this.episodes = result.episodes;
            this.meta = result.meta || this.meta;
        },
    },
};
</script>
