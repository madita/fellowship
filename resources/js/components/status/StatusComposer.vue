<script setup>
import { ref, computed } from 'vue';
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

const postStatus = async () => {
    if (!content.value.trim()) return;

    posting.value = true;
    try {
        const response = await axios.post('/api/statuses', {
            content: content.value,
        });

        content.value = '';
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
    expanded.value = false;
};
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
                        placeholder="What's on your mind?"
                        variant="outlined"
                        rows="1"
                        hide-details
                        @focus="expanded = true"
                    />

                    <!-- Expanded State -->
                    <div v-else>
                        <v-textarea
                            v-model="content"
                            placeholder="What's on your mind?"
                            variant="outlined"
                            rows="3"
                            auto-grow
                            hide-details
                            class="mb-3"
                        />

                        <div class="d-flex align-center justify-end">
                            <!-- Action Buttons -->
                            <div>
                                <v-btn
                                    variant="text"
                                    class="mr-2"
                                    @click="cancel"
                                    :disabled="posting"
                                >
                                    Cancel
                                </v-btn>
                                <v-btn
                                    color="primary"
                                    @click="postStatus"
                                    :loading="posting"
                                    :disabled="!content.trim()"
                                >
                                    Post
                                </v-btn>
                            </div>
                        </div>

                        <!-- Media/Emoji Options (placeholder) -->
                        <div class="d-flex mt-3 pt-3" style="border-top: 1px solid rgba(0,0,0,0.08)">
                            <v-btn variant="text" size="small" prepend-icon="mdi-image-outline">
                                Photo
                            </v-btn>
                            <v-btn variant="text" size="small" prepend-icon="mdi-emoticon-outline" class="ml-2">
                                Feeling
                            </v-btn>
                            <v-btn variant="text" size="small" prepend-icon="mdi-map-marker-outline" class="ml-2">
                                Location
                            </v-btn>
                        </div>
                    </div>
                </div>
            </div>
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
</style>
