<template>
    <v-container fluid class="pa-0 media-center">
        <v-row no-gutters class="fill-height">
            <!-- Sidebar - Folder Navigation -->
            <v-col cols="12" md="3" lg="2" class="sidebar-col">
                <v-card class="sidebar-card fill-height" rounded="0" flat>
                    <v-card-title class="d-flex align-center justify-space-between py-3">
                        <span>{{ t('mediaCenter.title') }}</span>
                        <v-btn
                            icon
                            size="small"
                            variant="text"
                            @click="fetchFolders"
                        >
                            <v-icon icon="mdi-refresh" />
                        </v-btn>
                    </v-card-title>

                    <v-divider />

                    <!-- Total Stats -->
                    <v-list-item
                        :active="!currentContext"
                        @click="navigateToRoot"
                        class="folder-item"
                    >
                        <template #prepend>
                            <v-icon icon="mdi-folder-home" />
                        </template>
                        <v-list-item-title>{{ t('mediaCenter.allMedia') }}</v-list-item-title>
                        <template #append>
                            <v-chip size="x-small" color="primary">{{ totalStats.count }}</v-chip>
                        </template>
                    </v-list-item>

                    <v-divider />

                    <!-- Folder Tree -->
                    <v-list density="compact" nav class="folder-list">
                        <template v-for="context in folders" :key="context.model_type">
                            <v-list-item
                                :active="currentContext === context.model_type && !currentCollection"
                                @click="navigateToContext(context)"
                                class="folder-item"
                            >
                                <template #prepend>
                                    <v-icon :icon="context.icon" />
                                </template>
                                <v-list-item-title>{{ context.label }}</v-list-item-title>
                                <template #append>
                                    <v-chip size="x-small">{{ context.count }}</v-chip>
                                </template>
                            </v-list-item>

                            <!-- Sub-folders (collections) -->
                            <v-list
                                v-if="currentContext === context.model_type"
                                density="compact"
                                class="ml-4"
                            >
                                <v-list-item
                                    v-for="collection in context.collections"
                                    :key="collection.name"
                                    :active="currentCollection === collection.name"
                                    @click="navigateToCollection(context, collection)"
                                    class="folder-item subfolder"
                                >
                                    <template #prepend>
                                        <v-icon icon="mdi-folder" size="small" />
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ collection.label }}</v-list-item-title>
                                    <template #append>
                                        <v-chip size="x-small">{{ collection.count }}</v-chip>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </template>
                    </v-list>

                    <!-- Storage Info -->
                    <v-divider />
                    <div class="pa-3">
                        <div class="text-caption text-grey">{{ t('mediaCenter.storageUsed') }}</div>
                        <div class="text-body-2 font-weight-medium">{{ totalStats.size_formatted }}</div>
                    </div>
                </v-card>
            </v-col>

            <!-- Main Content -->
            <v-col cols="12" md="9" lg="10" class="content-col">
                <div class="content-wrapper pa-4">
                    <!-- Breadcrumbs & Actions -->
                    <div class="d-flex align-center justify-space-between mb-4">
                        <v-breadcrumbs :items="breadcrumbs" class="pa-0">
                            <template #item="{ item }">
                                <v-breadcrumbs-item
                                    :disabled="item.disabled"
                                    @click="item.onClick && item.onClick()"
                                    class="breadcrumb-link"
                                >
                                    {{ item.title }}
                                </v-breadcrumbs-item>
                            </template>
                        </v-breadcrumbs>

                        <div class="d-flex align-center ga-2">
                            <!-- Upload Button -->
                            <v-btn
                                color="primary"
                                @click="showUploadDialog = true"
                            >
                                <v-icon icon="mdi-upload" start />
                                {{ t('mediaCenter.upload') }}
                            </v-btn>

                            <!-- Search -->
                            <v-text-field
                                v-model="search"
                                :placeholder="t('mediaCenter.searchPlaceholder')"
                                prepend-inner-icon="mdi-magnify"
                                variant="outlined"
                                density="compact"
                                hide-details
                                clearable
                                style="max-width: 250px;"
                                @update:model-value="debouncedSearch"
                            />

                            <!-- Collection Filter (only on All Media view) -->
                            <v-select
                                v-if="!currentContext"
                                v-model="collectionFilter"
                                :items="availableCollections"
                                item-title="label"
                                item-value="value"
                                :placeholder="t('mediaCenter.allCollections')"
                                prepend-inner-icon="mdi-filter-variant"
                                variant="outlined"
                                density="compact"
                                hide-details
                                clearable
                                style="max-width: 200px;"
                            >
                                <template #item="{ item, props }">
                                    <v-list-item v-bind="props">
                                        <template #append>
                                            <v-chip size="x-small">{{ item.raw.count }}</v-chip>
                                        </template>
                                    </v-list-item>
                                </template>
                            </v-select>

                            <!-- View Toggle -->
                            <v-btn-toggle v-model="viewMode" mandatory density="compact">
                                <v-btn value="grid" size="small">
                                    <v-icon icon="mdi-view-grid" />
                                </v-btn>
                                <v-btn value="list" size="small">
                                    <v-icon icon="mdi-view-list" />
                                </v-btn>
                            </v-btn-toggle>

                            <!-- Bulk Delete -->
                            <v-btn
                                v-if="selectedIds.length"
                                color="error"
                                variant="tonal"
                                size="small"
                                @click="confirmBulkDelete"
                            >
                                <v-icon icon="mdi-delete" start />
                                {{ t('common.delete') }} ({{ selectedIds.length }})
                            </v-btn>
                        </div>
                    </div>

                    <!-- Model Items View (when viewing a context) -->
                    <div v-if="showModelItems && !currentModelId" class="model-items-view">
                        <v-row>
                            <v-col
                                v-for="item in modelItems"
                                :key="`${item.model_id}-${item.collection_name}`"
                                cols="6"
                                sm="4"
                                md="3"
                                lg="2"
                            >
                                <v-card
                                    class="model-folder-card"
                                    @click="navigateToModel(item)"
                                >
                                    <div class="folder-preview">
                                        <v-icon icon="mdi-folder-open" size="48" color="amber" />
                                    </div>
                                    <v-card-text class="pa-2 text-center">
                                        <div class="text-body-2 text-truncate font-weight-medium">
                                            {{ item.model_name }}
                                        </div>
                                        <div class="text-caption text-grey">
                                            {{ t('mediaCenter.filesCount', { count: item.count }) }} - {{ item.size_formatted }}
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                        </v-row>

                        <div v-if="modelItems.length === 0 && !loading" class="text-center pa-8">
                            <v-icon icon="mdi-folder-open-outline" size="64" color="grey" />
                            <div class="text-h6 text-grey mt-4">{{ t('mediaCenter.noItemsFound') }}</div>
                        </div>
                    </div>

                    <!-- Media Grid/List View -->
                    <div v-else>
                        <MediaGrid
                            v-if="viewMode === 'grid'"
                            :media="media"
                            :loading="loading"
                            :selected-ids="selectedIds"
                            @click="openDetails"
                            @delete="confirmDelete"
                            @update:selected-ids="selectedIds = $event"
                        />

                        <MediaList
                            v-else
                            :media="media"
                            :loading="loading"
                            :selected-ids="selectedIds"
                            @click="openDetails"
                            @delete="confirmDelete"
                            @update:selected-ids="selectedIds = $event"
                        />

                        <!-- Pagination -->
                        <v-card v-if="meta.total > 0" class="mt-4" flat>
                            <v-card-text class="d-flex align-center justify-space-between">
                                <div class="text-body-2 text-grey">
                                    {{ t('mediaCenter.showingRange', { from: meta.from, to: meta.to, total: meta.total }) }}
                                </div>
                                <v-pagination
                                    v-model="page"
                                    :length="meta.last_page"
                                    :total-visible="5"
                                    density="compact"
                                />
                            </v-card-text>
                        </v-card>
                    </div>
                </div>
            </v-col>
        </v-row>

        <!-- Details Dialog -->
        <MediaDetailsDialog
            v-model="detailsDialogOpen"
            :media="selectedMedia"
            @delete="handleDelete"
        />

        <!-- Delete Confirmation Dialog -->
        <v-dialog v-model="deleteConfirmDialog" max-width="400">
            <v-card>
                <v-card-title>
                    {{ bulkDeleteMode ? t('mediaCenter.deleteSelectedMedia') : t('mediaCenter.deleteMedia') }}
                </v-card-title>
                <v-card-text>
                    <template v-if="bulkDeleteMode">
                        {{ t('mediaCenter.confirmDeleteMultiple', { count: selectedIds.length }) }}
                    </template>
                    <template v-else>
                        {{ t('mediaCenter.confirmDeleteSingle', { filename: mediaToDelete?.file_name }) }}
                    </template>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="cancelDelete">{{ t('common.cancel') }}</v-btn>
                    <v-btn color="error" :loading="deleting" @click="executeDelete">{{ t('common.delete') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Upload Dialog -->
        <v-dialog v-model="showUploadDialog" max-width="600" persistent>
            <v-card>
                <v-card-title class="d-flex align-center justify-space-between">
                    <span>{{ t('mediaCenter.uploadImages') }}</span>
                    <v-btn icon variant="text" @click="closeUploadDialog">
                        <v-icon icon="mdi-close" />
                    </v-btn>
                </v-card-title>
                <v-card-text>
                    <!-- Collection Selection -->
                    <v-select
                        v-model="uploadCollection"
                        :label="t('mediaCenter.collection')"
                        :items="uploadCollectionOptions"
                        item-title="label"
                        item-value="value"
                        variant="outlined"
                        density="compact"
                        class="mb-4"
                    />

                    <!-- Drop Zone -->
                    <div
                        class="upload-dropzone"
                        :class="{ 'dropzone-active': isDragging, 'dropzone-has-files': uploadFiles.length > 0 }"
                        @dragenter.prevent="onDragEnter"
                        @dragover.prevent
                        @dragleave.prevent="onDragLeave"
                        @drop.prevent="onDrop"
                        @click="triggerFileInput"
                    >
                        <input
                            ref="fileInputRef"
                            type="file"
                            multiple
                            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/svg+xml,application/pdf"
                            class="d-none"
                            @change="onFileSelect"
                        />

                        <template v-if="uploadFiles.length === 0">
                            <v-icon icon="mdi-cloud-upload" size="64" color="grey" />
                            <div class="text-h6 mt-2">{{ t('mediaCenter.dropFilesHere') }}</div>
                            <div class="text-caption text-grey">{{ t('mediaCenter.supportedFormats', { size: '10MB' }) }}</div>
                        </template>

                        <template v-else>
                            <v-icon icon="mdi-check-circle" size="48" color="success" />
                            <div class="text-h6 mt-2">{{ t('mediaCenter.filesSelected', { count: uploadFiles.length }) }}</div>
                            <v-btn
                                variant="text"
                                size="small"
                                color="primary"
                                @click.stop="triggerFileInput"
                            >
                                {{ t('mediaCenter.addMoreFiles') }}
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
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="closeUploadDialog" :disabled="uploading">
                        {{ t('common.cancel') }}
                    </v-btn>
                    <v-btn
                        color="primary"
                        :disabled="uploadFiles.length === 0 || uploading"
                        :loading="uploading"
                        @click="executeUpload"
                    >
                        {{ t('mediaCenter.uploadCount', { count: uploadFiles.length }) }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Snackbar -->
        <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="3000">
            {{ snackbar.text }}
        </v-snackbar>
    </v-container>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { debounce } from 'lodash';
import MediaGrid from '@/components/admin/media/MediaGrid.vue';
import MediaList from '@/components/admin/media/MediaList.vue';
import MediaDetailsDialog from '@/components/admin/media/MediaDetailsDialog.vue';

const { t } = useI18n();

// Navigation State
const folders = ref([]);
const totalStats = ref({ count: 0, size_formatted: '0 B' });
const currentContext = ref(null);
const currentContextLabel = ref(null);
const currentCollection = ref(null);
const currentCollectionLabel = ref(null);
const currentModelId = ref(null);
const currentModelName = ref(null);

// Model Items (folders within a context)
const modelItems = ref([]);
const showModelItems = computed(() => {
    return currentContext.value && !currentModelId.value;
});

// Media State
const loading = ref(false);
const media = ref([]);
const viewMode = ref('grid');
const page = ref(1);
const search = ref('');
const selectedIds = ref([]);
const collectionFilter = ref(null);

const meta = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 24,
    total: 0,
    from: 0,
    to: 0,
});

// Dialog State
const detailsDialogOpen = ref(false);
const selectedMedia = ref(null);
const deleteConfirmDialog = ref(false);
const mediaToDelete = ref(null);
const bulkDeleteMode = ref(false);
const deleting = ref(false);

const snackbar = reactive({
    show: false,
    text: '',
    color: 'success',
});

// Upload State
const showUploadDialog = ref(false);
const uploadFiles = ref([]);
const uploading = ref(false);
const uploadProgress = ref(0);
const uploadCollection = ref('images');
const isDragging = ref(false);
const fileInputRef = ref(null);

const uploadCollectionOptions = computed(() => [
    { label: t('mediaCenter.imagesForPages'), value: 'images' },
    { label: t('mediaCenter.documents'), value: 'documents' },
]);

// Computed
const availableCollections = computed(() => {
    const collections = new Map();
    folders.value.forEach(context => {
        context.collections.forEach(col => {
            if (!collections.has(col.name)) {
                collections.set(col.name, {
                    value: col.name,
                    label: col.label,
                    count: col.count,
                });
            } else {
                // Sum counts across contexts
                const existing = collections.get(col.name);
                existing.count += col.count;
            }
        });
    });
    return Array.from(collections.values());
});

const breadcrumbs = computed(() => {
    const items = [
        {
            title: t('mediaCenter.allMedia'),
            disabled: !currentContext.value,
            onClick: () => navigateToRoot(),
        },
    ];

    if (currentContext.value) {
        items.push({
            title: currentContextLabel.value,
            disabled: !currentCollection.value && !currentModelId.value,
            onClick: () => navigateToContext({ model_type: currentContext.value, label: currentContextLabel.value }),
        });
    }

    if (currentCollection.value) {
        items.push({
            title: currentCollectionLabel.value,
            disabled: !currentModelId.value,
            onClick: () => {
                currentModelId.value = null;
                currentModelName.value = null;
                fetchModelItems();
            },
        });
    }

    if (currentModelId.value) {
        items.push({
            title: currentModelName.value,
            disabled: true,
        });
    }

    return items;
});

// Methods
const fetchFolders = async () => {
    try {
        const response = await axios.get('/api/admin/media/folders');
        folders.value = response.data.folders;
        totalStats.value = response.data.total;
    } catch (error) {
        console.error('Failed to fetch folders:', error);
        showSnackbar(t('mediaCenter.loadFolderError'), 'error');
    }
};

const fetchModelItems = async () => {
    if (!currentContext.value) return;

    loading.value = true;
    try {
        const params = {
            model_type: currentContext.value,
        };
        if (currentCollection.value) {
            params.collection = currentCollection.value;
        }

        const response = await axios.get('/api/admin/media/model-items', { params });
        modelItems.value = response.data.items;
    } catch (error) {
        console.error('Failed to fetch model items:', error);
        showSnackbar(t('mediaCenter.loadItemsError'), 'error');
    } finally {
        loading.value = false;
    }
};

const fetchMedia = async () => {
    loading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: 24,
            sort_by: 'created_at',
            sort_dir: 'desc',
        };

        if (currentContext.value) {
            params.context = currentContext.value;
        }
        if (currentCollection.value) {
            params.collection = currentCollection.value;
        } else if (!currentContext.value && collectionFilter.value) {
            // Apply collection filter when viewing "All Media"
            params.collection = collectionFilter.value;
        }
        if (currentModelId.value) {
            params.model_id = currentModelId.value;
        }
        if (search.value) {
            params.search = search.value;
        }

        const response = await axios.get('/api/admin/media', { params });
        media.value = response.data.data;
        Object.assign(meta, response.data.meta);
    } catch (error) {
        console.error('Failed to fetch media:', error);
        showSnackbar(t('mediaCenter.loadMediaError'), 'error');
    } finally {
        loading.value = false;
    }
};

