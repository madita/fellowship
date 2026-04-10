import { ref, computed, onMounted, onUnmounted } from 'vue'
import eventBus from '@/components/common/eventBus.js'

// Shared state across all components
const onlineUserIds = ref(new Set())

export function useOnlineUsers() {
    const isUserOnline = (userId) => {
        return computed(() => onlineUserIds.value.has(userId))
    }

    const updateOnlineUsers = (users) => {
        const ids = new Set()
        if (Array.isArray(users)) {
            users.forEach(user => {
                if (user && user.id) {
                    ids.add(user.id)
                }
            })
        }
        onlineUserIds.value = ids
    }

    const addOnlineUser = (user) => {
        if (user && user.id) {
            const newSet = new Set(onlineUserIds.value)
            newSet.add(user.id)
            onlineUserIds.value = newSet
        }
    }

    const removeOnlineUser = (user) => {
        if (user && user.id) {
            const newSet = new Set(onlineUserIds.value)
            newSet.delete(user.id)
            onlineUserIds.value = newSet
        }
    }

    const setupListeners = () => {
        const offHere = (payload) => {
            const rawList = Array.isArray(payload) ? payload : []
            const list = rawList.filter(u => u && typeof u === 'object' && 'id' in u)
            updateOnlineUsers(list)
        }

        const offJoined = (user) => {
            addOnlineUser(user)
        }

        const offLeft = (user) => {
            removeOnlineUser(user)
        }

        eventBus.on('users.here', offHere)
        eventBus.on('users.joined', offJoined)
        eventBus.on('users.left', offLeft)

        return () => {
            eventBus.off('users.here', offHere)
            eventBus.off('users.joined', offJoined)
            eventBus.off('users.left', offLeft)
        }
    }

    return {
        onlineUserIds: computed(() => onlineUserIds.value),
        isUserOnline,
        setupListeners,
    }
}
