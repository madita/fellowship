<template>
    <widget-state :loading="loading" :error="error" :empty="!summary" :empty-icon="'mdi-trophy-outline'">
        <div v-if="summary">
            <div class="d-flex align-center justify-space-between mb-2">
                <div class="d-flex align-center ga-2">
                    <span class="text-h6 font-weight-bold">{{ summary.points }}</span>
                    <span class="text-caption text-medium-emphasis">{{ $t('achievements.points') }}</span>
                    <v-chip v-if="summary.rank?.current" size="x-small" :color="summary.rank.current.color" variant="tonal">
                        {{ summary.rank.current.name }}
                    </v-chip>
                </div>
                <span class="text-caption text-medium-emphasis">
                    {{ $t('achievements.earnedOf', { earned: summary.earned, total: summary.total }) }}
                </span>
            </div>

            <!-- What was earned lately -->
            <div v-if="summary.recent.length" class="d-flex ga-2 flex-wrap mb-3">
                <v-tooltip v-for="item in summary.recent" :key="item.id" location="top">
                    <template #activator="{ props }">
                        <achievement-badge v-bind="props" :achievement="item" :size="34" />
                    </template>
                    {{ item.name }}
                </v-tooltip>
            </div>

            <!-- And what is nearly there, which is the useful part -->
            <div v-for="item in summary.closest" :key="`next-${item.id}`" class="mb-2">
                <div class="d-flex align-center justify-space-between text-caption">
                    <span class="text-truncate">{{ item.name }}</span>
                    <span class="text-medium-emphasis">{{ item.progress }} / {{ item.threshold }}</span>
                </div>
                <v-progress-linear
                    :model-value="item.progress / Math.max(item.threshold, 1) * 100"
                    :color="item.color"
                    height="5"
                    rounded
                />
            </div>

            <div v-if="!summary.recent.length && !summary.closest.length" class="text-caption text-medium-emphasis">
                {{ $t('achievements.widget.nothingYet') }}
            </div>
        </div>
    </widget-state>
</template>

<script>
import axios from 'axios';
import WidgetState from './WidgetState.vue';
import AchievementBadge from '@/components/achievements/AchievementBadge.vue';
import widgetMixin from './widgetMixin.js';

export default {
    name: 'AchievementsWidget',
    components: { WidgetState, AchievementBadge },
    mixins: [widgetMixin],
    data() {
        return { summary: null };
    },
    methods: {
        async fetch() {
            const { data } = await axios.get('/api/achievements/summary');
            this.summary = data.data;

            this.$emit('update-meta', {
                subtitle: this.$t('achievements.widget.subtitle', { count: this.summary.earned }),
            });
        },
    },
};
</script>
