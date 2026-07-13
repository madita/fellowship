<template>
    <v-menu offset-y left transition="slide-y-transition" :close-on-content-click="false">
        <template v-slot:activator="{ props }">
            <v-badge
                :content="notifications.length"
                :model-value="notifications.length > 0"
                color="error"
                offset-x="2"
                offset-y="2"
            >
                <v-btn icon variant="text" v-bind="props">
                    <v-icon>mdi-bell-outline</v-icon>
                </v-btn>
            </v-badge>
        </template>

        <v-card min-width="360" max-width="420">
            <v-card-title class="d-flex align-center justify-space-between py-2 px-4">
                <span class="text-subtitle-1 font-weight-bold">Notifications</span>
                <v-btn
                    v-if="notifications.length > 0"
                    variant="text"
                    size="x-small"
                    color="primary"
                    @click="markAllAsRead"
                >
                    Mark all read
                </v-btn>
            </v-card-title>

            <v-divider />

            <div v-if="notifications.length === 0" class="text-center py-6 px-4">
                <v-icon size="40" color="medium-emphasis" class="mb-2">mdi-bell-check-outline</v-icon>
                <p class="text-body-2 text-medium-emphasis mb-0">No new notifications</p>
            </div>

            <v-list v-else density="compact" class="py-0" max-height="400" style="overflow-y: auto;">
                <v-list-item
                    v-for="(item, index) in notifications"
                    :key="item.id || index"
                    class="notification-item"
                    @click="goToNotification(item)"
                >
                    <template v-slot:prepend>
                        <v-avatar :color="getNotificationColor(item)" size="36">
                            <v-icon size="18" color="white">{{ getNotificationIcon(item) }}</v-icon>
                        </v-avatar>
                    </template>

                    <v-list-item-title class="text-body-2 font-weight-medium text-wrap">
                        {{ item.data.subject || 'Notification' }}
                    </v-list-item-title>
                    <v-list-item-subtitle class="text-caption text-wrap">
                        {{ item.data.body || '' }}
                    </v-list-item-subtitle>
                    <v-list-item-subtitle class="text-caption text-disabled mt-1">
                        {{ formatTime(item.created_at) }}
                    </v-list-item-subtitle>

                    <template v-slot:append>
                        <div class="d-flex flex-column ga-1">
                            <v-btn
                                icon
                                variant="text"
                                size="x-small"
                                color="medium-emphasis"
                                @click.stop="markAsRead(item.id)"
                            >
                                <v-icon size="16">mdi-eye-check-outline</v-icon>
                                <v-tooltip activator="parent" location="left">Mark as read</v-tooltip>
                            </v-btn>
                            <v-btn
                                icon
                                variant="text"
                                size="x-small"
                                color="error"
                                @click.stop="deleteNotification(item.id)"
                            >
                                <v-icon size="16">mdi-close</v-icon>
                                <v-tooltip activator="parent" location="left">Dismiss</v-tooltip>
                            </v-btn>
                        </div>
                    </template>
                </v-list-item>
            </v-list>

            <v-divider v-if="notifications.length > 0" />

            <div class="text-center py-2">
                <v-btn
                    variant="text"
                    size="small"
                    color="primary"
                    @click="$router.push({ name: 'my-notifications' })"
                >
                    See all notifications
                </v-btn>
            </div>
        </v-card>
    </v-menu>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '@/api/useAPI.js'
import { useAuthStore } from '@/store/authStore.js'
import { useUserStore } from '@/store/userStore.js'
import axios from 'axios'

export default {
    setup() {
        const allNotifications = ref([])
        const authStore = useAuthStore()
        const userStore = useUserStore()
        const router = useRouter()
        const api = useApi()

        // Filter out sandbox notifications (they have their own component)
        const notifications = computed(() =>
            allNotifications.value.filter(n => !n.data?.type?.startsWith('sandbox_'))
        )

        const getNotifications = async () => {
            try {
                const data = await api.get('/account/notification')
                allNotifications.value = data.data
            } catch (error) {
                console.warn(error)
            }
        }

        const goToNotification = async (item) => {
            try {
                await axios.get('/api/account/notification/markasread/' + item.id)
                getNotifications()
            } catch (error) {
                console.warn(error)
            }

            // Navigate based on notification type
            const url = item.data?.url
            if (url) {
                router.push(url)
            }
        }

        const markAsRead = async (id) => {
            try {
                await axios.get('/api/account/notification/markasread/' + id)
                getNotifications()
            } catch (error) {
                console.warn(error)
            }
        }

        const markAllAsRead = async () => {
            try {
                await axios.get('/api/account/notification/allasread')
                getNotifications()
            } catch (error) {
                console.warn(error)
            }
        }

        const deleteNotification = async (id) => {
            try {
                await axios.delete('/api/account/notification/delete/' + id)
                getNotifications()
            } catch (error) {
                console.warn(error)
            }
        }

        const getNotificationIcon = (item) => {
            const type = item.data?.type || ''

            if (type.startsWith('sandbox_')) {
                const iconMap = {
                    sandbox_shared: 'mdi-share-variant',
                    sandbox_removed: 'mdi-account-remove',
                    sandbox_comment: 'mdi-comment-text-outline',
                    sandbox_reply: 'mdi-reply',
                    sandbox_resolved: 'mdi-check-circle-outline',
                    sandbox_invite_accepted: 'mdi-account-check',
                }
                return iconMap[type] || 'mdi-file-document-edit-outline'
            }

            if (type.startsWith('forum_')) {
                const iconMap = {
                    forum_reply: 'mdi-forum-outline',
                    forum_mention: 'mdi-at',
                }
                return iconMap[type] || 'mdi-forum'
            }

            return 'mdi-bell-outline'
        }

        const getNotificationColor = (item) => {
            const type = item.data?.type || ''

            if (type.startsWith('sandbox_')) {
                const colorMap = {
                    sandbox_shared: 'primary',
                    sandbox_removed: 'error',
                    sandbox_comment: 'info',
                    sandbox_reply: 'info',
                    sandbox_resolved: 'success',
                    sandbox_invite_accepted: 'success',
                }
                return colorMap[type] || 'primary'
            }

            if (type.startsWith('forum_')) {
                return 'warning'
            }

            return 'primary'
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
            if (userStore.user?.id) {
                Echo.private('users.' + userStore.user.id)
                    .notification(() => {
                        getNotifications()
                    })
            }
            getNotifications()
        })

        return {
            notifications,
            user: userStore.user,
            authenticated: authStore.isLoggedIn,
            markAllAsRead,
            markAsRead,
            getNotifications,
            deleteNotification,
            goToNotification,
            getNotificationIcon,
            getNotificationColor,
            formatTime,
        }
    },
}
</script>

<style lang="scss" scoped>
.notification-item {
    cursor: pointer;
    border-bottom: 1px solid rgb(var(--v-border-color));
    transition: background 0.15s;

    &:last-child {
        border-bottom: none;
    }

    &:hover {
        background: rgba(var(--v-theme-on-surface), 0.04);
    }
}
</style>
