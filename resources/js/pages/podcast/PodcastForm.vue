<template>
    <div class="flex-grow-1">
        <page-header
            :title="editing ? $t('podcast.manage.editShow') : $t('podcast.manage.newShow')"
            :subtitle="$t('podcast.manage.showSubtitle')"
            icon="mdi-podcast"
            :breadcrumbs="breadcrumbs"
            :back-to="backTo"
        />

        <v-container>
            <loading-state v-if="loading" />

            <v-row v-else>
                <v-col cols="12" md="8">
                    <v-card variant="outlined">
                        <v-card-text>
                            <v-text-field
                                v-model="form.title"
                                :label="$t('common.title')"
                                :error-messages="fieldError('title')"
                                density="comfortable"
                                class="mb-2"
                            />
                            <v-text-field
                                v-model="form.subtitle"
                                :label="$t('admin.podcast.fields.subtitle')"
                                density="comfortable"
                                class="mb-2"
                            />
                            <v-textarea
                                v-model="form.description"
                                :label="$t('common.description')"
                                rows="5"
                                density="comfortable"
                                class="mb-2"
                            />
                            <v-row dense>
                                <v-col cols="12" sm="6">
                                    <v-text-field
                                        v-model="form.author"
                                        :label="$t('admin.podcast.fields.author')"
                                        density="comfortable"
                                    />
                                </v-col>
                                <v-col cols="12" sm="6">
                                    <v-text-field
                                        v-model="form.category"
                                        :label="$t('admin.podcast.fields.category')"
                                        density="comfortable"
                                    />
                                </v-col>
                                <v-col cols="12" sm="6">
                                    <v-select
                                        v-model="form.language"
                                        :items="languages"
                                        :label="$t('admin.podcast.fields.language')"
                                        density="comfortable"
                                    />
                                </v-col>
                            </v-row>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="4">
                    <v-card variant="outlined" class="mb-4">
                        <v-card-title class="text-subtitle-1 font-weight-medium">
                            {{ $t('admin.podcast.cover') }}
                        </v-card-title>
                        <v-img v-if="coverUrl" :src="coverUrl" aspect-ratio="1" cover />
                        <v-card-text>
                            <v-file-input
                                v-model="coverFile"
                                :label="$t('podcast.manage.chooseImage')"
                                accept="image/jpeg,image/png,image/webp"
                                prepend-icon="mdi-image"
                                density="comfortable"
                                hide-details
                                :disabled="!editing"
                                :hint="editing ? '' : $t('podcast.manage.coverAfterSave')"
                                persistent-hint
                            />
                            <v-btn
                                v-if="editing"
                                :disabled="!coverFile"
                                :loading="uploadingCover"
                                color="primary"
                                variant="tonal"
                                block
                                class="mt-3"
                                prepend-icon="mdi-upload"
                                @click="uploadCover"
                            >
                                {{ $t('common.upload') }}
                            </v-btn>
                            <p v-else class="text-caption text-medium-emphasis mt-2 mb-0">
                                {{ $t('podcast.manage.coverAfterSave') }}
                            </p>
                        </v-card-text>
                    </v-card>

                    <v-card variant="outlined">
                        <v-card-text>
                            <v-switch
                                v-model="form.explicit"
                                :label="$t('podcast.explicit')"
                                color="warning"
                                density="compact"
                                hide-details
                            />
                            <v-switch
                                v-model="form.is_published"
                                :label="$t('admin.podcast.publishShow')"
                                color="primary"
                                density="compact"
                                :hint="$t('admin.podcast.publishShowHint')"
                                persistent-hint
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

const blank = () => ({
    title: '',
    subtitle: '',
    description: '',
    author: '',
    category: '',
    language: 'de',
    explicit: false,
    is_published: false,
});

/**
 * Creating a show, or editing one, without the admin panel — for anyone
 * holding manage-podcast. The API has the final say; the route guard
 * keeps the page itself out of reach of everyone else.
 *
 * The cover is uploaded separately, because a file needs a show to
 * belong to: on a new show the field waits until there is one to attach
 * it to.
 */
