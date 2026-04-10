<template>
    <div class="conversation-manager">
        <!-- Minimized conversation avatars -->
        <div class="minimized-conversations" :style="getMinimizedPosition()">
            <v-tooltip
                v-for="conv in minimizedConversations"
                :key="conv.key"
                location="top"
            >
                <template #activator="{ props: tooltipProps }">
                    <div
                        v-bind="tooltipProps"
                        class="minimized-avatar"
                        @click="maximizeConversation(conv.key)"
                    >
                        <v-badge
                            v-if="conv.isGroup"
                            :content="conv.unreadCount"
                            :model-value="conv.unreadCount > 0"
                            color="error"
                            overlap
                        >
                            <v-avatar color="primary" size="48">
                                <v-icon color="white">mdi-account-group</v-icon>
                            </v-avatar>
                        </v-badge>
                        <v-badge
                            v-else
                            :content="conv.unreadCount"
                            :model-value="conv.unreadCount > 0"
                            color="error"
                            overlap
                        >
                            <user-avatar :user="conv.user" />
                        </v-badge>
                    </div>
                </template>
                <span>{{ conv.displayName }}</span>
            </v-tooltip>
        </div>

        <!-- Open conversation boxes -->
        <div class="conversation-boxes">
            <conversation-box
                v-for="(conv, index) in maximizedConversations"
                :key="conv.key"
                :initial-user="conv.user"
                :initial-conversation-uuid="conv.conversationUuid"
                :style="getBoxPosition(index)"
                @close="closeConversation(conv.key)"
                @minimize="minimizeConversation(conv.key)"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import ConversationBox from './ConversationBox.vue'
import UserAvatar from '@/components/common/UserAvatar.vue'
import eventBus from '@/components/common/eventBus.js'
import { useConversationRealtime } from '@/composables/conversation/useConversationRealtime'

const BOX_WIDTH = 360
const BOX_SPACING = 16
const MINIMIZED_SIZE = 64

// Track all open conversations
const openConversations = ref([])
const windowWidth = ref(window.innerWidth)

// Setup real-time conversation handling with auto-open enabled
const { setupListeners, cleanup } = useConversationRealtime({
    autoOpen: true,
    debug: false
})

// Calculate max visible conversations based on window width
const maxVisibleConversations = computed(() => {
    return Math.max(1, Math.floor((windowWidth.value - 32) / (BOX_WIDTH + BOX_SPACING)))
})

// Split conversations into minimized and maximized
const maximizedConversations = computed(() => {
    const allMaximized = openConversations.value.filter(conv => !conv.isMinimized)
    // Limit to max visible based on screen width
    return allMaximized.slice(0, maxVisibleConversations.value)
})

const minimizedConversations = computed(() => {
    // Include manually minimized conversations
    const manuallyMinimized = openConversations.value.filter(conv => conv.isMinimized)

    // Include overflow conversations (non-minimized but can't fit on screen)
    const allMaximized = openConversations.value.filter(conv => !conv.isMinimized)
    const overflowConversations = allMaximized.slice(maxVisibleConversations.value)

    // Combine both groups
    return [...manuallyMinimized, ...overflowConversations]
})

// Generate unique key for conversation
function getConversationKey(data) {
    return data.conversationUuid || `user-${data.user?.id}`
}

// Get display name for conversation
function getDisplayName(conv) {
    if (conv.isGroup) {
        return conv.groupName || 'Group Chat'
    }
    return conv.user?.username || 'User'
}

// Calculate position for each conversation box
function getBoxPosition(index) {
    const offset = index * (BOX_WIDTH + BOX_SPACING)

    return {
        right: `${16 + offset}px`,
        bottom: '16px'
    }
}

// Calculate position for minimized conversations
function getMinimizedPosition() {
    const numMaximized = maximizedConversations.value.length
    const offset = numMaximized * (BOX_WIDTH + BOX_SPACING)

    return {
        right: `${16 + offset}px`
    }
}

