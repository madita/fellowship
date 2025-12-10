<template>
    <v-card v-if="user || conversation" class="chat-component" elevation="2">
        <!-- Chat Header -->
        <v-card-title class="chat-header bg-gradient-to-r from-blue-500 to-teal-500 text-white pa-4">
            <div class="d-flex align-center justify-space-between w-100">
                <div class="d-flex align-center">
                    <!-- Group chat icon -->
                    <v-avatar v-if="isGroupChat" color="white" size="45" class="mr-3">
                        <v-icon color="primary" size="28">mdi-account-group</v-icon>
                    </v-avatar>
                    <!-- Single user avatar with online status -->
                    <v-badge
                        v-else-if="user"
                        :color="isUserOnline(user.id) ? 'success' : 'grey'"
                        dot
                        location="bottom right"
                        offset-x="4"
                        offset-y="4"
                    >
                        <user-avatar :user="user"></user-avatar>
                    </v-badge>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="d-flex align-center">
                            <v-tooltip v-if="isGroupChat" location="bottom">
                                <template #activator="{ props: tooltipProps }">
                                    <span v-bind="tooltipProps" class="font-weight-bold mr-2 text-truncate d-inline-block" style="max-width: 200px;">
                                        {{ displayName }}
                                    </span>
                                </template>
                                <span>{{ displayName }}</span>
                            </v-tooltip>
                            <span v-else class="font-weight-bold mr-2">{{ displayName }}</span>
                            <v-icon
                                v-if="!isGroupChat && user"
                                :color="isUserOnline(user.id) ? 'success' : 'grey'"
                                size="12"
                            >
                                mdi-circle
                            </v-icon>
                        </div>
                        <div v-if="!isGroupChat && user" class="text-caption opacity-70">
                            {{ isUserOnline(user.id) ? 'Online' : 'Offline' }}
                        </div>
                        <div v-else-if="isGroupChat" class="text-caption opacity-70">
                            {{ conversationUsers.length }} participants
                        </div>
                    </div>
                </div>

                <div class="d-flex align-center">
                    <v-btn
                        icon="mdi-minus"
                        variant="text"
                        color="white"
                        size="small"
                        @click="minimizeChat"
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
        <div ref="messageContainer" class="chat-messages pa-0">
            <!-- Loading state -->
            <div v-if="loading && !conversation" class="d-flex align-center justify-center" style="height: 400px;">
                <div class="text-center">
                    <v-progress-circular indeterminate size="64" color="primary" class="mb-4" />
                    <div class="text-body-1 text-medium-emphasis">Loading conversation...</div>
                </div>
            </div>

            <!-- Conversation messages -->
            <div v-else-if="conversation" class="pa-4">
                <conversation-messages
                    :id="conversation.uuid"
                    :messages="messages"
                    :loading="loading"
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
        </div>

        <!-- Chat Input -->
        <v-card-actions class="chat-input pa-4 border-t">
            <div class="w-100">
                <!-- Recipients selector (for new conversations) -->
                <v-combobox
                    v-if="!conversation"
                    v-model="recipients"
                    :items="autocompleteItems"
                    item-title="username"
                    item-value="id"
                    chips
                    multiple
                    clearable
                    placeholder="Type username to add recipients..."
                    class="mb-2"
                    variant="outlined"
                    density="compact"
                    hide-details
                    :disabled="loading"
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

                <!-- Message input row -->
                <div class="d-flex align-center">
                    <v-text-field
                        v-model="body"
                        placeholder="Type your message..."
                        variant="outlined"
                        density="compact"
                        hide-details
                        class="flex-grow-1"
                        @keyup.enter="handleSend"
                        :loading="isSending"
                        :disabled="isSending || (loading && !conversation)"
                    />

                    <v-btn
                        icon="mdi-send"
                        color="primary"
                        class="ml-2"
                        @click="handleSend"
                        :disabled="!body?.trim() || (!conversation && recipients.length === 0) || isSending || (loading && !conversation)"
                        :loading="isSending"
                    />
                </div>
            </div>
        </v-card-actions>
    </v-card>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import axios from 'axios'
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

// Emits
const emit = defineEmits(['close', 'minimize'])

// Reactive state
const user = ref(null)
const conversationUuid = ref(null)
const body = ref('')
const recipients = ref([])
const messageContainer = ref(null)
const isSending = ref(false)

