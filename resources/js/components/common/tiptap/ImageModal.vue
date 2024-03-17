<template>
    <div class="modal" v-if="show">
        <div class="modal-content">
            <h1>Add image</h1>
            <header class="tab-header">
                <button @click="tab = 1" :class="{ active: tab == 1 }">Link</button>
                <button @click="tab = 0" :class="{ active: tab == 0 }">Upload (Drag 'n' Drop)</button>
                <button @click="tab = 2" :class="{ active: tab == 2 }">Upload (Simple)</button>
            </header>

            <div v-if="tab === 1">
                <p>Here is a test image URL</p>
                <pre>https://i.imgur.com/0ogkTp7.jpg</pre>
                <label for="url">Image URL:</label>
                <input v-model="imageSrc" id="url" />
            </div>
            <div v-if="tab === 2">
                <label for="up">Really simple input upload:</label>
                <input type="file" @change="fileChange" id="up" />
            </div>
            <div v-if="tab === 0">
<!--                <vue-dropzone v-bind="dropzoneProps" @vdropzone-success="vfileUploaded"></vue-dropzone>-->
            </div>

            <footer class="modal-footer">
                <button @click="insertImage" class="success" :title="validImage ? '' : 'Image URL needs to be valid'" :disabled="!validImage">Add Image</button>
                <button @click="closeModal" class="danger">Close modal</button>
            </footer>
        </div>
    </div>
</template>

<script>
import { ref, computed } from 'vue';
// import { useDropzone } from 'vue3-dropzone';
import axios from 'axios';

export default {
    setup(props, { emit }) {
        const imageSrc = ref('');
        const show = ref(false);
        const tab = ref(1);

        const validImage = computed(() => {
            return (
                imageSrc.value.match(/unsplash/) !== null ||
                imageSrc.value.match(/\.(jpeg|jpg|gif|png)$/) != null
            );
        });

        const dropzoneProps = {
            url: '/api/upload-image',
            thumbnailWidth: 200,
            dictDefaultMessage: 'UPLOAD A FILE'
        };

        const showModal = (command) => {
            // Add the sent command
            // ... logic to handle command
            show.value = true;
        };

        const vfileUploaded = (file) => {
            // Logic after file upload
            imageSrc.value = 'https://source.unsplash.com/random/400x100';
        };

        const fileChange = (event) => {
            const file = event.target.files[0];
            let formData = new FormData();
            formData.append('file', file);

            axios.post('/api/upload-image', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }).then(response => {
                imageSrc.value = response.data.path;
            }).catch(error => {
                console.error('Upload failed:', error);
            });
        };

        const insertImage = () => {
            const data = {
                src: imageSrc.value
            };
            emit('onConfirm', data);
            closeModal();
        };

        const closeModal = () => {
            show.value = false;
            imageSrc.value = '';
            tab.value = 1;
        };

        // Export reactive properties and methods
        return {
            imageSrc,
            show,
            tab,
            validImage,
            showModal,
            vfileUploaded,
            fileChange,
            insertImage,
            closeModal,
            dropzoneProps
        };
    }
};
</script>


<style scoped>
.modal {
    display: flex;
    align-items: center;
    justify-content: center;
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    right: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 9999999;
}

.modal-content {
    width: 90%;
    background-color: #fff;
    padding: 30px;
    border-radius: 8px;
}

.modal-footer {
    margin-top: 10px;
}

label {
    display: block;
    margin: 0.25em 0;
}

button {
    font-family: inherit;
    font-size: 100%;
    padding: 0.5em 1em;
    color: white;
    text-shadow: 0 1px 1px rgba(0, 0, 0, 0.2);
    border: 1px solid #999;
    border: transparent;
    background-color: #e6e6e6;
    text-decoration: none;
    border-radius: 2px;
    cursor: pointer;
}

button.danger {
    background: rgb(202, 60, 60);
}

button.success {
    background: rgb(28, 184, 65);
}

button:disabled {
    opacity: 0.3;
}

button + button {
    margin-left: 10px;
}

.tab-header {
    display: flex;
    align-items: center;
    border-bottom: 1px solid #222;
}

.tab-header button {
    color: #222;
    background: none;
    border: 0;
    flex: 1;
    padding: 5px 10px;
    cursor: pointer;
}

.tab-header button.active {
    background-color: #222;
    color: #fff;
}
</style>
