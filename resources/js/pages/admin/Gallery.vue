<template>
    <v-container fluid class="pa-5">
        <v-row>
            <v-col cols="12">
                <v-select
                    v-model="selectedTaxonomy"
                    :items="taxonomies"
                    item-text="description"
                    item-value="id"
                    :label="$t('gallery.selectTaxonomy')"
                    outlined
                />
            </v-col>

            <v-col cols="12">
                <v-text-field
                    v-model="newCollection.name"
                    :label="$t('gallery.collectionName')"
                    outlined
                    :disabled="creating"
                />
                <v-btn @click="createCollection" color="primary" class="ma-2" :loading="creating" :disabled="busy && !creating">{{ $t('gallery.createCollection') }}</v-btn>
            </v-col>
        </v-row>

        <v-row v-if="collections.length" class="ma-4">
            <v-col v-for="collection in collections" :key="collection.id" cols="12" md="6" lg="4">
                <v-card outlined>
                    <v-card-title>{{ collection.name }}</v-card-title>
                    <v-card-subtitle v-if="collection.taxonomy">{{ collection.taxonomy.name }}</v-card-subtitle>

                    <v-card-text>
                        <v-row>
                            <v-col v-for="media in collection.media" :key="media.id" cols="12" md="6">
                                <v-img :src="media.url" :alt="media.file_name" height="150"></v-img>
                                <v-text-field
                                    v-model="media.newCaption"
                                    :label="media.caption ? $t('gallery.editCaption') : $t('gallery.addCaption')"
                                    outlined
                                    dense
                                />
                                <v-btn
                                    @click="updateMediaCaption(media.id, media.newCaption)"
                                    color="success"
                                    small
                                    :loading="savingCaptionId === media.id"
                                    :disabled="busy && savingCaptionId !== media.id"
                                >{{ $t('gallery.updateCaption') }}</v-btn>
                                <v-btn
                                    @click="deleteMedia(media.id)"
                                    color="error"
                                    small
                                    :loading="deletingId === media.id"
                                    :disabled="busy && deletingId !== media.id"
                                >{{ $t('common.delete') }}</v-btn>
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-card-actions>
                        <v-file-input @change="onFileChange(collection.id, $event)" :label="$t('gallery.selectFile')" outlined dense :disabled="uploadingId !== null"></v-file-input>
                        <v-text-field v-model="newCaption" :label="$t('gallery.addCaption')" outlined dense :disabled="uploadingId !== null"></v-text-field>
                        <v-btn
                            @click="uploadMedia(collection.id)"
                            color="primary"
                            :loading="uploadingId === collection.id"
                            :disabled="busy && uploadingId !== collection.id"
                        >{{ $t('gallery.uploadToCollection') }}</v-btn>
                    </v-card-actions>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<script setup>
import {ref, computed, onMounted, watch} from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useDialog } from '@/composables/useDialog.js';

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

<style scoped>
.gallery {
    padding: 20px;
}

.create-collection {
    margin-bottom: 20px;
}

.collection-list ul {
    list-style: none;
    padding: 0;
}

.collection-list li {
    margin-bottom: 20px;
}

.media-item {
    margin-bottom: 15px;
}

button {
    cursor: pointer;
    margin-left: 10px;
}
</style>
