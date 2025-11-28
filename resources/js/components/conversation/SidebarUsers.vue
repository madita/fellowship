<template>
  <v-card elevation="1">
    <v-card-title class="py-3 d-flex align-center justify-space-between">
      <div class="d-flex align-center">
        <v-icon class="mr-2" color="primary">mdi-forum</v-icon>
        <span class="text-subtitle-1 font-weight-medium">
          Messages
        </span>
        <v-chip v-if="totalUnreadCount > 0" size="x-small" color="error" variant="flat" class="ml-2">
          {{ totalUnreadCount }}
        </v-chip>
      </div>
      <div class="d-flex align-center">
        <v-btn icon variant="text" size="small" @click="refresh">
          <v-icon>mdi-refresh</v-icon>
        </v-btn>
        <v-btn icon variant="text" size="small" @click="collapsed = !collapsed">
          <v-icon>{{ collapsed ? 'mdi-chevron-down' : 'mdi-chevron-up' }}</v-icon>
        </v-btn>
      </div>
    </v-card-title>

    <v-expand-transition>
      <div v-show="!collapsed">
        <v-divider></v-divider>

        <!-- Tabs for switching between views -->
        <v-tabs v-model="activeTab" density="compact" grow>
          <v-tab value="conversations">
            <v-icon size="small" class="mr-1">mdi-message</v-icon>
            Chats
            <v-chip v-if="totalUnreadCount > 0" size="x-small" color="error" class="ml-1">
              {{ totalUnreadCount }}
            </v-chip>
          </v-tab>
          <v-tab value="users">
            <v-icon size="small" class="mr-1">mdi-account-group</v-icon>
            Users
            <v-chip size="x-small" color="success" class="ml-1">
              {{ onlineCount }}
            </v-chip>
          </v-tab>
        </v-tabs>

        <v-divider></v-divider>

        <v-card-text class="pa-0">
          <v-window v-model="activeTab">
            <!-- Conversations Tab -->
            <v-window-item value="conversations">
              <v-list density="compact" class="conversation-list">
                <!-- Group Conversations -->
                <template v-if="groupConversations.length > 0">
                  <v-list-subheader class="text-overline">Group Chats</v-list-subheader>
                  <v-list-item
                    v-for="conv in groupConversations"
                    :key="conv.uuid"
                    @click="openConversation(conv)"
                    class="cursor-pointer conversation-item"
                    :class="{ 'unread-conversation': conv.unread_count > 0 }"
                  >
                    <template #prepend>
                      <v-avatar color="primary" size="40">
                        <v-icon color="white">mdi-account-group</v-icon>
                      </v-avatar>
                    </template>

                    <v-list-item-title class="font-weight-medium d-flex align-center">
                      <span class="text-truncate" style="max-width: 160px;">
                        {{ getGroupChatName(conv) }}
                      </span>
                      <v-chip v-if="conv.unread_count > 0" size="x-small" color="error" class="ml-2">
                        {{ conv.unread_count }}
                      </v-chip>
                    </v-list-item-title>

                    <v-list-item-subtitle class="text-caption text-truncate">
                      {{ conv.participant_count }} participants • {{ conv.created_at_human }}
                    </v-list-item-subtitle>
                  </v-list-item>
                </template>

                <!-- Direct Conversations -->
                <template v-if="directConversations.length > 0">
                  <v-list-subheader class="text-overline">Direct Messages</v-list-subheader>
                  <v-list-item
                    v-for="conv in directConversations"
                    :key="conv.uuid"
                    @click="openConversation(conv)"
                    class="cursor-pointer conversation-item"
                    :class="{ 'unread-conversation': conv.unread_count > 0 }"
                  >
                    <template #prepend>
                      <v-badge
                        :color="isUserOnline(getOtherUser(conv)?.id).value ? 'success' : 'grey'"
                        dot
                        location="bottom right"
                        offset-x="4"
                        offset-y="4"
                      >
                        <user-avatar :user="getOtherUser(conv)"></user-avatar>
                      </v-badge>
                    </template>

                    <v-list-item-title class="font-weight-medium d-flex align-center">
                      <span>{{ getOtherUser(conv)?.username }}</span>
                      <v-chip v-if="conv.unread_count > 0" size="x-small" color="error" class="ml-2">
                        {{ conv.unread_count }}
                      </v-chip>
                    </v-list-item-title>

                    <v-list-item-subtitle class="text-caption">
                      <span :class="isUserOnline(getOtherUser(conv)?.id).value ? 'text-success' : 'text-grey'">
                        {{ isUserOnline(getOtherUser(conv)?.id).value ? 'Online' : 'Offline' }}
                      </span>
                      • {{ conv.created_at_human }}
                    </v-list-item-subtitle>
                  </v-list-item>
                </template>

                <!-- Loading state -->
                <v-list-item v-if="conversationsLoading">
                  <v-list-item-title class="text-center">
                    <v-progress-circular indeterminate size="24" color="primary" />
                  </v-list-item-title>
                </v-list-item>

                <!-- Empty state -->
                <v-list-item v-if="!conversationsLoading && allConversations.length === 0">
                  <v-list-item-title class="text-medium-emphasis text-center">
                    No conversations yet
                  </v-list-item-title>
                  <v-list-item-subtitle class="text-center text-caption">
                    Start a chat from the Users tab
                  </v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </v-window-item>

            <!-- Users Tab -->
            <v-window-item value="users">
              <v-list density="compact" class="user-list">
                <v-list-item
                  v-for="u in sortedUsers"
                  :key="u.id"
                  @click.prevent="makeConversationWith(u)"
                  class="cursor-pointer user-list-item"
                >
                  <template #prepend>
                    <v-badge
                      :color="isUserOnline(u.id).value ? 'success' : 'grey'"
                      dot
                      location="bottom right"
                      offset-x="4"
                      offset-y="4"
                    >
                      <user-avatar :user="u"></user-avatar>
                    </v-badge>
                  </template>

                  <v-list-item-title class="font-weight-medium">
                    {{ u.username }}
                  </v-list-item-title>

                  <v-list-item-subtitle class="text-caption">
                    <span :class="isUserOnline(u.id).value ? 'text-success' : 'text-grey'">
                      {{ isUserOnline(u.id).value ? 'Online' : 'Offline' }}
                    </span>
                  </v-list-item-subtitle>
                </v-list-item>

                <v-list-item v-if="loading">
                  <v-list-item-title class="text-center">
                    <v-progress-circular indeterminate size="24" color="primary" />
                  </v-list-item-title>
                </v-list-item>

                <v-list-item v-if="!loading && allUsers.length === 0">
                  <v-list-item-title class="text-medium-emphasis">No users found</v-list-item-title>
                </v-list-item>
              </v-list>
            </v-window-item>
          </v-window>
        </v-card-text>
      </div>
    </v-expand-transition>
  </v-card>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import eventBus from '../common/eventBus.js'
