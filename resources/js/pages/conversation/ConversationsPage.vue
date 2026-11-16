<template>
    <div>
        <page-header
            :title="$t('conversation.messages')"
            :subtitle="$t('conversation.subtitle')"
            icon="mdi-message-text-outline"
            fluid
        >
            <template #actions>
                <v-btn
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-plus"
                    @click="showNewConversationForm = !showNewConversationForm"
                >
                    {{ $t('conversation.newConversation') }}
                </v-btn>
            </template>
        </page-header>

        <v-container fluid class="conversation-layout pa-0">
            <v-row no-gutters class="fill-height">
                <!-- Left Panel - Conversation List -->
                <v-col cols="12" sm="5" md="4" lg="3" class="left-panel">
                    <v-card flat class="fill-height d-flex flex-column">
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
                            <empty-state
                                icon="mdi-chat-outline"
                                :title="$t('conversation.noConversationSelected')"
                                :text="$t('conversation.selectConversationHint')"
                            />
                        </div>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useConversationStore } from '@/store/conversationStore.js'
import { useConversationsStore } from '@/store/conversationsStore.js'
import PageHeader from '@/components/common/PageHeader.vue'
import EmptyState from '@/components/common/EmptyState.vue'
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
    selectedConversationId.value = conversationId
    conversationStore.fetchConversation(conversationId)
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
        await conversationsStore.fetchConversations()

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
    min-height: 60vh;
}

.left-panel {
    border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background-color: rgb(var(--v-theme-surface));
}

.right-panel {
    background-color: rgb(var(--v-theme-background));
}

.border-b {
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.fill-height {
    height: 100%;
}

/* Responsive design */
@media (max-width: 960px) {
    .left-panel {
        border-right: none;
        border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    }
}
</style>
