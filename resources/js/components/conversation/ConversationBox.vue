<template>
    <v-card v-if="user" class="chat-component" elevation="2">
        <!-- Chat Header -->
        <v-card-title class="chat-header bg-gradient-to-r from-blue-500 to-teal-500 text-white pa-4">
            <div class="d-flex align-center justify-space-between w-100">
                <div class="d-flex align-center">
                    <v-avatar size="40" class="mr-3">
                        <v-img :src="user.avatar" :alt="`${user.username} avatar`" />
                    </v-avatar>
                    <div>
                        <div class="font-weight-bold">{{ user.username }}</div>
                        <div class="text-caption opacity-70">Online</div>
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
        <v-card-text class="chat-messages pa-0" style="height: 400px; overflow-y: auto;">
            <div v-if="conversation" class="pa-4">
                <Conversation
                    :is-pinned="isPinned"
                    :id="conversation.id"
                    :messages="messages"
                />

                <!-- Message bubbles -->
                <div v-for="message in messages" :key="message.id" class="message-wrapper mb-4">
                    <div
                        class="d-flex align-start"
                        :class="{ 'flex-row-reverse': message.self_owned }"
                    >
                        <v-avatar size="32" class="mx-2">
                            <v-img
                                :src="message.user.avatar"
                                :alt="`${message.user.username} avatar`"
                            />
                        </v-avatar>

                        <div class="message-content" :class="{ 'text-right': message.self_owned }">
                            <div class="text-caption text-medium-emphasis mb-1">
                                {{ message.user.username }}
                            </div>

                            <v-sheet
                                :color="message.self_owned ? 'primary' : 'surface-variant'"
                                :class="[
                  'message-bubble pa-3 rounded-lg d-inline-block',
                  message.self_owned ? 'text-white' : 'text-on-surface'
                ]"
                                elevation="1"
                            >
                                {{ message.body }}
                            </v-sheet>

                            <div class="text-caption text-medium-emphasis mt-1">
                                {{ formatTime(message.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="d-flex align-center justify-center h-100">
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
                    v-if="!conversation && recipients.length === 0"
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
import { useStore } from 'vuex'
import eventBus from "../common/eventBus.js";

// Props
const props = defineProps({
    initialUser: {
        type: Object,
        default: null
    }
})

// Composables
const store = useStore()


// Reactive state
const user = ref(props.initialUser)
const body = ref('')
const recipients = ref([])
const autocompleteItems = ref([])
const isPinned = ref(false)
const debounceTimer = ref(null)

// Computed properties
const conversation = computed(() => store.getters.currentConversation)
const loading = computed(() => store.getters.loadingConversation)
const messages = computed(() => conversation.value?.messages || [])

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
    await store.dispatch('createConversationBox', {
        recipientIds: recipients.value.map(r => r.id),
        body: body.value
    })

    recipients.value = []
    body.value = ''
}

const createReply = async () => {
    if (!conversation.value) return

    await store.dispatch('createConversationReply', {
        id: conversation.value.id,
        body: body.value
    })

    body.value = ''
}

const handleUserSearch = (query) => {
    if (!query || query.length < 2) {
        autocompleteItems.value = []
        return
    }

    // Clear existing timer
    if (debounceTimer.value) {
        clearTimeout(debounceTimer.value)
    }

    // Set new timer
    debounceTimer.value = setTimeout(async () => {
        try {
            const response = await fetch(`/api/search/users?q=${encodeURIComponent(query)}`)
            const data = await response.json()

            autocompleteItems.value = data.data.map(user => ({
                text: user.username,
                username: user.username,
                id: user.id,
                avatar: user.avatar
            }))
        } catch (error) {
            console.error('Error searching users:', error)
        }
    }, 600)
}

const togglePin = () => {
    isPinned.value = !isPinned.value
}

const closeChat = () => {
    eventBus.emit('chat.close')
}

const formatTime = (timestamp) => {
    return new Date(timestamp).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
    })
}

// Event listeners
onMounted(() => {
    // Listen for new conversation events
    eventBus.on('conversation.new', (newUser) => {
        user.value = newUser
        recipients.value.push(newUser)
    })

    // Setup Echo listeners if available
    if (window.Echo && window.App?.user?.id) {
        const channel = window.Echo.private(`user.${window.App.user.id}`)

        channel
            .listen('Conversation.ConversationCreated', (e) => {
                console.log('ConversationCreated', e)
                store.dispatch('getConversation', e.data.id, true)
                user.value = e.data.user.data
            })
            .listen('Conversation.ConversationReplyCreated', (e) => {
                console.log('ConversationReplyCreated', e)
                store.dispatch('getConversation', e.data.parent.data.id, true)
                user.value = e.data.user.data
            })
            .listen('Conversation.ConversationUsersCreated', (e) => {
                console.log('ConversationUsersCreated', e)
            })
    }
})

onUnmounted(() => {
    // Clean up event listeners
    eventBus.off('conversation.new')

    if (debounceTimer.value) {
        clearTimeout(debounceTimer.value)
    }
})

// Watch for changes
watch(() => props.initialUser, (newUser) => {
    user.value = newUser
}, { immediate: true })
</script>

<style scoped>
.chat-component {
    max-width: 500px;
    margin: 0 auto;
}

.chat-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.chat-messages {
    background-color: rgb(var(--v-theme-surface));
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
