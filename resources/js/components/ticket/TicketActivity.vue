<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import UserAvatar from '@/components/common/UserAvatar.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import { useDialog } from '@/composables/useDialog.js';
import { formatDate, formatDateDistanceToNow } from '@/plugins/formatDate.js';

/**
 * Comments of a ticket, oldest first, with the composer below. Admins can
 * write internal notes, which only admins see.
 */
const props = defineProps({
    ticketId: { type: Number, required: true },
    comments: { type: Array, default: () => [] },
    canComment: { type: Boolean, default: false },
    isAdmin: { type: Boolean, default: false },
    currentUserId: { type: Number, default: null },
});

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

const post = async () => {
    if (posting.value || !newComment.value.trim()) return;
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
        <h2 class="text-h6 mb-3">{{ t('tickets.detail.activity', { count: items.length }) }}</h2>

        <v-card rounded="lg" variant="outlined">
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
                        <div class="plain-text text-body-2">{{ comment.comment }}</div>
                    </v-list-item>
                </template>
            </v-list>
            <empty-state v-else compact icon="mdi-comment-outline" :title="t('tickets.noComments')" />

            <template v-if="canComment">
                <v-divider />
                <v-card-text>
                    <v-textarea
                        v-model="newComment"
                        :label="t('tickets.detail.addComment')"
                        rows="3"
                        auto-grow
                        hide-details
                        :disabled="posting"
                        :color="isInternal ? 'warning' : undefined"
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
                            :disabled="!newComment.trim()"
                            @click="post"
                        >
                            {{ isInternal ? t('tickets.detail.addNote') : t('tickets.comment') }}
                        </v-btn>
                    </div>
                </v-card-text>
            </template>
        </v-card>
    </div>
</template>

<style scoped>
.plain-text {
    white-space: pre-line;
    overflow-wrap: anywhere;
}

.internal-comment {
    background-color: rgba(var(--v-theme-warning), 0.08);
    box-shadow: inset 3px 0 0 rgb(var(--v-theme-warning));
}

.official-comment {
    background-color: rgba(var(--v-theme-primary), 0.05);
}
</style>
