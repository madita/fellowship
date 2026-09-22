<template>
    <v-container>
        <page-header
            :title="$t('achievements.title')"
            :subtitle="$t('achievements.subtitle')"
            icon="mdi-trophy-outline"
        >
            <template #actions>
                <div class="d-flex align-center ga-3">
                    <!-- Where the points have carried them, and how far to
                         the next rung -->
                    <div v-if="rank.current" class="d-flex align-center ga-2">
                        <achievement-badge :achievement="rank.current" :size="40" />
                        <div>
                            <div class="text-body-2 font-weight-bold">{{ rank.current.name }}</div>
                            <div v-if="rank.next" class="text-caption text-medium-emphasis">
                                {{ $t('achievements.toNextRank', { points: rank.to_next, rank: rank.next.name }) }}
                            </div>
                            <div v-else class="text-caption text-medium-emphasis">
                                {{ $t('achievements.topRank') }}
                            </div>
                        </div>
                    </div>

                    <div class="text-right">
                        <div class="text-h5 font-weight-bold">{{ points }}</div>
                        <div class="text-caption text-medium-emphasis">{{ $t('achievements.points') }}</div>
                    </div>
                </div>
            </template>
        </page-header>

        <v-progress-linear
            v-if="rank.current && rank.next"
            :model-value="rank.percent"
            :color="rank.next.color"
            height="6"
            rounded
            class="mb-4"
        />

        <loading-state v-if="loading" />

        <template v-else>
            <v-row class="mb-2">
                <v-col cols="12" md="8">
                    <div class="d-flex align-center ga-3 mb-2">
                        <span class="text-body-2 font-weight-medium">
                            {{ $t('achievements.earnedOf', { earned: earnedCount, total: achievements.length }) }}
                        </span>
                        <v-progress-linear
                            :model-value="earnedCount / Math.max(achievements.length, 1) * 100"
                            color="amber"
                            height="8"
                            rounded
                        />
                    </div>

                    <!-- The kinds in use, named by whoever set them up and
                         already in the member's language when they arrive -->
                    <v-chip-group v-model="type" mandatory>
                        <v-chip size="small" value="all">{{ $t('achievements.all') }}</v-chip>
                        <v-chip
                            v-for="kind in types"
                            :key="kind"
                            size="small"
                            :value="kind"
                        >
                            {{ kind }}
                        </v-chip>
                    </v-chip-group>
                </v-col>

                <v-col cols="12" md="4">
                    <v-card variant="outlined" class="pa-3">
                        <div class="text-caption text-medium-emphasis mb-2">
                            {{ $t('achievements.leaderboard') }}
                        </div>
                        <div
                            v-for="(leader, index) in leaders"
                            :key="leader.id"
                            class="d-flex align-center ga-2 py-1"
                        >
                            <span class="text-caption text-medium-emphasis" style="width: 18px">{{ index + 1 }}</span>
                            <span class="text-body-2 flex-grow-1 text-truncate">
                                {{ leader.username }}
                                <span v-if="leader.rank" class="text-caption text-medium-emphasis">· {{ leader.rank }}</span>
                            </span>
                            <span class="text-body-2 font-weight-bold">{{ leader.points }}</span>
                        </div>
                        <empty-state
                            v-if="!leaders.length"
                            compact
                            icon="mdi-podium"
                            :title="$t('achievements.noLeaders')"
                        />
                    </v-card>
                </v-col>
            </v-row>

            <v-row>
                <v-col
                    v-for="achievement in visible"
                    :key="achievement.id"
                    cols="12"
                    sm="6"
                    md="4"
                >
                    <v-card
                        variant="outlined"
                        class="achievement pa-3 h-100"
                        :class="{ 'is-locked': !achievement.earned }"
                    >
                        <div class="d-flex ga-3">
                            <achievement-badge :achievement="achievement" :locked="!achievement.earned" :size="48" />

                            <div class="flex-grow-1 min-width-0">
                                <div class="d-flex align-center justify-space-between ga-2">
                                    <span class="text-body-1 font-weight-medium text-truncate">
                                        {{ achievement.name }}
                                    </span>
                                    <v-chip size="x-small" variant="tonal">{{ achievement.points }}</v-chip>
                                </div>

                                <div class="text-caption text-medium-emphasis">
                                    {{ achievement.description }}
                                </div>

                                <!-- Earned says when; still locked says how far -->
                                <div v-if="achievement.earned" class="text-caption text-success mt-1">
                                    <v-icon size="x-small" icon="mdi-check-circle" class="mr-1" />
                                    {{ formatDate(achievement.awarded_at) }}
                                </div>

                                <div v-else-if="achievement.trigger === 'metric'" class="mt-2">
                                    <v-progress-linear
                                        :model-value="achievement.progress / Math.max(achievement.threshold, 1) * 100"
                                        :color="achievement.color"
                                        height="6"
                                        rounded
                                    />
                                    <div class="text-caption text-medium-emphasis mt-1">
                                        {{ achievement.progress }} / {{ achievement.threshold }}
                                    </div>
                                </div>

                                <!-- Nothing on the site can count this one -->
                                <div v-else class="text-caption text-medium-emphasis mt-1">
                                    <v-icon size="x-small" icon="mdi-hand-heart-outline" class="mr-1" />
                                    {{ $t('achievements.awardedByHand') }}
                                </div>

                                <div v-if="achievement.note" class="text-caption font-italic mt-1">
                                    “{{ achievement.note }}”
                                </div>
                            </div>
                        </div>
                    </v-card>
                </v-col>
            </v-row>

            <empty-state
                v-if="!visible.length"
                icon="mdi-trophy-outline"
                :title="$t('achievements.none')"
            />
        </template>
    </v-container>
</template>

<script>
import axios from 'axios';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import AchievementBadge from '@/components/achievements/AchievementBadge.vue';

export default {
    name: 'AchievementsPage',
    components: { PageHeader, EmptyState, LoadingState, AchievementBadge },
    data() {
        return {
            loading: true,
            achievements: [],
            leaders: [],
            points: 0,
            rank: { current: null, next: null, to_next: null, percent: 0 },
            type: 'all',
        };
    },
    computed: {
        types() {
            return [...new Set(this.achievements.map(a => a.type).filter(Boolean))];
        },
        earnedCount() {
            return this.achievements.filter(a => a.earned).length;
        },
        visible() {
            if (this.type === 'all') return this.achievements;

            return this.achievements.filter(a => a.type === this.type);
        },
    },
    async mounted() {
        await this.load();
    },
    methods: {
        async load() {
            this.loading = true;
            try {
                const [mine, board] = await Promise.all([
                    axios.get('/api/achievements'),
                    axios.get('/api/achievements/leaderboard'),
                ]);

                this.achievements = mine.data.data || [];
                this.points = mine.data.points || 0;
                this.rank = mine.data.rank || this.rank;
                this.leaders = board.data.data || [];
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('achievements.loadFailed'));
            } finally {
                this.loading = false;
            }
        },
        formatDate(value) {
            if (!value) return '';

            return new Date(value).toLocaleDateString(this.$i18n?.locale || 'en', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
        },
    },
};
</script>

<style scoped>
.achievement.is-locked {
    opacity: 0.72;
}

.min-width-0 {
    min-width: 0;
}
</style>
