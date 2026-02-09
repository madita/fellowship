<template>
    <div>
        <div v-if="loading" class="d-flex justify-center pa-8">
            <v-progress-circular indeterminate color="primary" />
        </div>

        <div v-else-if="!media.length" class="text-center pa-8">
            <v-icon icon="mdi-image-off" size="64" color="grey" />
            <div class="text-h6 text-grey mt-4">{{ $t('mediaCenter.noMediaFound') }}</div>
            <div class="text-body-2 text-grey">
                {{ $t('mediaCenter.adjustFiltersHint') }}
            </div>
        </div>

        <v-table v-else>
            <thead>
                <tr>
                    <th style="width: 50px;">
                        <v-checkbox
                            :model-value="allSelected"
                            :indeterminate="someSelected && !allSelected"
                            hide-details
                            @update:model-value="toggleSelectAll"
                        />
                    </th>
                    <th style="width: 60px;"></th>
                    <th>{{ $t('mediaCenter.fileName') }}</th>
                    <th>{{ $t('mediaCenter.type') }}</th>
                    <th>{{ $t('mediaCenter.size') }}</th>
                    <th>{{ $t('mediaCenter.context') }}</th>
                    <th>{{ $t('mediaCenter.collectionName') }}</th>
                    <th>{{ $t('mediaCenter.date') }}</th>
                    <th style="width: 100px;">{{ $t('mediaCenter.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="item in media"
                    :key="item.id"
                    class="media-list-row"
                    :class="{ 'media-list-row--selected': isSelected(item.id) }"
                    @click="$emit('click', item)"
                >
                    <td @click.stop>
                        <v-checkbox
                            :model-value="isSelected(item.id)"
                            hide-details
                            @update:model-value="toggleSelection(item.id, $event)"
                        />
                    </td>
                    <td>
                        <v-avatar size="40" rounded="sm">
                            <v-img
                                v-if="isImage(item)"
                                :src="item.thumb_url || item.url"
                                :alt="item.file_name"
                            />
                            <v-icon v-else :icon="getFileIcon(item)" />
                        </v-avatar>
                    </td>
                    <td>
                        <div class="text-truncate" style="max-width: 200px;" :title="item.file_name">
                            {{ item.file_name }}
                        </div>
                    </td>
                    <td>
                        <v-chip size="x-small" variant="tonal">
                            {{ getFileType(item) }}
                        </v-chip>
                    </td>
                    <td>{{ item.size_formatted }}</td>
                    <td>
                        <div class="text-truncate" style="max-width: 120px;" :title="item.model_type_label">
                            {{ item.model_type_label }}
                        </div>
                    </td>
                    <td>
                        <v-chip size="x-small" color="secondary" variant="tonal">
                            {{ item.collection_name }}
                        </v-chip>
                    </td>
                    <td>{{ formatDate(item.created_at) }}</td>
                    <td @click.stop>
                        <v-btn
                            icon
                            size="small"
                            variant="text"
                            color="primary"
                            @click="$emit('click', item)"
                        >
                            <v-icon icon="mdi-eye" size="small" />
                        </v-btn>
                        <v-btn
                            icon
                            size="small"
                            variant="text"
                            color="error"
                            @click="$emit('delete', item)"
                        >
                            <v-icon icon="mdi-delete" size="small" />
                        </v-btn>
                    </td>
                </tr>
            </tbody>
        </v-table>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    media: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    selectedIds: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['click', 'delete', 'update:selectedIds']);

const allSelected = computed(() => {
    return props.media.length > 0 && props.selectedIds.length === props.media.length;
});

const someSelected = computed(() => {
    return props.selectedIds.length > 0;
});

const isSelected = (id) => {
    return props.selectedIds.includes(id);
};

const toggleSelection = (id, selected) => {
    let newSelection;
    if (selected) {
        newSelection = [...props.selectedIds, id];
    } else {
        newSelection = props.selectedIds.filter(i => i !== id);
    }
    emit('update:selectedIds', newSelection);
};

const toggleSelectAll = (selected) => {
    if (selected) {
        emit('update:selectedIds', props.media.map(m => m.id));
    } else {
        emit('update:selectedIds', []);
    }
};

const isImage = (item) => {
    return item.mime_type?.startsWith('image/');
};

const getFileIcon = (item) => {
    const mimeType = item.mime_type || '';

    if (mimeType.startsWith('video/')) return 'mdi-video';
    if (mimeType.startsWith('audio/')) return 'mdi-music';
    if (mimeType === 'application/pdf') return 'mdi-file-pdf-box';
    if (mimeType.includes('word') || mimeType.includes('document')) return 'mdi-file-word';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'mdi-file-excel';
    if (mimeType.includes('zip') || mimeType.includes('archive')) return 'mdi-folder-zip';

    return 'mdi-file';
};

const getFileType = (item) => {
    const mimeType = item.mime_type || '';
    const parts = mimeType.split('/');
    return parts[1]?.toUpperCase() || 'FILE';
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString();
};
</script>

<style scoped>
.media-list-row {
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.media-list-row:hover {
    background-color: rgba(var(--v-theme-primary), 0.05);
}

.media-list-row--selected {
    background-color: rgba(var(--v-theme-primary), 0.1);
}
</style>
