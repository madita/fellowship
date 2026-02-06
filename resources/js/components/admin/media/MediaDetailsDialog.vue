<template>
    <v-dialog
        v-model="internalModelValue"
        max-width="900"
        scrollable
    >
        <v-card v-if="media">
            <v-card-title class="d-flex align-center">
                <span class="text-truncate" style="max-width: 80%;">{{ media.file_name }}</span>
                <v-spacer />
                <v-btn icon variant="text" @click="close">
                    <v-icon icon="mdi-close" />
                </v-btn>
            </v-card-title>

            <v-divider />

            <v-card-text class="pa-0">
                <v-row no-gutters>
                    <!-- Preview Column -->
                    <v-col cols="12" md="7" class="preview-column">
                        <div class="preview-container d-flex align-center justify-center">
                            <v-img
                                v-if="isImage"
                                :src="media.url"
                                :alt="media.file_name"
                                max-height="500"
                                contain
                            >
                                <template #placeholder>
                                    <v-row class="fill-height ma-0" align="center" justify="center">
                                        <v-progress-circular indeterminate color="grey-lighten-1" />
                                    </v-row>
                                </template>
                            </v-img>
                            <div v-else class="d-flex flex-column align-center">
                                <v-icon :icon="fileIcon" size="128" color="grey" />
                                <div class="text-h6 text-grey mt-4">{{ fileType }}</div>
                            </div>
                        </div>
                    </v-col>

                    <!-- Details Column -->
                    <v-col cols="12" md="5">
                        <v-list lines="two" class="pa-4">
                            <v-list-subheader>File Information</v-list-subheader>

                            <v-list-item>
                                <template #prepend>
                                    <v-icon icon="mdi-file" />
                                </template>
                                <v-list-item-title>File Name</v-list-item-title>
                                <v-list-item-subtitle>{{ media.file_name }}</v-list-item-subtitle>
                            </v-list-item>

                            <v-list-item>
                                <template #prepend>
                                    <v-icon icon="mdi-file-document-outline" />
                                </template>
                                <v-list-item-title>MIME Type</v-list-item-title>
                                <v-list-item-subtitle>{{ media.mime_type }}</v-list-item-subtitle>
                            </v-list-item>

                            <v-list-item>
                                <template #prepend>
                                    <v-icon icon="mdi-database" />
                                </template>
                                <v-list-item-title>Size</v-list-item-title>
                                <v-list-item-subtitle>{{ media.size_formatted }}</v-list-item-subtitle>
                            </v-list-item>

                            <v-list-item v-if="media.width && media.height">
                                <template #prepend>
                                    <v-icon icon="mdi-aspect-ratio" />
                                </template>
                                <v-list-item-title>Dimensions</v-list-item-title>
                                <v-list-item-subtitle>{{ media.width }} x {{ media.height }} px</v-list-item-subtitle>
                            </v-list-item>

                            <v-list-item>
                                <template #prepend>
                                    <v-icon icon="mdi-calendar" />
                                </template>
                                <v-list-item-title>Upload Date</v-list-item-title>
                                <v-list-item-subtitle>{{ formatDate(media.created_at) }}</v-list-item-subtitle>
                            </v-list-item>

                            <v-divider class="my-2" />
                            <v-list-subheader>Associated Model</v-list-subheader>

                            <v-list-item>
                                <template #prepend>
                                    <v-icon icon="mdi-tag" />
                                </template>
                                <v-list-item-title>Context</v-list-item-title>
                                <v-list-item-subtitle>{{ media.model_type_label }}</v-list-item-subtitle>
                            </v-list-item>

                            <v-list-item>
                                <template #prepend>
                                    <v-icon icon="mdi-folder" />
                                </template>
                                <v-list-item-title>Collection</v-list-item-title>
                                <v-list-item-subtitle>{{ media.collection_name }}</v-list-item-subtitle>
                            </v-list-item>

                            <v-list-item v-if="media.model_name">
                                <template #prepend>
                                    <v-icon icon="mdi-link" />
                                </template>
                                <v-list-item-title>Associated With</v-list-item-title>
                                <v-list-item-subtitle>{{ media.model_name }}</v-list-item-subtitle>
                            </v-list-item>

                            <v-list-item>
                                <template #prepend>
                                    <v-icon icon="mdi-identifier" />
                                </template>
                                <v-list-item-title>UUID</v-list-item-title>
                                <v-list-item-subtitle class="text-truncate">{{ media.uuid }}</v-list-item-subtitle>
                            </v-list-item>

                            <template v-if="media.custom_properties && Object.keys(media.custom_properties).length">
                                <v-divider class="my-2" />
                                <v-list-subheader>Custom Properties</v-list-subheader>
                                <v-list-item
                                    v-for="(value, key) in media.custom_properties"
                                    :key="key"
                                >
                                    <template #prepend>
                                        <v-icon icon="mdi-tune" />
                                    </template>
                                    <v-list-item-title>{{ formatPropertyKey(key) }}</v-list-item-title>
                                    <v-list-item-subtitle>{{ formatPropertyValue(value) }}</v-list-item-subtitle>
                                </v-list-item>
                            </template>
                        </v-list>
                    </v-col>
                </v-row>
            </v-card-text>

            <v-divider />

            <v-card-actions>
                <v-btn
                    color="primary"
                    variant="tonal"
                    :href="media.url"
                    target="_blank"
                    download
                >
                    <v-icon icon="mdi-download" start />
                    Download
                </v-btn>

                <v-btn
                    color="primary"
                    variant="tonal"
                    @click="copyUrl"
                >
                    <v-icon icon="mdi-content-copy" start />
                    Copy URL
                </v-btn>

                <v-spacer />

                <v-btn
                    color="error"
                    variant="tonal"
                    @click="confirmDelete"
                >
                    <v-icon icon="mdi-delete" start />
                    Delete
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="deleteConfirmDialog" max-width="400">
        <v-card>
            <v-card-title>Delete Media</v-card-title>
            <v-card-text>
                Are you sure you want to delete this file? This action cannot be undone.
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn variant="text" @click="deleteConfirmDialog = false">Cancel</v-btn>
                <v-btn color="error" @click="deleteMedia">Delete</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    media: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue', 'delete']);

