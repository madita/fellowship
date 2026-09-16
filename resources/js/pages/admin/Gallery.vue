<template>
    <div>
        <page-header
            :title="$t('gallery.manageTitle')"
            :subtitle="$t('gallery.manageSubtitle')"
            icon="mdi-image-multiple-outline"
            fluid
        />

        <v-container fluid>
            <v-card rounded="lg" variant="outlined" class="mb-6">
                <v-card-title class="text-subtitle-1 font-weight-medium">{{ $t('gallery.createCollection') }}</v-card-title>
                <v-divider />
                <v-card-text>
                    <v-row>
                        <v-col cols="12" md="6">
                            <v-select
                                v-model="selectedTaxonomy"
                                :items="taxonomies"
                                item-title="description"
                                item-value="id"
                                :label="$t('gallery.selectTaxonomy')"
                                hide-details
                            />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="newCollection.name"
                                :label="$t('gallery.collectionName')"
                                :disabled="creating"
                                hide-details
                            />
                        </v-col>
                    </v-row>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-plus"
                        :loading="creating"
                        :disabled="busy && !creating"
                        @click="createCollection"
                    >
                        {{ $t('gallery.createCollection') }}
                    </v-btn>
                </v-card-actions>
            </v-card>

            <empty-state
                v-if="!selectedTaxonomy"
                icon="mdi-tag-outline"
                :title="$t('gallery.selectTaxonomy')"
                :text="$t('gallery.selectTaxonomyHint')"
            />

            <empty-state
                v-else-if="!collections.length"
                icon="mdi-image-off-outline"
                :title="$t('gallery.noCollections')"
                :text="$t('gallery.noCollectionsText')"
            />

            <v-row v-else>
                <v-col v-for="collection in collections" :key="collection.id" cols="12" md="6" lg="4">
                    <v-card rounded="lg" variant="outlined">
                        <v-card-title class="text-subtitle-1 font-weight-medium">{{ collection.name }}</v-card-title>
                        <v-card-subtitle v-if="collection.taxonomy">{{ collection.taxonomy.name }}</v-card-subtitle>
                        <v-divider class="mt-2" />

                        <v-card-text>
                            <empty-state
                                v-if="!collection.media.length"
                                compact
                                icon="mdi-image-off-outline"
                                :title="$t('gallery.noImages')"
                            />
                            <v-row v-else>
                                <v-col v-for="media in collection.media" :key="media.id" cols="12" md="6">
                                    <v-img :src="media.url" :alt="media.file_name" height="150" cover class="rounded mb-2" />
                                    <v-text-field
                                        v-model="media.newCaption"
                                        :label="media.caption ? $t('gallery.editCaption') : $t('gallery.addCaption')"
                                        density="compact"
                                        hide-details
                                        class="mb-2"
                                    />
                                    <div class="d-flex ga-2">
                                        <v-btn
                                            color="primary"
                                            variant="tonal"
                                            size="small"
                                            :loading="savingCaptionId === media.id"
                                            :disabled="busy && savingCaptionId !== media.id"
                                            @click="updateMediaCaption(media.id, media.newCaption)"
                                        >
                                            {{ $t('gallery.updateCaption') }}
                                        </v-btn>
                                        <v-btn
                                            color="error"
                                            variant="text"
                                            size="small"
                                            :loading="deletingId === media.id"
                                            :disabled="busy && deletingId !== media.id"
                                            @click="deleteMedia(media.id)"
                                        >
                                            {{ $t('common.delete') }}
                                        </v-btn>
                                    </div>
                                </v-col>
                            </v-row>
                        </v-card-text>

                        <v-divider />
                        <v-card-text>
                            <v-file-input
                                :label="$t('gallery.selectFile')"
                                density="compact"
                                :disabled="uploadingId !== null"
                                class="mb-2"
                                @change="onFileChange(collection.id, $event)"
                            />
                            <v-text-field
                                v-model="newCaption"
                                :label="$t('gallery.addCaption')"
                                density="compact"
                                :disabled="uploadingId !== null"
                                hide-details
                            />
                        </v-card-text>
                        <v-card-actions>
                            <v-spacer />
                            <v-btn
                                color="primary"
                                variant="flat"
                                size="small"
                                prepend-icon="mdi-upload"
                                :loading="uploadingId === collection.id"
                                :disabled="busy && uploadingId !== collection.id"
                                @click="uploadMedia(collection.id)"
                            >
                                {{ $t('gallery.uploadToCollection') }}
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </div>
</template>

