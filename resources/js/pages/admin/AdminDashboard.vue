<template>
    <div class="flex-grow-1">
        <page-header :title="$t('admin.dashboard.title')" :subtitle="subtitle" icon="mdi-view-dashboard-outline">
            <template #actions>
                <v-btn variant="tonal" prepend-icon="mdi-refresh" :loading="loading" :disabled="loading" @click="load">
                    {{ $t('dashboard.refresh') }}
                </v-btn>
            </template>
        </page-header>

        <v-container fluid>
            <loading-state v-if="loading && !data" />

            <template v-if="data">
                <!-- Needs attention -->
                <h2 class="text-h6 mb-2">{{ $t('admin.dashboard.attention.title') }}</h2>
                <v-row dense class="mb-4">
                    <v-col v-for="item in attentionItems" :key="item.key" cols="6" sm="4" md="3" lg="auto" class="flex-lg-grow-1">
                        <v-card
                            variant="tonal"
                            :color="item.value > 0 ? item.color : undefined"
                            :to="item.to"
                            class="h-100"
                        >
                            <v-card-text class="d-flex align-center pa-3">
                                <v-icon size="28" class="mr-3">{{ item.icon }}</v-icon>
                                <div>
                                    <div class="text-h5 font-weight-bold">{{ item.value ?? '–' }}</div>
                                    <div class="text-caption">{{ $t(`admin.dashboard.attention.${item.key}`) }}</div>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
                <v-alert v-if="attentionTotal === 0" type="success" variant="tonal" density="compact" class="mb-6">
                    {{ $t('admin.dashboard.attention.allClear') }}
                </v-alert>

                <v-row>
                    <!-- Community -->
                    <v-col cols="12" md="6">
                        <v-card variant="outlined" class="h-100">
                            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                                <v-icon class="mr-2" color="primary">mdi-account-group</v-icon>
                                {{ $t('admin.dashboard.users.title') }}
                                <v-spacer />
                                <v-btn size="small" variant="text" to="/admin/users">{{ $t('admin.dashboard.manage') }}</v-btn>
                            </v-card-title>
                            <v-card-text>
                                <div class="stat-grid">
                                    <div v-for="key in ['total', 'new_7d', 'new_30d', 'active_24h', 'active_7d', 'admins']" :key="key" class="text-center">
                                        <div class="text-h6 font-weight-bold">{{ data.users[key] }}</div>
                                        <div class="text-caption text-medium-emphasis">{{ $t(`admin.dashboard.users.${key}`) }}</div>
                                    </div>
                                </div>
                                <v-divider class="my-3" />
                                <div class="text-caption text-medium-emphasis mb-1">{{ $t('admin.dashboard.users.latest') }}</div>
                                <v-list density="compact" class="pa-0">
                                    <v-list-item v-for="user in data.recent.users" :key="user.id" class="px-0" to="/admin/users">
                                        <template v-slot:prepend>
                                            <v-avatar size="24" color="primary"><span class="text-caption text-white">{{ initial(user.username) }}</span></v-avatar>
                                        </template>
                                        <v-list-item-title class="text-body-2">{{ user.username }}</v-list-item-title>
                                        <v-list-item-subtitle class="text-caption">
                                            {{ $t('admin.dashboard.users.registered', { time: relative(user.created_at) }) }}
                                            <v-chip v-if="!user.email_verified_at" size="x-small" color="warning" variant="tonal" class="ml-1">
                                                {{ $t('admin.dashboard.users.unverified') }}
                                            </v-chip>
                                        </v-list-item-subtitle>
                                    </v-list-item>
                                </v-list>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <!-- Content -->
                    <v-col cols="12" md="6">
                        <v-card variant="outlined" class="h-100">
                            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                                <v-icon class="mr-2" color="warning">mdi-database</v-icon>
                                {{ $t('admin.dashboard.content.title') }}
                            </v-card-title>
                            <v-card-text>
                                <v-list density="compact" class="pa-0">
                                    <v-list-item v-for="item in contentItems" :key="item.key" :to="item.to" class="px-0">
                                        <template v-slot:prepend>
                                            <v-icon size="18" :color="item.color">{{ item.icon }}</v-icon>
                                        </template>
                                        <v-list-item-title class="text-body-2">{{ $t(`admin.dashboard.content.${item.key}`) }}</v-list-item-title>
                                        <template v-slot:append>
                                            <span class="font-weight-bold">{{ data.content[item.key] }}</span>
                                            <span v-if="item.delta !== undefined" class="text-caption text-success ml-2">
                                                +{{ data.content[item.delta] }} {{ $t('admin.dashboard.content.thisWeek') }}
                                            </span>
                                        </template>
                                    </v-list-item>
                                </v-list>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <!-- Unassigned tickets -->
                    <v-col cols="12" md="6">
                        <v-card variant="outlined" class="h-100">
                            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                                <v-icon class="mr-2" color="secondary">mdi-ticket-confirmation</v-icon>
                                {{ $t('admin.dashboard.tickets.title') }}
                                <v-spacer />
                                <v-btn size="small" variant="text" to="/admin/tickets?assigned_to=unassigned">{{ $t('admin.dashboard.manage') }}</v-btn>
                            </v-card-title>
                            <v-card-text>
                                <empty-state
                                    v-if="data.recent.tickets.length === 0"
                                    icon="mdi-ticket-outline"
                                    :title="$t('admin.dashboard.tickets.empty')"
                                    compact
                                />
                                <v-list v-else density="compact" class="pa-0">
                                    <v-list-item
                                        v-for="ticket in data.recent.tickets"
                                        :key="ticket.id"
                                        class="px-0"
                                        :to="{ path: '/admin/tickets', query: { assigned_to: 'unassigned', search: ticket.title } }"
                                    >
                                        <template v-slot:prepend>
                                            <v-avatar size="24" :color="getPriorityColor(ticket.priority)">
                                                <v-icon size="12" color="white">{{ getPriorityIcon(ticket.priority) }}</v-icon>
                                            </v-avatar>
                                        </template>
                                        <v-list-item-title class="text-body-2">{{ ticket.title }}</v-list-item-title>
                                        <v-list-item-subtitle class="text-caption">
                                            {{ ticket.creator }} · {{ relative(ticket.created_at) }}
                                        </v-list-item-subtitle>
                                    </v-list-item>
                                </v-list>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <!-- Recent activity -->
                    <v-col cols="12" md="6">
                        <v-card variant="outlined" class="h-100">
                            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                                <v-icon class="mr-2" color="primary">mdi-pulse</v-icon>
                                {{ $t('admin.dashboard.activity.title') }}
                            </v-card-title>
                            <v-card-text>
                                <empty-state
                                    v-if="data.recent.activity.length === 0"
                                    icon="mdi-pulse"
                                    :title="$t('admin.dashboard.activity.empty')"
                                    compact
                                />
                                <v-list v-else density="compact" class="pa-0">
                                    <v-list-item v-for="entry in data.recent.activity" :key="entry.id" class="px-0">
                                        <template v-slot:prepend>
                                            <v-icon size="18" color="primary">mdi-circle-small</v-icon>
                                        </template>
                                        <v-list-item-title class="text-body-2">
                                            <strong v-if="entry.causer">{{ entry.causer }}</strong> {{ entry.description }}
                                        </v-list-item-title>
                                        <v-list-item-subtitle class="text-caption">
                                            <span v-if="entry.subject">{{ entry.subject }} · </span>{{ relative(entry.created_at) }}
                                        </v-list-item-subtitle>
                                    </v-list-item>
                                </v-list>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <!-- System -->
                    <v-col cols="12">
                        <v-card variant="outlined">
                            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                                <v-icon class="mr-2" color="success">mdi-server</v-icon>
                                {{ $t('admin.dashboard.system.title') }}
                                <v-spacer />
                                <v-btn size="small" variant="text" to="/admin/settings">{{ $t('admin.dashboard.settings') }}</v-btn>
                            </v-card-title>
                            <v-card-text>
                                <div class="d-flex flex-wrap ga-2">
                                    <v-chip v-for="chip in systemChips" :key="chip.key" :color="chip.color" variant="tonal" :prepend-icon="chip.icon" size="small">
                                        {{ $t(`admin.dashboard.system.${chip.key}`) }}: {{ chip.value }}
                                    </v-chip>
                                </div>
                                <div v-if="data.system.last_migration" class="text-caption text-medium-emphasis mt-3">
                                    {{ $t('admin.dashboard.system.lastMigration', {
                                        name: data.system.last_migration.name,
                                        status: data.system.last_migration.status,
                                        time: relative(data.system.last_migration.completed_at)
                                    }) }}
                                    <router-link to="/admin/migrations" class="ml-1">{{ $t('admin.dashboard.manage') }}</router-link>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>

                <!-- Quick links -->
                <h2 class="text-h6 mt-6 mb-2">{{ $t('admin.dashboard.quickLinks') }}</h2>
                <div class="d-flex flex-wrap ga-2">
                    <v-btn v-for="link in quickLinks" :key="link.to" :to="link.to" variant="tonal" size="small" :prepend-icon="link.icon">
                        {{ $t(link.key) }}
                    </v-btn>
                </div>
            </template>
        </v-container>
    </div>
