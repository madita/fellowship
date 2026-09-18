<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import UserAvatar from '@/components/common/UserAvatar.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import SimpleEditor from '@/components/common/tiptap/SimpleEditor.vue';
import { renderRichText, hasRichText } from '@/utils/richText.js';
import { useDialog } from '@/composables/useDialog.js';
import { formatDate, formatDateDistanceToNow } from '@/plugins/formatDate.js';

/**
 * Comments of a ticket, oldest first, with the composer below (the Comments
 * tab of the ticket page). Admins can write internal notes, which only admins see.
 */
const props = defineProps({
    ticketId: { type: Number, required: true },
    comments: { type: Array, default: () => [] },
    canComment: { type: Boolean, default: false },
    isAdmin: { type: Boolean, default: false },
    currentUserId: { type: Number, default: null },
});

// The page shows the comment count on its tab
const emit = defineEmits(['count']);

const { t } = useI18n();
const dialog = useDialog();

const items = ref([]);
const newComment = ref('');
const isInternal = ref(false);
const posting = ref(false);
const deletingId = ref(null);

watch(() => props.comments, (comments) => {
    items.value = [...comments];
}, { immediate: true });

watch(() => items.value.length, (count) => emit('count', count));

const post = async () => {
    if (posting.value || !hasRichText(newComment.value)) return;
    posting.value = true;
    try {
        const response = await axios.post(`/api/tickets/${props.ticketId}/comments`, {
            comment: newComment.value,
            is_internal: isInternal.value,
        });
        items.value.push(response.data);
        newComment.value = '';
        isInternal.value = false;
    } catch (err) {
        console.error('Failed to add comment:', err);
        await dialog.requestError(err, t('tickets.messages.commentFailed'));
    } finally {
        posting.value = false;
    }
};

const remove = async (comment) => {
    if (deletingId.value) return;
    if (!(await dialog.confirmDelete(t('tickets.confirm.deleteCommentMessage')))) return;

    deletingId.value = comment.id;
    try {
        await axios.delete(`/api/ticket-comments/${comment.id}`);
        items.value = items.value.filter(c => c.id !== comment.id);
    } catch (err) {
        console.error('Failed to delete comment:', err);
        await dialog.requestError(err, t('tickets.messages.commentDeleteFailed'));
    } finally {
        deletingId.value = null;
    }
};
</script>

<template>
    <div>
        <v-list v-if="items.length" lines="three" class="py-0">
            <template v-for="(comment, index) in items" :key="comment.id">
                <v-divider v-if="index > 0" />
                <v-list-item class="py-3" :class="{ 'internal-comment': comment.is_internal, 'official-comment': comment.is_official && !comment.is_internal }">
                    <template #prepend>
                        <user-avatar :user="comment.user" class="mr-3" />
                    </template>
                    <div class="d-flex align-center flex-wrap ga-2 mb-1">
                        <strong>{{ comment.user?.username || comment.user?.name || t('tickets.unknown') }}</strong>
                        <v-chip v-if="comment.is_internal" size="x-small" variant="tonal" color="warning" prepend-icon="mdi-lock-outline">
                            {{ t('tickets.internal') }}
                        </v-chip>
                        <v-chip v-else-if="comment.is_official" size="x-small" color="primary" prepend-icon="mdi-shield-check">
                            {{ t('feedback.official') }}
                        </v-chip>
                        <span class="text-caption text-medium-emphasis" :title="formatDate(comment.created_at)">
                            {{ formatDateDistanceToNow(comment.created_at) }}
                        </span>
                        <v-spacer />
                        <v-btn
                            v-if="isAdmin || comment.user_id === currentUserId"
                            icon="mdi-delete-outline"
                            size="x-small"
                            variant="text"
                            :title="t('tickets.delete')"
                            :aria-label="t('tickets.delete')"
                            :loading="deletingId === comment.id"
                            :disabled="deletingId !== null && deletingId !== comment.id"
                            @click="remove(comment)"
                        />
                    </div>
                    <!-- Sanitized by renderRichText -->
                    <div class="rich-content comment-text text-body-2" v-html="renderRichText(comment.comment)" />
                </v-list-item>
            </template>
        </v-list>
        <empty-state v-else compact icon="mdi-comment-outline" :title="t('tickets.noComments')" />

        <template v-if="canComment">
            <v-divider />
            <v-card-text>
                <simple-editor
                    v-model="newComment"
                    :placeholder="isInternal ? t('tickets.detail.addNotePlaceholder') : t('tickets.detail.addComment')"
                    :limit="5000"
                    :disabled="posting"
                    min-height="88px"
                    :class="{ 'internal-editor': isInternal }"
                    @submit="post"
                />
                <div class="d-flex align-center flex-wrap ga-2 mt-2">
                    <v-switch
                        v-if="isAdmin"
                        v-model="isInternal"
                        color="warning"
                        density="compact"
                        hide-details
                        :disabled="posting"
                        :label="t('tickets.detail.internalNote')"
                    />
                    <v-spacer />
                    <v-btn
                        :color="isInternal ? 'warning' : 'primary'"
                        variant="flat"
                        :prepend-icon="isInternal ? 'mdi-lock-outline' : 'mdi-send'"
                        :loading="posting"
                        :disabled="!hasRichText(newComment)"
                        @click="post"
                    >
                        {{ isInternal ? t('tickets.detail.addNote') : t('tickets.comment') }}
                    </v-btn>
                </div>
            </v-card-text>
        </template>
    </div>
</template>

<style scoped>
.comment-text {
    overflow-wrap: anywhere;
}

.internal-editor {
    border-color: rgb(var(--v-theme-warning));
    background: rgba(var(--v-theme-warning), 0.04);
}

.internal-comment {
    background-color: rgba(var(--v-theme-warning), 0.08);
    box-shadow: inset 3px 0 0 rgb(var(--v-theme-warning));
}

.official-comment {
    background-color: rgba(var(--v-theme-primary), 0.05);
}
</style>
