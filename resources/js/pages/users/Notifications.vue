<template>
    <div class="d-flex flex-column flex-grow-1">
        <page-header
            :title="$t('notifications.title')"
            :subtitle="$t('notifications.subtitle')"
            icon="mdi-bell-outline"
        >
            <template v-if="unreadCount" #actions>
                <v-btn
                    variant="tonal"
                    prepend-icon="mdi-email-open-outline"
                    :loading="markingAll"
                    @click="markAllAsRead"
                >
                    {{ $t('notifications.markAllRead') }}
                </v-btn>
            </template>
        </page-header>

        <v-container>
            <loading-state v-if="isLoading && !notifications.length" />

            <empty-state
                v-else-if="!notifications.length"
                icon="mdi-bell-off-outline"
                :title="$t('notifications.noNotifications')"
                :text="$t('notifications.emptyHint')"
            />

            <v-card v-else class="notifications-card" rounded="lg" elevation="2" border>
                <v-list class="py-0">
                    <template v-for="(item, index) in notifications" :key="item.id">
                        <v-divider v-if="index > 0" />
                        <v-list-item
                            class="py-3"
                            :class="{ 'notification--unread': !item.read_at }"
                            :active="false"
                            @click="open(item)"
                        >
                            <template #prepend>
                                <v-avatar :color="color(item)" size="40" class="mr-3">
                                    <v-icon :icon="icon(item)" color="white" />
                                </v-avatar>
                            </template>

                            <v-list-item-title class="text-body-1">
                                {{ subject(item) }}
                                <v-chip v-if="!item.read_at" size="x-small" color="primary" class="ml-2">
                                    {{ $t('notifications.new') }}
                                </v-chip>
                            </v-list-item-title>

                            <!-- Announcements carry their own text; the rest an excerpt -->
                            <div
                                v-if="body(item)"
                                class="rich-content text-body-2 text-medium-emphasis mt-1"
                                v-html="body(item)"
                            />
                            <div v-else-if="excerpt(item)" class="text-body-2 text-medium-emphasis mt-1">
                                {{ excerpt(item) }}
                            </div>

                            <v-list-item-subtitle class="text-caption mt-1" :title="$formatDate(item.created_at)">
                                {{ $formatDistanceToNow(item.created_at) }}
                            </v-list-item-subtitle>

                            <template #append>
                                <div class="d-flex align-center ga-1">
                                    <v-btn
                                        v-if="!item.read_at"
                                        icon="mdi-email-open-outline"
                                        variant="text"
                                        size="small"
                                        :title="$t('notifications.markRead')"
                                        :aria-label="$t('notifications.markRead')"
                                        :loading="busy.includes(item.id)"
                                        @click.stop="markAsRead(item)"
                                    />
                                    <v-btn
                                        icon="mdi-delete-outline"
                                        variant="text"
                                        color="error"
                                        size="small"
                                        :title="$t('common.delete')"
                                        :aria-label="$t('common.delete')"
                                        :loading="deleting.includes(item.id)"
                                        @click.stop="deleteNotification(item.id)"
                                    />
                                </div>
                            </template>
                        </v-list-item>
                    </template>
                </v-list>
            </v-card>
        </v-container>
    </div>
</template>

<script>
import axios from 'axios';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import { useDialog } from '@/composables/useDialog.js';
import { sanitizeHtml } from '@/utils/sanitize.js';
import {
    notificationSubject,
    notificationIcon,
    notificationColor,
    notificationUrl,
    notificationExcerpt,
} from '@/utils/notifications.js';

/**
 * Everything that happened: mentions, ticket activity, forum replies,
 * announcements … Read ones stay in the list until they are deleted.
 */
export default {
    name: 'NotificationsPage',
    components: { PageHeader, EmptyState, LoadingState },
    setup() {
        return { dialog: useDialog() };
    },
    data() {
        return {
            notifications: [],
            isLoading: false,
            markingAll: false,
            // Ids with a request in flight
            busy: [],
            deleting: [],
        };
    },
    computed: {
        unreadCount() {
            return this.notifications.filter(item => !item.read_at).length;
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        subject(item) {
            return notificationSubject(item.data, this.$t);
        },
        icon(item) {
            return notificationIcon(item.data);
        },
        color(item) {
            return notificationColor(item.data);
        },
        excerpt(item) {
            return notificationExcerpt(item.data);
        },
        body(item) {
            return item.data?.body ? sanitizeHtml(item.data.body) : null;
        },
        async load() {
            this.isLoading = true;
            try {
                const { data } = await axios.get('/api/account/notifications');
                this.notifications = Array.isArray(data) ? data : Object.values(data ?? {});
            } catch (error) {
                console.warn(error);
                await this.dialog.requestError(error, this.$t('notifications.updateFailed'));
            } finally {
                this.isLoading = false;
            }
        },
        // Opening a notification reads it and goes to what it is about
        async open(item) {
            const url = notificationUrl(item.data);
            if (!item.read_at) await this.markAsRead(item);
            if (url) this.$router.push(url);
        },
        async markAsRead(item) {
            if (this.busy.includes(item.id)) return;
            this.busy.push(item.id);
            try {
                await axios.get(`/api/account/notification/markasread/${item.id}`);
                item.read_at = new Date().toISOString();
            } catch (error) {
                console.warn(error);
                await this.dialog.requestError(error, this.$t('notifications.updateFailed'));
            } finally {
                this.busy = this.busy.filter(id => id !== item.id);
            }
        },
        async markAllAsRead() {
            if (this.markingAll) return;
            this.markingAll = true;
            try {
                await axios.get('/api/account/notification/allasread');
                await this.load();
            } catch (error) {
                console.warn(error);
                await this.dialog.requestError(error, this.$t('notifications.updateFailed'));
            } finally {
                this.markingAll = false;
            }
        },
        async deleteNotification(id) {
            if (this.deleting.length) return;
            if (!(await this.dialog.confirmDelete(this.$t('notifications.confirmDelete')))) return;

            this.deleting.push(id);
            try {
                await axios.delete(`/api/account/notification/delete/${id}`);
                this.notifications = this.notifications.filter(item => item.id !== id);
            } catch (error) {
                console.warn(error);
                await this.dialog.requestError(error, this.$t('notifications.deleteFailed'));
            } finally {
                this.deleting = this.deleting.filter(item => item !== id);
            }
        },
    },
};
</script>

<style scoped>
.notification--unread {
    background-color: rgba(var(--v-theme-primary), 0.05);
    box-shadow: inset 3px 0 0 rgb(var(--v-theme-primary));
}
</style>