<script setup>
import {ref, computed, onMounted, watch} from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useDialog } from '@/composables/useDialog.js';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';

const { t } = useI18n();
const dialog = useDialog();

const taxonomies = ref([]);
const collections = ref([]);
const newCollection = ref({name: ''});
const selectedTaxonomy = ref(null);
const selectedFile = ref(null);
const newCaption = ref('');
const creating = ref(false);
const uploadingId = ref(null);
const savingCaptionId = ref(null);
const deletingId = ref(null);
const busy = computed(() =>
    creating.value || uploadingId.value !== null || savingCaptionId.value !== null || deletingId.value !== null
);

const fetchTaxonomies = async () => {
    try {
        const response = await axios.get('/api/tag/taxonomies');
        taxonomies.value = response.data;
    } catch (error) {
        console.error(error);
    }
};

const fetchCollections = async () => {
    if (!selectedTaxonomy.value) return;
    try {
        const response = await axios.get('/api/collections', {
            params: {taxonomy_id: selectedTaxonomy.value},
        });
        collections.value = response.data.map((collection) => {
            collection.media = collection.media.map((media) => {
                return {...media, newCaption: media.caption || ''};
            });
            return collection;
        });
    } catch (error) {
        console.error(error);
    }
};

const createCollection = async () => {
    if (busy.value) return;
    if (!newCollection.value.name.trim()) {
        await dialog.warning(t('gallery.collectionNameRequired'));
        return;
    }

    creating.value = true;
    try {
        await axios.post('/api/collections', {
            name: newCollection.value.name,
            taxonomy_id: selectedTaxonomy.value,
        });
        await fetchCollections();
        newCollection.value.name = '';
        await dialog.success(t('gallery.collectionCreated'));
    } catch (error) {
        console.error(error);
        await dialog.requestError(error, t('gallery.collectionCreateFailed'));
    } finally {
        creating.value = false;
    }
};

const onFileChange = (collectionId, event) => {
    selectedFile.value = event.target.files[0];
};

const uploadMedia = async (collectionId) => {
    if (busy.value) return;
    if (!selectedFile.value) {
        await dialog.warning(t('gallery.pleaseSelectFile'));
        return;
    }

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    formData.append('caption', newCaption.value);

    uploadingId.value = collectionId;
    try {
        await axios.post(`/api/collections/${collectionId}/media`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
        await fetchCollections();
        selectedFile.value = null;
        newCaption.value = '';
        await dialog.success(t('gallery.uploadSuccess'));
    } catch (error) {
        console.error(error);
        await dialog.requestError(error, t('gallery.uploadFailed'));
    } finally {
        uploadingId.value = null;
    }
};

const updateMediaCaption = async (mediaId, newCaption) => {
    if (busy.value) return;

    savingCaptionId.value = mediaId;
    try {
        await axios.patch(`/api/media/${mediaId}/caption`, {caption: newCaption});
        await fetchCollections();
        await dialog.success(t('gallery.captionSaved'));
    } catch (error) {
        console.error(error);
        await dialog.requestError(error, t('gallery.captionSaveFailed'));
    } finally {
        savingCaptionId.value = null;
    }
};

const deleteMedia = async (mediaId) => {
    if (busy.value) return;

    const confirmed = await dialog.confirmDelete(t('gallery.deleteImageConfirm'), {
        title: t('gallery.deleteImageTitle'),
    });
    if (!confirmed) return;

    deletingId.value = mediaId;
    try {
        await axios.delete(`/api/media/${mediaId}`);
        await fetchCollections();
    } catch (error) {
        console.error(error);
        await dialog.requestError(error, t('gallery.deleteImageFailed'));
    } finally {
        deletingId.value = null;
    }
};

onMounted(fetchTaxonomies);

watch(selectedTaxonomy, fetchCollections);
</script>
