<template>
    <v-card v-if="user" class="chat-component" elevation="2">
        <!-- Chat Header -->
        <v-card-title class="chat-header bg-gradient-to-r from-blue-500 to-teal-500 text-white pa-4">
            <div class="d-flex align-center justify-space-between w-100">
                <div class="d-flex align-center">
                    <v-badge
                        :color="isUserOnline(user.id) ? 'success' : 'grey'"
                        dot
                        location="bottom right"
                        offset-x="4"
                        offset-y="4"
                    >
                        <user-avatar :user="user"></user-avatar>
                    </v-badge>
                    <div>
                        <div class="d-flex align-center">
                            <span class="font-weight-bold mr-2">{{ user.username }}</span>
                            <v-icon
                                :color="isUserOnline(user.id) ? 'success' : 'grey'"
                                size="12"
                            >
                                mdi-circle
                            </v-icon>
                        </div>
                        <div class="text-caption opacity-70">
                            {{ isUserOnline(user.id) ? 'Online' : 'Offline' }}
                        </div>
                    </div>
                </div>

                <div class="d-flex align-center">
                    <v-btn
                        icon="mdi-pin"
                        variant="text"
                        color="white"
                        size="small"
                        @click="togglePin"
                        class="mr-2"
                    />
                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        color="white"
                        size="small"
                        @click="closeChat"
                    />
                </div>
            </div>
        </v-card-title>

        <!-- Messages Area -->
        <v-card-text ref="messageContainer" class="chat-messages pa-0">
            <div v-if="conversation" class="pa-4">
                <conversation-messages
                    :is-pinned="isPinned"
                    :id="conversation.uuid"
                />
            </div>

            <!-- Empty state -->
            <div v-else class="d-flex align-center justify-center" style="height: 400px;">
                <div class="text-center">
                    <v-icon size="64" color="medium-emphasis">mdi-chat-outline</v-icon>
                    <div class="text-h6 mt-2">No conversation yet</div>
                    <div class="text-body-2 text-medium-emphasis">Start typing to begin chatting</div>
                </div>
            </div>
        </v-card-text>

        <!-- Chat Input -->
        <v-card-actions class="chat-input pa-4 border-t">
            <div class="d-flex align-center w-100">
                <!-- Recipients selector (for new conversations) -->
                <v-combobox
                    v-if="recipients.length === 0"
                    v-model="recipients"
                    :items="autocompleteItems"
                    item-title="username"
                    item-value="id"
                    chips
                    multiple
                    clearable
                    placeholder="Type username to add recipients..."
                    class="mr-2"
                    variant="outlined"
                    density="compact"
                    hide-details
                    @update:search="handleUserSearch"
                >
                    <template #chip="{ props, item }">
                        <v-chip
                            v-bind="props"
                            :text="item.raw.username"
                            closable
                            size="small"
                        />
                    </template>
                </v-combobox>

                <!-- Message input -->
                <v-text-field
                    v-model="body"
                    placeholder="Type your message..."
                    variant="outlined"
                    density="compact"
                    hide-details
                    class="flex-grow-1"
                    @keyup.enter="handleSend"
                    :loading="loading"
                />

                <v-btn
                    icon="mdi-send"
                    color="primary"
                    class="ml-2"
                    @click="handleSend"
                    :disabled="!body?.trim()"
                />
            </div>
        </v-card-actions>
    </v-card>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import eventBus from "../common/eventBus.js";
import { useConversationStore } from "@/store/conversationStore.js";
import { useConversationsStore } from "@/store/conversationsStore.js";
import { storeConversation } from "@/api/all.js";
import ConversationMessages from "@/components/conversation/ConversationMessages.vue";
import { useUserSearch } from "@/composables/conversation/useUserSearch";
import { useOnlineUsers } from "@/composables/conversation/useOnlineUsers";
import { useScrollToBottom } from "@/composables/conversation/useScrollToBottom";
import UserAvatar from "@/components/common/UserAvatar.vue";
import {useUserStore} from "@/store/userStore.js";


// Props
const props = defineProps({
    initialUser: {
        type: Object,
        default: null
    },
    initialConversationUuid: {
        type: String,
        default: null
    }
})

// Reactive state
const user = ref(props.initialUser)
const conversationUuid = ref(props.initialConversationUuid)
const body = ref('')
const recipients = ref([])
const isPinned = ref(false)
const messageContainer = ref(null)

const conversationStore = useConversationStore();
const conversation = computed(() => conversationStore.currentConversation);
const loading = computed(() => conversationStore.loadingConversation);
const messages = computed(() => conversationStore.messages || []);

// Use composables
const { userList: autocompleteItems, handleUserSearch } = useUserSearch(600);
const { isUserOnline, setupListeners: setupOnlineListeners } = useOnlineUsers();
const { scrollToBottom } = useScrollToBottom(messageContainer);

const userStore = useUserStore();

// Methods
const handleSend = async () => {
    if (!body.value?.trim()) return

    try {
        if (!conversation.value) {
            await createConversation()
        } else {
            await createReply()
        }
    } catch (error) {
        console.error('Error sending message:', error)
    }
}

