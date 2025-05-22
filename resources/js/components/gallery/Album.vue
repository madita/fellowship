
<template>
    <v-container fluid class="pa-5">
        <v-row>
<!--            <v-col cols="12" v-if="album && album.media.length">-->
<!--                <v-carousel hide-delimiters>-->
<!--                    <v-carousel-item v-for="(media, index) in album.media"-->
<!--                        :src="media.original_url"-->
<!--                        cover-->
<!--                    >-->
<!--                        <div class="d-flex fill-height justify-center align-center">-->
<!--                            <div class="text-h2">-->
<!--                                {{media.caption}}-->
<!--                            </div>-->
<!--                        </div>-->

<!--                    </v-carousel-item>-->


<!--                </v-carousel>-->
<!--            </v-col>-->
            <v-col cols="6">
                <v-btn @click="$router.back()" color="primary" class="ma-2">Back to Gallery</v-btn>
            </v-col>

            <v-col cols="6" class="text-right">
                <v-btn v-if="!fileUpload" icon="" @click="fileUpload = true" color="primary" class="ma-2">
                    <v-icon>mdi-plus</v-icon>
                </v-btn>
                <v-btn v-if="fileUpload" icon="" @click="fileUpload = false" color="primary" class="ma-2">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-col>



            <file-uploader v-if="fileUpload"
                           upload-url="/api/collections"
                           :collection-id="album.id"
                           @upload-success="handleUploadSuccess"
                           @upload-failure="handleUploadFailure"/>


            <v-row justify="end" class="mt-5" >
                <v-col cols="12" v-if="album && album.media.length">
                    <v-row>
                        <v-col>
                            <!--                            <LightGallery  :index="selectedFile" :images="album.media" />-->
                            <TinyBox :index="selectedFile"
                                     :images="album.media"
                                     @change="(i) => {selectedFile = i}" loop no-thumbs />
                        </v-col>
                    </v-row>
                    <v-row>



                        <v-col cols="12" sm="6" md="3" v-for="(media, index) in album.media" :key="index">
                            <v-card
                                @click="changeIndex(index)"
                                class="card-hover"
                                elevation="10"
                                rounded="md"
                                link
                                :class="`v-theme--ORANGE_THEME`"
                                max-width="300"
                                @mouseenter="hoverIndex = index"
                                @mouseleave="hoverIndex = null"
                            >
                                <div class="relative-position">
                                    <!-- Make the image a square -->
                                    <v-img
                                        :src="media.original_url"
                                        :alt="media.file_name"
                                        class="gallery-image"
                                        height="300px"
                                        width="300px"
                                    ></v-img>

                                    <!-- Icons with tooltips, shown only when hovering -->
                                    <div v-if="hoverIndex === index" class="icon-overlay">
                                        <v-tooltip
                                            location="top"
                                        >
                                            <template v-slot:activator="{ props }">

                                                    <v-icon v-bind="props" color="white" class="icon-button">
                                                        mdi-account
                                                    </v-icon>

                                            </template>
                                            <span>Hochgeladen von: {{ media.uploader }}</span>
                                        </v-tooltip>

                                        <v-tooltip
                                            location="top"
                                        >
                                            <template v-slot:activator="{ props }">

                                                <v-icon v-bind="props" color="white" class="icon-button">
                                                    mdi-calendar
                                                </v-icon>

                                            </template>
                                            <span>Date: {{ new Date(media.created_at).toLocaleDateString() }}</span>
                                        </v-tooltip>

                                        <v-tooltip
                                            location="top"
                                        >
                                            <template v-slot:activator="{ props }">

                                                <v-icon v-bind="props" color="white" class="icon-button">
                                                    mdi-comment-text-outline
                                                </v-icon>

                                            </template>
                                            <span>{{ media.caption }}</span>
                                        </v-tooltip>


                                    </div>
                                </div>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-col>
            </v-row>


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
const hoverIndex = ref(null);
const newCaption = ref('');
const fileUpload = ref(false);
import TinyBox from "./TinyBox.vue";
// import LightGallery from "./LightGallery.vue";
import FileUploader from '../common/FileUploader.vue';


const fetchAlbum = async () => {
    try {
        const response = await axios.get(`/api/collections/${route.params.album}`);
        album.value = response.data;
        album.value.media = album.value.media.map((media) => {
            return { ...media, newCaption: media.caption || '' };
        });
    } catch (error) {
        console.error(error);
    }
};

const  handleFiles = (files) => {
    console.log('Selected files:', files);
    // You can now upload the files to your backend, handle them, etc.
}

const  handleUploadSuccess = (files) => {
    console.log('handleUploadSuccess :', files);
    // You can now upload the files to your backend, handle them, etc.
}

const  handleUploadFailure = (files) => {
    console.log('handleUploadSuccess :', files);
    // You can now upload the files to your backend, handle them, etc.
}


const deleteMedia = async (mediaId) => {
    try {
        await axios.delete(`/api/media/${mediaId}`);
        await fetchAlbum();
    } catch (error) {
        console.error(error);
    }
};

const changeIndex = (index) => {
    console.log(index)
    selectedFile.value = index;

}
onMounted(fetchAlbum);
</script>

<style scoped>
.album {
    padding: 20px;
}

.relative-position {
    position: relative;
}

.icon-overlay {
    position: absolute;
    top: 10px;
    right: 10px;
    display: flex;
    gap: 10px;
}

.icon-button {
    background-color: rgba(0, 0, 0, 0.5); /* semi-transparent background */
    border-radius: 50%;
    padding: 6px;
}

</style>
