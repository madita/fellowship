<template>
    <div>
        <div v-if="widgetData.nextEvent" class="mb-4">
            <v-card color="primary" variant="tonal">
                <v-card-text class="pa-3">
                    <div class="d-flex align-center mb-2">
                        <v-chip color="primary" size="small" class="mr-2">NEXT</v-chip>
                        <div class="text-caption">{{ widgetData.nextEvent.timeUntil }}</div>
                    </div>
                    <div class="text-subtitle-2 font-weight-bold mb-1">{{ widgetData.nextEvent.title }}</div>
                    <div class="text-caption">{{ widgetData.nextEvent.date }}</div>
                </v-card-text>
            </v-card>
        </div>
        <v-list density="compact" class="pa-0">
            <v-list-item
                v-for="event in widgetData.upcomingEvents?.slice(0, 3)"
                :key="event.id"
                class="px-0 mb-1"
            >
                <template v-slot:prepend>
                    <v-avatar color="surface-variant" size="24">
                        <v-icon size="12">mdi-calendar</v-icon>
                    </v-avatar>
                </template>
                <v-list-item-title class="text-body-2">{{ event.title }}</v-list-item-title>
                <v-list-item-subtitle class="text-caption">{{ event.date }}</v-list-item-subtitle>
            </v-list-item>
        </v-list>
    </div>
</template>

<script>
export default {
    name: 'EventsWidget',
    props: {
        widgetData: { type: Object, default: () => ({}) },
        widgetConfig: { type: Object, default: () => ({}) },
    },
};
</script>
