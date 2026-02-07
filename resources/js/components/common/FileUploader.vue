<template>
    <v-container fluid class="pa-5">
        <!-- Global Progress Bar -->
        <v-progress-linear
            v-if="uploadInProgress"
            :value="globalProgress"
            height="5"
            color="blue"
            class="global-progress-bar"
        ></v-progress-linear>

        <!-- Upload Button -->
        <v-btn
            :disabled="validFiles.length === 0 || uploadInProgress"
            color="primary"
            @click="uploadFiles"
        >
            {{ t('commonComponents.fileUploader.uploadFiles', { count: validFiles.length }) }}
        </v-btn>
        <!-- Dropzone -->
        <div
            class="dropzone"
            @dragover.prevent="dragOver"
            @dragleave.prevent="dragLeave"
            @drop.prevent="dropFiles"
        >
            <input
                type="file"
                ref="fileInput"
                class="hidden"
                multiple
                @change="handleFilesSelected"
                :accept="accept"
            />

            <div class="dropzone-content">
                <v-icon size="48">mdi-cloud-upload</v-icon>
                <p>
                    {{ t('commonComponents.fileUploader.dragDropOr') }}
                    <span class="file-select" @click="triggerFileInput">{{ t('commonComponents.fileUploader.browse') }}</span>
                </p>
                <p v-if="files.length">{{ t('commonComponents.fileUploader.filesSelected', { count: files.length }) }}</p>
            </div>
        </div>

        <!-- File List with Individual Progress and Warnings -->
        <v-row v-if="files.length > 0">
            <v-col
                v-for="(file, index) in files"
                :key="file.name"
                cols="12"
                sm="6"
                md="3"
            >
                <v-card class="mx-auto file-card">
                    <v-hover>
                        <template #default="{ isHovering }">
                            <v-img
                                :src="file.preview"
                                alt="File preview"
                                class="file-preview"
                            >
                                <!-- Delete Icon -->
                                <v-btn
                                    icon
                                    class="delete-icon"
                                    @click="removeFile(index)"
                                >
                                    <v-icon color="red">mdi-delete</v-icon>
                                </v-btn>
                                <!-- File Name (only visible on hover) -->
                                <div v-show="isHovering" class="file-name-overlay">
                                    {{ file.name }}
                                </div>
                                <v-progress-linear
                                    v-if="file.uploadProgress > 0"
                                    :value="file.uploadProgress"
                                    height="4"
                                    color="blue"
                                    class="thumbnail-progress-bar"
                                ></v-progress-linear>
                            </v-img>
                        </template>
                    </v-hover>
                    <v-card-text>
                        <v-text-field
                            v-model="file.caption"
                            :label="t('commonComponents.fileUploader.addCaption')"
                            :placeholder="t('commonComponents.fileUploader.captionPlaceholder')"
                        />
                        <div v-if="file.warning" class="warning">
                            {{ file.warning }}
                        </div>

                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- Upload Button -->
        <v-btn
            :disabled="validFiles.length === 0 || uploadInProgress"
            color="primary"
            @click="uploadFiles"
        >
            {{ t('commonComponents.fileUploader.uploadFiles', { count: validFiles.length }) }}
        </v-btn>
    </v-container>
</template>

<script setup>
import { ref, computed } from "vue";
import { useI18n } from 'vue-i18n';
import axios from "axios";
import { useSettingsStore } from "@/store/settingStore";

const { t } = useI18n();

