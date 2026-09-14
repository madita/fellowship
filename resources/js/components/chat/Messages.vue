<template>
    <div id="messages" ref="messageContainer">
        <loading-state v-if="loading" />
        <empty-state
            v-else-if="!messages.length"
            icon="mdi-chat-outline"
            :title="$t('chat.noMessages')"
        />
        <template v-else>
            <chat-message
                v-for="message in messages"
                :key="message.id"
                :message="message"
                class="my-4 mr-5"
            />
        </template>
    </div>
</template>

<script>
import {ref, computed, onMounted,onUpdated, watch} from 'vue';
import { useI18n } from 'vue-i18n';
import ChatMessage from './Message.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import {useChatStore} from "@/store/chatStore.js";
import { useDialog } from '@/composables/useDialog.js';

export default {
    components: {
        ChatMessage,
        EmptyState,
        LoadingState
    },
    setup() {
        const { t } = useI18n();
        const dialog = useDialog();
        const chatStore = useChatStore();

        const messageContainer = ref(null);
        const loading = ref(true);

        const messages = computed(() => {
            if (chatStore?.messages === []) {
                return [];
            }
            return chatStore.messages;
        });

        const removeMessage = (id) => {
            messages.value = messages.value.filter((message) => message.id !== id);
        };

        const scrollToEnd = () => {
            if (messageContainer.value) {
                messageContainer.value.scrollTop = messageContainer.value.scrollHeight;
            }
        }

        watch(
            () => messages.value,
            () => {
                if (chatStore.messages !== []) {
                    scrollToEnd()
                }
            },
            { deep: true }
        );

        onUpdated( () => {
            scrollToEnd()
        })

        onMounted(() => {
            // Load messages
            axios.get('/api/chat/messages').then((response) => {
                chatStore.setMessages(response.data)
            }).catch((error) => {
                dialog.requestError(error, t('chat.loadFailed'));
            }).finally(() => {
                loading.value = false;
            });

            scrollToEnd()
        });

        return {
            messages,
            loading,
            messageContainer,
            scrollToEnd,
            removeMessage
        }
    }
}
</script>

<style lang="scss">
#messages {
    height: calc(100vh - 360px);
    min-height: 320px;
    overflow-y: auto;
    background: rgb(var(--v-theme-surface)) !important;
}
</style>
