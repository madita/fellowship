// notificationStore.js
import { defineStore } from 'pinia';

export const useNotificationStore = defineStore('notification', {
    state: () => ({
        notifications: [],
    }),

    getters: {
        // Get unread count
        unreadCount: (state) => state.notifications.filter(n => n.status === 'unread').length,

        // Get notifications by status
        getNotificationsByStatus: (state) => (status) => {
            return state.notifications.filter(notification => notification.status === status);
        },
    },

    actions: {
        // Add a new notification
        addNotification(notification) {
            this.notifications.unshift(notification);
        },

        // Mark notification as read
        markAsRead(notificationId) {
            const notification = this.notifications.find(n => n.id === notificationId);
            if (notification) {
                notification.status = 'read';
            }
        },

        // Mark all notifications as read
        markAllAsRead() {
            this.notifications.forEach(notification => {
                notification.status = 'read';
            });
        },

        // Delete a notification
        deleteNotification(notificationId) {
            const index = this.notifications.findIndex(n => n.id === notificationId);
            if (index !== -1) {
                this.notifications.splice(index, 1);
            }
        },

        // Delete all notifications
        deleteAllNotifications() {
            this.notifications = [];
        },
    },
});
