<template>
    <v-dialog v-model="dialogOpen" max-width="900" scrollable>
        <v-card class="media-picker-modal">
            <v-card-title class="d-flex align-center justify-space-between py-3">
                <span>{{ $t('mediaPicker.selectImage') }}</span>
                <v-btn icon variant="text" @click="close">
                    <v-icon icon="mdi-close" />
                </v-btn>
            </v-card-title>

            <v-divider />

            <v-tabs v-model="activeTab" color="primary">
                <v-tab value="library">
                    <v-icon icon="mdi-image-multiple" start />
                    {{ $t('mediaPicker.mediaLibrary') }}
                </v-tab>
                <v-tab value="upload">
                    <v-icon icon="mdi-upload" start />
                    {{ $t('mediaPicker.uploadNew') }}
                </v-tab>
                <v-tab value="url">
                    <v-icon icon="mdi-link" start />
                    {{ $t('mediaPicker.fromUrl') }}
                </v-tab>
            </v-tabs>

            <v-divider />

            <v-card-text class="pa-0" style="height: 500px; overflow-y: auto;">
                <!-- Library Tab -->
                <v-window v-model="activeTab">
                    <v-window-item value="library">
                        <div class="pa-4">
                            <!-- Search and Filter -->
                            <div class="d-flex align-center ga-2 mb-4">
                                <v-text-field
                                    v-model="search"
                                    :placeholder="$t('mediaPicker.searchImages')"
                                    prepend-inner-icon="mdi-magnify"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                    clearable
                                    style="max-width: 300px;"
                                    @update:model-value="debouncedSearch"
                                />

                                <v-select
                                    v-model="collectionFilter"
                                    :items="collectionOptions"
                                    item-title="label"
                                    item-value="value"
                                    :placeholder="$t('mediaPicker.allCollections')"
                                    prepend-inner-icon="mdi-filter-variant"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                    clearable
                                    style="max-width: 200px;"
                                />
                            </div>

                            <!-- Media Grid -->
                            <div v-if="loading" class="d-flex justify-center pa-8">
                                <v-progress-circular indeterminate color="primary" />
                            </div>

                            <div v-else-if="!media.length" class="text-center pa-8">
                                <v-icon icon="mdi-image-off" size="64" color="grey" />
                                <div class="text-h6 text-grey mt-4">{{ $t('mediaPicker.noImagesFound') }}</div>
                                <div class="text-body-2 text-grey">
                                    {{ $t('mediaPicker.tryAdjustingSearch') }}
                                </div>
                            </div>

                            <v-row v-else>
                                <v-col
                                    v-for="item in media"
                                    :key="item.id"
                                    cols="6"
                                    sm="4"
                                    md="3"
                                >
                                    <v-card
                                        class="picker-card"
                                        :class="{ 'picker-card--selected': selectedMedia?.id === item.id }"
                                        @click="selectMedia(item)"
                                    >
                                        <div class="picker-card__image-container">
                                            <v-img
                                                v-if="isImage(item)"
                                                :src="item.thumb_url || item.url"
                                                :alt="item.file_name"
                                                aspect-ratio="1"
                                                cover
                                            >
                                                <template #placeholder>
                                                    <v-row class="fill-height ma-0" align="center" justify="center">
                                                        <v-progress-circular indeterminate color="grey-lighten-1" size="24" />
                                                    </v-row>
                                                </template>
                                            </v-img>
                                            <div v-else class="d-flex align-center justify-center fill-height">
                                                <v-icon icon="mdi-file" size="48" color="grey" />
                                            </div>

                                            <v-icon
                                                v-if="selectedMedia?.id === item.id"
                                                icon="mdi-check-circle"
                                                class="picker-card__check"
                                                color="primary"
                                                size="24"
                                            />
                                        </div>
                                        <v-card-text class="pa-2">
                                            <div class="text-caption text-truncate" :title="item.file_name">
                                                {{ item.file_name }}
                                            </div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>

                            <!-- Pagination -->
                            <div v-if="meta.last_page > 1" class="d-flex justify-center mt-4">
                                <v-pagination
                                    v-model="page"
                                    :length="meta.last_page"
                                    :total-visible="5"
                                    density="compact"
                                />
                            </div>
                        </div>
                    </v-window-item>

                    <!-- Upload Tab -->
                    <v-window-item value="upload">
                        <div class="pa-4">
                            <!-- Collection Selection -->
                            <v-select
                                v-model="uploadCollection"
                                :label="$t('mediaPicker.collection')"
                                :items="uploadCollectionOptions"
                                item-title="label"
                                item-value="value"
                                variant="outlined"
                                density="compact"
                                class="mb-4"
                                style="max-width: 300px;"
                            />

                            <!-- Drop Zone -->
                            <div
                                class="upload-dropzone"
                                :class="{ 'dropzone-active': isDragging, 'dropzone-has-files': uploadFiles.length > 0 }"
                                @dragenter.prevent="isDragging = true"
                                @dragover.prevent
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="onDrop"
                                @click="triggerFileInput"
                            >
                                <input
                                    ref="fileInputRef"
                                    type="file"
                                    multiple
                                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/svg+xml"
                                    class="d-none"
                                    @change="onFileSelect"
                                />

                                <template v-if="uploadFiles.length === 0">
                                    <v-icon icon="mdi-cloud-upload" size="64" color="grey" />
                                    <div class="text-h6 mt-2">{{ $t('mediaPicker.dropImagesHere') }}</div>
                                    <div class="text-caption text-grey">{{ $t('mediaPicker.supportedFormats') }}</div>
                                </template>

                                <template v-else>
                                    <v-icon icon="mdi-check-circle" size="48" color="success" />
                                    <div class="text-h6 mt-2">{{ $t('mediaPicker.filesSelected', { count: uploadFiles.length }) }}</div>
                                    <v-btn
                                        variant="text"
                                        size="small"
                                        color="primary"
                                        @click.stop="triggerFileInput"
                                    >
                                        {{ $t('mediaPicker.addMoreFiles') }}
                                    </v-btn>
                                </template>
                            </div>

                            <!-- Selected Files Preview -->
                            <v-list v-if="uploadFiles.length > 0" class="mt-4" density="compact">
                                <v-list-item
                                    v-for="(file, index) in uploadFiles"
                                    :key="index"
                                >
                                    <template #prepend>
                                        <v-avatar rounded size="40" color="grey-lighten-3">
                                            <v-img
                                                v-if="file.preview"
                                                :src="file.preview"
                                                cover
                                            />
                                            <v-icon v-else icon="mdi-file" />
                                        </v-avatar>
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ file.file.name }}</v-list-item-title>
                                    <v-list-item-subtitle>{{ formatFileSize(file.file.size) }}</v-list-item-subtitle>
                                    <template #append>
                                        <v-btn
                                            icon
                                            variant="text"
                                            size="small"
                                            @click="removeUploadFile(index)"
                                        >
                                            <v-icon icon="mdi-close" size="small" />
                                        </v-btn>
                                    </template>
                                </v-list-item>
                            </v-list>

                            <!-- Upload Progress -->
                            <v-progress-linear
                                v-if="uploading"
                                :model-value="uploadProgress"
                                color="primary"
                                height="8"
                                rounded
                                class="mt-4"
                            />

                            <!-- Upload Button -->
                            <div v-if="uploadFiles.length > 0" class="mt-4">
                                <v-btn
                                    color="primary"
                                    :disabled="uploading"
                                    :loading="uploading"
                                    @click="executeUpload"
                                >
                                    <v-icon icon="mdi-upload" start />
                                    {{ $t('mediaPicker.uploadFiles', { count: uploadFiles.length }) }}
                                </v-btn>
                            </div>

                            <!-- Recently Uploaded -->
                            <div v-if="recentlyUploaded.length > 0" class="mt-6">
                                <div class="text-subtitle-2 mb-2">{{ $t('mediaPicker.recentlyUploaded') }}</div>
                                <v-row>
                                    <v-col
                                        v-for="item in recentlyUploaded"
                                        :key="item.id"
                                        cols="6"
                                        sm="4"
                                        md="3"
                                    >
                                        <v-card
                                            class="picker-card"
                                            :class="{ 'picker-card--selected': selectedMedia?.id === item.id }"
                                            @click="selectMedia(item)"
                                        >
                                            <div class="picker-card__image-container">
                                                <v-img
                                                    :src="item.thumb_url || item.url"
                                                    :alt="item.file_name"
                                                    aspect-ratio="1"
                                                    cover
                                                />
                                                <v-icon
                                                    v-if="selectedMedia?.id === item.id"
                                                    icon="mdi-check-circle"
                                                    class="picker-card__check"
                                                    color="primary"
                                                    size="24"
                                                />
                                            </div>
                                            <v-card-text class="pa-2">
                                                <div class="text-caption text-truncate">{{ item.file_name }}</div>
                                            </v-card-text>
                                        </v-card>
                                    </v-col>
                                </v-row>
                            </div>
                        </div>
                    </v-window-item>

                    <!-- URL Tab -->
                    <v-window-item value="url">
                        <div class="pa-4">
                            <v-text-field
                                v-model="imageUrl"
                                :label="$t('mediaPicker.imageUrl')"
                                placeholder="https://example.com/image.jpg"
                                variant="outlined"
                                prepend-inner-icon="mdi-link"
                                :hint="$t('mediaPicker.enterFullUrl')"
                                persistent-hint
                            />

                            <v-text-field
                                v-model="imageAlt"
                                :label="$t('mediaPicker.altTextOptional')"
                                :placeholder="$t('mediaPicker.imageDescription')"
                                variant="outlined"
                                class="mt-4"
                                :hint="$t('mediaPicker.describeForAccessibility')"
                                persistent-hint
                            />

                            <!-- URL Preview -->
                            <div v-if="imageUrl && isValidUrl" class="mt-4">
                                <div class="text-subtitle-2 mb-2">{{ $t('mediaPicker.preview') }}</div>
                                <v-card max-width="300" class="mx-auto">
                                    <v-img
                                        :src="imageUrl"
                                        aspect-ratio="1.5"
                                        cover
                                        @error="urlPreviewError = true"
                                        @load="urlPreviewError = false"
                                    >
                                        <template #placeholder>
                                            <v-row class="fill-height ma-0" align="center" justify="center">
                                                <v-progress-circular indeterminate color="grey-lighten-1" />
                                            </v-row>
                                        </template>
                                        <template #error>
                                            <v-row class="fill-height ma-0 bg-grey-lighten-3" align="center" justify="center">
                                                <div class="text-center">
                                                    <v-icon icon="mdi-image-broken" size="48" color="grey" />
                                                    <div class="text-caption text-grey">{{ $t('mediaPicker.failedToLoadImage') }}</div>
                                                </div>
                                            </v-row>
                                        </template>
                                    </v-img>
                                </v-card>
                            </div>
                        </div>
                    </v-window-item>
                </v-window>
            </v-card-text>

            <v-divider />

            <v-card-actions class="pa-4">
                <div v-if="selectedMedia" class="d-flex align-center">
                    <v-avatar rounded size="40" class="mr-2">
                        <v-img :src="selectedMedia.thumb_url || selectedMedia.url" cover />
                    </v-avatar>
                    <div class="text-body-2 text-truncate" style="max-width: 200px;">
                        {{ selectedMedia.file_name }}
                    </div>
                </div>
                <v-spacer />
                <v-btn variant="text" @click="close">{{ $t('common.cancel') }}</v-btn>
                <v-btn
                    color="primary"
                    :disabled="!canInsert"
                    @click="insertImage"
                >
                    {{ $t('mediaPicker.insertImage') }}
                </v-btn>
            </v-card-actions>
        </v-card>

        <!-- Snackbar -->
        <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="3000">
            {{ snackbar.text }}
        </v-snackbar>
    </v-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { debounce } from 'lodash';

