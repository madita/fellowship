<template>
    <v-menu offset-y left transition="slide-y-transition" :close-on-content-click="false">
        <template v-slot:activator="{ props }">
            <v-badge
                :content="unreadCount"
                :model-value="unreadCount > 0"
                color="error"
                offset-x="2"
                offset-y="2"
            >
                <v-btn icon variant="text" v-bind="props">
                    <v-icon>mdi-file-document-edit-outline</v-icon>
                </v-btn>
            </v-badge>
        </template>

        <v-card min-width="380" max-width="440">
            <v-card-title class="d-flex align-center justify-space-between py-2 px-4 sandbox-header">
                <div class="d-flex align-center ga-2">
                    <v-icon size="20">mdi-file-document-edit-outline</v-icon>
                    <span class="text-subtitle-1 font-weight-bold">Sandbox</span>
                </div>
                <v-btn
                    v-if="notifications.length > 0"
                    variant="text"
                    size="x-small"
                    @click="markAllAsRead"
                >
                    Mark all read
                </v-btn>
            </v-card-title>

            <v-divider />

            <!-- Loading -->
            <div v-if="loading" class="text-center py-6">
                <v-progress-circular indeterminate color="primary" size="28" />
            </div>

            <!-- Empty State -->
            <div v-else-if="notifications.length === 0" class="text-center py-6 px-4">
                <v-icon size="44" color="medium-emphasis" class="mb-2">mdi-bell-check-outline</v-icon>
                <p class="text-body-2 text-medium-emphasis mb-0">No sandbox notifications</p>
            </div>

            <!-- Notification List -->
            <v-list v-else density="compact" class="py-0" max-height="420" style="overflow-y: auto;">
                <template v-for="(item, index) in notifications" :key="item.id || index">
                    <v-list-item
                        class="notification-item"
                        @click="goToNotification(item)"
                    >
                        <template v-slot:prepend>
                            <v-avatar :color="getColor(item)" size="36" class="mr-3">
                                <v-icon size="18" color="white">{{ getIcon(item) }}</v-icon>
                            </v-avatar>
                        </template>

                        <v-list-item-title class="text-body-2 font-weight-medium text-wrap">
                            {{ item.data.subject }}
                        </v-list-item-title>
                        <v-list-item-subtitle class="text-caption text-wrap notification-body">
                            {{ item.data.body }}
                        </v-list-item-subtitle>
                        <v-list-item-subtitle class="text-caption text-disabled mt-1">
                            {{ formatTime(item.created_at) }}
                        </v-list-item-subtitle>

                        <template v-slot:append>
                            <v-btn
                                icon
                                variant="text"
                                size="x-small"
                                color="medium-emphasis"
                                @click.stop="dismiss(item.id)"
                            >
                                <v-icon size="16">mdi-close</v-icon>
                                <v-tooltip activator="parent" location="left">Dismiss</v-tooltip>
                            </v-btn>
                        </template>
                    </v-list-item>

                    <v-divider v-if="index < notifications.length - 1" />
                </template>
            </v-list>

            <v-divider v-if="notifications.length > 0" />

            <div class="text-center py-2">
                <v-btn
                    variant="text"
                    size="small"
                    color="primary"
                    @click="$router.push('/sandbox')"
                >
                    Go to Sandboxes
                </v-btn>
            </div>
        </v-card>
    </v-menu>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/store/userStore.js'
import axios from 'axios'

export default {
    name: 'SandboxNotifications',

    setup() {
        const router = useRouter()
        const userStore = useUserStore()
        const allNotifications = ref([])
        const loading = ref(true)

        // Filter to only sandbox notifications
        const notifications = computed(() =>
            allNotifications.value.filter(n => n.data?.type?.startsWith('sandbox_'))
        )

        const unreadCount = computed(() => notifications.value.length)

        const fetchNotifications = async () => {
            try {
                const { data } = await axios.get('/api/account/notification')
                allNotifications.value = data
            } catch (error) {
                console.warn('Failed to fetch notifications:', error)
            } finally {
                loading.value = false
            }
        }

        const goToNotification = async (item) => {
            try {
                await axios.get('/api/account/notification/markasread/' + item.id)
                // Remove from local list only after successful mark-as-read
                allNotifications.value = allNotifications.value.filter(n => n.id !== item.id)
            } catch (error) {
                console.warn(error)
            }

            // Navigate to the sandbox
            const url = item.data?.url
            if (url) {
                router.push(url)
            }
        }

        const dismiss = async (id) => {
            try {
                await axios.delete('/api/account/notification/delete/' + id)
                allNotifications.value = allNotifications.value.filter(n => n.id !== id)
            } catch (error) {
                console.warn(error)
            }
        }

        const markAllAsRead = async () => {
            // Mark only sandbox notifications as read (one by one)
            const sandboxIds = notifications.value.map(n => n.id)
            const results = await Promise.allSettled(
                sandboxIds.map(id => axios.get('/api/account/notification/markasread/' + id))
            )
            const succeededIds = new Set(
                sandboxIds.filter((_, i) => results[i].status === 'fulfilled')
            )
            allNotifications.value = allNotifications.value.filter(
                n => !(n.data?.type?.startsWith('sandbox_') && succeededIds.has(n.id))
            )
        }

        const getIcon = (item) => {
            const map = {
                sandbox_shared: 'mdi-share-variant',
                sandbox_removed: 'mdi-account-remove',
                sandbox_comment: 'mdi-comment-text-outline',
                sandbox_reply: 'mdi-reply',
                sandbox_resolved: 'mdi-check-circle-outline',
                sandbox_invite_accepted: 'mdi-account-check',
            }
            return map[item.data?.type] || 'mdi-file-document-edit-outline'
        }

        const getColor = (item) => {
            const map = {
                sandbox_shared: 'primary',
                sandbox_removed: 'error',
                sandbox_comment: 'info',
                sandbox_reply: 'info',
                sandbox_resolved: 'success',
                sandbox_invite_accepted: 'success',
            }
            return map[item.data?.type] || 'primary'
        }

        const formatTime = (dateStr) => {
            if (!dateStr) return ''
            const date = new Date(dateStr)
            const now = new Date()
            const diffMs = now - date
            const diffMins = Math.floor(diffMs / 60000)
            const diffHours = Math.floor(diffMs / 3600000)
            const diffDays = Math.floor(diffMs / 86400000)

            if (diffMins < 1) return 'Just now'
            if (diffMins < 60) return `${diffMins}m ago`
            if (diffHours < 24) return `${diffHours}h ago`
            if (diffDays < 7) return `${diffDays}d ago`

            return date.toLocaleDateString()
        }

        onMounted(() => {
            fetchNotifications()

            // Listen for real-time notifications
            if (window.Echo && userStore.user?.id) {
                window.Echo.private('users.' + userStore.user.id)
                    .notification(() => {
                        fetchNotifications()
                    })
            }
        })

        return {
            notifications,
            loading,
            unreadCount,
            goToNotification,
            dismiss,
            markAllAsRead,
            getIcon,
            getColor,
            formatTime,
        }
    },
}
</script>

<style lang="scss" scoped>
.sandbox-header {
    background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-secondary)) 100%);
    color: white;

    .v-btn {
        color: white;
    }
}

.notification-item {
    cursor: pointer;
    transition: background-color 0.15s;

    &:hover {
        background-color: rgba(var(--v-theme-on-surface), 0.04);
    }
}

.notification-body {
    -webkit-line-clamp: 2;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    overflow: hidden;
    white-space: normal !important;
}
</style>
