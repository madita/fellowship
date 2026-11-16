import { ref, onUnmounted } from 'vue'
import axios from 'axios'

/**
 * Composable for debounced user search
 * @param {number} debounceMs - Debounce delay in milliseconds
 * @returns {Object} - Object with search methods and state
 */
export function useUserSearch(debounceMs = 500) {
    const userList = ref([])
    const userSearch = ref('')
    const loadingUsers = ref(false)
    const debounceTimer = ref(null)

    const handleUserSearch = (query) => {
        userSearch.value = query

        if (!query || query.length < 2) {
            userList.value = []
            return
        }

        // Clear existing timer
        if (debounceTimer.value) {
            clearTimeout(debounceTimer.value)
        }

        // Set new timer for debounced search
        debounceTimer.value = setTimeout(async () => {
            await searchUsers(query)
        }, debounceMs)
    }

    const searchUsers = async (query) => {
        loadingUsers.value = true

        try {
            const response = await axios.post('/api/users/search', {
                query: query,
            })
            userList.value = response.data
        } catch (error) {
            console.error('Error searching users:', error)
            userList.value = []
        } finally {
            loadingUsers.value = false
        }
    }

    const clearSearch = () => {
        userList.value = []
        userSearch.value = ''
        loadingUsers.value = false
    }

    // Cleanup
    onUnmounted(() => {
        if (debounceTimer.value) {
            clearTimeout(debounceTimer.value)
        }
    })

    return {
        userList,
        userSearch,
        loadingUsers,
        handleUserSearch,
        searchUsers,
        clearSearch
    }
}
