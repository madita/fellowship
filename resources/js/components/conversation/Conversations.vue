<template>
    <v-container fluid class="pa-0">
        <v-card elevation="2" rounded="lg" class="mx-auto" max-width="100%">
            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center ga-2 bg-gradient">
                <v-icon>mdi-chat-outline</v-icon>
                {{ $t('conversation.allConversations') }}
            </v-card-title>

            <v-divider></v-divider>

            <v-card-text class="pa-0">
                <!-- Loading State -->
                <loading-state v-if="loading" :text="$t('conversation.loading')" />

                <!-- Conversations List -->
                <div v-else-if="conversations.length > 0" class="conversation-list">
                    <v-hover v-for="conversation in conversations" :key="conversation.id">
                        <template v-slot:default="{ isHovering, props }">
                            <v-card
                                v-bind="props"
                                :elevation="isHovering ? 4 : 0"
                                rounded="lg"
                                class="conversation-card ma-2 transition-all"
                                :class="{
                  'hover-effect': isHovering,
                  'selected-conversation': selectedId && conversation.uuid === selectedId
                }"
                                @click="getConversation(conversation.uuid)"
                                ripple
                            >
                                <v-card-text class="pa-4">
                                    <div class="d-flex align-start">
                                        <!-- Content -->
                                        <div class="flex-grow-1">
                                            <h3 class="text-h6 font-weight-medium mb-2 conversation-title">
                                                {{ conversation.body }}
                                            </h3>

                                            <p class="text-body-2 text-medium-emphasis mb-3">
                                                <v-icon size="16" class="mr-1">mdi-account-group</v-icon>
                                                {{ $t('conversation.youAnd') }} {{ conversation.participant_count }}
                                                {{ $t('pluralize.other', conversation.participant_count) }}
                                            </p>

                                            <div class="d-flex flex-wrap ga-2">
                                                <v-chip
                                                    size="small"
                                                    color="success"
                                                    variant="tonal"
                                                    prepend-icon="mdi-clock-plus-outline"
                                                >
                                                    {{ $t('conversation.started') }} {{ conversation.created_at_human }}
                                                </v-chip>

                                                <v-chip
                                                    size="small"
                                                    color="info"
                                                    variant="tonal"
                                                    prepend-icon="mdi-clock-outline"
                                                >
                                                    {{ $t('conversation.lastReply') }} {{ conversation.last_reply_human }}
                                                </v-chip>
                                            </div>
                                            <!-- Avatar Group -->
                                            <div class="avatar-group d-flex flex-wrap ga-1 mt-3">
                                                    <user-avatar
                                                        v-for="user in conversation.users || []"
                                                        :key="`user-${user.id}`"
                                                        :user="user"
                                                        size="40"
                                                    />
                                            </div>
                                        </div>

                                        <!-- Action / New badge -->
                                        <div class="d-flex align-center ga-2">
                                            <v-chip
                                                v-if="newCounts[conversation.uuid] > 0"
                                                size="small"
                                                color="error"
                                                variant="elevated"
                                                prepend-icon="mdi-bell-badge-outline"
                                            >
                                                {{ newCounts[conversation.uuid] }} {{ $t('conversation.new') }}
                                            </v-chip>
                                            <v-btn
                                                icon="mdi-chevron-right"
                                                variant="text"
                                                size="small"
                                                color="primary"
                                            />
                                        </div>
                                    </div>
                                </v-card-text>
                            </v-card>
                        </template>
                    </v-hover>
                </div>

                <!-- Empty State -->
                <empty-state
                    v-else
                    icon="mdi-chat-outline"
                    :title="$t('conversation.noConversationsYet')"
                    :text="$t('conversation.startToSee')"
                >
                    <template #actions>
                        <v-btn
                            color="primary"
                            variant="elevated"
                            prepend-icon="mdi-plus"
                        >
                            {{ $t('conversation.startNew') }}
                        </v-btn>
                    </template>
                </empty-state>
            </v-card-text>
        </v-card>
    </v-container>
</template>

<script>
import { useConversationsStore } from "@/store/conversationsStore";
import { onMounted, onUnmounted, computed, ref, watch } from "vue";
import UserAvatar from "@/components/common/UserAvatar.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import LoadingState from "@/components/common/LoadingState.vue";

export default {
    name: "ConversationsList",
    components: {
        UserAvatar,
        EmptyState,
        LoadingState
    },
    props: {
        selectedId: {
            type: [String, Number],
            default: null
        }
    },
    emits: ['conversation-selected'],
    setup(props, { emit }) {
        const conversationsStore = useConversationsStore();
        const conversations = computed(() => conversationsStore.allConversations);
        const loading = computed(() => conversationsStore.isLoading);

        const newCounts = ref({});
        const joined = new Set();

        const increment = (uuid) => {
            if (!uuid) return;
            newCounts.value[uuid] = (newCounts.value[uuid] || 0) + 1;
        };
        const clearCount = (uuid) => {
            if (!uuid) return;
            newCounts.value[uuid] = 0;
        };

        const subscribeTo = (uuid) => {
            if (!window.Echo || !uuid || joined.has(uuid)) return;
            const channelName = `conversations.${uuid}`;
            window.Echo.private(channelName)
                .listen('Conversations\\\\MessageAdded', (e) => {
                    // If this conversation is currently opened, don't count
                    if (props.selectedId && props.selectedId === uuid) return;
                    increment(uuid);
                });
            joined.add(uuid);
        };

        const ensureSubscriptions = () => {
            (conversations.value || []).forEach(c => subscribeTo(c.uuid));
        };

        const getConversation = (uuid) => {
            // reset new counter then emit selection
            clearCount(uuid);
            emit('conversation-selected', uuid);
        };

        onMounted(() => {
            conversationsStore.fetchConversations().then(() => ensureSubscriptions());
        });

        watch(conversations, () => {
            ensureSubscriptions();
        }, { deep: true });

        watch(() => props.selectedId, (val) => {
            if (val) clearCount(val);
        });

        onUnmounted(() => {
            if (window.Echo) {
                (conversations.value || []).forEach(c => {
                    const name = `conversations.${c.uuid}`;
                    if (joined.has(c.uuid)) {
                        window.Echo.leave(name);
                        joined.delete(c.uuid);
                    }
                });
            }
        });

        return {
            conversations,
            loading,
            getConversation,
            newCounts,
        };
    },
};
</script>

<style scoped>
.conversation-card {
    cursor: pointer;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.conversation-card:hover {
    border-color: rgba(var(--v-theme-primary), 0.5);
}

.conversation-title {
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.selected-conversation {
    border-color: rgb(var(--v-theme-primary)) !important;
    background-color: rgba(var(--v-theme-primary), 0.05);
}

.selected-conversation .conversation-title {
    color: rgb(var(--v-theme-primary));
}

.transition-all {
    transition: all 0.3s ease;
}

.conversation-list {
    max-height: 70vh;
    overflow-y: auto;
}

.conversation-list::-webkit-scrollbar {
    width: 8px;
}

.conversation-list::-webkit-scrollbar-track {
    background: rgba(var(--v-theme-on-surface), 0.05);
    border-radius: 4px;
}

.conversation-list::-webkit-scrollbar-thumb {
    background: rgba(var(--v-theme-on-surface), 0.2);
    border-radius: 4px;
}

.conversation-list::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--v-theme-on-surface), 0.3);
}

@media (max-width: 600px) {
    .d-flex.align-start {
        flex-direction: column;
    }
}
</style>