const createConversation = async () => {
    const payload = {
        recipientIds: recipients.value.map(r => r.id),
        body: body.value,
    };
    try {
        const response = await storeConversation(payload);
        const data = response?.data ?? response;
        const conv = data?.data ?? data?.conversation ?? data;

        if (conv?.uuid) {
            conversationUuid.value = conv.uuid
            await conversationStore.fetchConversation(conv.uuid)

            recipients.value = [];
            body.value = '';
            scrollToBottom()
        } else {
            console.error('[ConversationBox] No conversation UUID in response')
        }
    } catch (e) {
        console.error('Failed to create conversation:', e);
        throw e;
    }
}

const createReply = async () => {
    if (!conversation.value) return
    try {
        await conversationStore.createConversationReply({
            id: conversation.value.id,
            uuid: conversation.value.uuid,
            body: body.value.trim(),
        });
        body.value = ''
        scrollToBottom()
    } catch (e) {
        console.error('Failed to send reply:', e)
        throw e
    }
}

const togglePin = () => {
    isPinned.value = !isPinned.value
}

const closeChat = () => {
    eventBus.emit('chat.close')
}

// Find existing conversation with a user (returns the most recent one)
const findConversationWithUser = async (userId) => {
    try {
        const conversationsStore = useConversationsStore()

        if (!conversationsStore.allConversations || conversationsStore.allConversations.length === 0) {
            await conversationsStore.fetchConversations()
        }

        const conversations = conversationsStore.allConversations || []
        const userConversations = conversations.filter(conv => {
            return conv.users?.some(u => u.id === userId)
        })

        if (userConversations.length > 0) {
            userConversations.sort((a, b) => {
                const dateA = new Date(a.last_message_at || a.updated_at || a.created_at || 0)
                const dateB = new Date(b.last_message_at || b.updated_at || b.created_at || 0)
                return dateB - dateA
            })

            const mostRecentConv = userConversations[0]
            await conversationStore.fetchConversation(mostRecentConv.uuid)
            return true
        }

        return false
    } catch (error) {
        console.error('[ConversationBox] Error finding conversation:', error)
        return false
    }
}

// Event listeners
let cleanupOnlineListeners = null

onMounted(() => {
    cleanupOnlineListeners = setupOnlineListeners()

    eventBus.on('conversation.new', async (newUser) => {
        user.value = newUser
        const hasExisting = await findConversationWithUser(newUser.id)

        if (!hasExisting) {
            recipients.value = [newUser]
        }
    })

    eventBus.on('chat.open', async (data) => {
        if (data.user) {
            user.value = data.user

            if (data.conversationUuid) {
                recipients.value = []
                await conversationStore.fetchConversation(data.conversationUuid)
            } else {
                conversationStore.clearCurrentConversation()
                conversationStore.setMessages([])

                const hasExisting = await findConversationWithUser(data.user.id)
                if (!hasExisting) {
                    recipients.value = [data.user]
                } else {
                    recipients.value = []
                }
            }
        }
    })
})

onUnmounted(() => {
    // Note: Don't remove 'chat.open' listener here because ConversationBoxManager owns it
    // This component gets destroyed/recreated when chat closes/opens, but the manager stays mounted
    eventBus.off('conversation.new')

    // Cleanup online listeners
    if (cleanupOnlineListeners) {
        cleanupOnlineListeners()
    }
})

watch(() => props.initialConversationUuid, (newConversationUuid) => {
    if (newConversationUuid) {
        conversationUuid.value = newConversationUuid
        conversationStore.fetchConversation(newConversationUuid).then(() => {
            scrollToBottom()
        })
    }
}, { immediate: true })

watch(() => props.initialUser, (newUser) => {
    if (newUser) {
        user.value = newUser
        recipients.value = [newUser]
        if (props.initialConversationUuid === null) {
            findConversationWithUser(newUser.id).then(() => {
                scrollToBottom()
            })
        }
    }
}, { immediate: true })

watch(messages, () => {
    scrollToBottom()
})
</script>

<style scoped>
.chat-component {
    position: fixed;
    right: 16px;
    bottom: 16px;
    width: 360px;
    max-width: calc(100vw - 32px);
    max-height: calc(100vh - 100px);
    display: flex;
    flex-direction: column;
    z-index: 2000; /* above footer and most UI */
}

/* Responsive adjustments */
@media (max-width: 600px) {
    .chat-component {
        width: calc(100vw - 32px);
        right: 16px;
        bottom: 16px;
    }

    .chat-messages {
        height: 300px;
        max-height: 300px;
    }
}

.chat-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.chat-messages {
    background-color: rgb(var(--v-theme-surface));
    height: 400px;
    max-height: 400px;
    overflow-y: auto;
    overflow-x: hidden;
}

/* Custom scrollbar for chat messages */
.chat-messages::-webkit-scrollbar {
    width: 8px;
}

.chat-messages::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.chat-messages::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.chat-messages::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

.message-wrapper {
    animation: fadeIn 0.3s ease-in-out;
}

.message-bubble {
    max-width: 70%;
    word-wrap: break-word;
}

.chat-input {
    border-top: 1px solid rgb(var(--v-border-color));
    background-color: rgb(var(--v-theme-surface));
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive design */
@media (max-width: 600px) {
    .chat-component {
        max-width: 100%;
        height: 100vh;
    }

    .message-bubble {
        max-width: 85%;
    }
}
</style>
