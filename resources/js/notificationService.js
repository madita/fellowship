// notificationService.js
import { ref } from "vue";
import createEcho from "./echo";
import { useNotificationStore } from "@/store/notificationStore";
import { formatDate } from "@/plugins/formatDate.js";

// Initialization key
const USER_NOTIFICATION_INIT_KEY = 'user_notification_service_initialized';

export const useNotificationService = async (providedUserStore = null) => {
    // Create a fresh Echo instance with the current token
    const echo = createEcho();

    // Get user store (passed or dynamically imported)
    const userStore = providedUserStore || (await getUserStore());

    // Initialize notification store
    const notificationStore = useNotificationStore();

    // Notification type constants
    const notificationTypes = {
        TRIP_CREATED: "trip_created",
        WALLET_FUNDED: "wallet_funded",
        // Add more notification types...
    };

    // Format notification based on type and data
    const formatNotification = (type, data) => {
        const currentTime = new Date();
        const formattedTime = formatDate(currentTime, 'H:i');
        const formattedDate = formatDate(currentTime, 'F j, Y');

        let message = "";
        let title = "";

        // Format notification based on type
        switch (type) {
            case notificationTypes.TRIP_CREATED:
                title = "Trip Created";
                message = data.message;
                break;
            case notificationTypes.WALLET_FUNDED:
                title = "Wallet Funded";
                message = data.message;
                break;
            // Add more notification types...
            default:
                title = "Notification";
                message = "You have a new notification";
        }

        return {
            id: Date.now(),
            type,
            title,
            message,
            time: formattedTime,
            date: formattedDate,
            fullTime: `${formattedDate} . ${formattedTime}`,
            data,
            status: "unread",
        };
    };

    // Add notification to store and show browser notification
    const addNotification = (notification) => {
        // Add to global notification store
        notificationStore.addNotification(notification);

        // Show browser notification if allowed
        if (Notification.permission === "granted") {
            try {
                new Notification(notification.title, {
                    body: notification.message,
                    icon: "/favicon.png",
                });
            } catch (error) {
                console.warn("Error displaying browser notification:", error);
            }
        }
    };

    // Helper to get user store dynamically (avoids circular dependency)
    const getUserStore = async () => {
        const { useUserStore } = await import("@/store/userStore");
        return useUserStore();
    };

    // Subscribe to a private channel with error handling
    const subscribeToPrivateChannel = (channelName, eventName, handler, handlerType) => {
        try {
            // Check if channel already exists
            const existingChannels = echo.connector.pusher.channels.channels;
            if (existingChannels[channelName]) {
                existingChannels[channelName].bind(eventName, handler);
                return existingChannels[channelName];
            }

            // Create new subscription
            const channel = echo.private(channelName);

            // Debug subscription status
            channel.subscribed(() => {
                console.log(`Successfully subscribed to private channel: ${channelName}`);

                // Track this subscription with its handler info
                echo.saveSubscription(channelName, eventName, { type: handlerType });
            });

            // Error handler
            channel.error((err) => {
                console.error(`Channel subscription error for ${channelName}:`, err);
            });

            // Bind the event handler
            channel.listen(eventName, (data) => {
                console.log(`Received event ${eventName} on channel ${channelName}:`, data);
                handler(data);
            });

            return channel;
        } catch (error) {
            console.error(`Exception when subscribing to private channel ${channelName}:`, error);
            return null;
        }
    };

    // Global resubscription handler for saved channels
    window.userResubscribeToChannels = (channels, handlers) => {
        if (!userStore.user?.id) {
            console.error("User ID not available for resubscription");
            return;
        }

        const userId = userStore.user.id;

        channels.forEach(channelName => {
            const channelHandlers = handlers[channelName] || [];

            // Resubscribe based on saved handlers
            channelHandlers.forEach(handlerInfo => {
                // Create appropriate event handlers based on channel and event
                if (channelName === `trip.created.${userId}` && handlerInfo.event === 'TripCreated') {
                    subscribeToPrivateChannel(
                        channelName,
                        handlerInfo.event,
                        (data) => {
                            const notification = formatNotification(notificationTypes.TRIP_CREATED, data);
                            addNotification(notification);
                        },
                        'TRIP_CREATED'
                    );
                }
                // Add more channel/event combinations...
            });

            // If no handlers found, create based on channel name pattern
            if (!channelHandlers.length) {
                if (channelName === `trip.created.${userId}`) {
                    subscribeToPrivateChannel(
                        channelName,
                        "TripCreated",
                        (data) => {
                            const notification = formatNotification(notificationTypes.TRIP_CREATED, data);
                            addNotification(notification);
                        },
                        'TRIP_CREATED'
                    );
                }
                // Add more channel patterns...
            }
        });
    };

    // Initialize Echo and subscribe to channels
    const initializeEcho = async () => {
        if (!userStore.user?.id) {
            console.error("User ID not available for notification subscription");
            return;
        }

        const userId = userStore.user.id;
        console.log(`Initializing notifications for user ID: ${userId}`);

        // Mark service as initialized
        localStorage.setItem(USER_NOTIFICATION_INIT_KEY, 'true');

        // Subscribe to user-specific channels
        subscribeToPrivateChannel(
            `trip.created.${userId}`,
            "TripCreated",
            (data) => {
                const notification = formatNotification(notificationTypes.TRIP_CREATED, data);
                addNotification(notification);
            },
            'TRIP_CREATED'
        );

        // Add more channel subscriptions...

        // Subscribe to trip-specific channels if needed
        subscribeToTripChannels();
    };

    // Subscribe to trip-specific channels
    const subscribeToTripChannels = async () => {
        // Get user's active trips
        const userTrips = userStore.activeTrips || [];

        if (userTrips.length === 0) {
            console.log("No active trips found for subscription");
            return;
        }

        // Subscribe to channels for each trip
        userTrips.forEach((trip) => {
            const tripId = trip;

            // Add trip-specific subscriptions...
            subscribeToPrivateChannel(
                `trip.departure.${tripId}`,
                "TripDeparture",
                (data) => {
                    const notification = formatNotification(
                        notificationTypes.TRIP_DEPARTURE,
                        {
                            ...data,
                            departure: trip.departure,
                            destination: trip.destination,
                        }
                    );
                    addNotification(notification);
                },
                'TRIP_DEPARTURE'
            );

            // Add more trip-specific channels...
        });
    };

    // Request browser notification permission
    const requestNotificationPermission = async () => {
        if (!("Notification" in window)) {
            console.log("This browser does not support desktop notifications");
            return false;
        }

        if (Notification.permission === "granted") {
            return true;
        }

        if (Notification.permission !== "denied") {
            const permission = await Notification.requestPermission();
            return permission === "granted";
        }

        return false;
    };

    // Cleanup function for logout
    const cleanup = () => {
        try {
            console.log("Cleaning up user notification subscriptions...");

            // Use the clearEchoState method to remove localStorage entries
            createEcho.clearEchoState();

            // Remove global resubscribe handler
            delete window.userResubscribeToChannels;

            // Remove the notification initialization flag
            localStorage.removeItem(USER_NOTIFICATION_INIT_KEY);

            // Clear the notification store
            notificationStore.deleteAllNotifications();

            console.log('User notification service cleaned up');
        } catch (error) {
            console.error("Error cleaning up user notifications:", error);
        }
    };

    // Return service methods and state
    return {
        // Get notifications from store
        get notifications() {
            return {
                value: notificationStore.notifications
            };
        },
        get unreadCount() {
            return {
                value: notificationStore.unreadCount
            };
        },
        initializeEcho,
        markAllAsRead: notificationStore.markAllAsRead,
        markAsRead: notificationStore.markAsRead,
        subscribeToTripChannels,
        requestNotificationPermission,
        cleanup,
    };
};

export default useNotificationService;