const { t } = useI18n();

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'select']);

// Dialog state
const dialogOpen = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const activeTab = ref('library');

// Library state
const loading = ref(false);
const media = ref([]);
const page = ref(1);
const search = ref('');
const collectionFilter = ref(null);
const selectedMedia = ref(null);

const meta = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 12,
    total: 0,
});

const collectionOptions = computed(() => [
    { value: 'images', label: t('mediaPicker.images') },
    { value: 'avatars', label: t('mediaPicker.avatars') },
]);

// Upload state
const uploadFiles = ref([]);
const uploading = ref(false);
const uploadProgress = ref(0);
const uploadCollection = ref('images');
const isDragging = ref(false);
const fileInputRef = ref(null);
const recentlyUploaded = ref([]);

const uploadCollectionOptions = computed(() => [
    { label: t('mediaPicker.imagesForPages'), value: 'images' },
]);

// URL state
const imageUrl = ref('');
const imageAlt = ref('');
const urlPreviewError = ref(false);

// Snackbar
const snackbar = reactive({
    show: false,
    text: '',
    color: 'success',
});

// Computed
const isValidUrl = computed(() => {
    try {
        new URL(imageUrl.value);
        return true;
    } catch {
        return false;
    }
});

const canInsert = computed(() => {
    if (activeTab.value === 'url') {
        return imageUrl.value && isValidUrl.value && !urlPreviewError.value;
    }
    return selectedMedia.value !== null;
});

