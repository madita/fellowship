<template>
    <div class="chat-page">
        <page-header
            :title="$t('chat.title')"
            :subtitle="$t('chat.subtitle')"
            icon="mdi-chat-outline"
            fluid
            class="chat-page__header mb-0"
        />

        <v-container fluid class="chat-container pa-0">
            <div class="d-flex">
                <div class="flex-grow-1 chat-main">
                    <perfect-scrollbar class="chat-scroll">
                        <ChatMessages></ChatMessages>
                    </perfect-scrollbar>
                </div>
                <div class="right-sidebar">
                    <v-sheet class="chat-scroll">
                        <Users></Users>
                    </v-sheet>
                </div>
            </div>

            <v-divider />

            <!---Chat send-->
            <form class="w-100 d-flex align-center ga-2 pa-4" @submit.prevent="handleMessageInput()">
                <v-text-field
                    v-model="body"
                    hide-details
                    density="compact"
                    :placeholder="$t('chat.typeMessage')"
                />
                <v-btn
                    icon="mdi-send"
                    variant="text"
                    color="primary"
                    type="submit"
                    :title="$t('chat.sendMessage')"
                    :disabled="!body"
                    :loading="sending"
                />
            </form>
        </v-container>
    </div>
</template>

<script>
import {onMounted, ref} from 'vue';
import { useI18n } from 'vue-i18n';
import ChatMessages from './Messages.vue';
import Users from './Users.vue';
import PageHeader from '../common/PageHeader.vue';
import { useUserStore } from "@/store/userStore.js";
import { useChatStore } from '@/store/chatStore';
import {useOnlineUsersStore} from "@/store/onlineUsersStore.js";
import { useDialog } from '@/composables/useDialog.js';
const { onlineUsersStore } = useOnlineUsersStore();

export default {
    components: {
        Users,
        ChatMessages,
        PageHeader
    },
    setup() {
        const { t } = useI18n();
        const dialog = useDialog();
        const body = ref('');
        const bodyBackedUp = ref('');
        const sending = ref(false);
        const usersDrawer = ref(true);
        const userStore = useUserStore();

        const chatStore = useChatStore();
        const onlineUsersStore = useOnlineUsersStore();

        const handleMessageInput = () => {
            bodyBackedUp.value = body.value;
            send()
        }

        const buildTempMessage = () => {
            let tempId = Date.now();
            return {
                id: tempId,
                body: body.value,
                selfOwned: true,
                user: {
                    username: userStore.user.username
                }
            }
        }

        const send = async () => {
            if (!body.value || body.value.trim() === '' || sending.value) {
                return;
            }
            const text = body.value.trim();
            sending.value = true;
            body.value = '';
            try {
                const response = await axios.post('/api/chat/messages', { body: text });
                chatStore.addMessage(response.data);
            } catch (error) {
                body.value = bodyBackedUp.value;
                await dialog.requestError(error, t('chat.sendFailed'));
            } finally {
                sending.value = false;
            }
        };

        onMounted(() => {
            Echo.join('chat')
                .here((users) => {
                    onlineUsersStore.setUsers(users)
                })
                .joining((user) => {
                    onlineUsersStore.addUser(user)
                })
                .leaving((user) => {
                    onlineUsersStore.removeUser(user)
                })
                .listen('.message-created', (e) => {
                    chatStore.addMessage(e.message);
                })
        })

        return {
            body,
            sending,
            send,
            handleMessageInput,
            user: userStore.user
        }
    }
}
</script>

<style lang="scss">
.chat-page__header {
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.chat-container {
    background: rgb(var(--v-theme-surface)) !important;
}

.chat-main {
    min-width: 0;
}

.chat-scroll {
    height: calc(100vh - 360px);
    min-height: 320px;
}

.right-sidebar {
    width: 320px;
    border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    transition: 0.1s ease-in;
    flex-shrink: 0;
    background: rgb(var(--v-theme-surface)) !important;
}

@media (max-width: 960px) {
    .right-sidebar {
        display: none;
    }
}
</style>
