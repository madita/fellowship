<script setup>
import { ref, computed, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import UserAvatar from '../common/UserAvatar.vue';
import axios from 'axios';
import { useUserStore } from '@/store/userStore.js';

const emit = defineEmits(['statusPosted']);

const { t } = useI18n();
const userStore = useUserStore();

const user = computed(() => userStore.user || { id: null });

const content = ref('');
const posting = ref(false);
const expanded = ref(false);

// Image upload
const fileInput = ref(null);
const selectedFiles = ref([]);
const imagePreviews = ref([]);
const MAX_IMAGES = 10;
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

// Feeling
const selectedFeeling = ref(null);
const showFeelingMenu = ref(false);

const feelingOptions = [
    { key: 'happy', emoji: '\u{1F60A}' },
    { key: 'excited', emoji: '\u{1F389}' },
    { key: 'loved', emoji: '\u{2764}\u{FE0F}' },
    { key: 'thoughtful', emoji: '\u{1F914}' },
    { key: 'sad', emoji: '\u{1F622}' },
    { key: 'angry', emoji: '\u{1F621}' },
    { key: 'surprised', emoji: '\u{1F62E}' },
    { key: 'grateful', emoji: '\u{1F64F}' },
    { key: 'tired', emoji: '\u{1F634}' },
    { key: 'amused', emoji: '\u{1F602}' },
    { key: 'proud', emoji: '\u{1F4AA}' },
    { key: 'relaxed', emoji: '\u{1F60C}' },
];

const canPost = computed(() => {
    return content.value.trim() || selectedFiles.value.length > 0;
});

const triggerFileInput = () => {
    fileInput.value?.click();
};

const onFilesSelected = (event) => {
    const files = Array.from(event.target.files);
    addFiles(files);
    // Reset input so the same file can be re-selected
    event.target.value = '';
};

const addFiles = (files) => {
    for (const file of files) {
        if (selectedFiles.value.length >= MAX_IMAGES) break;
        if (file.size > MAX_FILE_SIZE) {
            alert(`"${file.name}" exceeds the 5MB size limit.`);
            continue;
        }
        if (!file.type.startsWith('image/')) continue;

        selectedFiles.value.push(file);
        imagePreviews.value.push(URL.createObjectURL(file));
    }
};

const removeImage = (index) => {
    URL.revokeObjectURL(imagePreviews.value[index]);
    selectedFiles.value.splice(index, 1);
    imagePreviews.value.splice(index, 1);
};

const selectFeeling = (feeling) => {
    selectedFeeling.value = feeling;
    showFeelingMenu.value = false;
};

const removeFeeling = () => {
    selectedFeeling.value = null;
};

const postStatus = async () => {
    if (!canPost.value) return;

    posting.value = true;
    try {
        const formData = new FormData();
        formData.append('content', content.value);

        if (selectedFeeling.value) {
            formData.append('feeling', selectedFeeling.value.key);
        }

        selectedFiles.value.forEach((file) => {
            formData.append('images[]', file);
        });

        const response = await axios.post('/api/statuses', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        // Cleanup
        imagePreviews.value.forEach(url => URL.revokeObjectURL(url));
        content.value = '';
        selectedFiles.value = [];
        imagePreviews.value = [];
        selectedFeeling.value = null;
        expanded.value = false;

        emit('statusPosted', response.data);
    } catch (error) {
        console.error('Failed to post status:', error);
    } finally {
        posting.value = false;
    }
};

const cancel = () => {
    content.value = '';
    imagePreviews.value.forEach(url => URL.revokeObjectURL(url));
    selectedFiles.value = [];
    imagePreviews.value = [];
    selectedFeeling.value = null;
    expanded.value = false;
};

onBeforeUnmount(() => {
    imagePreviews.value.forEach(url => URL.revokeObjectURL(url));
});
</script>

<template>
    <v-card class="status-composer mb-4" elevation="2" rounded="lg">
        <v-card-text class="pa-4">
            <div class="d-flex">
                <UserAvatar :user="user" size="48" class="mr-3" />
                <div class="flex-grow-1">
                    <!-- Collapsed State -->
                    <v-textarea
                        v-if="!expanded"
                        v-model="content"
                        :placeholder="t('timeline.whatsOnYourMind')"
                        variant="outlined"
                        rows="1"
                        hide-details
                        @focus="expanded = true"
                    />

                    <!-- Expanded State -->
                    <div v-else>
                        <v-textarea
                            v-model="content"
                            :placeholder="t('timeline.whatsOnYourMind')"
                            variant="outlined"
                            rows="3"
                            auto-grow
                            hide-details
                            class="mb-3"
                        />

                        <!-- Selected Feeling Chip -->
                        <v-chip
                            v-if="selectedFeeling"
                            closable
                            size="small"
                            color="primary"
                            variant="tonal"
                            class="mb-3"
                            @click:close="removeFeeling"
                        >
                            {{ selectedFeeling.emoji }} {{ t('timeline.feeling') }} {{ t('timeline.feelings.' + selectedFeeling.key) }}
                        </v-chip>

                        <!-- Image Previews -->
                        <div v-if="imagePreviews.length > 0" class="image-previews mb-3">
                            <div class="preview-grid">
                                <div
                                    v-for="(preview, index) in imagePreviews"
                                    :key="index"
                                    class="preview-item"
                                >
                                    <v-img :src="preview" cover class="rounded" height="150" />
                                    <v-btn
                                        icon="mdi-close-circle"
                                        size="x-small"
                                        color="error"
                                        variant="elevated"
                                        class="preview-remove-btn"
                                        @click="removeImage(index)"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-center justify-end">
                            <div>
                                <v-btn
                                    variant="text"
                                    class="mr-2"
                                    @click="cancel"
                                    :disabled="posting"
                                >
                                    {{ t('timeline.cancel') }}
                                </v-btn>
                                <v-btn
                                    color="primary"
                                    @click="postStatus"
                                    :loading="posting"
                                    :disabled="!canPost"
                                >
                                    {{ t('timeline.post') }}
                                </v-btn>
                            </div>
                        </div>

                        <!-- Media/Feeling Options -->
                        <div class="d-flex mt-3 pt-3" style="border-top: 1px solid rgba(0,0,0,0.08)">
                            <v-btn
                                variant="text"
                                size="small"
                                prepend-icon="mdi-image-outline"
                                @click="triggerFileInput"
                                :disabled="selectedFiles.length >= MAX_IMAGES"
                            >
                                {{ t('timeline.photo') }}
                                <span v-if="selectedFiles.length > 0" class="ml-1 text-caption">
                                    ({{ selectedFiles.length }}/{{ MAX_IMAGES }})
                                </span>
                            </v-btn>

                            <v-menu v-model="showFeelingMenu" :close-on-content-click="false">
                                <template #activator="{ props: menuProps }">
                                    <v-btn
                                        variant="text"
                                        size="small"
                                        prepend-icon="mdi-emoticon-outline"
                                        class="ml-2"
                                        v-bind="menuProps"
                                    >
                                        {{ t('timeline.feeling') }}
                                    </v-btn>
                                </template>

                                <v-card min-width="280" max-width="320">
                                    <v-card-title class="text-subtitle-2 pb-1">{{ t('timeline.howAreYouFeeling') }}</v-card-title>
                                    <v-card-text class="pt-1">
                                        <div class="feeling-grid">
                                            <v-btn
                                                v-for="feeling in feelingOptions"
                                                :key="feeling.key"
                                                variant="text"
                                                size="small"
                                                class="feeling-btn"
                                                :color="selectedFeeling?.key === feeling.key ? 'primary' : undefined"
                                                @click="selectFeeling(feeling)"
                                            >
                                                <span class="feeling-emoji">{{ feeling.emoji }}</span>
                                                <span class="text-caption ml-1">{{ t('timeline.feelings.' + feeling.key) }}</span>
                                            </v-btn>
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-menu>

                            <v-btn variant="text" size="small" prepend-icon="mdi-map-marker-outline" class="ml-2">
                                {{ t('timeline.location') }}
                            </v-btn>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hidden file input -->
            <input
                ref="fileInput"
                type="file"
                multiple
                accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                style="display: none"
                @change="onFilesSelected"
            />
        </v-card-text>
    </v-card>
</template>

<style scoped>
.status-composer {
    transition: box-shadow 0.2s;
}

.status-composer:focus-within {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}

.preview-grid {
    display: grid;
    gap: 8px;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
}

.preview-item {
    position: relative;
}

.preview-remove-btn {
    position: absolute;
    top: 4px;
    right: 4px;
    z-index: 1;
}

.feeling-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px;
}

.feeling-btn {
    justify-content: flex-start;
    text-transform: none;
}

.feeling-emoji {
    font-size: 1.2em;
}
</style>
