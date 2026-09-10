<template>
    <div>
        <page-header
            :title="album?.name || $t('gallery.album')"
            :subtitle="albumSubtitle"
            icon="mdi-image-album"
            :back-to="{ name: 'gallery-index' }"
            fluid
        >
            <template #actions>
                <v-btn
                    :color="fileUpload ? undefined : 'primary'"
                    :variant="fileUpload ? 'tonal' : 'elevated'"
                    :prepend-icon="fileUpload ? 'mdi-close' : 'mdi-plus'"
                    @click="fileUpload = !fileUpload"
                >
                    {{ fileUpload ? $t('common.close') : $t('gallery.addImages') }}
                </v-btn>
            </template>
        </page-header>

        <v-container fluid>
            <file-uploader
                v-if="fileUpload"
                upload-url="/api/collections"
                :collection-id="album.id"
                class="mb-6"
                @upload-success="handleUploadSuccess"
                @upload-failure="handleUploadFailure"
            />

            <loading-state v-if="loading" />

            <template v-else-if="album && album.media.length">
                <TinyBox
                    :index="selectedFile"
                    :images="album.media"
                    loop
                    no-thumbs
                    @change="(i) => {selectedFile = i}"
                />

                <v-row>
                    <v-col cols="12" sm="6" md="3" v-for="(media, index) in album.media" :key="index">
                        <v-card
                            class="card-hover"
                            rounded="lg"
                            link
                            @click="changeIndex(index)"
                            @mouseenter="hoverIndex = index"
                            @mouseleave="hoverIndex = null"
                        >
                            <div class="position-relative">
                                <v-img
                                    :src="media.original_url"
                                    :alt="media.file_name"
                                    aspect-ratio="1"
                                    cover
                                />

                                <!-- Icons with tooltips, shown only when hovering -->
                                <div v-if="hoverIndex === index" class="icon-overlay d-flex ga-2">
                                    <v-tooltip location="top">
                                        <template v-slot:activator="{ props }">
                                            <v-icon v-bind="props" color="white" class="icon-button">mdi-account</v-icon>
                                        </template>
                                        <span>{{ $t('gallery.uploadedBy', { name: media.uploader }) }}</span>
                                    </v-tooltip>

                                    <v-tooltip location="top">
                                        <template v-slot:activator="{ props }">
                                            <v-icon v-bind="props" color="white" class="icon-button">mdi-calendar</v-icon>
                                        </template>
                                        <span>{{ $t('gallery.uploadedOn', { date: $formatDate(media.created_at) }) }}</span>
                                    </v-tooltip>

                                    <v-tooltip v-if="media.caption" location="top">
                                        <template v-slot:activator="{ props }">
                                            <v-icon v-bind="props" color="white" class="icon-button">mdi-comment-text-outline</v-icon>
                                        </template>
                                        <span>{{ media.caption }}</span>
                                    </v-tooltip>
                                </div>
                            </div>
                        </v-card>
                    </v-col>
                </v-row>
            </template>

            <!-- Empty album -->
            <empty-state
                v-else-if="album"
                icon="mdi-image-off-outline"
                :title="$t('gallery.noImages')"
                :text="$t('gallery.noImagesText')"
            />
        </v-container>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useDialog } from '@/composables/useDialog.js';
import TinyBox from "@/components/gallery/TinyBox.vue";
import FileUploader from '@/components/common/FileUploader.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';

const { t } = useI18n();
const dialog = useDialog();
const route = useRoute();
const album = ref(null);
const loading = ref(true);
// The API may send the taxonomy as a plain string or as an object
const albumSubtitle = computed(() => {
    const taxonomy = album.value?.taxonomy;
    if (!taxonomy) return '';
    return typeof taxonomy === 'string' ? taxonomy : (taxonomy.name || '');
});
const selectedFile = ref(null);
const hoverIndex = ref(null);
const newCaption = ref('');
const fileUpload = ref(false);

const fetchAlbum = async () => {
    try {
        const response = await axios.get(`/api/collections/${route.params.album}`);
        album.value = response.data;
        album.value.media = album.value.media.map((media) => {
            return { ...media, newCaption: media.caption || '' };
        });
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const handleUploadSuccess = async () => {
    // Show the new images and close the uploader
    await fetchAlbum();
    fileUpload.value = false;
    await dialog.success(t('gallery.uploadSuccess'));
}

const handleUploadFailure = async (error) => {
    console.error('Upload failed:', error);
    await dialog.requestError(error, t('gallery.uploadFailed'));
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
    selectedFile.value = index;
}
onMounted(fetchAlbum);
</script>

<style scoped>
.icon-overlay {
    position: absolute;
    top: 10px;
    right: 10px;
}

.icon-button {
    background-color: rgba(0, 0, 0, 0.5); /* semi-transparent scrim over the photo */
    border-radius: 50%;
    padding: 6px;
}
</style>
