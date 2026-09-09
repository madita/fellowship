<template>
    <v-menu location="bottom end" transition="slide-y-transition">

        <template v-slot:activator="{ props }">
            <v-badge
                :content="unreadCount"
                :model-value="unreadCount > 0"
                color="error"
                offset-x="22"
                offset-y="22"
                v-bind="props"
            >
                <v-btn icon="mdi-message-outline" variant="text" />
            </v-badge>
        </template>

        <!-- Conversations menu list -->
        <v-card
            class="mx-auto"
            min-width="350"
            max-width="450"
        >
            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center ga-2">
                <v-icon>mdi-message-text</v-icon>
                {{ $t('conversation.messages') }}
            </v-card-title>

            <v-divider></v-divider>

            <loading-state v-if="loading" compact />

            <empty-state
                v-else-if="recentConversations.length === 0"
                compact
                icon="mdi-message-outline"
                :title="$t('conversation.noRecentMessages')"
            />

            <v-list v-else class="py-0" max-height="400" style="overflow-y: auto;">
                <template v-for="(conversation, index) in recentConversations" :key="conversation.uuid">
                    <v-list-item
                        @click="openConversation(conversation)"
                        class="conversation-item"
                        :class="{ 'unread': hasUnreadMessages(conversation) }"
                    >
                        <template v-slot:prepend>
                            <user-avatar :user="getOtherUser(conversation)"></user-avatar>
                        </template>

                        <v-list-item-title class="text-subtitle-2 font-weight-medium">
                            {{ getConversationTitle(conversation) }}
                        </v-list-item-title>

                        <v-list-item-subtitle class="text-caption conversation-item__preview">
                            {{ conversation.body || $t('conversation.newConversation') }}
                        </v-list-item-subtitle>

                        <template v-slot:append>
                            <div class="d-flex flex-column align-end">
                                <span class="text-caption text-medium-emphasis">
                                    {{ conversation.created_at_human }}
                                </span>
                                <v-badge
                                    v-if="hasUnreadMessages(conversation)"
                                    color="error"
                                    :content="getUnreadCount(conversation)"
                                    inline
                                    class="mt-1"
                                ></v-badge>
                            </div>
                        </template>
                    </v-list-item>

                    <v-divider v-if="index < recentConversations.length - 1"></v-divider>
                </template>
            </v-list>

            <v-divider></v-divider>

            <div class="text-center py-2">
                <v-btn
                    @click="goToAllConversations"
                    variant="text"
                    size="small"
                    color="primary"
                >
                    {{ $t('conversation.seeAllMessages') }}
                </v-btn>
            </div>
        </v-card>
    </v-menu>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useConversationsStore } from "@/store/conversationsStore.js";
import { useUserStore } from '@/store/userStore.js';
import eventBus from '../common/eventBus.js';
import UserAvatar from "@/components/common/UserAvatar.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import LoadingState from "@/components/common/LoadingState.vue";

export default {
    components: {UserAvatar, EmptyState, LoadingState},
    setup() {
        const router = useRouter();
        const conversationsStore = useConversationsStore();
        const userStore = useUserStore();

        const conversations = computed(() => conversationsStore.allConversations || []);
        const loading = computed(() => conversationsStore.isLoading);

        // Show only recent 5 conversations
        const recentConversations = computed(() => conversations.value.slice(0, 5));

        // Calculate total unread count from API data
        const unreadCount = computed(() => {
            return conversations.value.reduce((total, conv) => {
                return total + (conv.unread_count || 0);
            }, 0);
        });

        const getOtherUser = (conversation) => {
            if (!conversation.users || conversation.users.length === 0) return null;

            const currentUserId = userStore.user?.id || window.App?.user?.id;
            const otherUser = conversation.users.find(u => u.id !== currentUserId);

            return otherUser || conversation.users[0];
        };

        const getConversationTitle = (conversation) => {
            const otherUser = getOtherUser(conversation);

            if (otherUser) {
                return otherUser.username || otherUser.email || 'Unknown User';
            }

            // If group conversation
            if (conversation.users && conversation.users.length > 2) {
                return `${conversation.users.length} participants`;
            }

            return 'Conversation';
        };

        const hasUnreadMessages = (conversation) => {
            return conversation.is_unread === true || (conversation.unread_count && conversation.unread_count > 0);
        };

        const getUnreadCount = (conversation) => {
            return conversation.unread_count || 0;
        };

        const openConversation = async (conversation) => {
            // Open the chat box with this conversation
            const otherUser = getOtherUser(conversation);
            console.log('[ConversationsNotification] Opening conversation:', {
                uuid: conversation.uuid,
                otherUser,
                conversation
            });
            if (otherUser) {
                eventBus.emit('chat.open', {
                    user: otherUser,
                    conversationUuid: conversation.uuid
                });
                console.log('[ConversationsNotification] Emitted chat.open event with user:', otherUser);

                // Refresh conversation list after a short delay to update unread counts
                setTimeout(() => {
                    conversationsStore.fetchConversations();
                }, 10);
            }
        };

        const goToAllConversations = () => {
            router.push({ name: 'conversations' });
        };

        onMounted(() => {
            // Fetch conversations on mount
            conversationsStore.fetchConversations();

            // Note: Echo listeners are centralized in useConversationRealtime (ConversationBoxManager)
            // This prevents channel subscription conflicts and handles both:
            // - Auto-opening chat box when messages arrive
            // - Refreshing conversations list to update notification badge
        });

        onUnmounted(() => {
            // No cleanup needed - Echo listeners managed by useConversationRealtime
        });

        return {
            conversations,
            recentConversations,
            loading,
            unreadCount,
            getOtherUser,
            getConversationTitle,
            hasUnreadMessages,
            getUnreadCount,
            openConversation,
            goToAllConversations,
        };
    },
};
</script>

<style scoped>
.conversation-item {
    cursor: pointer;
    transition: background-color 0.2s;
}

.conversation-item:hover {
    background-color: rgba(var(--v-theme-on-surface), 0.04);
}

.conversation-item.unread {
    background-color: rgba(var(--v-theme-primary), 0.05);
}

.conversation-item.unread .v-list-item-title {
    font-weight: 700;
}

.conversation-item__preview {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 250px;
}
</style>
