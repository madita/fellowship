<template>

            <!-- Messages/Replies -->


                <v-card-text class="pa-0">

                    <div v-if="messages?.length" class="messages-container">
                        <div
                            v-for="reply in messages"
                            :key="reply.id"
                            class="message-item"
                        >
                            <div class="d-flex pa-4">
                                <user-avatar :user="reply.user"></user-avatar>

                                <div class="flex-grow-1">
                                    <div class="d-flex align-center mb-2">
                    <span class="text-subtitle-1 font-weight-medium mr-2">

                    </span>
                                        <v-chip
                                            size="x-small"
                                            color="grey"
                                            variant="outlined"
                                            class="text-caption"
                                        >
                                            {{ reply.created_at_human }}
                                        </v-chip>
                                    </div>

                                    <v-card
                                        class="message-bubble"
                                        :color="reply.self_owned ? 'primary' : 'grey-lighten-4'"
                                        elevation="1"
                                    >
                                        <v-card-text class="pa-3">
                                            {{ reply.body }}
                                        </v-card-text>
                                    </v-card>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-8">
                        <v-icon size="64" color="grey-lighten-2">mdi-message-outline</v-icon>
                        <p class="text-body-1 text-medium-emphasis mt-2">
                            No messages yet. Be the first to reply!
                        </p>
                    </div>
                </v-card-text>


</template>

<script>
import { useConversationStore } from "@/store/conversationStore";
import { computed } from "vue";
import UserAvatar from "@/components/common/UserAvatar.vue";

export default {
    name: "ConversationMessages",
    props: {
        id: {
            type: [String, Number],
            default: null
        }
    },
    components: {
        UserAvatar
    },
    setup() {
        const conversationStore = useConversationStore();
        const messages = computed(() => {
            const msgs = conversationStore.messages || [];
            // console.log('[ConversationMessages] Computed messages:', msgs.length, 'messages');
            return msgs;
        });
        const loading = computed(() => conversationStore.loadingConversation);

        // Note: Scrolling is now handled by parent ConversationBox component

        return {
            messages,
            loading,
        };
    },
};
</script>

<style scoped>
@import './styles.css';
</style>