import { useUserStore } from '@/store/userStore.js'
import { useConversationsStore } from '@/store/conversationsStore.js'
import { useOnlineUsers } from '@/composables/conversation/useOnlineUsers'
import UserAvatar from "@/components/common/UserAvatar.vue";
import axios from 'axios'

const allUsers = ref([])
const collapsed = ref(false)
const loading = ref(false)
const activeTab = ref('conversations')

const userStore = useUserStore()
const conversationsStore = useConversationsStore()
const { isUserOnline, setupListeners: setupOnlineListeners } = useOnlineUsers()

// Get conversations from store
const allConversations = computed(() => conversationsStore.allConversations || [])
const conversationsLoading = computed(() => conversationsStore.isLoading)

// Split conversations into group chats and direct messages
const groupConversations = computed(() => {
  return allConversations.value
    .filter(conv => conv.participant_count > 1)
    .sort((a, b) => {
      // Sort by unread status first, then by last message date
      if (a.unread_count > 0 && b.unread_count === 0) return -1
      if (a.unread_count === 0 && b.unread_count > 0) return 1
      return new Date(b.last_message_at || b.created_at) - new Date(a.last_message_at || a.created_at)
    })
})

const directConversations = computed(() => {
  return allConversations.value
    .filter(conv => conv.participant_count === 1)
    .sort((a, b) => {
      // Sort by unread status first, then by last message date
      if (a.unread_count > 0 && b.unread_count === 0) return -1
      if (a.unread_count === 0 && b.unread_count > 0) return 1
      return new Date(b.last_message_at || b.created_at) - new Date(a.last_message_at || a.created_at)
    })
})