// Local state for this conversation box (not shared with other boxes)
const conversation = ref(null)
const messages = ref([])
const loading = ref(false)

console.log('[ConversationBox] Created with props:', {
    initialUser: props.initialUser?.username,
    initialConversationUuid: props.initialConversationUuid
})

const conversationStore = useConversationStore();

// Use composables
const { userList: autocompleteItems, handleUserSearch } = useUserSearch(600);
const { isUserOnline, setupListeners: setupOnlineListeners } = useOnlineUsers();
const { scrollToBottom } = useScrollToBottom(messageContainer);

// Debug: Check if ref is set
watch(messageContainer, (val) => {
    console.log('[ConversationBox] messageContainer ref changed:', val ? val.tagName : 'null')
})

const userStore = useUserStore();

// Computed properties for group chat support
const conversationUsers = computed(() => {
    if (!conversation.value?.users) return [];
    // Exclude current user from the list
    return conversation.value.users.filter(u => u.id !== userStore.user?.id);
});

const isGroupChat = computed(() => conversationUsers.value.length > 1);

const displayName = computed(() => {
    if (isGroupChat.value) {
        return conversationUsers.value.map(u => u.username).join(', ');
    }
    return user.value?.username || '';
});

// Methods
const handleSend = async () => {
    if (!body.value?.trim() || isSending.value) return

    isSending.value = true
    try {
        if (!conversation.value) {
            await createConversation()
        } else {
            await createReply()
        }
    } catch (error) {
        console.error('Error sending message:', error)
    } finally {
        isSending.value = false
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
            await fetchConversation(conv.uuid)

            recipients.value = [];
            body.value = '';
            // scrollToBottom will be triggered by conversation watcher
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
        const response = await axios.post(`/api/conversations/${conversation.value.uuid}/reply`, {
            body: body.value.trim()
        })

        // Add the new message to local messages
        addLocalMessage(response.data)

        body.value = ''
        // scrollToBottom will be triggered by messages watcher
    } catch (e) {
        console.error('Failed to send reply:', e)
        throw e
    }
}

const minimizeChat = () => {
    emit('minimize')
}

const closeChat = () => {
    emit('close')
}

// Fetch conversation data locally for this box
const fetchConversation = async (uuid) => {
    if (!uuid) return

    console.log('[ConversationBox] Fetching conversation:', uuid, 'for user:', user.value?.username)
    loading.value = true
    try {
        const response = await axios.get(`/api/conversations/${uuid}`)
        conversation.value = response.data
        console.log('[ConversationBox] Loaded conversation:', conversation.value?.uuid, 'messages:', response.data.messages?.length)

        // Set messages from the conversation response
        if (response.data.messages && Array.isArray(response.data.messages)) {
            const reversedMessages = [...response.data.messages].reverse()
            messages.value = reversedMessages
        } else {
            messages.value = []
        }

        return response.data
    } catch (error) {
        console.error('[ConversationBox] Failed to fetch conversation:', error)
        throw error
    } finally {
        loading.value = false
    }
}

// Add message to local messages array
const addLocalMessage = (message) => {
    const id = message?.id
    if (id == null) {
        messages.value = [...messages.value, message]
        return
    }
    const exists = messages.value.some(m => m?.id === id)
    if (exists) {
        messages.value = messages.value.map(m => (m?.id === id ? { ...m, ...message } : m))
    } else {
        messages.value = [...messages.value, message]
    }
}

