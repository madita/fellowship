<template>
    <v-container fluid class="conversation-layout pa-0">
        <v-row no-gutters class="fill-height">
            <!-- Left Panel - Conversation List -->
            <v-col cols="12" sm="5" md="4" lg="3" class="left-panel">
                <v-card flat class="fill-height d-flex flex-column">
                    <!-- Conversation Form -->
                    <v-card-title class="conversation-header pa-4 border-b">
                        <div class="d-flex align-center justify-space-between w-100">
                            <h3 class="text-h6 font-weight-bold">Messages</h3>
                            <v-btn
                                icon="mdi-plus"
                                variant="text"
                                color="primary"
                                size="small"
                                @click="showNewConversationForm = !showNewConversationForm"
                            />
                        </div>
                    </v-card-title>

                    <!-- New Conversation Form -->
                    <v-expand-transition>
                        <v-card-text v-if="showNewConversationForm" class="pa-4 border-b">
                            <ConversationForm @conversation-created="handleConversationCreated" />
                        </v-card-text>
                    </v-expand-transition>

                    <!-- Conversations List -->
                    <v-card-text class="flex-grow-1 pa-0 overflow-y-auto">
                        <Conversations
                            :selected-id="selectedConversationId"
                            @conversation-selected="handleConversationSelected"
                        />
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- Right Panel - Active Conversation -->
            <v-col cols="12" sm="7" md="8" lg="9" class="right-panel">
                <v-card flat class="fill-height">
                    <div v-if="selectedConversationId" class="fill-height">
                        <Conversation
                            :id="selectedConversationId"
                            :key="selectedConversationId"
                        />
                    </div>

                    <!-- Empty State -->
                    <div v-else class="d-flex align-center justify-center fill-height">
                        <div class="text-center">
                            <v-icon size="64" color="medium-emphasis" class="mb-4">
                                mdi-chat-outline
                            </v-icon>
                            <h3 class="text-h5 mb-2">Select a conversation</h3>
                            <p class="text-body-1 text-medium-emphasis">
                                Choose a conversation from the list to start chatting
                            </p>
                        </div>
                    </div>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useConversationStore } from '@/store/conversationStore.js'
import { useConversationsStore } from '@/store/conversationsStore.js'
import Conversation from '@/components/conversation/Conversation.vue'
import Conversations from '@/components/conversation/Conversations.vue'
import ConversationForm from '@/components/conversation/forms/ConversationForm.vue'

// Props
const props = defineProps({
    id: {
        type: [String, Number],
        default: null
    }
})

// Composables
const route = useRoute()
const router = useRouter()
const conversationStore = useConversationStore()
const conversationsStore = useConversationsStore()

// Reactive state
const showNewConversationForm = ref(false)
const selectedConversationId = ref(props.id)

// Computed properties
const conversations = computed(() => conversationStore.conversations)
const currentConversation = computed(() => conversationStore.currentConversation)
const isLoading = computed(() => conversationStore.isLoading)

// Methods
const handleConversationSelected = (conversationId) => {
    console.log('#selected', conversationId)
    selectedConversationId.value = conversationId

    conversationStore.fetchConversation(conversationId);

    // Update URL if needed
   /* if (route.params.id !== conversationId) {
        router.push({
            name: route.name,
            params: { ...route.params, id: conversationId }
        })
    }*/

    // Load conversation details
    conversationStore.currentConversation
}

const handleConversationCreated = (newConversation) => {
    showNewConversationForm.value = false

    // Select the newly created conversation
    if (newConversation && newConversation.id) {
        handleConversationSelected(newConversation.id)
    }
}

// Watchers
watch(
    () => props.id,
    (newId) => {
        if (newId && newId !== selectedConversationId.value) {
            selectedConversationId.value = newId
            conversationStore.setCurrentConversation(newId)
        }
    },
    { immediate: true }
)

watch(
    () => route.params.id,
    (newId) => {
        if (newId && newId !== selectedConversationId.value) {
            selectedConversationId.value = newId
            conversationStore.setCurrentConversation(newId)
        }
    }
)

// Lifecycle
onMounted(async () => {
    try {

        // const channelName = `conversation.${conversationId}`;
        //
        // Echo.private(channelName)
        //     .subscribed((e) => {
        //         console.log('subscribed:', e.data);
        //
        //
        //     })
        //     .listenToAll(() => {
        //         console.log('listentoall:', e.data);
        //         // Update store with new reply
        //         //conversationStore.appendReplyToConversation(e.data);
        //     })
        //     .listen('.message-added', (e) => {
        //         console.log('New reply received:', e.data);
        //         // Update store with new reply
        //         conversationStore.appendReplyToConversation(e.data);
        //     })
        //     .listen('UserAdded', (e) => {
        //         console.log('Users updated:', e.data);
        //         // Update store with new users
        //         conversationStore.updateConversationUsers(e.data.users.data);
        //     });

        // Load conversations list
        await conversationsStore.fetchConversations()

        // If we have an ID, load that conversation
        if (selectedConversationId.value) {
            await conversationStore.setCurrentConversation(selectedConversationId.value)
        }
    } catch (error) {
        console.error('Error loading conversations:', error)
    }
})
</script>

<style scoped>
.conversation-layout {
    height: 100vh;
    max-height: 100vh;
}

.left-panel {
    border-right: 1px solid rgb(var(--v-border-color));
    background-color: rgb(var(--v-theme-surface));
}

.right-panel {
    background-color: rgb(var(--v-theme-background));
}

.conversation-header {
    background-color: rgb(var(--v-theme-surface));
    border-bottom: 1px solid rgb(var(--v-border-color));
}

.border-b {
    border-bottom: 1px solid rgb(var(--v-border-color));
}

.fill-height {
    height: 100%;
}

/* Responsive design */
@media (max-width: 960px) {
    .left-panel {
        border-right: none;
        border-bottom: 1px solid rgb(var(--v-border-color));
    }

    .conversation-layout {
        height: auto;
        min-height: 100vh;
    }
}

@media (max-width: 600px) {
    .left-panel {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        height: 100vh;
        transform: translateX(-100%);
        transition: transform 0.3s ease-in-out;
    }

    .left-panel.active {
        transform: translateX(0);
    }

    .right-panel {
        width: 100%;
        height: 100vh;
    }
}
</style>
