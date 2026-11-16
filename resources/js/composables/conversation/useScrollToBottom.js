import { nextTick } from 'vue'

/**
 * Composable for auto-scrolling to bottom of a container
 * @param {Ref<HTMLElement>} containerRef - Reference to the scrollable container
 * @returns {Object} - Object with scrollToBottom function
 */
export function useScrollToBottom(containerRef) {
    const scrollToBottom = () => {
        // Use multiple nextTicks to ensure DOM is fully updated
        nextTick(() => {
            nextTick(() => {
                setTimeout(() => {
                    if (containerRef.value) {
                        const element = containerRef.value
                        console.log('[scrollToBottom] Container found:', {
                            scrollHeight: element.scrollHeight,
                            scrollTop: element.scrollTop,
                            clientHeight: element.clientHeight,
                            element: element.tagName
                        })

                        if (element.scrollHeight > 0) {
                            element.scrollTop = element.scrollHeight
                            console.log('[scrollToBottom] Scrolled to:', element.scrollTop)
                        } else {
                            console.warn('[scrollToBottom] scrollHeight is 0, waiting...')
                            // Try again after a short delay
                            setTimeout(() => {
                                if (element.scrollHeight > 0) {
                                    element.scrollTop = element.scrollHeight
                                }
                            }, 100)
                        }
                    } else {
                        console.warn('[scrollToBottom] Container ref is null')
                    }
                }, 50)
            })
        })
    }

    return {
        scrollToBottom
    }
}