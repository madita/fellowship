<template>
    <div class="flex-grow-1">
        <page-header
            :title="editing ? $t('podcast.manage.editEpisode') : $t('podcast.manage.newEpisode')"
            :subtitle="show?.title || ''"
            icon="mdi-music-note"
            :breadcrumbs="breadcrumbs"
            :back-to="backTo"
        />

        <v-container>
            <loading-state v-if="loading" />

            <v-row v-else>
                <v-col cols="12" md="8">
                    <v-card variant="outlined" class="mb-4">
                        <v-card-text>
                            <v-text-field
                                v-model="form.title"
                                :label="$t('common.title')"
                                :error-messages="fieldError('title')"
                                density="comfortable"
                                class="mb-2"
                            />
                            <v-textarea
                                v-model="form.summary"
                                :label="$t('admin.podcast.fields.summary')"
                                :hint="$t('admin.podcast.fields.summaryHint')"
                                rows="2"
                                density="comfortable"
                                class="mb-3"
                            />

                            <div class="text-subtitle-2 font-weight-medium mb-1">
                                {{ $t('podcast.showNotes') }}
                            </div>
                            <simple-editor
                                v-model="form.show_notes"
                                :placeholder="$t('admin.podcast.fields.showNotesHint')"
                                :limit="60000"
                                :disabled="saving"
                                min-height="220px"
                            />
                        </v-card-text>
                    </v-card>

                    <!-- Audio: the point of the whole thing, so it gets a
                         card of its own rather than a row in a form. -->
                    <v-card variant="outlined">
                        <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                            <v-icon class="mr-2" color="primary">mdi-waveform</v-icon>
                            {{ $t('admin.podcast.audio') }}
                        </v-card-title>
                        <v-card-text>
                            <v-alert
                                v-if="!editing"
                                type="info"
                                variant="tonal"
                                density="comfortable"
                                class="mb-0"
                            >
                                {{ $t('podcast.manage.audioAfterSave') }}
                            </v-alert>

                            <template v-else>
                                <div v-if="episode?.audio_url" class="mb-4">
                                    <audio :src="episode.audio_url" controls class="w-100"></audio>
                                    <div class="d-flex align-center flex-wrap ga-2 mt-2">
                                        <v-chip v-if="episode.audio_size" size="small" variant="tonal">
                                            {{ formatFileSize(episode.audio_size) }}
                                        </v-chip>
                                        <v-chip v-if="episode.audio_mime" size="small" variant="tonal">
                                            {{ episode.audio_mime }}
                                        </v-chip>
                                        <v-spacer />
                                        <v-btn
                                            v-if="episode.audio_size"
                                            size="small"
                                            variant="text"
                                            color="error"
                                            prepend-icon="mdi-delete"
                                            :loading="deletingAudio"
                                            @click="deleteAudio"
                                        >
                                            {{ $t('podcast.manage.removeAudio') }}
                                        </v-btn>
                                    </div>
                                </div>

                                <v-file-input
                                    v-model="audioFile"
                                    :label="$t('podcast.manage.chooseAudio')"
                                    :hint="$t('podcast.manage.audioHint')"
                                    persistent-hint
                                    accept="audio/*"
                                    prepend-icon="mdi-music-note-plus"
                                    density="comfortable"
                                    :error-messages="audioError"
                                    show-size
                                />

                                <v-progress-linear
                                    v-if="uploadingAudio"
                                    :model-value="uploadProgress"
                                    color="primary"
                                    height="6"
                                    rounded
                                    class="mt-3"
                                />

                                <v-btn
                                    :disabled="!canUploadAudio"
                                    :loading="uploadingAudio"
                                    color="primary"
                                    variant="tonal"
                                    block
                                    class="mt-3"
                                    prepend-icon="mdi-upload"
                                    @click="uploadAudio"
                                >
                                    {{ $t('podcast.manage.uploadAudio') }}
                                </v-btn>

                                <v-divider class="my-4" />

                                <v-text-field
                                    v-model="form.audio_url"
                                    :label="$t('admin.podcast.fields.audioUrl')"
                                    :hint="$t('admin.podcast.fields.audioUrlHint')"
                                    :error-messages="fieldError('audio_url')"
                                    :disabled="!!episode?.audio_size"
                                    density="comfortable"
                                    persistent-hint
                                />
                            </template>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="4">
                    <v-card variant="outlined">
                        <v-card-text>
                            <v-row dense>
                                <v-col cols="6">
                                    <v-text-field
                                        v-model="form.episode_number"
                                        :label="$t('admin.podcast.fields.episodeNumber')"
                                        type="number"
                                        min="0"
                                        density="comfortable"
                                    />
                                </v-col>
                                <v-col cols="6">
                                    <v-text-field
                                        v-model="form.season_number"
                                        :label="$t('admin.podcast.fields.seasonNumber')"
                                        type="number"
                                        min="0"
                                        density="comfortable"
                                    />
                                </v-col>
                            </v-row>

                            <v-text-field
                                v-model="form.duration"
                                :label="$t('admin.podcast.fields.duration')"
                                :hint="$t('admin.podcast.fields.durationHint')"
                                :error-messages="durationError"
                                density="comfortable"
                                persistent-hint
                                class="mb-2"
                            />

                            <custom-date-picker
                                v-model="form.published_at"
                                :label="$t('admin.podcast.fields.publishedAt')"
                            />
                            <div class="text-caption text-medium-emphasis mb-2">
                                {{ $t('admin.podcast.fields.publishedAtHint') }}
                            </div>

                            <v-switch
                                v-model="form.explicit"
                                :label="$t('podcast.explicit')"
                                color="warning"
                                density="compact"
                                hide-details
                            />
                            <v-switch
                                v-model="form.is_published"
                                :label="$t('admin.podcast.publishEpisode')"
                                color="primary"
                                density="compact"
                                hide-details
                            />
                        </v-card-text>
                        <v-card-actions>
                            <v-btn variant="text" :to="backTo">{{ $t('common.cancel') }}</v-btn>
                            <v-spacer />
                            <v-btn
                                color="primary"
                                variant="elevated"
                                :loading="saving"
                                @click="save"
                            >
                                {{ $t('common.save') }}
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </div>
</template>

