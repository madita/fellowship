<template>
    <div>
        <loading-state v-if="loading" />

        <empty-state
            v-else-if="!member"
            icon="mdi-account-question-outline"
            :title="$t('memberProfile.notFound')"
            :text="$t('memberProfile.notFoundText')"
        />

        <template v-else>
            <!-- Same header as every other top-level page -->
            <page-header :title="member.username" :subtitle="headerSubtitle" icon="mdi-account-circle-outline">
                <template #actions>
                    <div class="d-flex align-center ga-4">
                        <v-avatar :color="member.avatar ? undefined : 'primary'" size="64">
                            <v-img v-if="member.avatar" :src="member.avatar" :alt="member.username" />
                            <span v-else class="text-h5 text-white">{{ member.initials }}</span>
                        </v-avatar>

                        <div class="text-center">
                            <div class="text-h5 font-weight-bold">{{ member.points }}</div>
                            <div class="text-caption text-medium-emphasis">{{ $t('achievements.points') }}</div>
                        </div>
                    </div>
                </template>

                <!-- Rank and the details the member chose to share -->
                <div class="d-flex align-center flex-wrap ga-3">
                    <v-chip v-if="member.rank" size="small" :color="member.rank.color" variant="tonal">
                        <v-icon :icon="member.rank.icon" size="x-small" start />
                        {{ member.rank.name }}
                    </v-chip>

                    <span
                        v-for="item in about"
                        :key="item.key"
                        class="text-caption text-medium-emphasis d-inline-flex align-center"
                    >
                        <v-icon :icon="item.icon" size="x-small" class="mr-1" />
                        <a
                            v-if="item.key === 'website'"
                            :href="item.value"
                            target="_blank"
                            rel="noopener noreferrer"
                        >{{ item.value }}</a>
                        <template v-else>{{ item.value }}</template>
                    </span>
                </div>
            </page-header>

            <v-container>
                <p v-if="member.about?.bio" class="text-body-1 member-bio mb-4">
                    {{ member.about.bio }}
                </p>

                <v-row>
                    <!-- What they have made, one card per kind, the way the
                         dashboard shows its widgets -->
                    <v-col
                        v-for="kind in kinds"
                        :key="kind.key"
                        cols="12"
                        md="6"
                    >
                        <v-card variant="outlined" class="pa-4 h-100">
                            <div class="d-flex align-center justify-space-between mb-3">
                                <span class="text-subtitle-2 font-weight-medium d-inline-flex align-center">
                                    <v-icon :icon="kind.icon" :color="kind.color" size="small" class="mr-2" />
                                    {{ $t(`memberProfile.recent.${kind.key}`) }}
                                </span>
                                <v-chip size="x-small" variant="tonal">{{ kind.count }}</v-chip>
                            </div>

                            <v-list v-if="kind.items.length" density="compact" class="py-0">
                                <v-list-item
                                    v-for="item in kind.items"
                                    :key="`${kind.key}-${item.id}`"
                                    :to="item.url"
                                    class="px-0"
                                >
                                    <v-list-item-title class="text-body-2">
                                        {{ item.title || item.excerpt }}
                                    </v-list-item-title>
                                    <v-list-item-subtitle v-if="item.title && item.excerpt" class="text-caption">
                                        {{ item.excerpt }}
                                    </v-list-item-subtitle>
                                    <template #append>
                                        <span class="text-caption text-medium-emphasis">
                                            {{ formatSince(item.at) }}
                                        </span>
                                    </template>
                                </v-list-item>
                            </v-list>

                            <empty-state
                                v-else
                                compact
                                :icon="kind.icon"
                                :title="$t('memberProfile.recent.none')"
                            />
                        </v-card>
                    </v-col>

                    <!-- The badge case -->
                    <v-col cols="12">
                        <v-card variant="outlined" class="pa-4">
                            <div class="text-subtitle-2 font-weight-medium mb-3 d-inline-flex align-center">
                                <v-icon icon="mdi-trophy-outline" color="amber" size="small" class="mr-2" />
                                {{ $t('achievements.profile.title') }}
                                <span class="text-caption text-medium-emphasis ml-1">
                                    ({{ member.achievements.length }})
                                </span>
                            </div>

                            <div v-if="member.achievements.length" class="d-flex ga-3 flex-wrap">
                                <v-tooltip
                                    v-for="badge in member.achievements"
                                    :key="badge.id"
                                    location="top"
                                >
                                    <template #activator="{ props }">
                                        <achievement-badge v-bind="props" :achievement="badge" :size="48" />
                                    </template>
                                    <div class="font-weight-medium">{{ badge.name }}</div>
                                    <div v-if="badge.description" class="text-caption">{{ badge.description }}</div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ formatDate(badge.awarded_at) }}
                                    </div>
                                </v-tooltip>
                            </div>

                            <empty-state
                                v-else
                                compact
                                icon="mdi-trophy-outline"
                                :title="$t('achievements.profile.none')"
                            />
                        </v-card>
                    </v-col>
                </v-row>
            </v-container>
        </template>
    </div>
