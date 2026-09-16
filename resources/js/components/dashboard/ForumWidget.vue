<template>
    <widget-state
        :loading="loading"
        :error="error"
        :empty="threads.length === 0"
        empty-icon="mdi-forum-outline"
        :empty-text="$t('dashboard.widgets.forum.empty')"
    >
        <v-list density="compact" class="pa-0">
            <v-list-item
                v-for="thread in threads"
                :key="thread.id"
                :to="threadRoute(thread)"
                class="px-0 mb-1"
            >
                <template v-slot:prepend>
                    <v-avatar :color="thread.category_color || 'pink'" size="24">
                        <v-icon size="12" color="white">{{ thread.is_read === false ? 'mdi-forum' : 'mdi-forum-outline' }}</v-icon>
                    </v-avatar>
                </template>
                <v-list-item-title class="text-body-2" :class="{ 'font-weight-bold': thread.is_read === false }">
                    {{ thread.title }}
                </v-list-item-title>
                <v-list-item-subtitle class="text-caption">
                    <span v-if="thread.category_name">{{ thread.category_name }} · </span>
                    {{ $t('dashboard.widgets.forum.replies', { count: thread.reply_count || 0 }) }}
                    · {{ relative(thread.last_post_at || thread.created_at) }}
                </v-list-item-subtitle>
            </v-list-item>
        </v-list>
    </widget-state>
</template>

<script>
import axios from 'axios';
import widgetMixin from './widgetMixin.js';
import WidgetState from './WidgetState.vue';
import { formatDateDistanceToNow } from '@/plugins/formatDate.js';

/**
 * Newest forum threads, from /api/forums/recent-threads.
 */
export default {
    name: 'ForumWidget',
    components: { WidgetState },
    mixins: [widgetMixin],
    data() {
        return {
            threads: [],
        };
    },
    methods: {
        async fetch() {
            const { data } = await axios.get('/api/forums/recent-threads');
            const threads = data.data || [];
            this.threads = threads.slice(0, this.limit);
            const unread = threads.filter(t => t.is_read === false).length;
            this.setSubtitle(this.$t('dashboard.widgets.forum.subtitle', { count: unread }));
        },
        threadRoute(thread) {
            return {
                name: 'forum-thread',
                params: { forumSlug: thread.category_slug, threadSlug: thread.slug },
            };
        },
        relative(date) {
            return formatDateDistanceToNow(date);
        },
    },
};
</script>
