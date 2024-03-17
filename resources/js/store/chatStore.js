// store/chatStore.js
import { defineStore } from 'pinia';
// import { pusher } from 'pusher-js'

export const useChatStore = defineStore({
    id: 'chat',
    state: () => ({
        messages: []
    }),
    actions: {
        setMessages(messages) {
            this.messages = messages
        },
        addMessage(message) {
            //this.messages.push(message);
            console.log('new message added', message)
            this.messages = [...this.messages, message];
        }
    }
});