</template>

<script>
import axios from 'axios';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import AchievementBadge from '@/components/achievements/AchievementBadge.vue';
import { formatDateDistanceToNow } from '@/plugins/formatDate.js';

// What each kind of thing looks like, and which count belongs to it
const KINDS = [
    { key: 'forum', icon: 'mdi-forum-outline', color: 'primary', count: 'posts' },
    { key: 'timeline', icon: 'mdi-timeline-text-outline', color: 'teal', count: 'timeline' },
    { key: 'wiki', icon: 'mdi-book-open-page-variant-outline', color: 'deep-purple', count: 'wiki_pages' },
    { key: 'gallery', icon: 'mdi-image-multiple-outline', color: 'pink', count: 'albums' },
];

export default {
    name: 'MemberProfilePage',
    components: { PageHeader, EmptyState, LoadingState, AchievementBadge },
    data() {
        return {
            loading: true,
            member: null,
        };
    },
    computed: {
        headerSubtitle() {
            return this.$t('memberProfile.memberSince', { date: this.formatDate(this.member?.member_since) });
        },

        /**
         * The details the member shares, in a fixed order. The bio is shown
         * on its own below, so it is not repeated here.
         */
        about() {
            const shared = this.member?.about || {};
            const place = [shared.city, shared.country].filter(Boolean).join(', ');

            return [
                { key: 'pronouns', icon: 'mdi-account-outline', value: shared.pronouns },
                { key: 'location', icon: 'mdi-map-marker-outline', value: place },
                { key: 'website', icon: 'mdi-link-variant', value: shared.website },
                { key: 'birthday', icon: 'mdi-cake-variant-outline', value: this.formatDate(shared.birthday) },
            ].filter(item => item.value);
        },

        kinds() {
            const recent = this.member?.recent || {};
            const counts = this.member?.counts || {};

            return KINDS.map(kind => ({
                ...kind,
                items: recent[kind.key] || [],
                count: counts[kind.count] ?? 0,
            }));
        },
    },
    watch: {
        // Clicking a name on the page one is already on has to reload it
        '$route.params.username'() {
            this.load();
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        async load() {
            this.loading = true;
            try {
                const { data } = await axios.get(`/api/members/${encodeURIComponent(this.$route.params.username)}`);
                this.member = data.data;
            } catch {
                // A name nobody holds is not an error worth a dialog
                this.member = null;
            } finally {
                this.loading = false;
            }
        },
        formatDate(value) {
            if (!value) return '';

            return new Date(value).toLocaleDateString(this.$i18n?.locale || 'en', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        },
        formatSince(value) {
            return value ? formatDateDistanceToNow(value) : '';
        },
    },
};
</script>

<style scoped>
.member-bio {
    white-space: pre-line;
}
</style>
