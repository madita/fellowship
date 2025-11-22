<template>
    <div v-if="isVisible" class="conversation-box-wrapper">
        <conversation-box
            :key="currentUser?.id || 'no-user'"
            :initial-user="currentUser"
            :initial-conversationUuid="currentConversationUuid"
        />
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import ConversationBox from './ConversationBox.vue'
import eventBus from '@/components/common/eventBus.js'
import { useConversationRealtime } from '@/composables/conversation/useConversationRealtime'

const isVisible = ref(false)
const currentUser = ref(null)
const currentConversationUuid = ref(null)

// Setup real-time conversation handling with auto-open enabled
const { setupListeners, cleanup } = useConversationRealtime({
    autoOpen: true,
    debug: true // Set to false in production
})

onMounted(() => {
    console.log('[ConversationBoxManager] Component mounted, setting up listeners')

    // Debug: Set a global flag
    window.__conversationBoxManagerMounted = true
    window.__conversationBoxManagerEventBus = eventBus

    // Setup Echo listeners for real-time updates and auto-opening
    setupListeners()

    // Show chat box when conversation.new or chat.show is emitted
    eventBus.on('conversation.new', (user) => {
        console.log('[ConversationBoxManager] conversation.new event received:', user)
        currentUser.value = user
        isVisible.value = true
    })

    eventBus.on('chat.show', (data) => {
        console.log('chat.show', data)
        if (data.user) {
            currentUser.value = data.user
            isVisible.value = true
        }
    })

    eventBus.on('chat.open', (data) => {
        console.log('[ConversationBoxManager] chat.open event received:', data)
        if(data.conversationUuid) {
            currentConversationUuid.value = data.conversationUuid
            isVisible.value = true
        }
        if (data.user) {
            currentUser.value = data.user
            isVisible.value = true
            //console.log('[ConversationBoxManager] Set isVisible to true, currentUser:', currentUser.value)
        }
    })

    eventBus.on('chat.close', () => {
        console.log('[ConversationBoxManager] chat.close event received')
        isVisible.value = false
        // Clear currentUser immediately to force a fresh component when reopened
        currentUser.value = null
        currentConversationUuid.value = null
    })

    // Test eventBus is working
    console.log('[ConversationBoxManager] EventBus instance:', eventBus)
    console.log('[ConversationBoxManager] All listeners set up successfully')
})

onUnmounted(() => {
    // Cleanup Echo listeners
    cleanup()

    // Cleanup event bus listeners
    eventBus.off('conversation.new')
    eventBus.off('chat.show')
    eventBus.off('chat.open')
    eventBus.off('chat.close')
})
</script>

<style scoped>
.conversation-box-wrapper {
    position: fixed;
    right: 16px;
    bottom: 16px;
    z-index: 2000;
}
</style>