// Navigation
const navigateToRoot = () => {
    currentContext.value = null;
    currentContextLabel.value = null;
    currentCollection.value = null;
    currentCollectionLabel.value = null;
    currentModelId.value = null;
    currentModelName.value = null;
    modelItems.value = [];
    page.value = 1;
    selectedIds.value = [];
    fetchMedia();
};

const navigateToContext = (context) => {
    currentContext.value = context.model_type;
    currentContextLabel.value = context.label;
    currentCollection.value = null;
    currentCollectionLabel.value = null;
    currentModelId.value = null;
    currentModelName.value = null;
    collectionFilter.value = null;
    page.value = 1;
    selectedIds.value = [];
    fetchModelItems();
};

const navigateToCollection = (context, collection) => {
    currentContext.value = context.model_type;
    currentContextLabel.value = context.label;
    currentCollection.value = collection.name;
    currentCollectionLabel.value = collection.label;
    currentModelId.value = null;
    currentModelName.value = null;
    page.value = 1;
    selectedIds.value = [];
    fetchModelItems();
};

const navigateToModel = (item) => {
    currentModelId.value = item.model_id;
    currentModelName.value = item.model_name;
    page.value = 1;
    selectedIds.value = [];
    fetchMedia();
};

// Search
const debouncedSearch = debounce(() => {
    page.value = 1;
    // Search works on All Media view or when viewing a specific model
    if (!currentContext.value || currentModelId.value) {
        fetchMedia();
    }
}, 300);

