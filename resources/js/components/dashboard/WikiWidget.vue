<template>
    <div>
        <div v-for="change in widgetData.recentChanges?.slice(0, 4)" :key="change.id" class="mb-3">
            <div class="d-flex align-center mb-1">
                <v-chip
                    :color="change.type === 'edit' ? 'warning' : 'success'"
                    size="x-small"
                    class="mr-2"
                >
                    {{ change.type }}
                </v-chip>
                <div class="text-caption text-medium-emphasis">{{ change.date }}</div>
            </div>
            <div class="text-body-2 font-weight-medium">{{ change.page }}</div>
            <div class="text-caption text-medium-emphasis">by {{ change.author }}</div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'WikiWidget',
    props: {
        widgetData: { type: Object, default: () => ({}) },
        widgetConfig: { type: Object, default: () => ({}) },
    },
};
</script>