const deleteConfirmDialog = ref(false);

const internalModelValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const isImage = computed(() => {
    return props.media?.mime_type?.startsWith('image/');
});

const fileType = computed(() => {
    const mimeType = props.media?.mime_type || '';
    const parts = mimeType.split('/');
    return parts[1]?.toUpperCase() || 'FILE';
});

const fileIcon = computed(() => {
    const mimeType = props.media?.mime_type || '';

    if (mimeType.startsWith('video/')) return 'mdi-video';
    if (mimeType.startsWith('audio/')) return 'mdi-music';
    if (mimeType === 'application/pdf') return 'mdi-file-pdf-box';
    if (mimeType.includes('word') || mimeType.includes('document')) return 'mdi-file-word';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'mdi-file-excel';
    if (mimeType.includes('zip') || mimeType.includes('archive')) return 'mdi-folder-zip';

    return 'mdi-file';
});

const close = () => {
    internalModelValue.value = false;
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString();
};

const formatPropertyKey = (key) => {
    return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const formatPropertyValue = (value) => {
    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }
    if (typeof value === 'object') {
        return JSON.stringify(value);
    }
    return String(value);
};

const copyUrl = async () => {
    try {
        await navigator.clipboard.writeText(props.media.url);
        // You could emit an event to show a snackbar notification
    } catch (error) {
        console.error('Failed to copy URL:', error);
    }
};

const confirmDelete = () => {
    deleteConfirmDialog.value = true;
};

const deleteMedia = () => {
    emit('delete', props.media);
    deleteConfirmDialog.value = false;
    internalModelValue.value = false;
};
</script>

<style scoped>
.preview-column {
    background: rgb(var(--v-theme-surface-variant));
}

.preview-container {
    min-height: 300px;
    padding: 16px;
}
</style>