// Details
const openDetails = async (item) => {
    try {
        const response = await axios.get(`/api/admin/media/${item.id}`);
        selectedMedia.value = response.data.data;
        detailsDialogOpen.value = true;
    } catch (error) {
        console.error('Failed to fetch media details:', error);
        showSnackbar(t('mediaCenter.loadDetailsError'), 'error');
    }
};

// Delete operations
const confirmDelete = (item) => {
    mediaToDelete.value = item;
    bulkDeleteMode.value = false;
    deleteConfirmDialog.value = true;
};

const confirmBulkDelete = () => {
    bulkDeleteMode.value = true;
    deleteConfirmDialog.value = true;
};

const cancelDelete = () => {
    deleteConfirmDialog.value = false;
    mediaToDelete.value = null;
    bulkDeleteMode.value = false;
};

const executeDelete = async () => {
    deleting.value = true;
    try {
        if (bulkDeleteMode.value) {
            await axios.post('/api/admin/media/bulk-delete', { ids: selectedIds.value });
            showSnackbar(t('mediaCenter.itemsDeleted', { count: selectedIds.value.length }));
            selectedIds.value = [];
        } else {
            await axios.delete(`/api/admin/media/${mediaToDelete.value.id}`);
            showSnackbar(t('mediaCenter.deleteSuccess'));
        }

        deleteConfirmDialog.value = false;
        mediaToDelete.value = null;
        bulkDeleteMode.value = false;

        // Refresh data
        await Promise.all([fetchFolders(), currentModelId.value ? fetchMedia() : fetchModelItems()]);
    } catch (error) {
        console.error('Failed to delete media:', error);
        showSnackbar(t('mediaCenter.deleteError'), 'error');
    } finally {
        deleting.value = false;
    }
};

