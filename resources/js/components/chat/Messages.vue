<template>

        <div id="messages" ref="messages" class="messages mx-2">
            <transition-group name="list">
                <chat-message
                    v-for="message in messages"
                    :key="message.id"
                    :message="message"
                    class="my-4 d-flex"
                />
            </transition-group>
        </div>

</template>

<script>
    import Bus from '../../bus'
    import ChatMessage from './Message'

    export default {
        components: {
            ChatMessage
        },
        data(){
            return {
                messages: null
            }
        },
        updated() {
            this.$nextTick(() => this.scrollToEnd());
        },
        methods: {
            removeMessage(id) {
                this.messages = this.messages.filter((message) => {
                    return message.id !== id;
                });
            },
            scrollToEnd: function () {
                var content = this.$refs.messages;
                content.scrollTop = content.scrollHeight
            },
        },
        mounted () {
            axios.get('/api/chat/messages').then((response) => {
                this.messages = response.data
            });

            Bus.$on('message.added', (message) => {
                this.messages.push(message);

            }).$on('message.removed', (message) => {
                this.removeMessage(message.id);
            });
        }
    }
</script>

<style lang="scss">
    .chat {
        &__messages {
            height: 400px;
            max-height: 400px;
            overflow-y: scroll;
        }
    }
</style>
