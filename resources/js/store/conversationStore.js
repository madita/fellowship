// conversationStore.js
import { defineStore } from 'pinia';
import {getConversation, storeConversationReply, storeUserSearch} from '@/api/all.js';

export const useConversationStore = defineStore('conversation', {
    state: () => ({
        conversation: null,
        loading: false,
    }),

    getters: {
        currentConversation: (state) => state.conversation,
        loadingConversation: (state) => state.loading,
        isLoadingConversation: (state) => state.loadingConversation,
        isSubmittingReply: (state) => state.submittingReply,
        hasCurrentConversation: (state) => !!state.currentConversation,
        currentConversationId: (state) => state.currentConversation?.id || null,
    },

    actions: {
        async fetchConversation(id) {
            this.loading = true;
            try {
                const data = await getConversation(id);
                this.conversation = data;
                console.log('store', this.conversation)
            } catch (error) {
                console.error('Failed to fetch conversation:', error);
            } finally {
                this.loading = false;
            }
        },

        async createConversationReply(payload) {
            console.log('payload', payload);
            if (!payload.uuid || !payload.body?.trim()) {
                throw new Error('Invalid payload for creating reply');
            }

            this.submittingReply = true;
            try {
                const response = await axios.post(`/api/conversations/${payload.uuid}/reply`, {
                    body: payload.body.trim()
                });

                // Update current conversation with new reply
                if (this.currentConversation && this.currentConversation.id == payload.id) {
                    // Ensure replies structure exists
                    if (!this.currentConversation.replies) {
                        this.currentConversation.replies = { data: [] };
                    }

                    // Add new reply to the conversation
                    this.currentConversation.replies.data.push(response.data);
                }

                return response.data;
            } catch (error) {
                console.error('Error creating reply:', error);
                throw new Error(error.response?.data?.message || 'Failed to send reply');
            } finally {
                this.submittingReply = false;
            }
        },

        clearCurrentConversation() {
            this.currentConversation = null;
        },

        async addReply(body) {
            if (!this.conversation) return;

            try {
                const reply = await storeConversationReply(this.conversation.id, body);
                this.conversation.replies.data.unshift(reply); // Add reply to the list
            } catch (error) {
                console.error('Failed to add reply:', error);
            }
        },

        async searchUsers(query) {
            //if (!this.conversation) return;

            console.log('searchUsers')

            try {
                const users = await storeUserSearch(query);
               // this.conversation.users.data.unshift(reply); // Add reply to the list
            } catch (error) {
                console.error('Failed to add reply:', error);
            }
        },
        // In conversationStore.js, add this action:
        async addUserToConversation(conversationId, userId) {
            try {
                const response = await axios.post(`/api/conversations/${conversationId}/users`, {
                    recipients: userId
                });

                // Update the current conversation if it matches
                if (this.currentConversation?.data?.data?.id == conversationId) {
                    // Ensure users array exists
                    if (!this.currentConversation.data.data.users) {
                        this.currentConversation.data.data.users = [];
                    }

                    // Add the new user to the conversation
                    this.currentConversation.data.data.users.push(response.data);
                }

                return response.data;
            } catch (error) {
                console.error('Error adding user to conversation:', error);
                throw new Error(error.response?.data?.message || 'Failed to add user to conversation');
            }
        }

    },
});
