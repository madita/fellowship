<template>
    <div>
        <div :class="previewClass" class="d-flex flex-column align-center mb-4">
            <!-- Preview container with fixed dimensions -->
            <div
                v-if="preview"
                :class="['image-preview-container', placeholderClass, 'mb-2', imageClass]"
            >
                <v-img
                    :src="preview"
                    :max-height="maxHeight"
                    :max-width="maxWidth"
                    height="100%"
                    width="100%"
                    contain
                ></v-img>
            </div>
            <div v-else :class="['image-preview-container', placeholderClass, 'mb-2']">
                <v-icon :size="placeholderIconSize" color="grey-lighten-2">mdi-image-off</v-icon>
            </div>

            <v-file-input
                v-model="file"
                :label="label"
                :accept="accept"
                :prepend-icon="icon"
                :density="density"
                variant="outlined"
                @change="handleSelect"
                show-size
                :hint="hint"
                :persistent-hint="!!hint"
            ></v-file-input>

            <div class="d-flex gap-2 mt-2">
                <v-btn
                    v-if="file"
                    color="primary"
                    :size="buttonSize"
                    :loading="uploading"
                    @click="handleUpload"
                >
                    Upload
                </v-btn>
                <v-btn
                    v-if="currentImage"
                    color="error"
                    :size="buttonSize"
                    variant="outlined"
                    @click="handleDelete"
                >
                    Delete
                </v-btn>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    imageKey: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        default: 'Upload Image',
    },
    currentImage: {
        type: String,
        default: null,
    },
    accept: {
        type: String,
        default: 'image/*',
    },
    icon: {
        type: String,
        default: 'mdi-image',
    },
    maxHeight: {
        type: [String, Number],
        default: 100,
    },
    maxWidth: {
        type: [String, Number],
        default: 150,
    },
    placeholderSize: {
        type: String,
        default: 'normal', // 'small', 'normal', or 'large'
    },
    density: {
        type: String,
        default: 'compact',
    },
    buttonSize: {
        type: String,
        default: 'small',
    },
    hint: {
        type: String,
        default: '',
    },
    imageClass: {
        type: String,
        default: '',
    },
    previewClass: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['uploaded', 'deleted', 'error']);

const file = ref(null);
const preview = ref(null);
const uploading = ref(false);

const placeholderClass = props.placeholderSize === 'small'
    ? 'image-placeholder-small'
    : props.placeholderSize === 'large'
    ? 'image-placeholder-large'
    : 'image-placeholder';

const placeholderIconSize = props.placeholderSize === 'small' ? 32 : props.placeholderSize === 'large' ? 64 : 48;

// Initialize preview from current image
watch(() => props.currentImage, (newVal) => {
    if (newVal) {
        preview.value = `/storage/${newVal}`;
    } else {
        preview.value = null;
    }
}, { immediate: true });

function handleSelect() {
    const selectedFile = Array.isArray(file.value) ? file.value[0] : file.value;
    if (selectedFile) {
        const reader = new FileReader();
        reader.onload = (e) => {
            preview.value = e.target.result;
        };
        reader.readAsDataURL(selectedFile);
    }
}

async function handleUpload() {
    const selectedFile = Array.isArray(file.value) ? file.value[0] : file.value;
    if (!selectedFile) {
        emit('error', `Please select a ${props.label.toLowerCase()}`);
        return;
    }

    uploading.value = true;

    try {
        const formData = new FormData();
        formData.append('image', selectedFile);
        formData.append('key', props.imageKey);

        const response = await axios.post('/api/admin/settings/image', formData);

        preview.value = response.data.url;
        file.value = null;
        emit('uploaded', { key: props.imageKey, path: response.data.path, url: response.data.url });
    } catch (error) {
        console.error(`Failed to upload ${props.imageKey}:`, error);
        emit('error', `Failed to upload ${props.imageKey}`);
    } finally {
        uploading.value = false;
    }
}

async function handleDelete() {
    if (!confirm(`Are you sure you want to delete the ${props.label.toLowerCase()}?`)) {
        return;
    }

    try {
        await axios.delete('/api/admin/settings/image', {
            data: { key: props.imageKey }
        });
        preview.value = null;
        emit('deleted', props.imageKey);
    } catch (error) {
        console.error(`Failed to delete ${props.imageKey}:`, error);
        emit('error', `Failed to delete ${props.imageKey}`);
    }
}
</script>

<style scoped>
.image-preview-container {
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    background-color: #fafafa;
}

.image-placeholder {
    width: 100%;
    max-width: 300px;
    height: 200px;
    border-style: dashed;
}

.image-placeholder-small {
    width: 120px;
    height: 120px;
    border-style: dashed;
}

.image-placeholder-large {
    width: 100%;
    max-width: 600px;
    height: 300px;
    min-height: 250px;
    border-style: dashed;
}

.gap-2 {
    gap: 8px;
}

@media (max-width: 600px) {
    .image-placeholder-large {
        height: 200px;
        min-height: 180px;
    }

    .image-placeholder {
        height: 150px;
    }
}
</style>