// Methods
const fetchMedia = async () => {
    loading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: 12,
            sort_by: 'created_at',
            sort_dir: 'desc',
            mime_type: 'image',
            exclude_collection: 'gallery',
        };

        if (search.value) {
            params.search = search.value;
        }
        if (collectionFilter.value) {
            params.collection = collectionFilter.value;
        }

        const response = await axios.get('/api/admin/media', { params });
        media.value = response.data.data;
        Object.assign(meta, response.data.meta);
    } catch (error) {
        console.error('Failed to fetch media:', error);
        showSnackbar(t('mediaPicker.failedToLoadMedia'), 'error');
    } finally {
        loading.value = false;
    }
};

const debouncedSearch = debounce(() => {
    page.value = 1;
    fetchMedia();
}, 300);

const isImage = (item) => {
    return item.mime_type?.startsWith('image/');
};

const selectMedia = (item) => {
    selectedMedia.value = item;
};

const close = () => {
    dialogOpen.value = false;
    resetState();
};

const resetState = () => {
    selectedMedia.value = null;
    imageUrl.value = '';
    imageAlt.value = '';
    uploadFiles.value = [];
    recentlyUploaded.value = [];
    activeTab.value = 'library';
};

const insertImage = () => {
    let result;

    if (activeTab.value === 'url') {
        result = {
            src: imageUrl.value,
            alt: imageAlt.value || null,
        };
    } else if (selectedMedia.value) {
        result = {
            src: selectedMedia.value.url,
            alt: selectedMedia.value.name || selectedMedia.value.file_name,
        };
    }

    if (result) {
        emit('select', result);
        close();
    }
};

