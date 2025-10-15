import { ref, onUnmounted } from 'vue'

/**
 * Composable for managing Echo WebSocket listeners
 * @param {boolean} debug - Enable debug logging
 * @returns {Object} - Object with setup and cleanup methods
 */
export function useEchoListener(debug = false) {
    const currentChannel = ref(null)

    const setupListener = (channelName, eventName, callback) => {
        if (!window.Echo) {
            if (debug) console.warn('Echo is not available')
            return
        }

        // Leave previous channel if exists
        if (currentChannel.value) {
            window.Echo.leave(currentChannel.value)
        }

        currentChannel.value = channelName
        if (debug) console.log('Echo channel:', channelName)

        const channel = window.Echo.private(channelName)
            .subscribed((e) => {
                if (debug) console.log('Echo subscribed:', e)
            })
            .listenToAll((e) => {
                if (debug) console.log('Echo event:', e)
            })

        if (eventName && callback) {
            channel.listen(eventName, callback)
        }

        return channel
    }

    const cleanup = () => {
        if (window.Echo && currentChannel.value) {
            window.Echo.leave(currentChannel.value)
            currentChannel.value = null
        }
    }

    onUnmounted(() => {
        cleanup()
    })

    return {
        setupListener,
        cleanup,
        currentChannel
    }
}
