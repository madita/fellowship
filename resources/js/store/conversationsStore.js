// conversationsStore.js
import { defineStore } from 'pinia';
import { getConversations } from '@/api/all.js';

export const useConversationsStore = defineStore('conversations', {
    state: () => ({
        conversations: [],
        loading: false,
    }),

    getters: {
        allConversations: (state) => state.conversations,
        isLoading: (state) => state.loading,
    },

    actions: {
        async fetchConversations() {
            this.loading = true;
            try {
                const data = await getConversations();
                console.log('condata', data.data.data);
                this.conversations = data.data.data;
            } catch (error) {
                console.error('Failed to fetch conversations:', error);
            } finally {
                this.loading = false;
            }
        },
    },
});
