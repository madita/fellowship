import mitt from 'mitt'

// Create and export the event bus instance
const eventBus = mitt()

export default eventBus

// Optional: Also export as named export for backward compatibility
export { eventBus }
