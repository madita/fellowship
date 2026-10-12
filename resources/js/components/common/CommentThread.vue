<template>
    <div class="comment-thread">
        <loading-state v-if="loading" />

        <template v-else>
            <empty-state
                v-if="!topics.length"
                icon="mdi-comment-outline"
                :title="$t('comments.empty')"
                :text="$t('comments.emptyHint')"
            />

            <div v-else>
                <!-- Each top-level comment is a topic, its replies indented
                     under it — the shape of a talk page -->
                <div v-for="topic in topics" :key="topic.id" class="comment-topic">
                    <comment-row
                        :comment="topic"
                        :current-user="currentUser"
                        :busy-id="busyId"
                        can-reply
                        @reply="startReply"
                        @remove="remove"
                    />

                    <div v-if="topic.replies?.length" class="comment-replies">
                        <comment-row
                            v-for="reply in topic.replies"
                            :key="reply.id"
                            :comment="reply"
                            :current-user="currentUser"
                            :busy-id="busyId"
                            @remove="remove"
                        />
                    </div>

                    <!-- The composer moves to whichever topic is being
                         answered, rather than one box per topic -->
                    <div v-if="replyingTo === topic.id" class="comment-replies pt-2">
                        <div class="d-flex align-center mb-2 text-caption text-medium-emphasis">
                            <v-icon size="14" class="mr-1">mdi-reply</v-icon>
                            {{ $t('comments.replyingTo', { name: topic.user?.username }) }}
                            <v-btn
                                variant="text"
                                size="x-small"
                                density="comfortable"
                                class="ms-1"
                                @click="replyingTo = null"
                            >
                                {{ $t('common.cancel') }}
                            </v-btn>
                        </div>
                        <simple-editor
                            v-model="replyBody"
                            :limit="2000"
                            :placeholder="$t('comments.writeReply')"
                        />
                        <v-btn
                            color="primary"
                            variant="flat"
                            size="small"
                            class="mt-2"
                            :loading="posting"
                            @click="submit(topic.id)"
                        >
                            {{ $t('comments.post') }}
                        </v-btn>
                    </div>
                </div>
            </div>

            <!-- Starting a new topic -->
            <v-divider class="my-4" />

            <div v-if="currentUser?.id">
                <div class="text-subtitle-2 font-weight-medium mb-2">
                    {{ $t('comments.newTopic') }}
                </div>
                <simple-editor
                    v-model="body"
                    :limit="2000"
                    :placeholder="$t('comments.writeComment')"
                />
                <v-btn
                    color="primary"
                    variant="flat"
                    class="mt-2"
                    :loading="posting"
                    :disabled="!hasText(body)"
                    @click="submit(null)"
                >
                    {{ $t('comments.post') }}
                </v-btn>
            </div>
            <div v-else class="text-caption text-medium-emphasis">
                {{ $t('comments.loginHint') }}
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import LoadingState from '@/components/common/LoadingState.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import SimpleEditor from '@/components/common/tiptap/SimpleEditor.vue';
import CommentRow from '@/components/common/CommentRow.vue';
import { useDialog } from '@/composables/useDialog.js';
import { hasRichText } from '@/utils/richText.js';

/**
 * A discussion on anything the server calls commentable.
 *
 * `kind` is the registry slug the API expects ("wiki", "status"), never a
 * class name.
 */
const props = defineProps({
    kind: { type: String, required: true },
    id: { type: [Number, String], required: true },
    currentUser: { type: Object, default: null },
});

const emit = defineEmits(['count']);

const { t } = useI18n();
const dialog = useDialog();

const loading = ref(true);
const posting = ref(false);
const topics = ref([]);
const body = ref('');
const replyBody = ref('');
const replyingTo = ref(null);
const busyId = ref(null);

const hasText = value => hasRichText(value);

const total = computed(() => topics.value.reduce(
    (sum, topic) => sum + 1 + (topic.replies?.length ?? 0),
    0
));

const load = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(`/api/comments/${props.kind}/${props.id}`);

        topics.value = data.data ?? [];
        emit('count', data.count ?? total.value);
    } catch (error) {
        await dialog.requestError(error, t('comments.loadFailed'));
    } finally {
        loading.value = false;
    }
};

const startReply = (comment) => {
    replyingTo.value = comment.id;
    replyBody.value = '';
};

const submit = async (parentId) => {
    const content = parentId ? replyBody.value : body.value;

    if (posting.value || !hasText(content)) return;

    posting.value = true;
    try {
        const { data } = await axios.post(`/api/comments/${props.kind}/${props.id}`, {
            content,
            parent_id: parentId,
        });

        if (parentId) {
            const topic = topics.value.find(item => item.id === parentId);
            if (topic) (topic.replies ??= []).push(data);
            replyingTo.value = null;
            replyBody.value = '';
        } else {
            topics.value.push({ ...data, replies: [] });
            body.value = '';
        }

        emit('count', total.value);
    } catch (error) {
        await dialog.requestError(error, t('comments.postFailed'));
    } finally {
        posting.value = false;
    }
};

const remove = async (comment) => {
    const ok = await dialog.confirmDelete(t('comments.deleteConfirm'), {
        title: t('comments.deleteTitle'),
    });

    if (!ok) return;

    busyId.value = comment.id;
    try {
        await axios.delete(`/api/comments/${comment.id}`);

        // A reply is removed from its topic; a topic takes its replies with it
        if (comment.parent_id) {
            const topic = topics.value.find(item => item.id === comment.parent_id);
            if (topic) topic.replies = topic.replies.filter(item => item.id !== comment.id);
        } else {
            topics.value = topics.value.filter(item => item.id !== comment.id);
        }

        emit('count', total.value);
    } catch (error) {
        await dialog.requestError(error, t('comments.deleteFailed'));
    } finally {
        busyId.value = null;
    }
};

watch(() => [props.kind, props.id], load);
onMounted(load);
</script>

<style scoped>
.comment-topic + .comment-topic {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

/* Replies sit under the comment they answer, as on a talk page */
.comment-replies {
    margin-left: 28px;
    padding-left: 12px;
    border-left: 2px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
