<template>
    <div class="conversation-container">
        <!-- Loading State -->
        <div v-if="loading" class="text-center py-12">
            <v-progress-circular
                indeterminate
                color="primary"
                size="64"
            ></v-progress-circular>
            <p class="mt-4 text-body-1 text-medium-emphasis">{{ $t('conversation.loadingConversation') }}</p>
        </div>

        <!-- Conversation Content -->
        <v-container v-else-if="conversation" fluid class="pa-0">

            <!-- Conversation Header -->
            <v-card class="mb-4" elevation="2">
                <v-card-title class="bg-gradient text-white">
                    <v-icon class="mr-3">mdi-chat</v-icon>
                    {{ $t('conversation.conversationDetails') }}
                </v-card-title>


            </v-card>

            <v-card elevation="2">
                <v-card-title class="v-col-6 text-h6 font-weight-medium">
                    <v-icon class="mr-2">mdi-message-text</v-icon>
                    {{ $t('conversation.messages') }}
                    <v-spacer></v-spacer>
                    <v-chip
                        v-if="messages?.length"
                        size="small"
                        color="info"
                        variant="outlined"
                        class="mr-2"
                    >
                        {{ $t('conversation.messagesCount', { count: messages.length }) }}
                    </v-chip>

                    <!-- Add User Dropdown in title -->
                    <v-menu :close-on-content-click="false">
                        <template v-slot:activator="{ props }">
                            <v-btn
                                v-bind="props"
                                variant="outlined"
                                size="small"
                                prepend-icon="mdi-account-plus"
                            >
                                {{ $t('conversation.addParticipant') }}
                            </v-btn>
                        </template>
                        <v-card min-width="300">
                            <v-card-text>
                                <ConversationAddUserForm />
                            </v-card-text>
                        </v-card>
                    </v-menu>

                </v-card-title>

                <v-divider></v-divider>

            <conversation-messages :id="id" :messages="messages" :loading="loading" />

                </v-card>

            <!-- Reply Form -->
            <v-card style="padding-bottom: 20px;" class="mb-4" elevation="2">
                <v-card-title class="text-h6 font-weight-medium">
                    <v-icon class="mr-2">mdi-reply</v-icon>
                    {{ $t('conversation.sendReply') }}
                </v-card-title>
                <v-card-text>
                    <ConversationReplyForm />
                </v-card-text>
            </v-card>
        </v-container>

        <!-- Empty State -->
        <div v-else class="text-center py-12">
            <v-icon size="80" color="grey-lighten-2">mdi-chat-question-outline</v-icon>
            <h3 class="text-h5 font-weight-medium mt-4 mb-2">{{ $t('conversation.noConversationSelected') }}</h3>
            <p class="text-body-1 text-medium-emphasis">
                {{ $t('conversation.selectConversationHint') }}
            </p>
        </div>
    </div>
</template>

<script>
import { useI18n } from 'vue-i18n';
import { useConversationStore } from "@/store/conversationStore";
import { computed, ref, watch, onUnmounted } from "vue";
import ConversationAddUserForm from "@/components/conversation/forms/ConversationAddUserForm.vue";
import ConversationReplyForm from "@/components/conversation/forms/ConversationReplyForm.vue";
import ConversationMessages from "@/components/conversation/ConversationMessages.vue";
import UserAvatar from "@/components/common/UserAvatar.vue";
import { useEchoListener } from "@/composables/conversation/useEchoListener";
import { useScrollToBottom } from "@/composables/conversation/useScrollToBottom";

export default {
    name: "ConversationComponent",
    props: {
        id: {
            type: [String, Number],
            default: null
        }
    },
    components: {
        ConversationAddUserForm,
        ConversationReplyForm,
        ConversationMessages,
        UserAvatar
    },
    setup(props) {
        const { t } = useI18n();
        const conversationStore = useConversationStore();
        const conversation = computed(() => conversationStore.currentConversation);
        const loading = computed(() => conversationStore.loadingConversation);
        const messages = computed(() => conversationStore.messages || []);
        const messageContainer = ref(null);

        const DEBUG = false;

        // Use composables - destructure cleanup function
        const { setupListener, cleanup } = useEchoListener(DEBUG);
        const { scrollToBottom } = useScrollToBottom(messageContainer);

        const setupEchoListeners = (conversationId) => {
            // Cleanup previous listeners before setting up new ones
            // Note: useEchoListener already handles this internally, but we're being explicit
            const channelName = `conversations.${conversationId}`;
            setupListener(channelName, 'Conversations\\MessageAdded', (e) => {
                if (DEBUG) console.log('MessageAdded:', e);
                conversationStore.addMessage(e.message);
                scrollToBottom();
            });
        };

        // React to conversation id changes (including initial mount)
        watch(
            () => props.id,
            async (newId, oldId) => {
                if (!newId || newId === oldId) return;
                if (DEBUG) console.log('Conversation ID changed:', { newId, oldId });
                try {
                    conversationStore.setMessages([]);
                    // setupListener in useEchoListener will automatically leave the previous channel
                    setupEchoListeners(newId);

                    // fetchConversation now handles setting messages automatically
                    await conversationStore.fetchConversation(newId);

                    scrollToBottom();
                } catch (err) {
                    console.error('Failed to load conversation/messages', err);
                }
            },
            { immediate: true }
        );

        // Ensure cleanup happens when component is destroyed
        onUnmounted(() => {
            if (DEBUG) console.log('Conversation component unmounting, cleaning up listeners');
            cleanup();
        });

        return {
            t,
            conversation,
            loading,
            messages,
            messageContainer,
        };
    },
};
</script>

<style scoped>
@import './styles.css';

.messages-wrapper {
    padding-bottom: 20px;
}
</style>
