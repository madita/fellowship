import { ref } from 'vue';
import axios from 'axios';
import { useUserStore } from '@/store/userStore.js';

/**
 * Members a ticket can be assigned to, for the assignee pickers.
 * The member search leaves out the current user, who is added first.
 */
export function useAssignableUsers() {
    const userStore = useUserStore();
    const assignableUsers = ref([]);

    const loadAssignableUsers = async () => {
        try {
            const response = await axios.post('/api/users/search', { query: '' });
            const records = Array.isArray(response.data) ? response.data : [];
            const me = userStore.user;
            assignableUsers.value = [
                ...(me?.id ? [{ id: me.id, username: me.username, avatar: me.avatar }] : []),
                ...records.filter(u => u.id !== me?.id).map(u => ({ id: u.id, username: u.username, avatar: u.avatar })),
            ];
        } catch (err) {
            console.error('Failed to load assignable users:', err);
        }
    };

    return { assignableUsers, loadAssignableUsers };
}
