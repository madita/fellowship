<template>
    <div>
        <page-header
            :title="$t('gallery.title')"
            :subtitle="$t('gallery.subtitle')"
            icon="mdi-image-multiple-outline"
            fluid
        />

        <v-container fluid>
            <!-- Loading -->
            <loading-state v-if="loading" />

            <!-- Empty State -->
            <empty-state
                v-else-if="!collections.length"
                icon="mdi-image-off-outline"
                :title="$t('gallery.noCollections')"
                :text="$t('gallery.noCollectionsText')"
            />

            <!-- Gallery Collections -->
            <v-row v-else>
                <v-col cols="12" sm="6" md="4" v-for="(collection, index) in collections" :key="index">
                    <v-card
                        class="card-hover"
                        rounded="lg"
                        link
                        @click="openAlbum(collection.slug)"
                    >
                        <v-img :src="collection.coverImage" height="200" cover />
                        <v-card-text>
                            <h3 class="text-subtitle-1 font-weight-medium mb-1">{{ collection.name }}</h3>
                            <span class="d-block text-body-2 text-medium-emphasis text-truncate">{{ collection.taxonomy }}</span>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </div>
</template>

<script setup>
import {ref, onMounted, watch} from 'vue';
import {useI18n} from 'vue-i18n';
import {useRouter} from 'vue-router';
import axios from 'axios';
import {useDialog} from '@/composables/useDialog.js';
import PageHeader from '../common/PageHeader.vue';
import EmptyState from '../common/EmptyState.vue';
import LoadingState from '../common/LoadingState.vue';

const {t} = useI18n();
const dialog = useDialog();

const taxonomies = ref([]);
const collections = ref([]);
const loading = ref(true);
const newCollection = ref({name: ''});
const selectedTaxonomy = ref(null);
const selectedFile = ref(null);
const newCaption = ref('');

const router = useRouter();

const fetchTaxonomies = async () => {
    try {
        const response = await axios.get('/api/tag/taxonomies');
        taxonomies.value = response.data;
    } catch (error) {
        console.error(error);
    }
};

const fetchCollections = async () => {
    // if (!selectedTaxonomy.value) return;
    loading.value = true;
    try {
        const response = await axios.get('/api/collections', {
            params: {taxonomy_id: selectedTaxonomy.value},
        });
        collections.value = response.data.map((collection) => {
            collection.coverImage = collection.media.length ? collection.media[0].url : '';
            return collection;
        });
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const openAlbum = (collectionSlug) => {
    router.push({name: 'gallery-album', params: {album: collectionSlug}});
};

const createCollection = async () => {
    try {
        await axios.post('/api/collections', {
            name: newCollection.value.name,
            taxonomy_id: selectedTaxonomy.value,
        });
        await fetchCollections();
        newCollection.value.name = '';
    } catch (error) {
        console.error(error);
    }
};

const onFileChange = (collectionId, event) => {
    selectedFile.value = event.target.files[0];
};

const uploadMedia = async (collectionId) => {
    if (!selectedFile.value) {
        await dialog.warning(t('gallery.selectFileFirst'));
        return;
    }

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    formData.append('caption', newCaption.value);

    try {
        await axios.post(`/api/collections/${collectionId}/media`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
        await fetchCollections();
        selectedFile.value = null;
        newCaption.value = '';
    } catch (error) {
        console.error(error);
    }
};

const updateMediaCaption = async (mediaId, newCaption) => {
    try {
        await axios.patch(`/api/media/${mediaId}/caption`, {caption: newCaption});
        await fetchCollections();
    } catch (error) {
        console.error(error);
    }
};

const deleteMedia = async (mediaId) => {
    try {
        await axios.delete(`/api/media/${mediaId}`);
        await fetchCollections();
    } catch (error) {
        console.error(error);
    }
};

onMounted(fetchCollections);

watch(selectedTaxonomy, fetchCollections);
</script>
