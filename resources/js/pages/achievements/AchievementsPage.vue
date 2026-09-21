<template>
    <v-container>
        <page-header
            :title="$t('achievements.title')"
            :subtitle="$t('achievements.subtitle')"
            icon="mdi-trophy-outline"
        >
            <template #actions>
                <div class="text-right">
                    <div class="text-h5 font-weight-bold">{{ points }}</div>
                    <div class="text-caption text-medium-emphasis">{{ $t('achievements.points') }}</div>
                </div>
            </template>
        </page-header>

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

                    <v-chip-group v-model="category" mandatory>
                        <v-chip size="small" value="all">{{ $t('achievements.all') }}</v-chip>
                        <v-chip
                            v-for="group in categories"
                            :key="group"
                            size="small"
                            :value="group"
                        >
                            {{ $t(`achievements.categories.${group}`) }}
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
                            <span class="text-body-2 flex-grow-1 text-truncate">{{ leader.username }}</span>
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
                            <v-avatar :color="achievement.earned ? achievement.color : 'grey-lighten-1'" size="48">
                                <v-icon :icon="achievement.icon" color="white" />
                            </v-avatar>

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

export default {
    name: 'AchievementsPage',
    components: { PageHeader, EmptyState, LoadingState },
    data() {
        return {
            loading: true,
            achievements: [],
            leaders: [],
            points: 0,
            category: 'all',
        };
    },
    computed: {
        categories() {
            return [...new Set(this.achievements.map(a => a.category))];
        },
        earnedCount() {
            return this.achievements.filter(a => a.earned).length;
        },
        visible() {
            if (this.category === 'all') return this.achievements;

            return this.achievements.filter(a => a.category === this.category);
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
