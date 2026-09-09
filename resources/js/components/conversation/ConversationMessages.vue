<template>
    <!-- Messages/Replies -->
    <v-card-text class="pa-0">
        <div v-if="messages?.length" class="messages-container">
            <div
                v-for="reply in messages"
                :key="reply.id"
                class="message-item"
            >
                <div class="d-flex ga-3 pa-4">
                    <user-avatar :user="reply.user"></user-avatar>

                    <div class="flex-grow-1">
                        <div class="d-flex align-center mb-2">
                            <v-chip
                                size="x-small"
                                variant="tonal"
                            >
                                {{ reply.created_at_human }}
                            </v-chip>
                        </div>

                        <v-card
                            class="message-bubble"
                            :color="reply.self_owned ? 'primary' : undefined"
                            :variant="reply.self_owned ? 'flat' : 'tonal'"
                        >
                            <v-card-text class="pa-3">
                                {{ reply.body }}
                            </v-card-text>
                        </v-card>
                    </div>
                </div>
            </div>
        </div>

        <empty-state
            v-else
            compact
            icon="mdi-message-outline"
            :title="$t('conversation.noMessagesYet')"
            :text="$t('conversation.noMessagesYetHint')"
        />
    </v-card-text>
</template>

<script>
import { computed } from "vue";
import UserAvatar from "@/components/common/UserAvatar.vue";
import EmptyState from "@/components/common/EmptyState.vue";

export default {
    name: "ConversationMessages",
    props: {
        id: {
            type: [String, Number],
            default: null
        },
        messages: {
            type: Array,
            default: () => []
        },
        loading: {
            type: Boolean,
            default: false
        }
    },
    components: {
        UserAvatar,
        EmptyState
    },
    setup(props) {
        console.log('[ConversationMessages] Received messages prop:', props.messages?.length, 'messages')

        return {
            messages: computed(() => props.messages || []),
            loading: computed(() => props.loading),
        };
    },
};
</script>

<style scoped>
@import './styles.css';
</style>
