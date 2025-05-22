<template>
    <v-container fluid class="pa-5">
        <v-row>
            <v-col cols="12">
                <v-btn @click="$router.back()" color="primary" class="ma-2">Back to Gallery</v-btn>
            </v-col>

            <v-col cols="12">
                <v-row v-if="album && album.media.length">
                    <v-col v-for="media in album.media" :key="media.id" cols="12" md="6" lg="4">
                        <v-img :src="media.url" :alt="media.file_name" height="200"></v-img>
                        <v-text-field
                            v-model="media.newCaption"
                            :label="media.caption ? 'Edit Caption' : 'Add Caption'"
                            outlined
                            dense
                        />
                        <v-btn @click="updateMediaCaption(media.id, media.newCaption)" color="success" small>Update Caption</v-btn>
                        <v-btn @click="deleteMedia(media.id)" color="error" small>Delete</v-btn>
                    </v-col>
                </v-row>
            </v-col>

            <v-col cols="12">
                <v-file-input @change="onFileChange($event)" label="Select File" outlined dense></v-file-input>
                <v-text-field v-model="newCaption" label="Add Caption" outlined dense></v-text-field>
                <v-btn @click="uploadMedia" color="primary">Upload to Album</v-btn>
            </v-col>
        </v-row>
    </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const album = ref(null);
const selectedFile = ref(null);
const newCaption = ref('');

const fetchAlbum = async () => {
    try {
        const response = await axios.get(`/api/collections/${route.params.id}`);
        album.value = response.data;
        album.value.media = album.value.media.map((media) => {
            return { ...media, newCaption: media.caption || '' };
        });
    } catch (error) {
        console.error(error);
    }
};

const onFileChange = (event) => {
    selectedFile.value = event.target.files[0];
};

const uploadMedia = async () => {
    if (!selectedFile.value) {
        alert('Please select a file first');
        return;
    }

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    formData.append('caption', newCaption.value);

    try {
        await axios.post(`/api/collections/${route.params.id}/media`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
        await fetchAlbum();
        selectedFile.value = null;
        newCaption.value = '';
    } catch (error) {
        console.error(error);
    }
};

const updateMediaCaption = async (mediaId, newCaption) => {
    try {
        await axios.patch(`/api/media/${mediaId}/caption`, { caption: newCaption });
        await fetchAlbum();
    } catch (error) {
        console.error(error);
    }
};

const deleteMedia = async (mediaId) => {
    try {
        await axios.delete(`/api/media/${mediaId}`);
        await fetchAlbum();
    } catch (error) {
        console.error(error);
    }
};

onMounted(fetchAlbum);
</script>

<style scoped>
.album {
    padding: 20px;
}
</style>