// Open or focus a conversation
function openConversation(data) {
    const key = getConversationKey(data)
    console.log('[Manager] openConversation called with key:', key, 'uuid:', data.conversationUuid, 'user:', data.user?.username)
    const existingIndex = openConversations.value.findIndex(conv => conv.key === key)

    if (existingIndex !== -1) {
        console.log('[Manager] Conversation already exists at index:', existingIndex, 'updating...')
        // Conversation already open - update it and bring to front
        const existing = openConversations.value[existingIndex]

        // Update conversation data if provided
        if (data.conversationUuid) {
            existing.conversationUuid = data.conversationUuid
        }
        if (data.user) {
            existing.user = data.user
        }
        if (data.isGroup !== undefined) {
            existing.isGroup = data.isGroup
        }
        if (data.groupName) {
            existing.groupName = data.groupName
            existing.displayName = data.groupName
        }

        // Maximize if minimized
        if (existing.isMinimized) {
            existing.isMinimized = false
        }

        // Move to front of non-minimized conversations to ensure visibility
        openConversations.value.splice(existingIndex, 1)
        const firstMaximizedIndex = openConversations.value.findIndex(c => !c.isMinimized)
        if (firstMaximizedIndex === -1) {
            openConversations.value.push(existing)
        } else {
            openConversations.value.splice(firstMaximizedIndex, 0, existing)
        }
    } else {
        // Determine if this is a group chat
        const isGroup = data.isGroup || (data.conversation?.participant_count > 1) || false
        const groupName = data.groupName || data.conversation?.groupName || null
        const displayName = isGroup
            ? (groupName || 'Group Chat')
            : (data.user?.username || 'User')

        // Add new conversation
        const newConv = {
            key,
            user: data.user,
            conversationUuid: data.conversationUuid,
            isMinimized: false,
            isGroup,
            groupName,
            displayName,
            unreadCount: 0
        }
        console.log('[Manager] Creating new conversation:', newConv)
        openConversations.value.push(newConv)
        console.log('[Manager] Total open conversations:', openConversations.value.length)
    }
}

// Close a conversation
function closeConversation(key) {
    const index = openConversations.value.findIndex(conv => conv.key === key)
    if (index !== -1) {
        openConversations.value.splice(index, 1)
    }
}

// Minimize a conversation
function minimizeConversation(key) {
    const conv = openConversations.value.find(c => c.key === key)
    if (conv) {
        conv.isMinimized = true
    }
}

// Maximize a conversation
function maximizeConversation(key) {
    const index = openConversations.value.findIndex(c => c.key === key)
    if (index !== -1) {
        const conv = openConversations.value[index]

        // If manually minimized, just unminimize it
        if (conv.isMinimized) {
            conv.isMinimized = false
        }

        // Move this conversation to the front of the non-minimized list
        // This ensures it becomes visible even if it was in overflow
        openConversations.value.splice(index, 1)

        // Find the position to insert (after all manually minimized, at the start of maximized)
        const firstMaximizedIndex = openConversations.value.findIndex(c => !c.isMinimized)
        if (firstMaximizedIndex === -1) {
            // No maximized conversations, add to end
            openConversations.value.push(conv)
        } else {
            // Insert at the start of maximized conversations
            openConversations.value.splice(firstMaximizedIndex, 0, conv)
        }
    }
}

// Handle window resize
function handleResize() {
    windowWidth.value = window.innerWidth
}

onMounted(() => {
    // Debug: Set a global flag
    window.__conversationBoxManagerMounted = true
    window.__conversationBoxManagerEventBus = eventBus

    // Setup Echo listeners for real-time updates and auto-opening
    setupListeners()

    // Setup window resize listener
    window.addEventListener('resize', handleResize)

    // Handle conversation open events
    eventBus.on('conversation.new', (user) => {
        openConversation({ user })
    })

    eventBus.on('chat.show', (data) => {
        openConversation(data)
    })

    eventBus.on('chat.open', (data) => {
        openConversation(data)
    })

    // Legacy support - not typically used anymore since ConversationBox emits directly
    eventBus.on('chat.close', (data) => {
        if (data?.key) {
            closeConversation(data.key)
        } else if (openConversations.value.length > 0) {
            // If no key provided, close the most recent
            const lastConv = openConversations.value[openConversations.value.length - 1]
            closeConversation(lastConv.key)
        }
    })
})

onUnmounted(() => {
    // Cleanup Echo listeners
    cleanup()

    // Cleanup window resize listener
    window.removeEventListener('resize', handleResize)

    // Cleanup event bus listeners
    eventBus.off('conversation.new')
    eventBus.off('chat.show')
    eventBus.off('chat.open')
    eventBus.off('chat.close')
})
</script>

<style scoped>
.conversation-manager {
    position: fixed;
    bottom: 0;
    right: 0;
    z-index: 2000;
    pointer-events: none;
}

.conversation-manager > * {
    pointer-events: auto;
}

.minimized-conversations {
    position: fixed;
    bottom: 16px;
    right: 16px;
    display: flex;
    flex-direction: row-reverse;
    gap: 8px;
    z-index: 1999;
    transition: right 0.3s ease;
}

.minimized-avatar {
    cursor: pointer;
    transition: transform 0.2s ease;
}

.minimized-avatar:hover {
    transform: scale(1.1);
}

.conversation-boxes {
    position: relative;
}
</style>
