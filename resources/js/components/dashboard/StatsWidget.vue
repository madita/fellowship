<template>
    <div class="text-center">
        <div class="text-h2 font-weight-bold mb-2" :style="{ color: widgetConfig.color }">
            {{ widgetData.value }}
        </div>
        <div class="text-body-2 text-medium-emphasis mb-3">{{ widgetData.label }}</div>
        <div class="d-flex align-center justify-center">
            <v-icon
                :color="widgetData.trend > 0 ? 'success' : widgetData.trend < 0 ? 'error' : 'secondary'"
                size="16"
                class="mr-1"
            >
                {{ widgetData.trend > 0 ? 'mdi-trending-up' : widgetData.trend < 0 ? 'mdi-trending-down' : 'mdi-trending-neutral' }}
            </v-icon>
            <span class="text-caption" :class="widgetData.trend > 0 ? 'text-success' : widgetData.trend < 0 ? 'text-error' : 'text-medium-emphasis'">
                {{ Math.abs(widgetData.trend) }}% this week
            </span>
        </div>
    </div>
</template>

<script>
export default {
    name: 'StatsWidget',
    props: {
        widgetData: { type: Object, default: () => ({}) },
        widgetConfig: { type: Object, default: () => ({}) },
    },
};
</script>
