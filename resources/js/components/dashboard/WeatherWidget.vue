<template>
    <div class="text-center">
        <v-icon size="48" color="primary" class="mb-2">{{ widgetData.icon }}</v-icon>
        <div class="text-h4 font-weight-bold mb-1">{{ widgetData.temperature }}°</div>
        <div class="text-body-2 mb-2">{{ widgetData.condition }}</div>
        <div class="text-caption text-medium-emphasis">{{ widgetData.location }}</div>
    </div>
</template>

<script>
export default {
    name: 'WeatherWidget',
    props: {
        widgetData: { type: Object, default: () => ({}) },
        widgetConfig: { type: Object, default: () => ({}) },
    },
};
</script>
