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
        async fetchConversations(page = 1) {
            this.loading = true;
            try {
                const response = await getConversations(page);

                // Echo.private('user.' + 2)
                //     .listen('ConversationCreated', (e) => {
                //         console.log('ConversationCreated', e)
                //         //commit('prependToConversations', e.data)
                //     })
                //     .listen('ConversationReplyCreated', (e) => {
                //         // commit('prependToConversations', e.data.parent.data)
                //     })
                //     .listen('ConversationUsersCreated', (e) => {
                //         // commit('updateConversationInList', e.data)
                //     })


                if (window.Echo && window.App?.user?.id) {
                    const channel = window.Echo.private(`user.${window.App.user.id}`)

                    channel
                        .listen('Conversation.ConversationCreated', (e) => {
                            // console.log('ConversationCreated conversationsStore.js', e)
                            // store.dispatch('getConversation', e.data.id, true)
                            // user.value = e.data.user.data
                        })
                        .listen('Conversation.MessageAdded', (e) => {
                            // console.log('ConversationReplyCreated', e)
                            // store.dispatch('getConversation', e.data.parent.data.id, true)
                            // user.value = e.data.user.data
                        })
                        .listen('Conversation.ConversationUsersCreated', (e) => {
                            // console.log('ConversationUsersCreated', e)
                        })
                }

                // Check if response is paginated (Laravel pagination has data.data structure)
                if (response.data && response.data.data && Array.isArray(response.data.data)) {
                    this.conversations = response.data.data;
                } else if (Array.isArray(response.data)) {
                    this.conversations = response.data;
                } else {
                    console.warn('Unexpected conversation response structure:', response.data)
                    this.conversations = [];
                }
            } catch (error) {
                console.error('Failed to fetch conversations:', error);
                this.conversations = [];
            } finally {
                this.loading = false;
            }
        },
    },
});
