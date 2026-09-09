<template>
    <widget-state
        :loading="loading"
        :error="error"
        :empty="notifications.length === 0"
        empty-icon="mdi-bell-check-outline"
        :empty-text="$t('dashboard.widgets.notifications.empty')"
    >
        <div
            v-for="notification in notifications"
            :key="notification.id"
            class="d-flex align-center mb-3 notification-row"
            :class="{ 'notification-row--busy': isBusy(notification) }"
            @click="open(notification)"
        >
            <v-avatar :color="color(notification)" size="24" class="mr-3">
                <v-icon color="white" size="12">{{ icon(notification) }}</v-icon>
            </v-avatar>
            <div class="flex-grow-1 overflow-hidden">
                <div class="text-body-2 text-truncate">{{ subject(notification) }}</div>
                <div class="text-caption text-medium-emphasis">{{ relative(notification.created_at) }}</div>
            </div>
            <v-btn
                icon="mdi-eye-check-outline"
                size="x-small"
                variant="text"
                :title="$t('dashboard.widgets.notifications.markRead')"
                :loading="isBusy(notification)"
                :disabled="busyAll"
                @click.stop="markRead(notification)"
            />
        </div>
        <v-btn
            v-if="notifications.length > 1"
            variant="text"
            size="x-small"
            color="primary"
            block
            :loading="busyAll"
            :disabled="busy.length > 0"
            @click="markAllRead"
        >
            {{ $t('dashboard.widgets.notifications.markAllRead') }}
        </v-btn>
    </widget-state>
</template>

<script>
import axios from 'axios';
import widgetMixin from './widgetMixin.js';
import WidgetState from './WidgetState.vue';
import { formatDateDistanceToNow } from '@/plugins/formatDate.js';

/**
 * The user's unread notifications, from /api/account/notification.
 * Mark-as-read actions show a loader and ignore repeated clicks while
 * a request is in flight.
 */
export default {
    name: 'NotificationsWidget',
    components: { WidgetState },
    mixins: [widgetMixin],
    data() {
        return {
            notifications: [],
            unread: 0,
            // Ids of notifications with a request in flight.
            busy: [],
            busyAll: false,
        };
    },
    methods: {
        async fetch() {
            const { data } = await axios.get('/api/account/notification');
            const all = Array.isArray(data) ? data : [];
            this.unread = all.length;
            this.notifications = all.slice(0, this.limit);
            this.setSubtitle(this.$t('dashboard.widgets.notifications.subtitle', { count: this.unread }));
        },
        isBusy(notification) {
            return this.busy.includes(notification.id);
        },
        subject(notification) {
            const d = notification.data || {};
            return d.subject || d.thread_title || d.sandbox_title || this.$t('dashboard.widgets.notifications.title');
        },
        icon(notification) {
            const type = notification.data?.type || '';
            if (type.startsWith('forum_')) return type === 'forum_mention' ? 'mdi-at' : 'mdi-forum-outline';
            if (type.startsWith('sandbox_')) return 'mdi-file-document-edit-outline';
            return 'mdi-bell-outline';
        },
        color(notification) {
            const type = notification.data?.type || '';
            if (type.startsWith('forum_')) return 'warning';
            if (type.startsWith('sandbox_')) return 'info';
            return 'primary';
        },
        relative(date) {
            return formatDateDistanceToNow(date);
        },
        async open(notification) {
            if (this.isBusy(notification) || this.busyAll) return;
            const url = notification.data?.url || notification.data?.thread_url;
            const marked = await this.markRead(notification);
            if (url && marked) this.$router.push(url);
        },
        // Failures of these explicit actions are reported as a modal; the
        // widget's inline error state is reserved for the data load.
        async markRead(notification) {
            if (this.isBusy(notification) || this.busyAll) return false;
            this.busy.push(notification.id);
            try {
                await axios.get('/api/account/notification/markasread/' + notification.id);
                await this.load();
                return true;
            } catch (e) {
                await this.$dialog.requestError(e, this.$t('notifications.updateFailed'));
                return false;
            } finally {
                this.busy = this.busy.filter(id => id !== notification.id);
            }
        },
        async markAllRead() {
            if (this.busyAll || this.busy.length) return;
            this.busyAll = true;
            try {
                await axios.get('/api/account/notification/allasread');
                await this.load();
            } catch (e) {
                await this.$dialog.requestError(e, this.$t('notifications.updateFailed'));
            } finally {
                this.busyAll = false;
            }
        },
    },
};
</script>

<style scoped>
.notification-row {
    cursor: pointer;
    border-radius: 8px;
}
.notification-row:hover {
    background-color: rgba(var(--v-theme-on-surface), 0.04);
}
.notification-row--busy {
    opacity: 0.6;
    pointer-events: none;
}
</style>