const handleDelete = async (item) => {
    deleting.value = true;
    try {
        await axios.delete(`/api/admin/media/${item.id}`);
        showSnackbar(t('mediaCenter.deleteSuccess'));
        detailsDialogOpen.value = false;
        await Promise.all([fetchFolders(), currentModelId.value ? fetchMedia() : fetchModelItems()]);
    } catch (error) {
        console.error('Failed to delete media:', error);
        showSnackbar(t('mediaCenter.deleteError'), 'error');
    } finally {
        deleting.value = false;
    }
};

// Upload Methods
const triggerFileInput = () => {
    fileInputRef.value?.click();
};

const onFileSelect = (event) => {
    const files = Array.from(event.target.files);
    addFiles(files);
    event.target.value = ''; // Reset input
};

const onDragEnter = () => {
    isDragging.value = true;
};

const onDragLeave = () => {
    isDragging.value = false;
};

const onDrop = (event) => {
    isDragging.value = false;
    const files = Array.from(event.dataTransfer.files);
    addFiles(files);
};

const addFiles = (files) => {
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'application/pdf'];
    const maxSize = 10 * 1024 * 1024; // 10MB

    files.forEach(file => {
        if (!validTypes.includes(file.type)) {
            showSnackbar(t('mediaCenter.invalidFileType', { name: file.name }), 'error');
            return;
        }
        if (file.size > maxSize) {
            showSnackbar(t('mediaCenter.fileTooLarge', { name: file.name, size: '10MB' }), 'error');
            return;
        }

        // Create preview for images
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

const closeUploadDialog = () => {
    if (uploading.value) return;
    showUploadDialog.value = false;
    uploadFiles.value = [];
    uploadProgress.value = 0;
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
        await axios.post('/api/admin/media/upload', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
            onUploadProgress: (progressEvent) => {
                uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
            },
        });

        showSnackbar(t('mediaCenter.filesUploaded', { count: uploadFiles.value.length }));
        closeUploadDialog();

        // Refresh data
        await fetchFolders();
        if (!currentContext.value) {
            await fetchMedia();
        }
    } catch (error) {
        console.error('Failed to upload files:', error);
        showSnackbar(error.response?.data?.message || t('mediaCenter.uploadError'), 'error');
    } finally {
        uploading.value = false;
    }
};