const props = defineProps({
    accept: {
        type: String,
        default: "image/*,application/pdf", // Adjust as needed
    },
    uploadUrl: {
        type: String,
        required: true,
    },
    collectionId: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(["upload-success", "upload-failure"]);

const files = ref([]);
const uploadInProgress = ref(false); // Indicates if an upload is in progress
const settingStore = useSettingsStore(); // Access global settings
const uploadProgress = ref(0); // Tracks upload progress
const isDragging = ref(false); // Dragging state

// Only valid files are included in the batch
const validFiles = computed(() =>
    files.value.filter((file) => !file.warning)
);

// Calculate global progress
const globalProgress = computed(() => {
    if (files.value.length === 0) return 0;

    const totalProgress = files.value.reduce(
        (acc, file) => acc + file.uploadProgress,
        0
    );

    return totalProgress / files.value.length;
});

// Add files (process and append with validation)
const addFiles = (newFiles) => {

    const processedFiles = Array.from(newFiles).map((file) => {
        const warning =
            file.size > settingStore.maxFileSize
                ? t('commonComponents.fileUploader.fileTooLarge', { size: (settingStore.maxFileSize / 1024 / 1024).toFixed(2) })
                : null;

        return {
            rawFile: file,
            name: file.name,
            size: file.size,
            type: file.type,
            caption: "",
            preview: isImage(file) ? window.URL.createObjectURL(file) : null,
            uploadProgress: 0,
            warning,
        };
    });

    files.value = [...files.value, ...processedFiles];
};

// Upload files based on batch or single upload setting
const uploadFiles = async () => {
    uploadInProgress.value = true;
    // console.log('batchUploadEnabled', batchUploadEnabled)
    if (settingStore.batchUpload) {
        console.log('batch')
        await uploadInBatches();
    } else {
        console.log('individual')
        await uploadIndividually();
    }

    emit("upload-success", files.value);
};


const uploadInBatches = async () => {
    // const validFiles = files.value.filter((file) => !file.warning);


    for (let i = 0; i < validFiles.value.length; i += settingStore.maxBatchSize) {
        const batch = validFiles.value.slice(i, i + settingStore.maxBatchSize);
        console.log('batch', batch)

        const formData = new FormData();
        batch.forEach((file) => {
            formData.append("files[]", file.rawFile);
            formData.append("captions[]", file.caption || "");
        });

        console.log('formData', formData)

        try {
            await axios.post(`${props.uploadUrl}/${props.collectionId}`, formData, {
                headers: { "Content-Type": "multipart/form-data" },
                onUploadProgress: (progressEvent) => {
                    if (progressEvent.total) {
                        const progress = (progressEvent.loaded / progressEvent.total) * 100;
                        batch.forEach((file) => (file.uploadProgress = progress));
                    }
                },
            });

            console.log(`Batch uploaded successfully.`);
        } catch (error) {
            console.error(`Failed to upload batch:`, error.response?.data || error.message);
        }
    }
    uploadInProgress.value = false;
};

// Upload files one at a time
const uploadIndividually = async () => {
    // const validFiles = files.value.filter((file) => !file.warning);

    for (const file of validFiles.value) {
        const formData = new FormData();
        formData.append("files", file.rawFile);
        formData.append("caption", file.caption || "");

        try {
            await axios.post(`${props.uploadUrl}/${props.collectionId}`, formData, {
                headers: { "Content-Type": "multipart/form-data" },
                onUploadProgress: (progressEvent) => {
                    if (progressEvent.total) {
                        file.uploadProgress = (progressEvent.loaded / progressEvent.total) * 100;
                    }
                },
            });

            console.log(`File ${file.name} uploaded successfully.`);
            file.uploadProgress = 100;
        } catch (error) {
            console.error(`Failed to upload file ${file.name}:`, error.response?.data || error.message);
            file.warning = t('commonComponents.fileUploader.uploadFailed');
        }
    }
    uploadInProgress.value = false;
};

// Drag-and-drop handlers
const dragOver = () => {
    isDragging.value = true;
};

const dragLeave = () => {
    isDragging.value = false;
};

const dropFiles = (event) => {
    addFiles(event.dataTransfer.files);
    isDragging.value = false;
};

const handleFilesSelected = (event) => {
    addFiles(event.target.files);
};

const isImage = (file) => file && file.type && file.type.startsWith("image/");

const removeFile = (index) => {
    URL.revokeObjectURL(files.value[index].preview);
    files.value.splice(index, 1);
};

const triggerFileInput = () => {
    fileInput.value.click();
};

const fileInput = ref(null);

</script>


<style scoped>
.hidden {
    display: none;
}
/* Dropzone styles */
.dropzone {
    border: 2px dashed #aaa;
    padding: 30px;
    text-align: center;
    border-radius: 10px;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.dropzone-content {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.file-select {
    color: #3f51b5;
    text-decoration: underline;
    cursor: pointer;
}

.file-select:hover {
    color: #1e88e5;
}

/* File Card Styles */
.file-card {
    max-width: 200px; /* Make the cards smaller */
    position: relative;
}

.file-preview {
    position: relative;
    height: 150px;
    object-fit: cover;
    border-radius: 8px;
    overflow: hidden;
}

.delete-icon {
    position: absolute;
    top: 8px;
    right: 8px;
    z-index: 10;
}

.file-name-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    background: rgba(0, 0, 0, 0.7);
    color: #fff;
    text-align: center;
    padding: 5px;
    font-size: 14px;
}

.thumbnail-progress-bar {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 5;
}

.global-progress-bar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
}

.warning {
    color: red;
    font-size: 0.9em;
    margin-top: 5px;
}

</style>
