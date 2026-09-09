<template>
    <div>
        <page-header
            :title="album?.name || $t('gallery.album')"
            icon="mdi-image-album"
            :back-to="{ name: 'admin-gallery' }"
            fluid
        />

        <v-container fluid>
            <v-card rounded="lg" variant="outlined" class="mb-6">
                <v-card-title class="text-subtitle-1 font-weight-medium">{{ $t('gallery.uploadToAlbum') }}</v-card-title>
                <v-divider />
                <v-card-text>
                    <v-file-input
                        :label="$t('gallery.selectFile')"
                        :disabled="uploading"
                        class="mb-2"
                        @change="onFileChange($event)"
                    />
                    <v-text-field
                        v-model="newCaption"
                        :label="$t('gallery.addCaption')"
                        :disabled="uploading"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-upload"
                        :loading="uploading"
                        :disabled="busy && !uploading"
                        @click="uploadMedia"
                    >
                        {{ $t('gallery.uploadToAlbum') }}
                    </v-btn>
                </v-card-actions>
            </v-card>

            <loading-state v-if="!album" />

            <empty-state
                v-else-if="!album.media.length"
                icon="mdi-image-off-outline"
                :title="$t('gallery.noImages')"
                :text="$t('gallery.noImagesText')"
            />

            <v-row v-else>
                <v-col v-for="media in album.media" :key="media.id" cols="12" md="6" lg="4">
                    <v-card rounded="lg" variant="outlined">
                        <v-img :src="media.url" :alt="media.file_name" height="200" cover />
                        <v-card-text>
                            <v-text-field
                                v-model="media.newCaption"
                                :label="media.caption ? $t('gallery.editCaption') : $t('gallery.addCaption')"
                                density="compact"
                                hide-details
                            />
                        </v-card-text>
                        <v-card-actions class="ga-2">
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
                            <v-spacer />
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
                        </v-card-actions>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useDialog } from '@/composables/useDialog.js';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';

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
