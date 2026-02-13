<template>
    <v-container fluid class="pa-5">
        <!-- Gallery Collections -->
        <v-row justify="end" class="mt-5">
            <v-col cols="12" v-if="collections.length">
                <v-row>
                    <v-col cols="12" sm="6" md="4" v-for="(collection, index) in collections" :key="index">
                        <v-card @click="openAlbum(collection.slug)"
                            class="card-hover"
                            elevation="10"
                            rounded="md"
                            link
                            :class="`v-theme--ORANGE_THEME`"
                        >
                            <v-img :src="collection.coverImage" height="200"></v-img>
                            <v-card-text>
                                <div class="d-flex align-center gap-3">
                                    <div>
                                        <h6 class="text-h6 mb-1">{{ collection.name }}</h6>
                                        <span class="d-block text-truncate d-flex align-center gap-2 textSecondary">{{ collection.taxonomy }}</span>
                                    </div>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </v-col>
        </v-row>
    </v-container>
</template>

<script setup>
import {ref, onMounted, watch} from 'vue';
import {useRouter} from 'vue-router';
import axios from 'axios';

const taxonomies = ref([]);
const collections = ref([]);
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
        alert('Please select a file first');
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
// onMounted(() => {
//     fetchCollections();
// });

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
