<template>
    <!-- Only there when there is something to control, like the other
         conditional toolbar items. An empty slot in the bar would be one
         more thing to look past. -->
    <v-menu v-if="current" location="bottom end" :close-on-content-click="false">
        <template #activator="{ props }">
            <v-btn
                v-bind="props"
                icon
                variant="text"
                size="small"
                class="podcast-toolbar-btn"
                :title="current.title"
                :aria-label="$t('podcast.player.label')"
            >
                <!-- The ring doubles as the progress read-out, so the
                     button stays one icon wide and still says how far in
                     the episode is. -->
                <v-progress-circular
                    :model-value="progress"
                    :size="28"
                    :width="2"
                    :color="playing ? 'primary' : 'medium-emphasis'"
                >
                    <v-icon size="14">{{ playing ? 'mdi-pause' : 'mdi-play' }}</v-icon>
                </v-progress-circular>
            </v-btn>
        </template>

        <v-card min-width="260" max-width="320">
            <v-card-text class="pb-2">
                <div class="d-flex align-center ga-3">
                    <v-avatar v-if="current.cover_url" :image="current.cover_url" rounded="md" size="40" />
                    <v-avatar v-else color="primary" rounded="md" size="40">
                        <v-icon size="20">mdi-podcast</v-icon>
                    </v-avatar>
                    <div class="podcast-toolbar-meta">
                        <div class="text-body-2 font-weight-medium text-truncate">{{ current.title }}</div>
                        <div class="text-caption text-medium-emphasis text-truncate">
                            {{ currentShow?.title || $t('podcast.title') }}
                        </div>
                    </div>
                </div>

                <div class="d-flex align-center justify-space-between mt-2">
                    <span class="text-caption text-medium-emphasis">{{ formatDuration(position) }}</span>
                    <span class="text-caption text-medium-emphasis">{{ formatDuration(total) }}</span>
                </div>
                <v-progress-linear :model-value="progress" color="primary" height="4" rounded />
            </v-card-text>

            <v-card-actions class="pt-0">
                <v-btn
                    icon="mdi-rewind-15"
                    variant="text"
                    size="small"
                    :title="$t('podcast.player.back15')"
                    @click="store.seekBy(-15)"
                />
                <v-btn
                    :icon="playing ? 'mdi-pause' : 'mdi-play'"
                    color="primary"
                    variant="elevated"
                    size="small"
                    :title="playing ? $t('podcast.player.pause') : $t('podcast.player.play')"
                    @click="toggle"
                />
                <v-btn
                    icon="mdi-fast-forward-30"
                    variant="text"
                    size="small"
                    :title="$t('podcast.player.forward30')"
                    @click="store.seekBy(30)"
                />
                <v-spacer />
                <v-btn
                    v-if="episodeRoute"
                    variant="text"
                    size="small"
                    :to="episodeRoute"
                    :title="$t('podcast.manage.openEpisode')"
                >
                    {{ $t('podcast.episode') }}
                </v-btn>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    size="small"
                    :title="$t('common.close')"
                    @click="store.stop()"
                />
            </v-card-actions>
        </v-card>
    </v-menu>
</template>

<script>
import { usePodcastStore } from '@/store/podcastStore.js';
import { formatDuration } from '@/utils/podcast.js';

/**
 * The episode that is playing, as one button in the app bar.
 *
 * Deliberately a single icon wide: the toolbar is crowded already, and
 * the full controls belong on the podcast pages. What it does carry is
 * progress, drawn as the ring around the icon, so a listener can see how
 * far in they are from anywhere on the site. The rest — skip, the link
 * to the episode, stopping — is behind the menu.
 *
 * It owns no audio. PodcastAudioHost does, and everything here goes
 * through the store.
 */
export default {
    name: 'PodcastToolbarPlayer',
    setup() {
        return { store: usePodcastStore() };
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
        total() {
            return this.store.duration || this.current?.duration || 0;
        },
        progress() {
            return this.total > 0 ? (this.position / this.total) * 100 : 0;
        },
        episodeRoute() {
            const slug = this.currentShow?.slug;

            if ( ! slug || ! this.current?.slug) {
                return null;
            }

            return { name: 'podcast-episode', params: { slug, episodeSlug: this.current.slug } };
        },
    },
    methods: {
        formatDuration,

        toggle() {
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
/* Truncation needs a child that may shrink below its content */
.podcast-toolbar-meta {
    flex: 1 1 auto;
    min-width: 0;
}
</style>
