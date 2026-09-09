<template>
    <v-container fluid class="pa-5">
        <v-row>
            <v-col cols="12">
                <v-btn @click="$router.back()" color="primary" class="ma-2">{{ $t('gallery.backToGallery') }}</v-btn>
            </v-col>

            <v-col cols="12">
                <v-row v-if="album && album.media.length">
                    <v-col v-for="media in album.media" :key="media.id" cols="12" md="6" lg="4">
                        <v-img :src="media.url" :alt="media.file_name" height="200"></v-img>
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
            </v-col>

            <v-col cols="12">
                <v-file-input @change="onFileChange($event)" :label="$t('gallery.selectFile')" outlined dense :disabled="uploading"></v-file-input>
                <v-text-field v-model="newCaption" :label="$t('gallery.addCaption')" outlined dense :disabled="uploading"></v-text-field>
                <v-btn @click="uploadMedia" color="primary" :loading="uploading" :disabled="busy && !uploading">{{ $t('gallery.uploadToAlbum') }}</v-btn>
            </v-col>
        </v-row>
    </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useDialog } from '@/composables/useDialog.js';

const { t } = useI18n();
const dialog = useDialog();

const route = useRoute();
const album = ref(null);
const selectedFile = ref(null);
const newCaption = ref('');
const uploading = ref(false);
const savingCaptionId = ref(null);
const deletingId = ref(null);
const busy = computed(() => uploading.value || savingCaptionId.value !== null || deletingId.value !== null);

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
    if (busy.value) return;
    if (!selectedFile.value) {
        await dialog.warning(t('gallery.pleaseSelectFile'));
        return;
    }

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    formData.append('caption', newCaption.value);

    uploading.value = true;
    try {
        await axios.post(`/api/collections/${route.params.id}/media`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
        await fetchAlbum();
        selectedFile.value = null;
        newCaption.value = '';
        await dialog.success(t('gallery.uploadSuccess'));
    } catch (error) {
        console.error(error);
        await dialog.requestError(error, t('gallery.uploadFailed'));
    } finally {
        uploading.value = false;
    }
};

const updateMediaCaption = async (mediaId, newCaption) => {
    if (busy.value) return;

    savingCaptionId.value = mediaId;
    try {
        await axios.patch(`/api/media/${mediaId}/caption`, { caption: newCaption });
        await fetchAlbum();
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
        await fetchAlbum();
    } catch (error) {
        console.error(error);
        await dialog.requestError(error, t('gallery.deleteImageFailed'));
    } finally {
        deletingId.value = null;
    }
};

onMounted(fetchAlbum);
</script>

<style scoped>
.album {
    padding: 20px;
}
</style>