<script>
import axios from 'axios';
import PageHeader from '@/components/common/PageHeader.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import CustomDatePicker from '@/components/common/CustomDatePicker.vue';
import SimpleEditor from '@/components/common/tiptap/SimpleEditor.vue';
import { formatDuration, parseDuration, formatFileSize, uploadFailureMessage } from '@/utils/podcast.js';

/** Matches the 200 MB the API accepts, so the refusal comes early. */
const MAX_AUDIO_BYTES = 204800 * 1024;

const blank = () => ({
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
 * Writing an episode and uploading its audio, from the podcast's own
 * pages — for anyone holding manage-podcast.
 *
 * The audio is uploaded on its own rather than as part of the form,
 * because the file is large: the text saves immediately and the upload
 * gets its own progress bar. A new episode therefore saves first and
 * offers the upload once it exists.
 */
export default {
    name: 'EpisodeForm',
    components: { PageHeader, LoadingState, CustomDatePicker, SimpleEditor },
    data() {
        return {
            form: blank(),
            show: null,
            episode: null,
            loading: true,
            saving: false,
            uploadingAudio: false,
            deletingAudio: false,
            uploadProgress: 0,
            audioFile: null,
            errors: {},
        };
    },
    computed: {
        slug() {
            return this.$route.params.slug;
        },
        episodeSlug() {
            return this.$route.params.episodeSlug || null;
        },
        editing() {
            return !! this.episodeSlug;
        },
        backTo() {
            return { name: 'podcast-show', params: { slug: this.slug } };
        },
        breadcrumbs() {
            return [
                this.$crumb('podcast'),
                this.show ? { title: this.show.title, to: this.backTo } : null,
                this.editing ? this.form.title : this.$t('podcast.manage.newEpisode'),
            ].filter(Boolean);
        },
        durationError() {
            const raw = this.form.duration;

            if (raw === '' || raw === null || raw === undefined) {
                return [];
            }

            return parseDuration(raw) === null ? [this.$t('admin.podcast.fields.durationInvalid')] : [];
        },
        /**
         * The chosen file, whatever shape the input hands back.
         *
         * v-file-input's model is a File when it is not `multiple` and an
         * array when it is, and clearing it leaves either null or an
         * empty array behind. An empty array is truthy, so testing the
         * model directly left the upload button enabled with nothing to
         * upload — it then did nothing at all, with no hint as to why.
         * Everything reads this instead.
         */
        selectedAudio() {
            const value = Array.isArray(this.audioFile) ? this.audioFile[0] : this.audioFile;

            return value instanceof File ? value : null;
        },

        /**
         * Caught here as well as server-side: a member who picked a
         * 400 MB file should be told before it is uploaded, not after.
         */
        audioError() {
            if ( ! this.selectedAudio) {
                return [];
            }

            return this.selectedAudio.size > MAX_AUDIO_BYTES
                ? [this.$t('podcast.manage.audioTooLarge', { size: formatFileSize(MAX_AUDIO_BYTES) })]
                : [];
        },

        canUploadAudio() {
            return !! this.selectedAudio && this.audioError.length === 0 && !! this.episode;
        },
    },
    async mounted() {
        await this.load();
    },
    methods: {
        formatFileSize,

        fieldError(field) {
            return this.errors[field] || [];
        },

        async load() {
            this.loading = true;

            try {
                const { data } = await axios.get('/api/podcasts/manage');
                this.show = (data.podcasts || []).find(show => show.slug === this.slug) || null;

                if ( ! this.show) {
                    this.$dialog.error(this.$t('podcast.notFound.title'));

                    return;
                }

                if (this.editing) {
                    await this.loadEpisode();
                }
            } catch (error) {
                this.$dialog.requestError(error, this.$t('admin.podcast.loadFailed'));
            } finally {
                this.loading = false;
            }
        },

        async loadEpisode() {
            const { data } = await axios.get(`/api/podcasts/manage/${this.show.id}/episodes`, {
                params: { per_page: 100 },
            });

            this.episode = (data.episodes || []).find(item => item.slug === this.episodeSlug) || null;

            if ( ! this.episode) {
                this.$dialog.error(this.$t('podcast.episodeNotFound.title'));

                return;
            }

            this.form = {
                ...blank(),
                title: this.episode.title || '',
                summary: this.episode.summary || '',
                show_notes: this.episode.show_notes || '',
                // A hosted file is managed by the upload button above, so
                // the URL field stays empty rather than showing /storage/…
                audio_url: this.episode.audio_size ? '' : (this.episode.audio_url || ''),
                duration: this.episode.duration ? formatDuration(this.episode.duration) : '',
                episode_number: this.episode.episode_number ?? '',
                season_number: this.episode.season_number ?? '',
                explicit: !! this.episode.explicit,
                is_published: !! this.episode.is_published,
                published_at: this.episode.published_at || null,
            };
        },

        async save() {
            if (this.durationError.length) {
                return;
            }

            this.saving = true;
            this.errors = {};

            const payload = {
                title: this.form.title,
                summary: this.form.summary || null,
                show_notes: this.form.show_notes || null,
                audio_url: this.form.audio_url || null,
                duration: parseDuration(this.form.duration),
                episode_number: this.form.episode_number === '' ? null : Number(this.form.episode_number),
                season_number: this.form.season_number === '' ? null : Number(this.form.season_number),
                explicit: !! this.form.explicit,
                is_published: !! this.form.is_published,
                published_at: this.form.published_at || null,
            };

            const base = `/api/podcasts/manage/${this.show.id}/episodes`;

            try {
                const { data } = this.editing
                    ? await axios.patch(`${base}/${this.episode.id}`, payload)
                    : await axios.post(base, payload);

                this.episode = data.episode;
                this.$dialog.success(data.message);

                // A new episode stays on the form so its audio can go up
                // straight away; the address bar follows so a reload lands
                // on the episode that now exists.
                if ( ! this.editing) {
                    this.$router.replace({
                        name: 'podcast-episode-edit',
                        params: { slug: this.show.slug, episodeSlug: data.episode.slug },
                    });
                }
            } catch (error) {
                this.errors = error.response?.data?.errors || {};

                if ( ! Object.keys(this.errors).length) {
                    this.$dialog.requestError(error, this.$t('admin.podcast.saveFailed'));
                }
            } finally {
                this.saving = false;
            }
        },

        async uploadAudio() {
            if ( ! this.canUploadAudio) {
                return;
            }

            const form = new FormData();
            form.append('audio', this.selectedAudio);

            this.uploadingAudio = true;
            this.uploadProgress = 0;

            try {
                const { data } = await axios.post(
                    `/api/podcasts/manage/${this.show.id}/episodes/${this.episode.id}/audio`,
                    form,
                    {
                        headers: { 'Content-Type': 'multipart/form-data' },
                        onUploadProgress: (event) => {
                            if (event.total) {
                                this.uploadProgress = (event.loaded / event.total) * 100;
                            }
                        },
                    }
                );

                this.episode = data.episode;
                this.audioFile = null;
                this.form.audio_url = '';
                this.$dialog.success(data.message);
            } catch (error) {
                this.$dialog.error(
                    `${this.$t('admin.podcast.uploadFailed')} ${uploadFailureMessage(error, this.$t)}`
                );
            } finally {
                this.uploadingAudio = false;
                this.uploadProgress = 0;
            }
        },

        async deleteAudio() {
            const confirmed = await this.$dialog.confirmDelete(this.$t('podcast.manage.confirmRemoveAudio'));

            if ( ! confirmed) {
                return;
            }

            this.deletingAudio = true;

            try {
                const { data } = await axios.delete(
                    `/api/podcasts/manage/${this.show.id}/episodes/${this.episode.id}/audio`
                );

                this.episode = data.episode;
                this.$dialog.success(data.message);
            } catch (error) {
                this.$dialog.requestError(error, this.$t('admin.podcast.deleteFailed'));
            } finally {
                this.deletingAudio = false;
            }
        },
    },
};
</script>