// Total unread count
const totalUnreadCount = computed(() => {
  return allConversations.value.reduce((sum, conv) => sum + (conv.unread_count || 0), 0)
})

// Sorted users: online first, then offline (alphabetically within each group)
const sortedUsers = computed(() => {
  return [...allUsers.value].sort((a, b) => {
    const aOnline = isUserOnline(a.id).value
    const bOnline = isUserOnline(b.id).value

    // Sort by online status first
    if (aOnline && !bOnline) return -1
    if (!aOnline && bOnline) return 1

    // Within same status, sort alphabetically by username
    return (a.username || '').localeCompare(b.username || '')
  })
})

// Count online users
const onlineCount = computed(() => {
  return allUsers.value.filter(u => isUserOnline(u.id).value).length
})

// Get the other user in a direct conversation
function getOtherUser(conversation) {
  if (!conversation?.users) return null
  return conversation.users.find(u => u.id !== userStore.user?.id) || null
}

// Get group chat name (comma-separated usernames, excluding current user)
function getGroupChatName(conversation) {
  if (!conversation?.users) return 'Group Chat'
  const otherUsers = conversation.users.filter(u => u.id !== userStore.user?.id)
  return otherUsers.map(u => u.username).join(', ') || 'Group Chat'
}

// Open an existing conversation
function openConversation(conversation) {
  const otherUsers = conversation.users.filter(u => u.id !== userStore.user?.id)
  const primaryUser = otherUsers[0] // For group chats, this will be one of the participants

  eventBus.emit('chat.open', {
    user: primaryUser,
    conversationUuid: conversation.uuid
  })
}

async function fetchAllUsers() {
  loading.value = true
  try {
    const response = await axios.post('/api/users/search', { query: '' })
    allUsers.value = response.data || []
  } catch (error) {
    console.error('Failed to fetch users:', error)
    allUsers.value = []
  } finally {
    loading.value = false
  }
}

function makeConversationWith(user) {
  eventBus.emit('chat.open', { user })
}

function refresh() {
  fetchAllUsers()
  conversationsStore.fetchConversations()
  eventBus.emit('users.refresh')
}

let cleanupOnlineListeners = null

onMounted(() => {
  // Setup online users tracking
  cleanupOnlineListeners = setupOnlineListeners()

  // Fetch all users and conversations
  fetchAllUsers()
  conversationsStore.fetchConversations()
})

onUnmounted(() => {
  if (cleanupOnlineListeners) {
    cleanupOnlineListeners()
  }
})
</script>

<style scoped>
.user-list,
.conversation-list {
  max-height: 400px;
  overflow-y: auto;
}

.user-list-item,
.conversation-item {
  transition: background-color 0.2s ease;
}

.user-list-item:hover,
.conversation-item:hover {
  background-color: rgba(0, 0, 0, 0.04);
}

.unread-conversation {
  background-color: rgba(25, 118, 210, 0.08);
}

.unread-conversation:hover {
  background-color: rgba(25, 118, 210, 0.12);
}

.cursor-pointer {
  cursor: pointer;
}
</style>
