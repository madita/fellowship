<template>
    <div class="d-flex align-start py-2" :class="{ 'comment-helpful': comment.is_helpful }">
        <user-avatar :user="comment.user" size="32" link-to-profile class="mr-3 mt-1" />

        <div class="flex-grow-1" style="min-width: 0;">
            <div class="d-flex align-center flex-wrap ga-2">
                <span class="text-body-2 font-weight-medium">
                    {{ comment.user?.username }}
                </span>
                <span class="text-caption text-medium-emphasis">
                    {{ comment.time_ago }}
                </span>
                <v-chip
                    v-if="comment.is_helpful"
                    color="success"
                    variant="tonal"
                    size="x-small"
                    prepend-icon="mdi-check-circle"
                >
                    {{ $t('comments.helpful') }}
                </v-chip>
            </div>

            <div class="comment-body text-body-2 mt-1" v-html="rendered"></div>

            <div class="d-flex align-center ga-1 mt-1">
                <v-btn
                    v-if="canReply"
                    variant="text"
                    size="x-small"
                    density="comfortable"
                    prepend-icon="mdi-reply"
                    @click="$emit('reply', comment)"
                >
                    {{ $t('comments.reply') }}
                </v-btn>
                <v-btn
                    v-if="canMarkHelpful"
                    variant="text"
                    size="x-small"
                    density="comfortable"
                    color="success"
                    :prepend-icon="comment.is_helpful ? 'mdi-close-circle' : 'mdi-check-circle'"
                    :loading="busyId === comment.id"
                    @click="$emit('toggle-helpful', comment)"
                >
                    {{ comment.is_helpful ? $t('comments.unmarkHelpful') : $t('comments.markHelpful') }}
                </v-btn>
                <v-btn
                    v-if="canRemove"
                    variant="text"
                    size="x-small"
                    density="comfortable"
                    color="error"
                    prepend-icon="mdi-delete-outline"
                    :loading="busyId === comment.id"
                    @click="$emit('remove', comment)"
                >
                    {{ $t('common.delete') }}
                </v-btn>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import UserAvatar from '@/components/common/UserAvatar.vue';
import { renderRichText } from '@/utils/richText.js';

const props = defineProps({
    comment: { type: Object, required: true },
    currentUser: { type: Object, default: null },
    canReply: { type: Boolean, default: false },
    // Whether this reader may say which comment answered the thing
    // being discussed. The server decides and sends it down with the
    // discussion, because a host's ownership rule is not the client's
    // to guess at.
    canMarkHelpful: { type: Boolean, default: false },
    busyId: { type: [Number, String], default: null },
});

defineEmits(['reply', 'remove', 'toggle-helpful']);

const rendered = computed(() => renderRichText(props.comment.content));

/**
 * The server allows an admin to delete anyone's comment, so the button
 * follows that rather than only showing for the author — the old timeline
 * UI hid it from admins who were in fact allowed.
 */
const canRemove = computed(() => {
    const user = props.currentUser;

    if (!user?.id) return false;

    return user.id === props.comment.user_id || !!user.isAdmin;
});
</script>

<style scoped>
.comment-body :deep(p:last-child) {
    margin-bottom: 0;
}

/* The answer stands out without a card of its own, which would break
   the indent the replies are read by */
.comment-helpful {
    border-left: 3px solid rgb(var(--v-theme-success));
    margin-left: -12px;
    padding-left: 9px;
}
</style>
