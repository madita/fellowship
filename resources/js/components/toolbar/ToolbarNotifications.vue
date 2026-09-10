<template>
    <v-menu transition="slide-y-transition" :close-on-content-click="false">
        <template v-slot:activator="{ props }">
            <v-badge
                :content="notifications.length"
                :model-value="notifications.length > 0"
                color="error"
                offset-x="2"
                offset-y="2"
            >
                <v-btn icon="mdi-bell-outline" variant="text" v-bind="props" :title="$t('notifications.title')" />
            </v-badge>
        </template>

        <v-card min-width="360" max-width="420">
            <v-card-title class="d-flex align-center justify-space-between py-2 px-4">
                <span class="text-subtitle-1 font-weight-bold">{{ $t('notifications.title') }}</span>
                <v-btn
                    v-if="notifications.length > 0"
                    variant="text"
                    size="x-small"
                    color="primary"
                    :loading="busyAll"
                    :disabled="busy.length > 0"
                    @click="markAllAsRead"
                >
                    {{ $t('notifications.markAllRead') }}
                </v-btn>
            </v-card-title>

            <v-divider />

            <empty-state
                v-if="notifications.length === 0"
                compact
                icon="mdi-bell-check-outline"
                :title="$t('notifications.noNotifications')"
            />

            <v-list v-else density="compact" class="py-0" max-height="400" style="overflow-y: auto;">
                <v-list-item
                    v-for="(item, index) in notifications"
                    :key="item.id || index"
                    class="notification-item"
                    :disabled="isBusy(item.id) || busyAll"
                    @click="goToNotification(item)"
                >
                    <template v-slot:prepend>
                        <v-avatar :color="getNotificationColor(item)" size="36">
                            <v-icon size="18" color="white">{{ getNotificationIcon(item) }}</v-icon>
                        </v-avatar>
                    </template>

                    <v-list-item-title class="text-body-2 font-weight-medium text-wrap">
                        {{ subjectOf(item) }}
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
                                :loading="isBusy(item.id, 'read')"
                                :disabled="(isBusy(item.id) && !isBusy(item.id, 'read')) || busyAll"
                                @click.stop="markAsRead(item.id)"
                            >
                                <v-icon size="16">mdi-eye-check-outline</v-icon>
                                <v-tooltip activator="parent" location="left">{{ $t('notifications.markRead') }}</v-tooltip>
                            </v-btn>
                            <v-btn
                                icon
                                variant="text"
                                size="x-small"
                                color="error"
                                :loading="isBusy(item.id, 'delete')"
                                :disabled="(isBusy(item.id) && !isBusy(item.id, 'delete')) || busyAll"
                                @click.stop="deleteNotification(item.id)"
                            >
                                <v-icon size="16">mdi-close</v-icon>
                                <v-tooltip activator="parent" location="left">{{ $t('notifications.dismiss') }}</v-tooltip>
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
                    {{ $t('notifications.viewAll') }}
                </v-btn>
            </div>
        </v-card>
    </v-menu>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/api/useAPI.js'
import { useAuthStore } from '@/store/authStore.js'
import { useUserStore } from '@/store/userStore.js'
import { useDialog } from '@/composables/useDialog.js'
import axios from 'axios'
import EmptyState from '@/components/common/EmptyState.vue'

export default {
    components: { EmptyState },
    setup() {
        const allNotifications = ref([])
        const authStore = useAuthStore()
        const userStore = useUserStore()
        const router = useRouter()
        const api = useApi()
        const { t } = useI18n()
        // Confirmation and failures of the actions are modal
        const dialog = useDialog()

        // Notifications with a request in flight: [{ id, action }]
        const busy = ref([])
        const busyAll = ref(false)
        const isBusy = (id, action = null) => busy.value.some(b => b.id === id && (!action || b.action === action))
        const setBusy = (id, action) => { busy.value = [...busy.value, { id, action }] }
        const clearBusy = (id) => { busy.value = busy.value.filter(b => b.id !== id) }

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

        // Marks one notification as read; resolves to whether it worked.
        const markAsRead = async (id) => {
            if (isBusy(id) || busyAll.value) return false
            setBusy(id, 'read')
            try {
                await axios.get('/api/account/notification/markasread/' + id)
                await getNotifications()
                return true
            } catch (error) {
                console.warn(error)
                await dialog.requestError(error, t('notifications.updateFailed'))
                return false
            } finally {
                clearBusy(id)
            }
        }

        const goToNotification = async (item) => {
            if (isBusy(item.id) || busyAll.value) return
            const marked = await markAsRead(item.id)

            // Navigate based on notification type
            const url = item.data?.url
            if (url && marked) {
                router.push(url)
            }
        }

        const markAllAsRead = async () => {
            if (busyAll.value || busy.value.length) return
            busyAll.value = true
            try {
                await axios.get('/api/account/notification/allasread')
                await getNotifications()
            } catch (error) {
                console.warn(error)
                await dialog.requestError(error, t('notifications.updateFailed'))
            } finally {
                busyAll.value = false
            }
        }

        const deleteNotification = async (id) => {
            if (isBusy(id) || busyAll.value) return
            if (!(await dialog.confirmDelete(t('notifications.confirmDelete')))) return

            setBusy(id, 'delete')
            try {
                await axios.delete('/api/account/notification/delete/' + id)
                await getNotifications()
            } catch (error) {
                console.warn(error)
                await dialog.requestError(error, t('notifications.deleteFailed'))
            } finally {
                clearBusy(id)
            }
        }

        // Mentions carry no subject; build one from the mentioning member
        const subjectOf = (item) => {
            const d = item.data || {}
            if (d.type === 'status_mention' || d.type === 'status_comment_mention') {
                return t(d.type === 'status_mention' ? 'notifications.statusMention' : 'notifications.statusCommentMention', { name: d.mentioned_by })
            }
            return d.subject || d.thread_title || t('notifications.title')
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

            if (type.startsWith('status_')) return 'mdi-at'

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

            if (diffMins < 1) return t('notifications.justNow')
            if (diffMins < 60) return t('notifications.minutesAgo', { count: diffMins })
            if (diffHours < 24) return t('notifications.hoursAgo', { count: diffHours })
            if (diffDays < 7) return t('notifications.daysAgo', { count: diffDays })

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
            subjectOf,
            notifications,
            busy,
            busyAll,
            isBusy,
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
