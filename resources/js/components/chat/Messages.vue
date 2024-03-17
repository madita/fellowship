<template>


<!--        <perfect-scrollbar >-->
            <div id="messages" ref="messageContainer" style="height: calc(100vh - 290px)">
            <chat-message
                v-for="message in messages"
                :key="message.id"
                :message="message"
                class="my-4 mr-5"
            />
            </div>
<!--        </perfect-scrollbar>-->
<!--        <transition-group name="list">-->

<!--        </transition-group>-->

</template>

<script>
// import EventBus from '@/bus.js'
import {ref, computed, onMounted,onUpdated, watch} from 'vue';
import ChatMessage from './Message.vue';
import {useChatStore} from "@/store/chatStore.js";


export default {
    components: {
        ChatMessage
    },
    setup() {
        const chatStore = useChatStore();

        const messageContainer = ref(null);

        const messages = computed(() => {
            if (chatStore.messages === []) {
                return [];
            }
            return chatStore.messages;
        });

        const removeMessage = (id) => {
            messages.value = messages.value.filter((message) => message.id !== id);
        };

        const scrollToEnd = () => {
            console.log('call scroll')

            // const container = messageContainer.value;
            //
            console.log('messageContainer.value',messageContainer.value.scrollHeight)
            // console.log('scrolltop',container.scrollTop)
            // console.log('scrollheight',container.scrollHeight)
            if (messageContainer.value) {
                console.log('whyyyyy')
                messageContainer.value.scrollTop = messageContainer.value.scrollHeight;
            }
        }

        // Watch for changes in messages array to trigger scrolling
        // watch(messages, scrollToEnd, {deep: true});

        watch(
            () => messages.value,
            () => {
                if (chatStore.messages !== []) {
                    scrollToEnd()
                }
            },
            { deep: true }
        );

        // watch(() => chatStore.messages, (newMessages, oldMessages) => {
        //     console.log('Messages changed!', newMessages);
        //     scrollToEnd();
        // });

        // watch(
        //     () => chatStore.messages,
        //     () => {
        //         scrollToEnd()
        //         //this.messages = chatStore.messages;
        //     },
        // )

        onUpdated( () => {
            scrollToEnd()
        })

        onMounted(() => {
            // Load messages
            axios.get('/api/chat/messages').then((response) => {
                // messages = response.data;
                chatStore.setMessages(response.data)
                //this.messages = chatStore.messages
            });

            scrollToEnd()

            /* EventBus integration if required
            EventBus.on('message.added', (message) => {
                messages.value.push(message);
            });
            EventBus.on('message.removed', (message) => {
                removeMessage(message.id);
            });
            */
        });

        return {
            messages,
            messageContainer,
            scrollToEnd,
            removeMessage
        }
    }
}
</script>

<style lang="scss">
#messages {
    overflow-y: scroll;
}
.chat {
    &__messages {
        height: 400px;
        max-height: 400px;
        overflow-y: scroll;
    }
}
</style>
