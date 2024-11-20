<template>
    <v-container fluid class="pa-5">
        <v-btn class="mb-1" :disabled="files.length === 0" color="primary" @click="uploadFiles">
            Upload Files
        </v-btn>
        <!-- Hidden File Input -->
        <input
            type="file"
            ref="fileInput"
            class="hidden"
            multiple
            @change="handleFilesSelected"
            :accept="accept"
        />

        <!-- Dropzone -->
        <!-- Progress Bar -->
        <v-progress-linear
            v-if="uploadProgress > 0"
            :value="uploadProgress"
            height="10"
            color="blue"
            class="my-4"
        >
            {{ Math.round(uploadProgress) }}%
        </v-progress-linear>

        <div
            class="dropzone"
            @dragover.prevent="dragOver"
            @dragleave.prevent="dragLeave"
            @drop.prevent="dropFiles"
            :class="{ 'dropzone--dragging': isDragging }"
        >
            <div class="dropzone-content">
                <v-icon size="48">mdi-cloud-upload</v-icon>
                <p>
                    Drag & Drop files here, or
                    <span class="file-select" @click="triggerFileInput">browse</span>
                </p>
                <p v-if="files.length">{{ files.length }} file(s) selected</p>
            </div>
        </div>

        <!-- File Previews -->
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
                                    icon=""
                                    class="delete-icon"
                                    @click="removeFile(index)"
                                >
                                    <v-icon color="red">mdi-delete</v-icon>
                                </v-btn>
                                <!-- File Name (only visible on hover) -->
                                <div v-show="isHovering" class="file-name-overlay">
                                    {{ file.name }}
                                </div>
                            </v-img>
                        </template>
                    </v-hover>
                    <!-- Caption Input -->
                    <v-card-text>
                        <v-text-field
                            v-model="file.caption"
                            label="Add Caption"
                            placeholder="Enter caption"
                        />
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

    </v-container>
</template>

<script setup>
import { ref } from "vue";

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


const emit = defineEmits(["files-changed", "files-selected"]);

const files = ref([]); // File list
const uploadProgress = ref(0); // Tracks upload progress
const isDragging = ref(false); // Dragging state
const fileInput = ref(null); // File input reference



// Upload files to the Laravel endpoint
const uploadFiles = async () => {
    if (files.value.length === 0) {
        console.error('No files to upload.');
        return;
    }

    const formData = new FormData();

    files.value.forEach((file) => {
        console.log('file', file);
        formData.append('files[]', file.rawFile); // Use 'files[]' as the key
        formData.append('captions[]', file.caption || ''); // Use 'captions[]' for captions
    });


    try {
        const response = await axios.post(`${props.uploadUrl}/${props.collectionId}`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (progressEvent) => {
                uploadProgress.value = (progressEvent.loaded / progressEvent.total) * 100; // Calculate percentage
            },
        });

        console.log('Files uploaded successfully:', response.data);

        // Emit success event
        emit('upload-success', response.data);

        // Clear uploaded files
        files.value = [];
        uploadProgress.value = 0;
    } catch (error) {
        console.error('File upload failed:', error.response?.data || error.message);

        // Emit failure event
        emit('upload-failure', error.response?.data || error.message);
    }
};



const handleFilesSelected = (event) => {
    addFiles(event.target.files);
};

// Trigger the hidden file input
const triggerFileInput = () => {
    fileInput.value.click();
};

const addFiles = (newFiles) => {

    const processedFiles = Array.from(newFiles).map((file) => ({
        rawFile: file, // Keep the original File instance for backend uploads
        name: file.name, // File name for display
        size: file.size, // File size for display
        type: file.type, // File type for validation
        caption: '', // Optional caption field for user input
        preview: isImage(file) ? window.URL.createObjectURL(file) : null, // Generate preview for images
        showName: false, // Custom property to toggle filename visibility
    }));

    files.value = [...files.value, ...processedFiles];
};

// Drag over the dropzone
const dragOver = () => {
    isDragging.value = true;
};

// Drag leave the dropzone
const dragLeave = () => {
    isDragging.value = false;
};

// Handle file drop
const dropFiles = (event) => {
    // const droppedFiles = Array.from(event.dataTransfer.files);
    // const droppedFiles = Array.from(event.dataTransfer.files).map((file) => ({
    //     ...file,
    //     preview: isImage(file) ? window.URL.createObjectURL(file) : null,
    //     showName: false,
    // }));

    addFiles(event.dataTransfer.files);
    isDragging.value = false;
};

// Remove a file from the list
const removeFile = (index) => {
    if (files.value[index].preview) {
        window.URL.revokeObjectURL(files.value[index].preview); // Use `window.URL`
    }
    files.value.splice(index, 1);
    emit("files-selected", files.value);
};

// Check if a file is an image
const isImage = (file) => {
    return file && file.type && file.type.startsWith("image/");
};
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

</style>
