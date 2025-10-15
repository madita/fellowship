<template>
  <v-card elevation="1">
    <v-card-title class="py-3 d-flex align-center justify-space-between">
      <div class="d-flex align-center">
        <v-icon class="mr-2" color="primary">mdi-account-group</v-icon>
        <span class="text-subtitle-1 font-weight-medium">
          Users
        </span>
        <v-chip size="x-small" color="success" variant="flat" class="ml-2">
          {{ onlineCount }} online
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
        <v-card-text class="pa-0">
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
                  <!--v-avatar size="40">
                    <v-img v-if="u.avatar" :src="u.avatar" :alt="`${u.username} avatar`" />
                    <span v-else class="text-subtitle-2">{{ u.initials || '?' }}</span>
                  </v-avatar -->
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
        </v-card-text>
      </div>
    </v-expand-transition>
  </v-card>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import eventBus from '../common/eventBus.js'
import { useUserStore } from '@/store/userStore.js'
import { useOnlineUsers } from '@/composables/conversation/useOnlineUsers'
import UserAvatar from "@/components/common/UserAvatar.vue";
import axios from 'axios'

const allUsers = ref([])
const collapsed = ref(false)
const loading = ref(false)

const userStore = useUserStore()
const { isUserOnline, setupListeners: setupOnlineListeners } = useOnlineUsers()

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
    console.log('emit chat open', user)
  eventBus.emit('chat.open', { user })
}

function refresh() {
  fetchAllUsers()
  eventBus.emit('users.refresh')
}

let cleanupOnlineListeners = null

onMounted(() => {
  // Setup online users tracking
  cleanupOnlineListeners = setupOnlineListeners()

  // Fetch all users
  fetchAllUsers()
})

onUnmounted(() => {
  if (cleanupOnlineListeners) {
    cleanupOnlineListeners()
  }
})
</script>

<style scoped>
.user-list {
  max-height: 400px;
  overflow-y: auto;
}

.user-list-item {
  transition: background-color 0.2s ease;
}

.user-list-item:hover {
  background-color: rgba(0, 0, 0, 0.04);
}

.cursor-pointer {
  cursor: pointer;
}
</style>
