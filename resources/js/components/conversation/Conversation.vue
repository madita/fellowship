<template>
    <div class="conversation-container">
        <!-- Loading State -->
        <div v-if="loading" class="text-center py-12">
            <v-progress-circular
                indeterminate
                color="primary"
                size="64"
            ></v-progress-circular>
            <p class="mt-4 text-body-1 text-medium-emphasis">Loading conversation...</p>
        </div>

        <!-- Conversation Content -->
        <v-container v-else-if="conversation" fluid class="pa-0">

            <!-- Conversation Header -->
            <v-card class="mb-4" elevation="2">
                <v-card-title class="bg-gradient text-white">
                    <v-icon class="mr-3">mdi-chat</v-icon>
                    Conversation Details
                </v-card-title>


            </v-card>

            <!-- Messages/Replies -->
            <v-card elevation="2">
                <v-card-title class="v-col-6 text-h6 font-weight-medium">
                    <v-icon class="mr-2">mdi-message-text</v-icon>
                    Messages
                    <v-spacer></v-spacer>
                    <v-chip
                        v-if="messages?.length"
                        size="small"
                        color="info"
                        variant="outlined"
                        class="mr-2"
                    >
                        {{ messages.length }} messages
                    </v-chip>

                    <!-- Add User Dropdown in title -->
                    <v-menu :close-on-content-click="false">
                        <template v-slot:activator="{ props }">
                            <v-btn
                                v-bind="props"
                                variant="outlined"
                                size="small"
                                prepend-icon="mdi-account-plus"
                            >
                                Add Participant
                            </v-btn>
                        </template>
                        <v-card min-width="300">
                            <v-card-text>
                                <ConversationAddUserForm />
                            </v-card-text>
                        </v-card>
                    </v-menu>

                </v-card-title>

                <v-divider></v-divider>

                <v-card-text class="pa-0">

                    <div v-if="messages?.length" ref="messageContainer" class="messages-container">
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

                            <!--v-divider v-if="reply !== conversation.replies.data[conversation.replies.data.length - 1]"></v-divider-->
                        </div>
                    </div>

                    <div v-else class="text-center py-8">
                        <v-icon size="64" color="grey-lighten-2">mdi-message-outline</v-icon>
                        <p class="text-body-1 text-medium-emphasis mt-2">
                            No messages yet. Be the first to reply!
                        </p>
                    </div>
                </v-card-text>
            </v-card>

            <!-- Reply Form -->
            <v-card style="padding-bottom: 20px;" class="mb-4" elevation="2">
                <v-card-title class="text-h6 font-weight-medium">
                    <v-icon class="mr-2">mdi-reply</v-icon>
                    Send Reply
                </v-card-title>
                <v-card-text>
                    <ConversationReplyForm />
                </v-card-text>
            </v-card>
        </v-container>

        <!-- Empty State -->
        <div v-else class="text-center py-12">
            <v-icon size="80" color="grey-lighten-2">mdi-chat-question-outline</v-icon>
            <h3 class="text-h5 font-weight-medium mt-4 mb-2">No conversation selected</h3>
            <p class="text-body-1 text-medium-emphasis">
                Select a conversation from the list to view its details
            </p>
        </div>
    </div>
</template>

<script>
import { useConversationStore } from "@/store/conversationStore";
import { computed, ref, watch, onMounted, onUnmounted, nextTick } from "vue";
import axios from "axios";
import ConversationAddUserForm from "@/components/conversation/forms/ConversationAddUserForm.vue";
import ConversationReplyForm from "@/components/conversation/forms/ConversationReplyForm.vue";
import UserAvatar from "@/components/common/UserAvatar.vue";

export default {
    name: "ConversationComponent",
    props: {
        id: {
            type: [String, Number],
            default: null
        }
    },
    components: {
        ConversationAddUserForm,
        ConversationReplyForm,
        UserAvatar
    },
    setup(props) {
        const conversationStore = useConversationStore();
        const conversation = computed(() => conversationStore.currentConversation);

        const loading = computed(() => conversationStore.loadingConversation);

        const messageContainer = ref(null);

        let currentChannel = null;
        const DEBUG = false;

        const messages = computed(() => conversationStore.messages || []);

        // Add scrollToBottom method
        const scrollToBottom = () => {
            nextTick(() => {
                if (messageContainer.value) {
                    messageContainer.value.scrollTop = messageContainer.value.scrollHeight;
                }
            });
        };

        const setupEchoListeners = (conversationId) => {
            if (!window.Echo) return;

            // Leave previous channel if exists
            if (currentChannel) {
                window.Echo.leave(currentChannel);
            }

            // Join new channel
            const channelName = `conversations.${conversationId}`;
            currentChannel = channelName;
            if (DEBUG) console.log('Echo channel:', channelName);

            window.Echo.private(channelName)
                .subscribed((e) => {
                    if (DEBUG) console.log('Echo subscribed:', e);
                })
                .listenToAll((e) => {
                    if (DEBUG) console.log('Echo event:', e);
                })
                .listen('Conversations\\MessageAdded', (e) => {
                    if (DEBUG) console.log('MessageAdded:', e);
                    // Update store with new reply
                    conversationStore.addMessage(e.message);
                    scrollToBottom();
                });
        };

        // React to conversation id changes (including initial mount)
        watch(
            () => props.id,
            async (newId, oldId) => {
                if (!newId || newId === oldId) return;
                if (DEBUG) console.log('Conversation ID changed:', { newId, oldId });
                try {
                    // Set up Echo listeners immediately so tests and UI can receive events without waiting for API
                    setupEchoListeners(newId);

                    await conversationStore.fetchConversation(newId);
                    // Fetch messages for the conversation
                    const response = await axios.get(`/api/conversations/${newId}`);
                    conversationStore.setMessages((response.data?.messages || []).reverse());
                    scrollToBottom();
                } catch (err) {
                    console.error('Failed to load conversation/messages', err);
                }
            },
            { immediate: true }
        );

        onUnmounted(() => {
            if (window.Echo && currentChannel) {
                window.Echo.leave(currentChannel);
                currentChannel = null;
            }
        });

        return {
            conversation,
            loading,
            messages,
            messageContainer,
        };
    },
};
</script>

<style scoped>
.conversation-container {
    min-height: 400px;
}

.bg-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.messages-container {
    max-height: 60vh;
    overflow-y: auto;
}

.messages-container::-webkit-scrollbar {
    width: 8px;
}

.messages-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.messages-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.messages-container::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

.message-item:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.message-bubble {
    max-width: 80%;
    word-wrap: break-word;
    border-radius: 16px !important;
}

.gap-2 {
    gap: 8px;
}

@media (max-width: 600px) {
    .d-flex.flex-wrap {
        flex-direction: column;
    }

    .message-bubble {
        max-width: 100%;
    }
}

/* Add to your existing CSS */
.messages-wrapper {
    padding-bottom: 20px; /* This fixes the cut-off */
}

.messages-container {
    max-height: 50vh; /* Reduce from 60vh */
}
</style>
