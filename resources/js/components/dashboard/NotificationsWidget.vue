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
                @click.stop="markRead(notification)"
            />
        </div>
        <v-btn
            v-if="notifications.length > 1"
            variant="text"
            size="x-small"
            color="primary"
            block
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
 */
export default {
    name: 'NotificationsWidget',
    components: { WidgetState },
    mixins: [widgetMixin],
    data() {
        return {
            notifications: [],
            unread: 0,
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
            await this.markRead(notification);
            const url = notification.data?.url || notification.data?.thread_url;
            if (url) this.$router.push(url);
        },
        async markRead(notification) {
            await axios.get('/api/account/notification/markasread/' + notification.id);
            await this.load();
        },
        async markAllRead() {
            await axios.get('/api/account/notification/allasread');
            await this.load();
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
</style>