// Find existing 1-on-1 conversation with a user (returns the most recent one)
const findConversationWithUser = async (userId) => {
    try {
        const conversationsStore = useConversationsStore()

        if (!conversationsStore.allConversations || conversationsStore.allConversations.length === 0) {
            await conversationsStore.fetchConversations()
        }

        const conversations = conversationsStore.allConversations || []
        // Filter for 1-on-1 conversations only (exactly 2 users: current user + specified user)
        const userConversations = conversations.filter(conv => {
            return conv.users?.length === 2 && conv.users?.some(u => u.id === userId)
        })

        if (userConversations.length > 0) {
            userConversations.sort((a, b) => {
                const dateA = new Date(a.last_message_at || a.updated_at || a.created_at || 0)
                const dateB = new Date(b.last_message_at || b.updated_at || b.created_at || 0)
                return dateB - dateA
            })

            const mostRecentConv = userConversations[0]
            conversationUuid.value = mostRecentConv.uuid
            await fetchConversation(mostRecentConv.uuid)
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
let echoChannel = null

// Setup Echo listeners for this specific conversation
const setupConversationListeners = () => {
    if (!window.Echo || !conversation.value?.uuid) return

    const channelName = `conversations.${conversation.value.uuid}`
    echoChannel = window.Echo.private(channelName)

    echoChannel.listen('Conversations\\MessageAdded', (event) => {
        const message = event.message || event.data
        const isOwnMessage = message?.user?.id === userStore.user?.id

        // Only add message if it's not from the current user
        if (!isOwnMessage) {
            console.log('[ConversationBox] Received real-time message for conversation:', conversation.value.uuid)
            addLocalMessage(message)
            // scrollToBottom will be triggered by messages watcher
        }
    })
}

// Cleanup Echo listeners
const cleanupConversationListeners = () => {
    if (echoChannel && window.Echo && conversation.value?.uuid) {
        window.Echo.leave(`conversations.${conversation.value.uuid}`)
        echoChannel = null
    }
}

onMounted(() => {
    cleanupOnlineListeners = setupOnlineListeners()
})

onUnmounted(() => {
    // Cleanup online listeners
    if (cleanupOnlineListeners) {
        cleanupOnlineListeners()
    }

    // Cleanup conversation Echo listeners
    cleanupConversationListeners()
})

watch(() => props.initialConversationUuid, (newConversationUuid, oldConversationUuid) => {
    console.log('[ConversationBox] initialConversationUuid watcher:', {
        new: newConversationUuid,
        old: oldConversationUuid,
        current: conversationUuid.value
    })

    // Fetch if we have a UUID and it's different from what we currently have
    if (newConversationUuid && newConversationUuid !== conversationUuid.value) {
        console.log('[ConversationBox] UUID changed, fetching:', newConversationUuid)
        conversationUuid.value = newConversationUuid
        fetchConversation(newConversationUuid)
        // scrollToBottom will be triggered by conversation watcher
    }
}, { immediate: true })

watch(() => props.initialUser, async (newUser, oldUser) => {
    console.log('[ConversationBox] initialUser watcher:', {
        newUser: newUser?.username,
        oldUser: oldUser?.username,
        hasConversationUuid: !!props.initialConversationUuid
    })

    // Always update the user reference
    if (newUser) {
        user.value = newUser
    }

    // Only process user change logic if user actually changed and we don't have a conversationUuid
    if (newUser && newUser?.id !== oldUser?.id && !props.initialConversationUuid) {
        console.log('[ConversationBox] User changed, finding conversation for:', newUser.username)
        recipients.value = [newUser]
        const hasExisting = await findConversationWithUser(newUser.id)
        if (!hasExisting) {
            // Keep recipients set for new conversation
            recipients.value = [newUser]
        } else {
            // Clear recipients since we loaded an existing conversation
            recipients.value = []
        }
        // scrollToBottom will be triggered by conversation watcher
    }
}, { immediate: true })

watch(messages, (newMessages) => {
    console.log('[ConversationBox] Messages changed, count:', newMessages?.length)
    // Scroll to bottom when messages change
    scrollToBottom()
}, { deep: true })

// Watch conversation to setup Echo listeners when it loads
watch(conversation, (newConv, oldConv) => {
    console.log('[ConversationBox] Conversation changed:', newConv?.uuid)
    // Cleanup old conversation listeners
    if (oldConv?.uuid !== newConv?.uuid) {
        cleanupConversationListeners()
    }

    // Setup new conversation listeners
    if (newConv?.uuid) {
        setupConversationListeners()
        // Scroll to bottom when conversation loads
        scrollToBottom()
    }
})
</script>

<style scoped>
.chat-component {
    position: fixed;
    width: 360px;
    max-width: calc(100vw - 32px);
    max-height: calc(100vh - 100px);
    display: flex;
    flex-direction: column;
    z-index: 2000;
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
    background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-secondary)) 100%);
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
    background: rgba(var(--v-theme-on-surface), 0.05);
    border-radius: 4px;
}

.chat-messages::-webkit-scrollbar-thumb {
    background: rgba(var(--v-theme-on-surface), 0.2);
    border-radius: 4px;
}

.chat-messages::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--v-theme-on-surface), 0.3);
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
