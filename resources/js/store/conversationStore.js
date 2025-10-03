// conversationStore.js - Clean data-only store
import { defineStore } from 'pinia';
import { getConversation, storeConversationReply, storeUserSearch } from '@/api/all.js';

export const useConversationStore = defineStore('conversation', {
    state: () => ({
        conversation: null,
        loading: false,
        submittingReply: false,
        messages: [],
    }),

    getters: {
        currentConversation: (state) => state.conversation,
        loadingConversation: (state) => state.loading,
        isLoadingConversation: (state) => state.loading,
        isSubmittingReply: (state) => state.submittingReply,
        hasCurrentConversation: (state) => !!state.conversation,
        currentConversationId: (state) => state.conversation?.id || null,
    },

    actions: {
        setMessages(messages) {
            this.messages = messages
        },
        addMessage(message) {
            //this.messages.push(message);
            // console.log('new message added', message)
            this.messages = [...this.messages, message];
        },
        async fetchConversation(id) {
            this.loading = true;
            try {
                const response = await getConversation(id);
                // console.log('fetchConversation', response);
                this.conversation = response.data;
                // console.log('store', this.conversation);

                if (window.Echo && window.App?.user?.id) {
                    const channel = window.Echo.private(`user.${window.App.user.id}`)

                    channel
                        .listen('Conversation.ConversationCreated', (e) => {
                            console.log('ConversationCreated', e)
                            // store.dispatch('getConversation', e.data.id, true)
                            // user.value = e.data.user.data
                        })
                        .listen('Conversation.ConversationReplyCreated', (e) => {
                            console.log('ConversationReplyCreated', e)
                            // store.dispatch('getConversation', e.data.parent.data.id, true)
                            // user.value = e.data.user.data
                        })
                        .listen('Conversation.ConversationUsersCreated', (e) => {
                            console.log('ConversationUsersCreated', e)
                        })
                }

                //this.setMessages(response.data.messages);

                return response.data;
            } catch (error) {
                console.error('Failed to fetch conversation:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createConversationReply(payload) {
            // console.log('payload!!!!', payload);
            if (!payload.uuid || !payload.body?.trim()) {
                throw new Error('Invalid payload for creating reply');
            }

            this.submittingReply = true;
            try {
                const response = await axios.post(`/api/conversations/${payload.uuid}/reply`, {
                    body: payload.body.trim()
                });

                // console.log('add message', this.currentConversation)
                // Update current conversation with new reply
                if (this.currentConversation && this.currentConversation.uuid == payload.uuid) {
                    //console.log('add message', this.currentConversation.messages)
                    // Ensure replies structure exists
                    if (!this.currentConversation.messages) {
                        this.currentConversation.messages = [];
                    }

                    // Add new reply to the conversation
                    // this.currentConversation.messages.push(response.data);
                    this.currentConversation.messages.unshift(response.data);
                    //console.log('add message doen?', this.currentConversation.messages)
                }

                this.addMessage(response.data);

                return response.data;
            } catch (error) {
                console.error('Error creating reply:', error);
                throw new Error(error.response?.data?.message || 'Failed to send reply');
            } finally {
                this.submittingReply = false;
            }
        },

        async addReply(body) {
            if (!this.conversation) return;

            try {
                const reply = await storeConversationReply(this.conversation.id, body);
                return reply;
            } catch (error) {
                console.error('Failed to add reply:', error);
                throw error;
            }
        },

        async searchUsers(query) {
            console.log('searchUsers');
            try {
                const users = await storeUserSearch(query);
                return users;
            } catch (error) {
                console.error('Failed to search users:', error);
                throw error;
            }
        },

        async addUserToConversation(conversationId, userId) {
            try {
                const response = await axios.post(`/api/conversations/${conversationId}/users`, {
                    recipients: userId
                });
                return response.data;
            } catch (error) {
                console.error('Error adding user to conversation:', error);
                throw new Error(error.response?.data?.message || 'Failed to add user to conversation');
            }
        },

        // Pure state mutations (called by components after Echo events)
        appendReplyToConversation(reply) {
            if (this.conversation && this.conversation.replies) {
                if (!this.conversation.replies.data) {
                    this.conversation.replies.data = [];
                }
                this.conversation.replies.data.unshift(reply);
            }
        },

        updateConversationUsers(users) {
            if (this.conversation && this.conversation.users) {
                this.conversation.users.data = users;
            }
        },

        prependReplyToConversation(reply) {
            if (this.conversation && this.conversation.replies) {
                if (!this.conversation.replies.data) {
                    this.conversation.replies.data = [];
                }
                this.conversation.replies.data.unshift(reply);
            }
        },

        clearCurrentConversation() {
            this.conversation = null;
        },
    },
});