</template>

<script>
import axios from 'axios';
import { useTicketHelpers } from '@/composables/useTicketHelpers.js';
import { formatDateDistanceToNow } from '@/plugins/formatDate.js';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';

/**
 * Admin overview: moderation queues, community and content numbers,
 * system health and the latest registrations, activity and tickets —
 * all from /api/admin/dashboard.
 */
export default {
    name: 'AdminDashboard',
    components: { PageHeader, EmptyState, LoadingState },
    setup() {
        const { getPriorityColor, getPriorityIcon } = useTicketHelpers();

        return { getPriorityColor, getPriorityIcon };
    },
    data() {
        return {
            data: null,
            loading: false,
            quickLinks: [
                { to: '/admin/settings', icon: 'mdi-cog-outline', key: 'menu.adminSettings' },
                { to: '/admin/users', icon: 'mdi-account-group-outline', key: 'menu.adminUsers' },
                { to: '/admin/tickets', icon: 'mdi-ticket-outline', key: 'menu.adminTickets' },
                { to: '/admin/polls', icon: 'mdi-poll', key: 'menu.adminPolls' },
                { to: '/admin/media', icon: 'mdi-folder-multiple-image', key: 'menu.adminMedia' },
                { to: '/admin/announcements', icon: 'mdi-bullhorn-outline', key: 'menu.adminAnnouncement' },
                { to: '/admin/settings/localization/translations', icon: 'mdi-translate', key: 'menu.adminTranslations' },
                { to: '/admin/settings/tools/migrations', icon: 'mdi-database-import-outline', key: 'migrationDashboard.title' },
            ],
        };
    },
    computed: {
        subtitle() {
            const base = this.$t('admin.dashboard.subtitle');
            if (!this.data) return base;
            return `${base} · ${this.$t('admin.dashboard.updated', { time: this.relative(this.data.generated_at) })}`;
        },
        attentionItems() {
            const a = this.data.attention;
            return [
                { key: 'pending_wiki', value: a.pending_wiki, icon: 'mdi-book-clock-outline', color: 'warning', to: '/wiki' },
                { key: 'pending_event_guests', value: a.pending_event_guests, icon: 'mdi-account-clock-outline', color: 'warning', to: '/admin/events' },
                { key: 'unassigned_tickets', value: a.unassigned_tickets, icon: 'mdi-ticket-account', color: 'secondary', to: '/admin/tickets?assigned_to=unassigned' },
                { key: 'overdue_tickets', value: a.overdue_tickets, icon: 'mdi-clock-alert-outline', color: 'error', to: '/admin/tickets?due=overdue' },
                { key: 'legacy_claims', value: a.legacy_claims, icon: 'mdi-account-convert', color: 'info', to: '/admin/migrations?tab=legacyUsers' },
                { key: 'unverified_users', value: a.unverified_users, icon: 'mdi-email-alert-outline', color: 'secondary', to: '/admin/users' },
                { key: 'failed_jobs', value: a.failed_jobs, icon: 'mdi-alert-octagon-outline', color: 'error', to: null },
            ];
        },
        attentionTotal() {
            return this.attentionItems.reduce((sum, item) => sum + (item.value || 0), 0);
        },
        contentItems() {
            return [
                { key: 'wiki_pages', icon: 'mdi-book-open-variant', color: 'warning', to: '/wiki' },
                { key: 'forum_threads', delta: 'forum_threads_7d', icon: 'mdi-forum', color: 'primary', to: '/admin/forums' },
                { key: 'forum_posts', delta: 'forum_posts_7d', icon: 'mdi-comment-multiple', color: 'primary', to: '/forum' },
                { key: 'events_upcoming', icon: 'mdi-calendar-clock', color: 'info', to: '/admin/events' },
                { key: 'open_tickets', icon: 'mdi-ticket-confirmation', color: 'secondary', to: '/admin/tickets' },
                { key: 'albums', icon: 'mdi-image-multiple', color: 'warning', to: '/admin/gallery' },
                { key: 'media_files', icon: 'mdi-folder-multiple-image', color: 'warning', to: '/admin/media' },
                { key: 'sandboxes', icon: 'mdi-file-document-edit', color: 'info', to: '/sandbox' },
                { key: 'messages_7d', icon: 'mdi-message-text', color: 'success', to: '/conversations' },
            ];
        },
        systemChips() {
            const s = this.data.system;
            const onOff = value => this.$t(value ? 'admin.dashboard.system.on' : 'admin.dashboard.system.off');
            const chips = [
                { key: 'environment', value: s.environment, icon: 'mdi-earth', color: s.environment === 'production' ? 'success' : 'warning' },
                { key: 'debug', value: onOff(s.debug), icon: 'mdi-bug-outline', color: s.debug ? 'error' : 'success' },
                { key: 'maintenance', value: onOff(s.maintenance), icon: 'mdi-wrench-outline', color: s.maintenance ? 'error' : 'success' },
                { key: 'php', value: s.php_version, icon: 'mdi-language-php', color: 'secondary' },
                { key: 'laravel', value: s.laravel_version, icon: 'mdi-laravel', color: 'secondary' },
                { key: 'cache', value: `${onOff(s.cache_enabled)} (${s.cache_driver})`, icon: 'mdi-lightning-bolt-outline', color: s.cache_enabled ? 'success' : 'warning' },
                { key: 'queue', value: s.queue_driver, icon: 'mdi-tray-full', color: s.queue_driver === 'sync' ? 'warning' : 'success' },
                { key: 'sandbox', value: onOff(s.sandbox_enabled), icon: 'mdi-file-document-edit-outline', color: 'secondary' },
                { key: 'media', value: this.data.content.media_size, icon: 'mdi-harddisk', color: 'secondary' },
            ];
            if (s.irc_daemon !== null) {
                chips.push({ key: 'ircDaemon', value: onOff(s.irc_daemon), icon: 'mdi-console-network-outline', color: s.irc_daemon ? 'success' : 'warning' });
            }
            if (s.disk_free) {
                chips.push({ key: 'disk', value: `${s.disk_free} (${s.disk_used_pct}%)`, icon: 'mdi-harddisk', color: s.disk_used_pct > 90 ? 'error' : 'secondary' });
            }
            return chips;
        },
    },
    methods: {
        async load() {
            if (this.loading) return;
            this.loading = true;
            try {
                const { data } = await axios.get('/api/admin/dashboard');
                this.data = data.data;
            } catch (e) {
                this.$dialog.requestError(e);
            } finally {
                this.loading = false;
            }
        },
        relative(date) {
            return date ? formatDateDistanceToNow(date) : '';
        },
        initial(name) {
            return (name || '?').charAt(0).toUpperCase();
        },
    },
    mounted() {
        this.load();
    },
};
</script>

<style scoped>
.stat-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}
</style>