// Upload methods
const triggerFileInput = () => {
    fileInputRef.value?.click();
};

const onFileSelect = (event) => {
    const files = Array.from(event.target.files);
    addFiles(files);
    event.target.value = '';
};

const onDrop = (event) => {
    isDragging.value = false;
    const files = Array.from(event.dataTransfer.files);
    addFiles(files);
};

const addFiles = (files) => {
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    const maxSize = 10 * 1024 * 1024;

    files.forEach(file => {
        if (!validTypes.includes(file.type)) {
            showSnackbar(t('mediaPicker.invalidFileType', { name: file.name }), 'error');
            return;
        }
        if (file.size > maxSize) {
            showSnackbar(t('mediaPicker.fileTooLarge', { name: file.name }), 'error');
            return;
        }

        const fileObj = { file, preview: null };
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                fileObj.preview = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        uploadFiles.value.push(fileObj);
    });
};

const removeUploadFile = (index) => {
    uploadFiles.value.splice(index, 1);
};

const formatFileSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const executeUpload = async () => {
    if (uploadFiles.value.length === 0) return;

    uploading.value = true;
    uploadProgress.value = 0;

    const formData = new FormData();
    formData.append('collection', uploadCollection.value);
    uploadFiles.value.forEach(({ file }) => {
        formData.append('files[]', file);
    });

    try {
        const response = await axios.post('/api/admin/media/upload', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
            onUploadProgress: (progressEvent) => {
                uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
            },
        });

        showSnackbar(t('mediaPicker.filesUploadedSuccessfully', { count: uploadFiles.value.length }));
        recentlyUploaded.value = response.data.data;
        uploadFiles.value = [];

        // Auto-select the first uploaded image
        if (recentlyUploaded.value.length > 0) {
            selectedMedia.value = recentlyUploaded.value[0];
        }
    } catch (error) {
        console.error('Failed to upload files:', error);
        showSnackbar(error.response?.data?.message || t('mediaPicker.failedToUploadFiles'), 'error');
    } finally {
        uploading.value = false;
    }
};

const showSnackbar = (text, color = 'success') => {
    snackbar.text = text;
    snackbar.color = color;
    snackbar.show = true;
};

// Watchers
watch(page, fetchMedia);

watch(collectionFilter, () => {
    page.value = 1;
    fetchMedia();
});

watch(dialogOpen, (open) => {
    if (open) {
        fetchMedia();
    }
});
</script>

<style scoped>
.media-picker-modal {
    display: flex;
    flex-direction: column;
    max-height: 90vh;
}

.ga-2 {
    gap: 8px;
}

.picker-card {
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.picker-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.picker-card--selected {
    outline: 3px solid rgb(var(--v-theme-primary));
}

.picker-card__image-container {
    position: relative;
    aspect-ratio: 1;
    background: rgb(var(--v-theme-surface-variant));
}

.picker-card__check {
    position: absolute;
    top: 8px;
    right: 8px;
    background: white;
    border-radius: 50%;
}

.upload-dropzone {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 200px;
    border: 2px dashed rgba(var(--v-border-color), 0.4);
    border-radius: 8px;
    padding: 24px;
    cursor: pointer;
    transition: all 0.2s ease;
    background: rgba(var(--v-theme-surface-variant), 0.3);
}

.upload-dropzone:hover {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.05);
}

.upload-dropzone.dropzone-active {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.1);
}

.upload-dropzone.dropzone-has-files {
    border-style: solid;
    border-color: rgb(var(--v-theme-success));
    background: rgba(var(--v-theme-success), 0.05);
}
</style>
