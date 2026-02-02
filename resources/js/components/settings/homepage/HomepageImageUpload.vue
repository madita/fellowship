<template>
  <div>
    <div class="d-flex flex-column">
      <!-- Preview -->
      <v-img
        v-if="preview"
        :src="preview"
        max-height="200"
        max-width="300"
        class="mb-2 rounded"
        contain
      ></v-img>
      <div v-else class="image-placeholder mb-2">
        <v-icon size="48" color="grey-lighten-2">mdi-image-off</v-icon>
      </div>

      <!-- File Input -->
      <v-file-input
        v-model="file"
        :label="label"
        accept="image/*"
        prepend-icon="mdi-image"
        density="compact"
        variant="outlined"
        @change="handleSelect"
        show-size
        :hint="hint"
        :persistent-hint="!!hint"
        class="mb-2"
      ></v-file-input>

      <!-- Actions -->
      <div class="d-flex gap-2">
        <v-btn
          v-if="file"
          color="primary"
          size="small"
          :loading="uploading"
          @click="handleUpload"
        >
          Upload
        </v-btn>
        <v-btn
          v-if="modelValue"
          color="error"
          size="small"
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
  modelValue: {
    type: String,
    default: null
  },
  label: {
    type: String,
    default: 'Upload Image'
  },
  hint: {
    type: String,
    default: ''
  }
});

const emit = defineEmits(['update:modelValue']);

const file = ref(null);
const preview = ref(null);
const uploading = ref(false);

// Initialize preview from model value
watch(() => props.modelValue, (newVal) => {
  if (newVal && typeof newVal === 'string' && newVal.trim() !== '') {
    preview.value = newVal.startsWith('/storage/') ? newVal : `/storage/${newVal}`;
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
    return;
  }

  uploading.value = true;

  try {
    const formData = new FormData();
    formData.append('image', selectedFile);
    formData.append('collection', 'home'); // Upload to 'home' collection

    const response = await axios.post('/api/admin/homepage/upload-image', formData);

    const imagePath = response.data.path;
    console.log('Image uploaded successfully. Path:', imagePath);
    preview.value = `/storage/${imagePath}`;
    file.value = null;
    emit('update:modelValue', imagePath);
    console.log('Emitted image path to parent:', imagePath);
  } catch (error) {
    console.error('Failed to upload image:', error);
    alert('Failed to upload image. Please try again.');
  } finally {
    uploading.value = false;
  }
}

function handleDelete() {
  if (!confirm('Are you sure you want to delete this image?')) {
    return;
  }

  preview.value = null;
  file.value = null;
  emit('update:modelValue', null);
}
</script>

<style scoped>
.image-placeholder {
  width: 300px;
  height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.05);
  border-radius: 4px;
}
</style>