const showSnackbar = (text, color = 'success') => {
    snackbar.text = text;
    snackbar.color = color;
    snackbar.show = true;
};

// Watch for page changes
watch(page, () => {
    if (currentModelId.value) {
        fetchMedia();
    }
});

// Watch for collection filter changes (on All Media view)
watch(collectionFilter, () => {
    if (!currentContext.value) {
        page.value = 1;
        fetchMedia();
    }
});

// Initial load
onMounted(async () => {
    await fetchFolders();
    await fetchMedia();
});
</script>

<style scoped>
.media-center {
    height: calc(100vh - 64px);
    overflow: hidden;
}

.sidebar-col {
    border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    height: 100%;
    overflow: hidden;
}

.sidebar-card {
    display: flex;
    flex-direction: column;
}

.folder-list {
    flex: 1;
    overflow-y: auto;
}

.folder-item {
    cursor: pointer;
}

.folder-item.subfolder {
    min-height: 36px;
}

.content-col {
    height: 100%;
    overflow: hidden;
}

.content-wrapper {
    height: 100%;
    overflow-y: auto;
}

.ga-2 {
    gap: 8px;
}

.breadcrumb-link {
    cursor: pointer;
}

.breadcrumb-link:hover {
    text-decoration: underline;
}

.model-folder-card {
    cursor: pointer;
    transition: all 0.2s ease;
}

.model-folder-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.folder-preview {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 80px;
    background: rgba(var(--v-theme-surface-variant), 0.5);
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
