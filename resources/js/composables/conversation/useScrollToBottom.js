import { nextTick } from 'vue'

/**
 * Composable for auto-scrolling to bottom of a container
 * @param {Ref<HTMLElement>} containerRef - Reference to the scrollable container
 * @returns {Object} - Object with scrollToBottom function
 */
export function useScrollToBottom(containerRef) {
    const scrollToBottom = () => {
        nextTick(() => {
            if (containerRef.value) {
                containerRef.value.scrollTop = containerRef.value.scrollHeight
            }
        })
    }

    return {
        scrollToBottom
    }
}