export default {
    name: 'PodcastForm',
    components: { PageHeader, LoadingState },
    data() {
        return {
            form: blank(),
            podcast: null,
            loading: false,
            saving: false,
            uploadingCover: false,
            coverFile: null,
            errors: {},
            languages: ['de', 'en'],
        };
    },
    computed: {
        slug() {
            return this.$route.params.slug || null;
        },
        editing() {
            return !! this.slug;
        },
        coverUrl() {
            return this.podcast?.cover_url || null;
        },
        backTo() {
            return this.editing && this.podcast
                ? { name: 'podcast-show', params: { slug: this.podcast.slug } }
                : { name: 'podcast' };
        },
        breadcrumbs() {
            return [
                this.$crumb('podcast'),
                this.editing ? this.podcast?.title || '' : this.$t('podcast.manage.newShow'),
            ].filter(Boolean);
        },
    },
    mounted() {
        if (this.editing) {
            this.load();
        }
    },
    methods: {
        fieldError(field) {
            return this.errors[field] || [];
        },

        /**
         * The manage list is the only place that hands back a show the
         * way the form needs it — including one that is not published,
         * which the public endpoint would refuse.
         */
        async load() {
            this.loading = true;

            try {
                const { data } = await axios.get('/api/podcasts/manage');
                this.podcast = (data.podcasts || []).find(show => show.slug === this.slug) || null;

                if ( ! this.podcast) {
                    this.$dialog.error(this.$t('podcast.notFound.title'));

                    return;
                }

                this.form = {
                    ...blank(),
                    title: this.podcast.title || '',
                    subtitle: this.podcast.subtitle || '',
                    description: this.podcast.description || '',
                    author: this.podcast.author || '',
                    category: this.podcast.category || '',
                    language: this.podcast.language || 'de',
                    explicit: !! this.podcast.explicit,
                    is_published: !! this.podcast.is_published,
                };
            } catch (error) {
                this.$dialog.requestError(error, this.$t('admin.podcast.loadFailed'));
            } finally {
                this.loading = false;
            }
        },

        async save() {
            this.saving = true;
            this.errors = {};

            const payload = {
                title: this.form.title,
                subtitle: this.form.subtitle || null,
                description: this.form.description || null,
                author: this.form.author || null,
                category: this.form.category || null,
                language: this.form.language || 'de',
                explicit: !! this.form.explicit,
                is_published: !! this.form.is_published,
            };

            try {
                const { data } = this.editing
                    ? await axios.patch(`/api/podcasts/manage/${this.podcast.id}`, payload)
                    : await axios.post('/api/podcasts/manage', payload);

                this.podcast = data.podcast;
                await this.$dialog.success(data.message);

                this.$router.push({ name: 'podcast-show', params: { slug: data.podcast.slug } });
            } catch (error) {
                this.errors = error.response?.data?.errors || {};

                if ( ! Object.keys(this.errors).length) {
                    this.$dialog.requestError(error, this.$t('admin.podcast.saveFailed'));
                }
            } finally {
                this.saving = false;
            }
        },

        async uploadCover() {
            if ( ! this.coverFile || ! this.podcast) {
                return;
            }

            const file = Array.isArray(this.coverFile) ? this.coverFile[0] : this.coverFile;
            const form = new FormData();
            form.append('cover', file);

            this.uploadingCover = true;

            try {
                const { data } = await axios.post(
                    `/api/podcasts/manage/${this.podcast.id}/cover`,
                    form,
                    { headers: { 'Content-Type': 'multipart/form-data' } }
                );

                this.podcast = data.podcast;
                this.coverFile = null;
                this.$dialog.success(data.message);
            } catch (error) {
                this.$dialog.requestError(error, this.$t('admin.podcast.uploadFailed'));
            } finally {
                this.uploadingCover = false;
            }
        },
    },
};
</script>
