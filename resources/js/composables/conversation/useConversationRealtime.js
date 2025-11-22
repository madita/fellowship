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
        console.log('conversation created', event)
        try {
            const conversation = event.conversation || event.data
            const creator = conversation?.creator

            log('Conversation created:', {
                uuid: conversation?.uuid,
                creator: creator?.username,
                creatorId: creator?.id,
                currentUserId: userStore.user?.id
            })

            // Refresh conversations list to show new conversation
            conversationsStore.fetchConversations()

            // Auto-open chat box if someone else started the conversation with us
            if (autoOpen && creator && creator.id !== userStore.user?.id) {
                log('Auto-opening chat box for new conversation from:', creator.username)

                // Emit event to open chat box
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
        console.log('message added', event)
        try {
            const message = event.message || event.data
            const sender = message?.user
            const conversation = message?.conversation
            const isOwnMessage = sender?.id === userStore.user?.id

            log('Message added:', {
                messageId: message?.id,
                sender: sender?.username,
                senderId: sender?.id,
                conversationUuid: conversation?.uuid,
                currentUserId: userStore.user?.id,
                currentConversationUuid: conversationStore.currentConversation?.uuid,
                isOwnMessage
            })

            // Only add message if it's NOT from the current user
            // (own messages are already added locally when sent)
            if (!isOwnMessage && conversationStore.currentConversation?.uuid === conversation?.uuid) {
                log('Adding message from other user to currently open conversation')
                conversationStore.addMessage(message)
            }

            // Refresh conversations list to update unread counts
            conversationsStore.fetchConversations()

            // Auto-open chat box if message is from someone else
            if (autoOpen && !isOwnMessage) {
                log('Auto-opening chat box for new message from:', sender?.username)
                console.log('[useConversationRealtime] EventBus instance:', eventBus)
                console.log('[useConversationRealtime] ConversationBoxManager mounted?', window.__conversationBoxManagerMounted)
                console.log('[useConversationRealtime] EventBus instances match?', eventBus === window.__conversationBoxManagerEventBus)
                console.log('[useConversationRealtime] Emitting chat.open event with data:', {
                    user: sender,
                    conversationUuid: conversation?.uuid
                })

                // Emit event to open chat box with the sender's info and conversation
                eventBus.emit('chat.open', {
                    user: sender,
                    conversationUuid: conversation?.uuid
                })

                console.log('[useConversationRealtime] Event emitted successfully')

                // Check if there are any listeners
                console.log('[useConversationRealtime] Active listeners on chat.open:', eventBus.all.get('chat.open'))
            } else {
                console.log('[useConversationRealtime] NOT auto-opening because:', {
                    autoOpen,
                    isOwnMessage,
                    sender: sender?.username
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
