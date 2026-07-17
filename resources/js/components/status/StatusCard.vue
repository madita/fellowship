<script setup>
import { ref, computed, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import UserAvatar from '../common/UserAvatar.vue';
import TinyBox from '../gallery/TinyBox.vue';
import axios from 'axios';
import { useUserStore } from '@/store/userStore.js';

const MAX_IMAGES = 10;
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

const props = defineProps({
    status: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['updated', 'deleted']);

const { t } = useI18n();
const userStore = useUserStore();

const user = computed(() => userStore.user || { id: null });
const isMyStatus = computed(() => user.value.id === props.status.user_id);

const isLiked = ref(props.status.is_liked_by_me);
const likesCount = ref(props.status.likes_count);
const commentsCount = ref(props.status.comments_count);

const showComments = ref(false);
const comments = ref(props.status.comments || []);
const newComment = ref('');
const loadingComments = ref(false);
const addingComment = ref(false);

const feelingMap = {
    happy: '\u{1F60A}',
    excited: '\u{1F389}',
    loved: '\u{2764}\u{FE0F}',
    thoughtful: '\u{1F914}',
    sad: '\u{1F622}',
    angry: '\u{1F621}',
    surprised: '\u{1F62E}',
    grateful: '\u{1F64F}',
    tired: '\u{1F634}',
    amused: '\u{1F602}',
    proud: '\u{1F4AA}',
    relaxed: '\u{1F60C}',
};

const replyingTo = ref(null);

const showActions = ref(false);
const editMode = ref(false);
const editedContent = ref(props.status.content);
const saving = ref(false);

// Image editing state (only used while in edit mode)
const removedMediaIds = ref([]); // existing media marked for deletion
const newFiles = ref([]); // File objects to upload
const newPreviews = ref([]); // object URLs for the new files
const editFileInput = ref(null);

// Existing images not yet marked for removal
const editableMedia = computed(() =>
    (props.status.media || []).filter((m) => !removedMediaIds.value.includes(m.id))
);

// Total images that will exist after saving (kept ≤ MAX_IMAGES)
const editImageCount = computed(() => editableMedia.value.length + newFiles.value.length);

const removeExistingImage = (id) => {
    removedMediaIds.value.push(id);
};

const triggerEditFileInput = () => {
    editFileInput.value?.click();
};

const onEditFilesSelected = (event) => {
    for (const file of Array.from(event.target.files)) {
        if (editImageCount.value >= MAX_IMAGES) break;
        if (!file.type.startsWith('image/')) continue;
        if (file.size > MAX_FILE_SIZE) {
            alert(`"${file.name}" exceeds the 5MB size limit.`);
            continue;
        }
        newFiles.value.push(file);
        newPreviews.value.push(URL.createObjectURL(file));
    }
    event.target.value = '';
};

const removeNewImage = (index) => {
    URL.revokeObjectURL(newPreviews.value[index]);
    newFiles.value.splice(index, 1);
    newPreviews.value.splice(index, 1);
};

const resetImageEditing = () => {
    newPreviews.value.forEach((url) => URL.revokeObjectURL(url));
    removedMediaIds.value = [];
    newFiles.value = [];
    newPreviews.value = [];
};

// Display images from the always-eager-loaded `media` relation rather than the
// `media_urls` accessor, which comes back empty for feed-loaded posts.
const images = computed(() => props.status.media || []);

// Lightbox: index of the image currently open (null = closed)
const lightboxIndex = ref(null);

// Shape media into the objects TinyBox expects, carrying the author and post
// time so the lightbox caption reads meaningfully.
const galleryImages = computed(() =>
    images.value.map((media) => ({
        original_url: media.original_url,
        caption: '',
        uploader: props.status.user?.name,
        created_at: props.status.created_at,
    }))
);

const openLightbox = (index) => {
    lightboxIndex.value = index;
};

const toggleLike = async () => {
    const previousState = isLiked.value;
    const previousCount = likesCount.value;

    // Optimistic update
    isLiked.value = !isLiked.value;
    likesCount.value += isLiked.value ? 1 : -1;

    try {
        const response = await axios.post(`/api/statuses/${props.status.id}/like`);
        isLiked.value = response.data.liked;
        likesCount.value = response.data.likes_count;
    } catch (error) {
        // Revert on error
        isLiked.value = previousState;
        likesCount.value = previousCount;
        console.error('Failed to toggle like:', error);
    }
};

const toggleComments = async () => {
    showComments.value = !showComments.value;

    if (showComments.value && comments.value.length === 0) {
        await loadComments();
    }
};

const loadComments = async () => {
    loadingComments.value = true;
    try {
        const response = await axios.get(`/api/statuses/${props.status.id}`);
        comments.value = response.data.all_comments || [];
    } catch (error) {
        console.error('Failed to load comments:', error);
    } finally {
        loadingComments.value = false;
    }
};

const startReply = (comment) => {
    replyingTo.value = comment;
    newComment.value = '';
};

const cancelReply = () => {
    replyingTo.value = null;
    newComment.value = '';
};

const addComment = async () => {
    if (!newComment.value.trim()) return;

    addingComment.value = true;
    try {
        const payload = {
            content: newComment.value,
        };

        if (replyingTo.value) {
            payload.parent_id = replyingTo.value.id;
        }

        const response = await axios.post(`/api/statuses/${props.status.id}/comments`, payload);

        if (replyingTo.value) {
            // Add reply nested under the parent comment
            const parent = comments.value.find(c => c.id === replyingTo.value.id);
            if (parent) {
                if (!parent.replies) parent.replies = [];
                parent.replies.push(response.data);
            }
        } else {
            comments.value.push(response.data);
        }

        commentsCount.value++;
        newComment.value = '';
        replyingTo.value = null;
    } catch (error) {
        console.error('Failed to add comment:', error);
    } finally {
        addingComment.value = false;
    }
};

const deleteComment = async (commentId) => {
    try {
        await axios.delete(`/api/status-comments/${commentId}`);
        comments.value = comments.value.filter(c => c.id !== commentId);
        commentsCount.value--;
    } catch (error) {
        console.error('Failed to delete comment:', error);
    }
};

const editStatus = () => {
    editMode.value = true;
    editedContent.value = props.status.content;
    resetImageEditing();
};

const cancelEdit = () => {
    editMode.value = false;
    editedContent.value = props.status.content;
    resetImageEditing();
};

const saveEdit = async () => {
    if (!editedContent.value.trim()) return;

    saving.value = true;
    try {
        let response;

        if (newFiles.value.length > 0) {
            // Files require a multipart POST with method spoofing — PHP does not
            // parse uploads from a raw PATCH body.
            const formData = new FormData();
            formData.append('_method', 'PATCH');
            formData.append('content', editedContent.value);
            removedMediaIds.value.forEach((id) => formData.append('remove_media_ids[]', id));
            newFiles.value.forEach((file) => formData.append('images[]', file));

            response = await axios.post(`/api/statuses/${props.status.id}`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
        } else {
            response = await axios.patch(`/api/statuses/${props.status.id}`, {
                content: editedContent.value,
                remove_media_ids: removedMediaIds.value,
            });
        }

        resetImageEditing();
        editMode.value = false;
        emit('updated', response.data);
    } catch (error) {
        console.error('Failed to update status:', error);
    } finally {
        saving.value = false;
    }
};

const deleteStatus = async () => {
    if (!confirm('Are you sure you want to delete this status?')) return;

    try {
        await axios.delete(`/api/statuses/${props.status.id}`);
        emit('deleted', props.status.id);
    } catch (error) {
        console.error('Failed to delete status:', error);
    }
};

onBeforeUnmount(() => {
    newPreviews.value.forEach((url) => URL.revokeObjectURL(url));
});
</script>

<template>
    <v-card class="status-card mb-4" elevation="2" rounded="lg">
        <v-card-text class="pa-4">
            <!-- Header -->
            <div class="d-flex align-center mb-3">
                <UserAvatar :user="status.user" size="48" class="mr-3" />
                <div class="flex-grow-1">
                    <div class="d-flex align-center flex-wrap">
                        <span class="font-weight-medium text-subtitle-1">{{ status.user.name }}</span>
                        <template v-if="status.feeling && feelingMap[status.feeling]">
                            <span class="text-caption text-medium-emphasis ml-1">
                                {{ t('timeline.isFeeling') }} {{ feelingMap[status.feeling] }} {{ t('timeline.feelings.' + status.feeling) }}
                            </span>
                        </template>
                        <span class="text-caption text-medium-emphasis mx-2">&middot;</span>
                        <span class="text-caption text-medium-emphasis">{{ status.time_ago }}</span>
                    </div>
                    <div class="text-caption text-medium-emphasis" v-if="status.user.username">
                        @{{ status.user.username }}
                    </div>
                </div>

                <!-- Actions Menu -->
                <v-menu v-if="isMyStatus" v-model="showActions">
                    <template #activator="{ props: menuProps }">
                        <v-btn
                            icon="mdi-dots-horizontal"
                            variant="text"
                            size="small"
                            v-bind="menuProps"
                        />
                    </template>

                    <v-list density="compact">
                        <v-list-item @click="editStatus">
                            <template #prepend>
                                <v-icon size="small">mdi-pencil</v-icon>
                            </template>
                            <v-list-item-title>Edit</v-list-item-title>
                        </v-list-item>

                        <v-list-item @click="deleteStatus">
                            <template #prepend>
                                <v-icon size="small" color="error">mdi-delete</v-icon>
                            </template>
                            <v-list-item-title class="text-error">Delete</v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-menu>
            </div>

            <!-- Content -->
            <div v-if="!editMode" class="status-content mb-3">
                <p class="text-body-1" style="white-space: pre-line">{{ status.content }}</p>
            </div>

            <!-- Edit Mode -->
            <div v-else class="mb-3">
                <v-textarea
                    v-model="editedContent"
                    variant="outlined"
                    rows="3"
                    hide-details
                    class="mb-3"
                />

                <!-- Existing + newly added images -->
                <div v-if="editableMedia.length || newPreviews.length" class="edit-media-grid mb-3">
                    <div
                        v-for="media in editableMedia"
                        :key="`existing-${media.id}`"
                        class="edit-media-item"
                    >
                        <v-img :src="media.original_url" cover height="110" class="rounded" />
                        <v-btn
                            icon="mdi-close"
                            size="x-small"
                            color="error"
                            variant="elevated"
                            class="edit-media-remove"
                            @click="removeExistingImage(media.id)"
                        />
                    </div>
                    <div
                        v-for="(preview, index) in newPreviews"
                        :key="`new-${index}`"
                        class="edit-media-item"
                    >
                        <v-img :src="preview" cover height="110" class="rounded" />
                        <v-btn
                            icon="mdi-close"
                            size="x-small"
                            color="error"
                            variant="elevated"
                            class="edit-media-remove"
                            @click="removeNewImage(index)"
                        />
                    </div>
                </div>

                <div class="d-flex align-center">
                    <v-btn
                        variant="text"
                        size="small"
                        prepend-icon="mdi-image-plus-outline"
                        :disabled="editImageCount >= MAX_IMAGES"
                        @click="triggerEditFileInput"
                    >
                        Add photos
                        <span v-if="editImageCount > 0" class="ml-1 text-caption">
                            ({{ editImageCount }}/{{ MAX_IMAGES }})
                        </span>
                    </v-btn>
                    <v-spacer />
                    <v-btn
                        size="small"
                        variant="text"
                        class="mr-2"
                        @click="cancelEdit"
                        :disabled="saving"
                    >
                        Cancel
                    </v-btn>
                    <v-btn
                        size="small"
                        color="primary"
                        @click="saveEdit"
                        :loading="saving"
                        :disabled="!editedContent.trim()"
                    >
                        Save
                    </v-btn>
                </div>

                <!-- Hidden file input for adding images during edit -->
                <input
                    ref="editFileInput"
                    type="file"
                    multiple
                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                    style="display: none"
                    @change="onEditFilesSelected"
                />
            </div>

            <!-- Media (if exists) -->
            <div v-if="images.length > 0" class="status-media mb-3">
                <div class="media-grid" :class="`media-grid-${Math.min(images.length, 4)}`">
                    <v-img
                        v-for="(media, index) in images"
                        :key="media.id"
                        :src="media.original_url"
                        cover
                        class="rounded media-item"
                        @click="openLightbox(index)"
                    />
                </div>
            </div>

            <!-- Fullscreen gallery for this post's images.
                 Teleported to <body> so its fixed overlay isn't trapped by an
                 ancestor's stacking/transform context and overlapped by the next post. -->
            <Teleport to="body">
                <TinyBox
                    :index="lightboxIndex"
                    :images="galleryImages"
                    loop
                    no-thumbs
                    @change="(i) => (lightboxIndex = i)"
                />
            </Teleport>

            <v-divider class="my-3" />

            <!-- Stats -->
            <div class="d-flex align-center justify-space-between mb-3 text-caption text-medium-emphasis">
                <div>
                    <span v-if="likesCount > 0">{{ likesCount }} {{ likesCount === 1 ? 'like' : 'likes' }}</span>
                </div>
                <div>
                    <span v-if="commentsCount > 0" class="cursor-pointer" @click="toggleComments">
                        {{ commentsCount }} {{ commentsCount === 1 ? 'comment' : 'comments' }}
                    </span>
                </div>
            </div>

            <v-divider class="my-3" />

            <!-- Action Buttons -->
            <div class="d-flex justify-space-around">
                <v-btn
                    variant="text"
                    :color="isLiked ? 'pink' : 'default'"
                    @click="toggleLike"
                    class="flex-grow-1"
                >
                    <v-icon :start="true">{{ isLiked ? 'mdi-heart' : 'mdi-heart-outline' }}</v-icon>
                    Like
                </v-btn>

                <v-btn
                    variant="text"
                    @click="toggleComments"
                    class="flex-grow-1"
                >
                    <v-icon start>mdi-comment-outline</v-icon>
                    Comment
                </v-btn>

            </div>

            <!-- Comments Section -->
            <div v-if="showComments" class="comments-section mt-4">
                <v-divider class="mb-3" />

                <!-- Loading -->
                <div v-if="loadingComments" class="text-center py-4">
                    <v-progress-circular indeterminate color="primary" size="32" />
                </div>

                <!-- Comments List -->
                <div v-else>
                    <div v-for="comment in comments" :key="comment.id" class="comment-item mb-3">
                        <div class="d-flex">
                            <UserAvatar :user="comment.user" size="32" class="mr-2" />
                            <div class="flex-grow-1">
                                <v-card variant="flat" color="grey-lighten-4" class="pa-2" rounded="lg">
                                    <div class="d-flex align-center justify-space-between">
                                        <span class="font-weight-medium text-caption">{{ comment.user.name }}</span>
                                        <v-btn
                                            v-if="user.id === comment.user_id"
                                            icon="mdi-delete"
                                            size="x-small"
                                            variant="text"
                                            @click="deleteComment(comment.id)"
                                        />
                                    </div>
                                    <p class="text-body-2 mb-0" style="white-space: pre-line">{{ comment.content }}</p>
                                </v-card>
                                <div class="d-flex align-center mt-1 text-caption text-medium-emphasis ml-2">
                                    <span>{{ comment.time_ago }}</span>
                                    <span class="mx-2">&middot;</span>
                                    <span class="cursor-pointer">Like</span>
                                    <span class="mx-2">&middot;</span>
                                    <span class="cursor-pointer" @click="startReply(comment)">Reply</span>
                                </div>

                                <!-- Replies -->
                                <div v-if="comment.replies && comment.replies.length > 0" class="replies-list mt-2 ml-2">
                                    <div v-for="reply in comment.replies" :key="reply.id" class="reply-item mb-2">
                                        <div class="d-flex">
                                            <UserAvatar :user="reply.user" size="24" class="mr-2" />
                                            <div class="flex-grow-1">
                                                <v-card variant="flat" color="grey-lighten-4" class="pa-2" rounded="lg">
                                                    <div class="d-flex align-center justify-space-between">
                                                        <span class="font-weight-medium text-caption">{{ reply.user.name }}</span>
                                                        <v-btn
                                                            v-if="user.id === reply.user_id"
                                                            icon="mdi-delete"
                                                            size="x-small"
                                                            variant="text"
                                                            @click="deleteComment(reply.id)"
                                                        />
                                                    </div>
                                                    <p class="text-body-2 mb-0" style="white-space: pre-line">{{ reply.content }}</p>
                                                </v-card>
                                                <div class="d-flex align-center mt-1 text-caption text-medium-emphasis ml-2">
                                                    <span>{{ reply.time_ago }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- No Comments -->
                    <div v-if="comments.length === 0" class="text-center text-medium-emphasis py-4">
                        No comments yet. Be the first to comment!
                    </div>
                </div>

                <!-- Add Comment -->
                <div class="add-comment mt-3">
                    <!-- Reply indicator -->
                    <div v-if="replyingTo" class="d-flex align-center mb-2 text-caption text-medium-emphasis">
                        <v-icon size="14" class="mr-1">mdi-reply</v-icon>
                        Replying to <span class="font-weight-medium ml-1">{{ replyingTo.user.name }}</span>
                        <v-btn
                            icon="mdi-close"
                            size="x-small"
                            variant="text"
                            density="compact"
                            class="ml-1"
                            @click="cancelReply"
                        />
                    </div>

                    <div class="d-flex">
                        <UserAvatar :user="user" size="32" class="mr-2" />
                        <v-textarea
                            v-model="newComment"
                            :placeholder="replyingTo ? `Reply to ${replyingTo.user.name}...` : 'Write a comment...'"
                            variant="outlined"
                            density="compact"
                            rows="2"
                            hide-details
                            class="flex-grow-1"
                            @keydown.ctrl.enter="addComment"
                            @keydown.meta.enter="addComment"
                        />
                    </div>
                    <div class="d-flex justify-end mt-2">
                        <v-btn
                            v-if="replyingTo"
                            size="small"
                            variant="text"
                            class="mr-2"
                            @click="cancelReply"
                        >
                            Cancel
                        </v-btn>
                        <v-btn
                            size="small"
                            color="primary"
                            @click="addComment"
                            :loading="addingComment"
                            :disabled="!newComment.trim()"
                        >
                            {{ replyingTo ? 'Reply' : 'Post Comment' }}
                        </v-btn>
                    </div>
                </div>
            </div>
        </v-card-text>
    </v-card>
</template>

<style scoped>
.status-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.status-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

.status-content {
    font-size: 1rem;
    line-height: 1.6;
}

.cursor-pointer {
    cursor: pointer;
}

.cursor-pointer:hover {
    text-decoration: underline;
}

.comment-item {
    position: relative;
}

.replies-list {
    border-left: 2px solid rgba(var(--v-theme-on-surface), 0.12);
    padding-left: 12px;
}

.media-grid {
    display: grid;
    gap: 4px;
    border-radius: 12px;
    overflow: hidden;
}

.media-item {
    cursor: pointer;
    transition: opacity 0.15s ease;
}

.media-item:hover {
    opacity: 0.92;
}

.edit-media-grid {
    display: grid;
    gap: 8px;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
}

.edit-media-item {
    position: relative;
}

.edit-media-remove {
    position: absolute;
    top: 4px;
    right: 4px;
    z-index: 1;
}

.media-grid-1 {
    grid-template-columns: 1fr;
}

.media-grid-1 .media-item {
    max-height: 400px;
}

.media-grid-2 {
    grid-template-columns: 1fr 1fr;
}

.media-grid-2 .media-item {
    height: 250px;
}

.media-grid-3 {
    grid-template-columns: 1fr 1fr;
    grid-template-rows: 1fr 1fr;
}

.media-grid-3 .media-item:first-child {
    grid-row: 1 / 3;
    height: 100%;
}

.media-grid-3 .media-item {
    height: 150px;
}

.media-grid-4 {
    grid-template-columns: 1fr 1fr;
    grid-template-rows: 1fr 1fr;
}

.media-grid-4 .media-item {
    height: 180px;
}
</style>
