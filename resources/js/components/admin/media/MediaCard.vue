<template>
    <v-card
        class="media-card"
        :class="{ 'media-card--selected': selected }"
        @click="$emit('click', media)"
    >
        <div class="media-card__image-container">
            <v-img
                v-if="isImage"
                :src="media.thumb_url || media.url"
                :alt="media.file_name"
                aspect-ratio="1"
                cover
                class="media-card__image"
            >
                <template #placeholder>
                    <v-row class="fill-height ma-0" align="center" justify="center">
                        <v-progress-circular indeterminate color="grey-lighten-1" />
                    </v-row>
                </template>
            </v-img>
            <div v-else class="media-card__file-icon d-flex align-center justify-center fill-height">
                <v-icon :icon="fileIcon" size="64" color="grey" />
            </div>

            <v-checkbox
                v-model="internalSelected"
                class="media-card__checkbox"
                color="primary"
                hide-details
                @click.stop
            />

            <v-btn
                icon
                size="small"
                class="media-card__delete-btn"
                color="error"
                variant="flat"
                @click.stop="$emit('delete', media)"
            >
                <v-icon icon="mdi-delete" size="small" />
            </v-btn>
        </div>

        <v-card-text class="pa-2">
            <div class="text-body-2 text-truncate" :title="media.file_name">
                {{ media.file_name }}
            </div>
            <div class="d-flex align-center justify-space-between mt-1">
                <v-chip size="x-small" color="primary" variant="tonal">
                    {{ media.size_formatted }}
                </v-chip>
                <v-chip size="x-small" color="secondary" variant="tonal">
                    {{ media.collection_name }}
                </v-chip>
            </div>
        </v-card-text>
    </v-card>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    media: {
        type: Object,
        required: true,
    },
    selected: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['click', 'delete', 'update:selected']);

const internalSelected = computed({
    get: () => props.selected,
    set: (value) => emit('update:selected', value),
});

const isImage = computed(() => {
    return props.media.mime_type?.startsWith('image/');
});

const fileIcon = computed(() => {
    const mimeType = props.media.mime_type || '';

    if (mimeType.startsWith('video/')) return 'mdi-video';
    if (mimeType.startsWith('audio/')) return 'mdi-music';
    if (mimeType === 'application/pdf') return 'mdi-file-pdf-box';
    if (mimeType.includes('word') || mimeType.includes('document')) return 'mdi-file-word';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'mdi-file-excel';
    if (mimeType.includes('zip') || mimeType.includes('archive')) return 'mdi-folder-zip';

    return 'mdi-file';
});
</script>

<style scoped>
.media-card {
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.media-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.media-card--selected {
    outline: 2px solid rgb(var(--v-theme-primary));
}

.media-card__image-container {
    position: relative;
    aspect-ratio: 1;
    background: rgb(var(--v-theme-surface-variant));
}

.media-card__image {
    background: rgb(var(--v-theme-surface-variant));
}

.media-card__file-icon {
    background: rgb(var(--v-theme-surface-variant));
}

.media-card__checkbox {
    position: absolute;
    top: 4px;
    left: 4px;
    z-index: 1;
}

.media-card__checkbox :deep(.v-selection-control) {
    min-height: auto;
}

.media-card__delete-btn {
    position: absolute;
    top: 4px;
    right: 4px;
    z-index: 1;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.media-card:hover .media-card__delete-btn {
    opacity: 1;
}
</style>
