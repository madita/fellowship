<template>
    <div>
        <div v-for="notification in widgetData.recent?.slice(0, 4)" :key="notification.id" class="d-flex align-center mb-3">
            <v-avatar :color="notification.type === 'info' ? 'blue' : notification.type === 'warning' ? 'orange' : 'green'" size="24" class="mr-3">
                <v-icon color="white" size="12">{{ getNotificationIcon(notification.type) }}</v-icon>
            </v-avatar>
            <div class="flex-grow-1">
                <div class="text-body-2">{{ notification.message }}</div>
                <div class="text-caption text-medium-emphasis">{{ notification.time }}</div>
            </div>
            <v-btn v-if="!notification.read" icon="mdi-circle" size="x-small" color="primary"></v-btn>
        </div>
    </div>
</template>

<script>
export default {
    name: 'NotificationsWidget',
    props: {
        widgetData: { type: Object, default: () => ({}) },
        widgetConfig: { type: Object, default: () => ({}) },
    },
    methods: {
        getNotificationIcon(type) {
            const icons = {
                info: 'mdi-information',
                warning: 'mdi-alert',
                success: 'mdi-check-circle',
            };
            return icons[type] || 'mdi-bell';
        },
    },
};
</script>
