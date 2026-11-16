<template>
    <widget-state
        :loading="loading"
        :error="error"
        :empty="recentConversations.length === 0"
        empty-icon="mdi-message-outline"
        :empty-text="t('dashboard.widgets.conversations.empty')"
    >
        <!-- Unread Count Summary -->
        <div v-if="totalUnreadCount > 0" class="mb-3">
            <v-card color="primary" variant="tonal">
                <v-card-text class="pa-3">
                    <div class="d-flex align-center justify-space-between">
                        <div>
                            <div class="text-h5 font-weight-bold">{{ totalUnreadCount }}</div>
                            <div class="text-caption">{{ t('dashboard.widgets.conversations.unread') }}</div>
                        </div>
                        <v-icon size="40" color="primary">mdi-message-badge</v-icon>
                    </div>
                </v-card-text>
            </v-card>
        </div>

        <!-- Recent Conversations List -->
        <v-list density="compact" class="pa-0">
            <v-list-item
                v-for="(conversation, index) in recentConversations"
                :key="conversation.uuid || conversation.id || index"
                @click="openConversation(conversation)"
                class="px-0 mb-2 conversation-item"
                :class="{ 'unread-item': conversation.unread_count > 0 }"
            >
                <template v-slot:prepend>
                    <v-badge
                        v-if="(conversation.participant_count || conversation.users?.length || 0) > 1"
                        :content="conversation.unread_count"
                        :model-value="conversation.unread_count > 0"
                        color="error"
                        overlap
                    >
                        <v-avatar color="primary" size="40">
                            <v-icon color="white" size="20">mdi-account-group</v-icon>
                        </v-avatar>
                    </v-badge>
                    <v-badge
                        v-else
                        :content="conversation.unread_count"
                        :model-value="conversation.unread_count > 0"
                        color="error"
                        overlap
                    >
                        <user-avatar
                            :user="getOtherUser(conversation)"
                            :size="40"
                        />
                    </v-badge>
                </template>

                <v-list-item-title class="text-body-2 font-weight-medium">
                    {{ getConversationName(conversation) }}
                </v-list-item-title>

                <v-list-item-subtitle class="text-caption">
                    <span v-if="conversation.last_message">
                        {{ truncateMessage(conversation.last_message) }}
                    </span>
                    <span v-else-if="conversation.body">
                        {{ truncateMessage(conversation.body) }}
                    </span>
                    <span v-else class="text-medium-emphasis">
                        {{ t('dashboard.widgets.conversations.noMessages') }}
                    </span>
                    <div class="text-caption text-medium-emphasis mt-1">
                        {{ conversation.created_at_human || t('dashboard.widgets.conversations.recently') }}
                    </div>
                </v-list-item-subtitle>
            </v-list-item>
        </v-list>
    </widget-state>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useConversationsStore } from '@/store/conversationsStore.js'
import { useUserStore } from '@/store/userStore.js'
import UserAvatar from '@/components/common/UserAvatar.vue'
import WidgetState from './WidgetState.vue'
import eventBus from '@/components/common/eventBus.js'

const { t } = useI18n()

const props = defineProps({
    widgetData: {
        type: Object,
        default: () => ({})
    },
    widgetConfig: {
        type: Object,
        default: () => ({
            maxItems: 5
        })
    }
})

const conversationsStore = useConversationsStore()
const userStore = useUserStore()
const loading = ref(false)
const error = ref(null)

const conversations = computed(() => conversationsStore.allConversations || [])

const recentConversations = computed(() => {
    const max = props.widgetConfig?.maxItems || 5
    return conversations.value
        .sort((a, b) => {
            // Sort by unread first, then by date
            if (a.unread_count > 0 && b.unread_count === 0) return -1
            if (a.unread_count === 0 && b.unread_count > 0) return 1
            return new Date(b.last_message_at || b.created_at) - new Date(a.last_message_at || a.created_at)
        })
        .slice(0, max)
})

const totalUnreadCount = computed(() => {
    return conversations.value.reduce((sum, conv) => sum + (conv.unread_count || 0), 0)
})

function getOtherUser(conversation) {
    if (!conversation?.users) return null
    return conversation.users.find(u => u.id !== userStore.user?.id) || null
}

function getConversationName(conversation) {
    const participantCount = conversation.participant_count || conversation.users?.length || 0

    if (participantCount > 1) {
        // Group chat
        const otherUsers = conversation.users?.filter(u => u.id !== userStore.user?.id) || []
        return otherUsers.map(u => u.username).join(', ') || t('dashboard.widgets.conversations.groupChat')
    } else {
        // 1-on-1
        const otherUser = getOtherUser(conversation)
        return otherUser?.username || t('dashboard.widgets.conversations.unknownUser')
    }
}

function truncateMessage(message, maxLength = 40) {
    if (!message) return ''
    if (message.length <= maxLength) return message
    return message.substring(0, maxLength) + '...'
}

function openConversation(conversation) {
    const otherUsers = conversation.users?.filter(u => u.id !== userStore.user?.id) || []
    const primaryUser = otherUsers[0]
    const isGroup = conversation.participant_count > 1
    const groupName = isGroup ? getConversationName(conversation) : null

    eventBus.emit('chat.open', {
        user: primaryUser,
        conversationUuid: conversation.uuid,
        isGroup,
        groupName,
        conversation
    })
}

onMounted(async () => {
    loading.value = true
    error.value = null
    try {
        await conversationsStore.fetchConversations()
    } catch (e) {
        console.error('Failed to load conversations:', e)
        error.value = e.response?.data?.message || e.message
    } finally {
        loading.value = false
    }
})
</script>

<style scoped>
.conversation-item {
    cursor: pointer;
    border-radius: 8px;
    transition: background-color 0.2s ease;
}

.conversation-item:hover {
    background-color: rgba(var(--v-theme-on-surface), 0.04);
}

.unread-item {
    background-color: rgba(var(--v-theme-primary), 0.08);
}

.unread-item:hover {
    background-color: rgba(var(--v-theme-primary), 0.12);
}
</style>
