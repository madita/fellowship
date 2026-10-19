<template>
    <div class="flex-grow-1">
        <page-header
            :title="$t('admin.podcast.title')"
            :subtitle="$t('admin.podcast.subtitle')"
            icon="mdi-podcast"
            :breadcrumbs="[$crumb('admin'), $t('admin.podcast.title')]"
        >
            <template #actions>
                <v-btn
                    variant="tonal"
                    prepend-icon="mdi-refresh"
                    :loading="loadingShows"
                    :disabled="loadingShows"
                    @click="refresh"
                >
                    {{ $t('dashboard.refresh') }}
                </v-btn>
                <v-btn color="primary" variant="elevated" prepend-icon="mdi-plus" @click="newShow">
                    {{ $t('admin.podcast.newShow') }}
                </v-btn>
            </template>
        </page-header>

        <v-container fluid>
            <!-- The feature is off: the page still works, because this is
                 where an admin comes to turn it back on. -->
            <v-alert
                v-if="!featureEnabled"
                type="info"
                variant="tonal"
                class="mb-4"
                prepend-icon="mdi-toggle-switch-off-outline"
            >
                <div class="d-flex align-center justify-space-between flex-wrap ga-2">
                    <span>{{ $t('admin.podcast.featureOff') }}</span>
                    <v-btn
                        size="small"
                        color="primary"
                        variant="elevated"
                        :to="{ name: 'admin-settings-page', params: { category: 'features', setting: 'toggles' } }"
                    >
                        {{ $t('admin.podcast.goToToggles') }}
                    </v-btn>
                </div>
            </v-alert>

            <v-row v-if="stats" dense class="mb-4">
                <v-col v-for="tile in statTiles" :key="tile.key" cols="6" sm="4" md="2">
                    <v-card variant="tonal" :color="tile.color" class="h-100">
                        <v-card-text class="d-flex align-center pa-3">
                            <v-icon size="28" class="mr-3">{{ tile.icon }}</v-icon>
                            <div>
                                <div class="text-h6 font-weight-bold">{{ tile.value }}</div>
                                <div class="text-caption">{{ $t(`admin.podcast.stats.${tile.key}`) }}</div>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <loading-state v-if="loadingShows && !shows.length" />

            <empty-state
                v-else-if="!shows.length"
                icon="mdi-podcast"
                :title="$t('admin.podcast.empty.title')"
                :text="$t('admin.podcast.empty.text')"
            />

            <v-row v-else>
                <!-- Shows -->
                <v-col cols="12" md="4" lg="3">
                    <v-card variant="outlined">
                        <v-list density="compact" class="pa-0">
                            <v-list-item
                                v-for="show in shows"
                                :key="show.id"
                                :active="selectedId === show.id"
                                @click="select(show)"
                            >
                                <template #prepend>
                                    <v-avatar v-if="show.cover_url" :image="show.cover_url" rounded="sm" size="36" />
                                    <v-avatar v-else color="primary" rounded="sm" size="36">
                                        <v-icon size="20">mdi-podcast</v-icon>
                                    </v-avatar>
                                </template>
                                <v-list-item-title class="text-body-2 font-weight-medium">
                                    {{ show.title }}
                                </v-list-item-title>
                                <v-list-item-subtitle class="text-caption">
                                    {{ $t('podcast.episodeCount', { count: show.episodes_count ?? 0 }) }}
                                </v-list-item-subtitle>
                                <template #append>
                                    <v-icon
                                        size="small"
                                        :color="show.is_published ? 'success' : 'warning'"
                                        :title="show.is_published ? $t('admin.podcast.published') : $t('admin.podcast.draft')"
                                    >
                                        {{ show.is_published ? 'mdi-eye-outline' : 'mdi-eye-off-outline' }}
                                    </v-icon>
                                </template>
                            </v-list-item>
                        </v-list>
                    </v-card>
                </v-col>

                <!-- The selected show and its episodes -->
                <v-col v-if="selected" cols="12" md="8" lg="9">
                    <v-card variant="outlined" class="mb-4">
                        <v-card-title class="d-flex align-center flex-wrap ga-2">
                            <span class="text-subtitle-1 font-weight-medium flex-grow-1">{{ selected.title }}</span>
                            <v-chip
                                size="small"
                                :color="selected.is_published ? 'success' : 'warning'"
                                variant="tonal"
                            >
                                {{ selected.is_published ? $t('admin.podcast.published') : $t('admin.podcast.draft') }}
                            </v-chip>
                        </v-card-title>

                        <v-card-text>
                            <p v-if="selected.description" class="text-body-2 text-medium-emphasis">
                                {{ selected.description }}
                            </p>
                            <div class="text-caption text-medium-emphasis">
                                {{ $t('podcast.feedUrl') }}:
                                <a :href="selected.feed_url" target="_blank" rel="noopener">{{ selected.feed_url }}</a>
                            </div>
                        </v-card-text>

                        <v-card-actions class="flex-wrap ga-1">
                            <v-btn variant="text" prepend-icon="mdi-pencil" @click="editShow(selected)">
                                {{ $t('common.update') }}
                            </v-btn>
                            <v-btn
                                variant="text"
                                prepend-icon="mdi-image"
                                :loading="busy === 'cover'"
                                @click="pickCover"
                            >
                                {{ $t('admin.podcast.cover') }}
                            </v-btn>
                            <v-btn
                                variant="text"
                                prepend-icon="mdi-plus"
                                color="primary"
                                @click="newEpisode"
                            >
                                {{ $t('admin.podcast.newEpisode') }}
                            </v-btn>
                            <v-spacer />
                            <v-btn
                                variant="text"
                                color="error"
                                prepend-icon="mdi-delete"
                                :loading="busy === 'show-delete'"
                                @click="deleteShow(selected)"
                            >
                                {{ $t('common.delete') }}
                            </v-btn>
                        </v-card-actions>

                        <!-- Kept out of the layout; opened by the button -->
                        <input
                            ref="coverInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="d-none"
                            @change="uploadCover"
                        />
                    </v-card>

                    <loading-state v-if="loadingEpisodes && !episodes.length" />

                    <empty-state
                        v-else-if="!episodes.length"
                        icon="mdi-playlist-plus"
                        :title="$t('admin.podcast.noEpisodes.title')"
                        :text="$t('admin.podcast.noEpisodes.text')"
                    />

                    <v-card v-else variant="outlined">
                        <v-table density="comfortable">
                            <thead>
                                <tr>
                                    <th>{{ $t('common.title') }}</th>
                                    <th class="d-none d-md-table-cell">{{ $t('admin.podcast.fields.number') }}</th>
                                    <th class="d-none d-sm-table-cell">{{ $t('admin.podcast.fields.duration') }}</th>
                                    <th class="d-none d-md-table-cell">{{ $t('admin.podcast.audio') }}</th>
                                    <th>{{ $t('admin.podcast.state') }}</th>
                                    <th class="text-right">{{ $t('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="episode in episodes" :key="episode.id">
                                    <td>
                                        <div class="text-body-2 font-weight-medium">{{ episode.title }}</div>
                                        <div v-if="episode.summary" class="text-caption text-medium-emphasis">
                                            {{ truncate(episode.summary) }}
                                        </div>
                                    </td>
                                    <td class="d-none d-md-table-cell text-caption">
                                        {{ numberLabel(episode) || '–' }}
                                    </td>
                                    <td class="d-none d-sm-table-cell text-caption">
                                        {{ episode.duration ? formatDuration(episode.duration) : '–' }}
                                    </td>
                                    <td class="d-none d-md-table-cell text-caption">
                                        <template v-if="episode.audio_url">
                                            {{ formatFileSize(episode.audio_size) }}
                                        </template>
                                        <v-chip v-else size="x-small" color="error" variant="tonal">
                                            {{ $t('podcast.noAudio') }}
                                        </v-chip>
                                    </td>
                                    <td>
                                        <v-chip
                                            size="x-small"
                                            :color="episode.is_published ? 'success' : 'warning'"
                                            variant="tonal"
                                        >
                                            {{ episode.is_published ? $t('admin.podcast.published') : $t('admin.podcast.draft') }}
                                        </v-chip>
                                    </td>
                                    <td class="text-right text-no-wrap">
                                        <v-btn
                                            icon="mdi-upload"
                                            variant="text"
                                            size="small"
                                            :title="$t('admin.podcast.uploadAudio')"
                                            :loading="busy === `audio-${episode.id}`"
                                            @click="pickAudio(episode)"
                                        />
                                        <v-btn
                                            icon="mdi-pencil"
                                            variant="text"
                                            size="small"
                                            :title="$t('common.update')"
                                            @click="editEpisode(episode)"
                                        />
                                        <v-btn
                                            icon="mdi-delete"
                                            variant="text"
                                            size="small"
                                            color="error"
                                            :title="$t('common.delete')"
                                            :loading="busy === `delete-${episode.id}`"
                                            @click="deleteEpisode(episode)"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>

                        <v-card-text v-if="uploading">
                            <div class="text-caption mb-1">{{ $t('admin.podcast.uploadingAudio') }}</div>
                            <v-progress-linear :model-value="uploadProgress" color="primary" height="6" rounded />
                        </v-card-text>
                    </v-card>

                    <input
                        ref="audioInput"
                        type="file"
                        accept="audio/*"
                        class="d-none"
                        @change="uploadAudio"
                    />

                    <v-pagination
                        v-if="episodeMeta.last_page > 1"
                        v-model="episodePage"
                        :length="episodeMeta.last_page"
                        :total-visible="7"
                        class="mt-4"
                        @update:model-value="loadEpisodes"
                    />
                </v-col>
            </v-row>
        </v-container>

        <!-- Show editor -->
        <v-dialog v-model="showDialog" max-width="640" scrollable>
            <v-card>
                <v-card-title class="text-subtitle-1 font-weight-medium">
                    {{ showForm.id ? $t('admin.podcast.editShow') : $t('admin.podcast.newShow') }}
                </v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model="showForm.title"
                        :label="$t('common.title')"
                        :error-messages="fieldError('title')"
                        density="comfortable"
                        class="mb-2"
                    />
                    <v-text-field
                        v-model="showForm.subtitle"
                        :label="$t('admin.podcast.fields.subtitle')"
                        density="comfortable"
                        class="mb-2"
                    />
                    <v-textarea
                        v-model="showForm.description"
                        :label="$t('common.description')"
                        rows="4"
                        density="comfortable"
                        class="mb-2"
                    />
                    <v-row dense>
                        <v-col cols="12" sm="6">
                            <v-text-field
                                v-model="showForm.author"
                                :label="$t('admin.podcast.fields.author')"
                                density="comfortable"
                            />
                        </v-col>
                        <v-col cols="12" sm="6">
                            <v-text-field
                                v-model="showForm.category"
                                :label="$t('admin.podcast.fields.category')"
                                density="comfortable"
                            />
                        </v-col>
                        <v-col cols="12" sm="6">
                            <v-select
                                v-model="showForm.language"
                                :items="languages"
                                :label="$t('admin.podcast.fields.language')"
                                density="comfortable"
                            />
                        </v-col>
                    </v-row>
                    <v-switch
                        v-model="showForm.explicit"
                        :label="$t('podcast.explicit')"
                        color="warning"
                        density="compact"
                        hide-details
                    />
                    <v-switch
                        v-model="showForm.is_published"
                        :label="$t('admin.podcast.publishShow')"
                        color="primary"
                        density="compact"
                        :hint="$t('admin.podcast.publishShowHint')"
                        persistent-hint
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn
                        color="primary"
                        variant="elevated"
                        :loading="saving"
                        @click="saveShow"
                    >
                        {{ $t('common.save') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Episode editor -->
        <v-dialog v-model="episodeDialog" max-width="820" scrollable>
            <v-card>
                <v-card-title class="text-subtitle-1 font-weight-medium">
                    {{ episodeForm.id ? $t('admin.podcast.editEpisode') : $t('admin.podcast.newEpisode') }}
                </v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model="episodeForm.title"
                        :label="$t('common.title')"
                        :error-messages="fieldError('title')"
                        density="comfortable"
                        class="mb-2"
                    />
                    <v-textarea
                        v-model="episodeForm.summary"
                        :label="$t('admin.podcast.fields.summary')"
                        :hint="$t('admin.podcast.fields.summaryHint')"
                        rows="2"
                        density="comfortable"
                        class="mb-3"
                    />

                    <div class="text-subtitle-2 font-weight-medium mb-1">{{ $t('podcast.showNotes') }}</div>
                    <simple-editor
                        v-model="episodeForm.show_notes"
                        :placeholder="$t('admin.podcast.fields.showNotesHint')"
                        :limit="60000"
                        :disabled="saving"
                        min-height="200px"
                    />

                    <v-row dense class="mt-3">
                        <v-col cols="6" sm="3">
                            <v-text-field
                                v-model="episodeForm.episode_number"
                                :label="$t('admin.podcast.fields.episodeNumber')"
                                type="number"
                                min="0"
                                density="comfortable"
                            />
                        </v-col>
                        <v-col cols="6" sm="3">
                            <v-text-field
                                v-model="episodeForm.season_number"
                                :label="$t('admin.podcast.fields.seasonNumber')"
                                type="number"
                                min="0"
                                density="comfortable"
                            />
                        </v-col>
                        <v-col cols="12" sm="6">
                            <v-text-field
                                v-model="episodeForm.duration"
                                :label="$t('admin.podcast.fields.duration')"
                                :hint="$t('admin.podcast.fields.durationHint')"
                                :error-messages="durationError"
                                density="comfortable"
                                persistent-hint
                            />
                        </v-col>
                    </v-row>

                    <v-text-field
                        v-model="episodeForm.audio_url"
                        :label="$t('admin.podcast.fields.audioUrl')"
                        :hint="$t('admin.podcast.fields.audioUrlHint')"
                        :error-messages="fieldError('audio_url')"
                        density="comfortable"
                        persistent-hint
                        class="mb-2"
                    />

                    <!-- A date in the future keeps the episode out of the
                         feed until then; the hint says so, since the
                         picker has nowhere of its own to put one. -->
                    <custom-date-picker
                        v-model="episodeForm.published_at"
                        :label="$t('admin.podcast.fields.publishedAt')"
                    />
                    <div class="text-caption text-medium-emphasis mb-2">
                        {{ $t('admin.podcast.fields.publishedAtHint') }}
                    </div>

                    <v-switch
                        v-model="episodeForm.explicit"
                        :label="$t('podcast.explicit')"
                        color="warning"
                        density="compact"
                        hide-details
                    />
                    <v-switch
                        v-model="episodeForm.is_published"
                        :label="$t('admin.podcast.publishEpisode')"
                        color="primary"
                        density="compact"
                        hide-details
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="episodeDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn
                        color="primary"
                        variant="elevated"
                        :loading="saving"
                        @click="saveEpisode"
                    >
                        {{ $t('common.save') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import axios from 'axios';
import { useSettingsStore } from '@/store/settingStore.js';
import { formatDuration, parseDuration, formatFileSize, uploadFailureMessage } from '@/utils/podcast.js';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import CustomDatePicker from '@/components/common/CustomDatePicker.vue';
import SimpleEditor from '@/components/common/tiptap/SimpleEditor.vue';

const emptyShow = () => ({
    id: null,
    title: '',
    subtitle: '',
    description: '',
    author: '',
    category: '',
    language: 'de',
    explicit: false,
    is_published: false,
});

const emptyEpisode = () => ({
    id: null,
    title: '',
    summary: '',
    show_notes: '',
    audio_url: '',
    duration: '',
    episode_number: '',
    season_number: '',
    explicit: false,
    is_published: false,
    published_at: null,
});

/**
 * Shows, their episodes and the audio for each.
 *
 * Reachable with the podcast feature switched off on purpose — this is
 * where an admin goes to switch it back on, and the episodes have to
 * stay editable meanwhile. The banner at the top says so.
 */
export default {
    name: 'PodcastManager',
    components: { PageHeader, EmptyState, LoadingState, CustomDatePicker, SimpleEditor },
    setup() {
        return { settingsStore: useSettingsStore() };
    },
    data() {
        return {
            shows: [],
            stats: null,
            loadingShows: false,
            selectedId: null,
            episodes: [],
            episodeMeta: { current_page: 1, last_page: 1, total: 0 },
            episodePage: 1,
            loadingEpisodes: false,
            showDialog: false,
            episodeDialog: false,
            showForm: emptyShow(),
            episodeForm: emptyEpisode(),
            saving: false,
            // 'show-delete' | `audio-<id>` | `delete-<id>` while running
            busy: null,
            uploading: false,
            uploadProgress: 0,
            // The episode whose audio the file input is about to replace
            audioTarget: null,
            errors: {},
            languages: ['de', 'en'],
        };
    },
    computed: {
        featureEnabled() {
            return this.settingsStore.isFeatureEnabled('podcast');
        },
        selected() {
            return this.shows.find((show) => show.id === this.selectedId) || null;
        },
        statTiles() {
            return [
                { key: 'podcasts', icon: 'mdi-podcast', color: 'primary', value: this.stats.podcasts },
                { key: 'published_podcasts', icon: 'mdi-eye-outline', color: 'success', value: this.stats.published_podcasts },
                { key: 'episodes', icon: 'mdi-playlist-music', color: 'info', value: this.stats.episodes },
                { key: 'published_episodes', icon: 'mdi-check-circle-outline', color: 'success', value: this.stats.published_episodes },
                { key: 'plays', icon: 'mdi-play-circle-outline', color: 'secondary', value: this.stats.plays },
                { key: 'audio_bytes', icon: 'mdi-database-outline', color: 'warning', value: formatFileSize(this.stats.audio_bytes) },
            ];
        },
        /**
         * The duration field takes "1:04:05" as happily as "3845", so it
         * has to say when it understood neither.
         */
        durationError() {
            const raw = this.episodeForm.duration;

            if (raw === '' || raw === null || raw === undefined) {
                return [];
            }

            return parseDuration(raw) === null ? [this.$t('admin.podcast.fields.durationInvalid')] : [];
        },
    },
    mounted() {
        this.refresh();
    },
    methods: {
        formatDuration,
        formatFileSize,

        fieldError(field) {
            return this.errors[field] || [];
        },

        truncate(text, length = 90) {
            return text.length > length ? `${text.slice(0, length)}…` : text;
        },

        numberLabel(episode) {
            if ( ! episode.episode_number) {
                return '';
            }

            return episode.season_number
                ? `S${episode.season_number}E${episode.episode_number}`
                : `E${episode.episode_number}`;
        },

        async refresh() {
            this.loadingShows = true;

            try {
                const [showsResponse, statsResponse] = await Promise.all([
                    axios.get('/api/admin/podcasts'),
                    axios.get('/api/admin/podcasts/stats'),
                ]);

                this.shows = showsResponse.data.podcasts || [];
                this.stats = statsResponse.data.stats || null;

                // Keep the selection across a refresh where possible
                if ( ! this.selected && this.shows.length) {
                    this.selectedId = this.shows[0].id;
                }

                if (this.selectedId) {
                    await this.loadEpisodes();
                }
            } catch (error) {
                this.$dialog.requestError(error, this.$t('admin.podcast.loadFailed'));
            } finally {
                this.loadingShows = false;
            }
        },

        select(show) {
            if (this.selectedId === show.id) {
                return;
            }

            this.selectedId = show.id;
            this.episodePage = 1;
            this.episodes = [];
            this.loadEpisodes();
        },

        async loadEpisodes() {
            if ( ! this.selectedId) {
                return;
            }

            this.loadingEpisodes = true;

            try {
                const { data } = await axios.get(`/api/admin/podcasts/${this.selectedId}/episodes`, {
                    params: { page: this.episodePage },
                });

                this.episodes = data.episodes || [];
                this.episodeMeta = data.meta || this.episodeMeta;
            } catch (error) {
                this.$dialog.requestError(error, this.$t('admin.podcast.loadFailed'));
            } finally {
                this.loadingEpisodes = false;
            }
        },

        // ---------------- Shows ----------------

        newShow() {
            this.errors = {};
            this.showForm = emptyShow();
            this.showDialog = true;
        },

        editShow(show) {
            this.errors = {};
            this.showForm = { ...emptyShow(), ...show };
            this.showDialog = true;
        },

        async saveShow() {
            this.saving = true;
            this.errors = {};

            const payload = {
                title: this.showForm.title,
                subtitle: this.showForm.subtitle || null,
                description: this.showForm.description || null,
                author: this.showForm.author || null,
                category: this.showForm.category || null,
                language: this.showForm.language || 'de',
                explicit: !! this.showForm.explicit,
                is_published: !! this.showForm.is_published,
            };

            try {
                const { data } = this.showForm.id
                    ? await axios.patch(`/api/admin/podcasts/${this.showForm.id}`, payload)
                    : await axios.post('/api/admin/podcasts', payload);

                this.showDialog = false;
                this.selectedId = data.podcast?.id ?? this.selectedId;
                await this.refresh();
                this.$dialog.success(data.message);
            } catch (error) {
                this.errors = error.response?.data?.errors || {};

                if ( ! Object.keys(this.errors).length) {
                    this.$dialog.requestError(error, this.$t('admin.podcast.saveFailed'));
                }
            } finally {
                this.saving = false;
            }
        },

        async deleteShow(show) {
            const confirmed = await this.$dialog.confirmDelete(
                this.$t('admin.podcast.confirmDeleteShow', { title: show.title })
            );

            if ( ! confirmed) {
                return;
            }

            this.busy = 'show-delete';

            try {
                const { data } = await axios.delete(`/api/admin/podcasts/${show.id}`);
                this.selectedId = null;
                this.episodes = [];
                await this.refresh();
                this.$dialog.success(data.message);
            } catch (error) {
                this.$dialog.requestError(error, this.$t('admin.podcast.deleteFailed'));
            } finally {
                this.busy = null;
            }
        },

        pickCover() {
            this.$refs.coverInput?.click();
        },

        async uploadCover(event) {
            const file = event.target.files?.[0];
            // Clear it so picking the same file again still fires change
            event.target.value = '';

            if ( ! file || ! this.selected) {
                return;
            }

            const form = new FormData();
            form.append('cover', file);

            this.busy = 'cover';

            try {
                const { data } = await axios.post(
                    `/api/admin/podcasts/${this.selected.id}/cover`,
                    form,
                    { headers: { 'Content-Type': 'multipart/form-data' } }
                );

                await this.refresh();
                this.$dialog.success(data.message);
            } catch (error) {
                this.$dialog.requestError(error, this.$t('admin.podcast.uploadFailed'));
            } finally {
                this.busy = null;
            }
        },

        // ---------------- Episodes ----------------

        newEpisode() {
            this.errors = {};
            this.episodeForm = emptyEpisode();
            this.episodeDialog = true;
        },

        editEpisode(episode) {
            this.errors = {};
            this.episodeForm = {
                ...emptyEpisode(),
                ...episode,
                // The form shows a clock, not a count of seconds
                duration: episode.duration ? formatDuration(episode.duration) : '',
                // A hosted file is managed by the upload button, so the
                // URL field stays empty rather than showing /storage/…
                audio_url: episode.audio_size ? '' : (episode.audio_url || ''),
                episode_number: episode.episode_number ?? '',
                season_number: episode.season_number ?? '',
                show_notes: episode.show_notes || '',
            };
            this.episodeDialog = true;
        },

        async saveEpisode() {
            if (this.durationError.length) {
                return;
            }

            this.saving = true;
            this.errors = {};

            const payload = {
                title: this.episodeForm.title,
                summary: this.episodeForm.summary || null,
                show_notes: this.episodeForm.show_notes || null,
                audio_url: this.episodeForm.audio_url || null,
                duration: parseDuration(this.episodeForm.duration),
                episode_number: this.episodeForm.episode_number === '' ? null : Number(this.episodeForm.episode_number),
                season_number: this.episodeForm.season_number === '' ? null : Number(this.episodeForm.season_number),
                explicit: !! this.episodeForm.explicit,
                is_published: !! this.episodeForm.is_published,
                published_at: this.episodeForm.published_at || null,
            };

            try {
                const base = `/api/admin/podcasts/${this.selectedId}/episodes`;
                const { data } = this.episodeForm.id
                    ? await axios.patch(`${base}/${this.episodeForm.id}`, payload)
                    : await axios.post(base, payload);

                this.episodeDialog = false;
                await this.loadEpisodes();
                await this.refreshStats();
                this.$dialog.success(data.message);
            } catch (error) {
                this.errors = error.response?.data?.errors || {};

                if ( ! Object.keys(this.errors).length) {
                    this.$dialog.requestError(error, this.$t('admin.podcast.saveFailed'));
                }
            } finally {
                this.saving = false;
            }
        },

        async deleteEpisode(episode) {
            const confirmed = await this.$dialog.confirmDelete(
                this.$t('admin.podcast.confirmDeleteEpisode', { title: episode.title })
            );

            if ( ! confirmed) {
                return;
            }

            this.busy = `delete-${episode.id}`;

            try {
                const { data } = await axios.delete(
                    `/api/admin/podcasts/${this.selectedId}/episodes/${episode.id}`
                );

                await this.loadEpisodes();
                await this.refreshStats();
                this.$dialog.success(data.message);
            } catch (error) {
                this.$dialog.requestError(error, this.$t('admin.podcast.deleteFailed'));
            } finally {
                this.busy = null;
            }
        },

        pickAudio(episode) {
            this.audioTarget = episode;
            this.$refs.audioInput?.click();
        },

        async uploadAudio(event) {
            const file = event.target.files?.[0];
            event.target.value = '';

            if ( ! file || ! this.audioTarget) {
                return;
            }

            const episode = this.audioTarget;
            const form = new FormData();
            form.append('audio', file);

            this.busy = `audio-${episode.id}`;
            this.uploading = true;
            this.uploadProgress = 0;

            try {
                const { data } = await axios.post(
                    `/api/admin/podcasts/${this.selectedId}/episodes/${episode.id}/audio`,
                    form,
                    {
                        headers: { 'Content-Type': 'multipart/form-data' },
                        onUploadProgress: (progressEvent) => {
                            if (progressEvent.total) {
                                this.uploadProgress = (progressEvent.loaded / progressEvent.total) * 100;
                            }
                        },
                    }
                );

                await this.loadEpisodes();
                await this.refreshStats();
                this.$dialog.success(data.message);
            } catch (error) {
                // The field error says what was actually wrong with the
                // file; requestError would only show "Validation failed".
                this.$dialog.error(
                    `${this.$t('admin.podcast.uploadFailed')} ${uploadFailureMessage(error, this.$t)}`
                );
            } finally {
                this.busy = null;
                this.uploading = false;
                this.uploadProgress = 0;
                this.audioTarget = null;
            }
        },

        /**
         * Only the numbers, after an episode changed. Reloading the whole
         * screen would throw away the page the admin is looking at.
         */
        async refreshStats() {
            try {
                const { data } = await axios.get('/api/admin/podcasts/stats');
                this.stats = data.stats || this.stats;
            } catch (error) {
                // The numbers are not worth a dialog of their own
            }
        },
    },
};
</script>
