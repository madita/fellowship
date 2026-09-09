<template>
    <v-card variant="outlined" rounded="lg">
        <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center ga-2">
            <v-icon>mdi-pulse</v-icon>
            {{ $t('forum.recentActivity') }}
        </v-card-title>
        <v-card-text v-if="activities.length > 0" class="pa-0">
            <v-list density="compact">
                <v-list-item
                    v-for="activity in activities"
                    :key="activity.id"
                    class="activity-item"
                >
                    <template v-slot:prepend>
                        <UserAvatar v-if="activity.causer" :user="activity.causer" size="32" />
                    </template>
                    <v-list-item-title class="text-body-2">
                        <strong>{{ activity.causer?.username }}</strong>
                        {{ getActivityText(activity) }}
                    </v-list-item-title>
                    <v-list-item-subtitle class="text-caption">
                        {{ formatDateDistance(activity.created_at) }}
                    </v-list-item-subtitle>
                </v-list-item>
            </v-list>
        </v-card-text>
        <empty-state v-else compact icon="mdi-pulse" :title="$t('forum.noActivity')" />
    </v-card>
</template>

<script>
import { formatDateDistanceToNow } from '@/plugins/formatDate.js'
import UserAvatar from '@/components/common/UserAvatar.vue'
import EmptyState from '@/components/common/EmptyState.vue'

export default {
    name: 'ForumActivityFeed',
    components: { UserAvatar, EmptyState },
    props: {
        activities: { type: Array, default: () => [] }
    },
    methods: {
        getActivityText(activity) {
            const title = activity.properties?.thread_title || ''
            switch (activity.event) {
                case 'thread_created':
                    return this.$t('forum.activityThreadCreated', { title })
                case 'post_created':
                    return this.$t('forum.activityPostCreated', { title })
                case 'post_liked':
                    return this.$t('forum.activityPostLiked', { title })
                case 'solution_marked':
                    return this.$t('forum.activitySolutionMarked', { title })
                default:
                    return activity.description
            }
        },
        formatDateDistance(date) {
            if (!date) return ''
            return formatDateDistanceToNow(date)
        }
    }
}
</script>

<style scoped>
.activity-item {
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.activity-item:last-child {
    border-bottom: none;
}
</style>
