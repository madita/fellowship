import { onMounted, onUnmounted } from 'vue'
import { useUserStore } from '@/store/userStore.js'
import { useConversationsStore } from '@/store/conversationsStore.js'
import { useConversationStore } from '@/store/conversationStore.js'
import eventBus from '@/components/common/eventBus.js'

/**
 * Composable for handling real-time conversation events
 * Manages Echo subscriptions for conversations and auto-opens chat when messages arrive
 *
 * @param {Object} options - Configuration options
 * @param {boolean} options.autoOpen - Auto-open chat box when new message arrives (default: true)
 * @param {boolean} options.debug - Enable debug logging (default: false)
 * @returns {Object} - Object with cleanup method
 */
export function useConversationRealtime(options = {}) {
    const {
        autoOpen = true,
        debug = false
    } = options

    const userStore = useUserStore()
    const conversationsStore = useConversationsStore()
    const conversationStore = useConversationStore()
    let echoChannel = null

    const log = (...args) => {
        if (debug) console.log('[useConversationRealtime]', ...args)
    }

    /**
     * Setup Echo listeners for conversation events
     */
    const setupListeners = () => {
        if (!window.Echo) {
            console.warn('[useConversationRealtime] Echo is not available')
            return
        }

        if (!userStore.user?.id) {
            console.warn('[useConversationRealtime] User ID not available')
            return
        }

        const userId = userStore.user.id
        const channelName = `user.${userId}`

        log('Setting up Echo listeners on channel:', channelName)

        // Subscribe to private user channel
        echoChannel = window.Echo.private(channelName)

        // Listen for new conversations created
        echoChannel.listen('Conversations\\ConversationCreated', (event) => {
            log('ConversationCreated event:', event)
            handleConversationCreated(event)
        })

        // Listen for new messages added
        echoChannel.listen('Conversations\\MessageAdded', (event) => {
            log('MessageAdded event:', event)
            handleMessageAdded(event)
        })

        log('Echo listeners setup complete')
    }

    /**
     * Handle ConversationCreated event
     * Auto-opens chat box if someone else created the conversation
     */
    const handleConversationCreated = (event) => {
        log('ConversationCreated event received:', event)
        try {
            const conversation = event.conversation || event.data
            const creator = conversation?.creator
            const isOwnConversation = creator?.id === userStore.user?.id

            log('Conversation created details:', {
                uuid: conversation?.uuid,
                creator: creator?.username,
                isOwnConversation,
                autoOpen
            })

            conversationsStore.fetchConversations()

            if (autoOpen && creator && !isOwnConversation) {
                log('Auto-opening chat box for new conversation from:', creator.username)

                eventBus.emit('chat.open', {
                    user: creator,
                    conversationUuid: conversation.uuid
                })
            }
        } catch (error) {
            console.error('[useConversationRealtime] Error handling ConversationCreated:', error)
        }
    }

    /**
     * Handle MessageAdded event
     * Auto-opens chat box if message is from someone else
     */
    const handleMessageAdded = (event) => {
        log('MessageAdded event received:', event)
        try {
            const message = event.message || event.data
            const sender = message?.user
            const conversation = message?.conversation
            const isOwnMessage = sender?.id === userStore.user?.id

            log('Message added:', {
                sender: sender?.username,
                conversationUuid: conversation?.uuid,
                isOwnMessage
            })

            // Only add message if it's NOT from the current user
            // (own messages are already added locally when sent)
            if (!isOwnMessage && conversationStore.currentConversation?.uuid === conversation?.uuid) {
                log('Adding message from other user to currently open conversation')
                conversationStore.addMessage(message)
            }

            conversationsStore.fetchConversations()

            // Auto-open chat box if message is from someone else
            if (autoOpen && !isOwnMessage) {
                log('Auto-opening chat box for new message from:', sender?.username)

                eventBus.emit('chat.open', {
                    user: sender,
                    conversationUuid: conversation?.uuid
                })
            }
        } catch (error) {
            console.error('[useConversationRealtime] Error handling MessageAdded:', error)
        }
    }

    /**
     * Cleanup Echo listeners
     */
    const cleanup = () => {
        if (echoChannel && window.Echo && userStore.user?.id) {
            const channelName = `user.${userStore.user.id}`
            log('Cleaning up Echo listeners from channel:', channelName)
            window.Echo.leave(channelName)
            echoChannel = null
        }
    }

    return {
        setupListeners,
        cleanup
    }
}
