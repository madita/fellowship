<template>
    <div>
        <v-file-input
            v-model="files"
            multiple
            label="Upload Files"
            prepend-icon="mdi-upload"
            @change="onFileChange"
            accept="image/*,application/pdf"
            class="d-none"
            ref="fileInput"
        ></v-file-input>

        <!-- Dropzone for drag and drop -->
        <div
            class="dropzone"
            @dragover.prevent="onDragOver"
            @dragleave.prevent="onDragLeave"
            @drop.prevent="onDrop"
            :class="{ 'dropzone--hover': isDragging }"
        >
            <div class="dropzone-content">
                <v-icon size="48">mdi-cloud-upload</v-icon>
                <p>Drag & Drop files here, or <span class="file-select" @click="triggerFileSelect">browse</span></p>
                <p v-if="files.length">{{ files.length }} file(s) selected</p>
            </div>
        </div>

        <!-- Preview of selected files -->
        <v-row v-if="files.length > 0">
            <v-col v-for="(file, index) in files" :key="index" cols="12" sm="6" md="3">
                <v-card class="mx-auto" max-width="300">
                    <v-card-text>
                        <v-icon v-if="isImage(file)">mdi-file-image</v-icon>
                        <v-icon v-else>mdi-file</v-icon>
                        {{ file.name }} ({{ (file.size / 1024).toFixed(2) }} KB)
                    </v-card-text>
                    <v-card-actions>
                        <v-btn @click="removeFile(index)" color="red">
                            Remove
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-col>
        </v-row>
    </div>
</template>

<script>
export default {
    name: "FileUploader",
    data() {
        return {
            files: [], // Holds the selected files
            isDragging: false, // Indicates if files are being dragged over the dropzone
        };
    },
    methods: {
        onFileChange(files) {
            // Update the file list when files are selected via input
            this.files = files;
            this.$emit("files-selected", files);
        },

        onDragOver(event) {
            this.isDragging = true;
        },

        onDragLeave(event) {
            this.isDragging = false;
        },

        onDrop(event) {
            this.isDragging = false;

            // Handle the dropped files
            const droppedFiles = Array.from(event.dataTransfer.files);
            this.files = [...this.files, ...droppedFiles];
            this.$emit("files-selected", this.files);
        },

        triggerFileSelect() {
            // Programmatically trigger file input click
            this.$refs.fileInput.click();
        },

        removeFile(index) {
            this.files.splice(index, 1);
        },

        isImage(file) {
            return file.type.includes("image");
        },
    },
};
</script>

<style scoped>
/* Dropzone styles */
.dropzone {
    border: 2px dashed #9e9e9e;
    padding: 30px;
    text-align: center;
    cursor: pointer;
    border-radius: 10px;
    transition: background-color 0.2s ease;
}

.dropzone:hover {
    background-color: #f0f0f0;
}

.dropzone--hover {
    background-color: #e0e0e0;
    border-color: #3f51b5;
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
</style>
