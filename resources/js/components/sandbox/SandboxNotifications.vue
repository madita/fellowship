<template>
    <v-menu location="bottom end" transition="slide-y-transition" :close-on-content-click="false">
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
            <v-card-title class="d-flex align-center justify-space-between py-2 px-4">
                <div class="d-flex align-center ga-2 text-subtitle-1 font-weight-medium">
                    <v-icon size="20" color="primary">mdi-file-document-edit-outline</v-icon>
                    {{ $t('sandbox.notifications.title') }}
                </div>
                <v-btn
                    v-if="notifications.length > 0"
                    variant="text"
                    size="x-small"
                    color="primary"
                    :loading="markingAll"
                    :disabled="busyIds.length > 0"
                    @click="markAllAsRead"
                >
                    {{ $t('sandbox.notifications.markAllRead') }}
                </v-btn>
            </v-card-title>

            <v-divider />

            <!-- Loading -->
            <loading-state v-if="loading" compact />

            <!-- Empty State -->
            <empty-state
                v-else-if="notifications.length === 0"
                compact
                icon="mdi-bell-check-outline"
                :title="$t('sandbox.notifications.empty')"
            />

            <!-- Notification List -->
            <v-list v-else density="compact" class="py-0" max-height="420" style="overflow-y: auto;">
                <template v-for="(item, index) in notifications" :key="item.id || index">
                    <v-list-item
                        class="notification-item"
                        :disabled="busyIds.includes(item.id) || markingAll"
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
                                :loading="busyIds.includes(item.id)"
                                :disabled="markingAll"
                                @click.stop="dismiss(item.id)"
                            >
                                <v-icon size="16">mdi-close</v-icon>
                                <v-tooltip activator="parent" location="left">{{ $t('sandbox.notifications.dismiss') }}</v-tooltip>
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
                    {{ $t('sandbox.notifications.goToSandboxes') }}
                </v-btn>
            </div>
        </v-card>
    </v-menu>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '@/store/userStore.js'
import { useRelativeTime } from '@/composables/useRelativeTime.js'
import { useDialog } from '@/composables/useDialog.js'
import axios from 'axios'
import EmptyState from '../common/EmptyState.vue'
import LoadingState from '../common/LoadingState.vue'

export default {
    name: 'SandboxNotifications',

    components: {
        EmptyState,
        LoadingState,
    },

    setup() {
        const router = useRouter()
        const { t } = useI18n()
        const dialog = useDialog()
        const userStore = useUserStore()
        const allNotifications = ref([])
        const loading = ref(true)
        // Notification ids with a mark-as-read / dismiss request in flight
        const busyIds = ref([])
        const markingAll = ref(false)

        const setBusy = (id, busy) => {
            busyIds.value = busy
                ? [...busyIds.value, id]
                : busyIds.value.filter(i => i !== id)
        }

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
            if (busyIds.value.includes(item.id) || markingAll.value) return

            setBusy(item.id, true)
            try {
                await axios.get('/api/account/notification/markasread/' + item.id)
            } catch (error) {
                // Keep the notification in local state and don't navigate it as
                // read when the mark-as-read request fails.
                console.warn(error)
                setBusy(item.id, false)
                await dialog.requestError(error, t('sandbox.notifications.markReadFailed'))
                return
            }
            setBusy(item.id, false)

            // Remove from local list only after successful mark-as-read
            allNotifications.value = allNotifications.value.filter(n => n.id !== item.id)

            // Navigate to the sandbox
            const url = item.data?.url
            if (url) {
                router.push(url)
            }
        }

        const dismiss = async (id) => {
            if (busyIds.value.includes(id) || markingAll.value) return

            setBusy(id, true)
            try {
                await axios.delete('/api/account/notification/delete/' + id)
                allNotifications.value = allNotifications.value.filter(n => n.id !== id)
            } catch (error) {
                console.warn(error)
                await dialog.requestError(error, t('sandbox.notifications.dismissFailed'))
            } finally {
                setBusy(id, false)
            }
        }

        const markAllAsRead = async () => {
            if (markingAll.value || busyIds.value.length > 0) return

            markingAll.value = true
            try {
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
                if (succeededIds.size < sandboxIds.length) {
                    await dialog.error(t('sandbox.notifications.markAllFailed'))
                }
            } finally {
                markingAll.value = false
            }
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

        const { formatRelativeTime: formatTime } = useRelativeTime()

        let notificationChannel = null

        onMounted(() => {
            fetchNotifications()

            // Listen for real-time notifications
            if (window.Echo && userStore.user?.id) {
                notificationChannel = 'users.' + userStore.user.id
                window.Echo.private(notificationChannel)
                    .notification(() => {
                        fetchNotifications()
                    })
            }
        })

        onUnmounted(() => {
            // Leave the channel so remounts don't accumulate listeners or
            // duplicate fetchNotifications calls.
            if (window.Echo && notificationChannel) {
                window.Echo.leave(notificationChannel)
                notificationChannel = null
            }
        })

        return {
            notifications,
            loading,
            busyIds,
            markingAll,
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
