<template>
    <widget-state :loading="loading" :error="error" :empty="!summary" :empty-icon="'mdi-trophy-outline'">
        <div v-if="summary">
            <div class="d-flex align-center justify-space-between mb-2">
                <div>
                    <span class="text-h6 font-weight-bold">{{ summary.points }}</span>
                    <span class="text-caption text-medium-emphasis ml-1">{{ $t('achievements.points') }}</span>
                </div>
                <span class="text-caption text-medium-emphasis">
                    {{ $t('achievements.earnedOf', { earned: summary.earned, total: summary.total }) }}
                </span>
            </div>

            <!-- What was earned lately -->
            <div v-if="summary.recent.length" class="d-flex ga-2 flex-wrap mb-3">
                <v-tooltip v-for="item in summary.recent" :key="item.id" location="top">
                    <template #activator="{ props }">
                        <v-avatar v-bind="props" :color="item.color" size="34">
                            <v-icon :icon="item.icon" size="small" color="white" />
                        </v-avatar>
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
import widgetMixin from './widgetMixin.js';

export default {
    name: 'AchievementsWidget',
    components: { WidgetState },
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
