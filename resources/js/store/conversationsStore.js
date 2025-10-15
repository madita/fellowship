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
                const response = await getConversations();
                console.log('response', response)

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

                this.conversations = response.data;
            } catch (error) {
                console.error('Failed to fetch conversations:', error);
            } finally {
                this.loading = false;
            }
        },
    },
});
