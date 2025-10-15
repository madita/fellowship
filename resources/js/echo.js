// echo.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// LocalStorage keys for persistence
const ECHO_CONNECTION_ID = 'user_echo_connection_id';
const ECHO_CONNECTION_STATE = 'user_echo_connection_state';
const ECHO_SUBSCRIBED_CHANNELS = 'user_echo_subscribed_channels';
const ECHO_CHANNEL_HANDLERS = 'user_echo_channel_handlers';
const ECHO_LAST_ACTIVE = 'user_echo_last_active';

// Singleton instance
let echoInstance = null;

const echo = () => {
    const token = localStorage.getItem('token');
    const lastActive = localStorage.getItem(ECHO_LAST_ACTIVE);
    const timeElapsed = lastActive ? Date.now() - parseInt(lastActive) : null;

    // Update last active time
    localStorage.setItem(ECHO_LAST_ACTIVE, Date.now().toString());

    // Reuse existing connection if active
    if (echoInstance &&
        echoInstance.connector &&
        echoInstance.connector.pusher &&
        echoInstance.connector.pusher.connection.state === 'connected') {
        console.log('Reusing existing Echo instance with active connection');
        return echoInstance;
    }

    // Create new Echo instance
    echoInstance = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_REVERB_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT,
        forceTLS: import.meta.env.MODE === 'production' ? import.meta.env.VITE_REVERB_SCHEME : false,
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        cluster: 'mt1', //we are using a dummy cluster
        activityTimeout: 30000,
        pongTimeout: 15000,
        enableLogging: true,

        // Required for private channels
        authEndpoint: '/broadcasting/auth',

        auth: {
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')) || undefined,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            withCredentials: true
        }
    });

    // Connection event handlers
    echoInstance.connector.pusher.connection.bind('connected', () => {
        const socketId = echoInstance.connector.pusher.connection.socket_id;
        localStorage.setItem(ECHO_CONNECTION_ID, socketId);
        localStorage.setItem(ECHO_CONNECTION_STATE, 'connected');
        localStorage.setItem(ECHO_LAST_ACTIVE, Date.now().toString());

        // Automatic resubscription to saved channels
        resubscribeToSavedChannels();
    });

    // Add more connection event handlers...
    echoInstance.connector.pusher.connection.bind('disconnected', () => {
        localStorage.setItem(ECHO_CONNECTION_STATE, 'disconnected');
    });

    echoInstance.connector.pusher.connection.bind('error', (err) => {
        console.error('Pusher connection error:', err);
    });

    // Track subscriptions for persistence
    echoInstance.saveSubscription = (channelName, eventName, handlerInfo) => {
        try {
            // Get current subscriptions
            const subscribedChannels = JSON.parse(localStorage.getItem(ECHO_SUBSCRIBED_CHANNELS) || '[]');
            if (!subscribedChannels.includes(channelName)) {
                subscribedChannels.push(channelName);
                localStorage.setItem(ECHO_SUBSCRIBED_CHANNELS, JSON.stringify(subscribedChannels));
            }

            // Save event handlers
            const handlers = JSON.parse(localStorage.getItem(ECHO_CHANNEL_HANDLERS) || '{}');
            if (!handlers[channelName]) {
                handlers[channelName] = [];
            }

            // Check for duplicate handlers
            const existingHandler = handlers[channelName].find(h => h.event === eventName);
            if (!existingHandler) {
                handlers[channelName].push({
                    event: eventName,
                    type: handlerInfo.type
                });
                localStorage.setItem(ECHO_CHANNEL_HANDLERS, JSON.stringify(handlers));
            }
        } catch (error) {
            console.error('Error saving subscription:', error);
        }
    };

    // Handle visibility changes to reconnect when tab becomes active
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            const pusher = echoInstance?.connector?.pusher;
            if (pusher && pusher.connection.state !== 'connected') {
                console.log('Page visible, reconnecting...');
                pusher.connect();
            }
        }
    });

    return echoInstance;
};

// Function to resubscribe to saved channels
function resubscribeToSavedChannels() {
    try {
        const subscribedChannels = JSON.parse(localStorage.getItem(ECHO_SUBSCRIBED_CHANNELS) || '[]');
        const handlers = JSON.parse(localStorage.getItem(ECHO_CHANNEL_HANDLERS) || '{}');

        if (subscribedChannels.length === 0) return;

        // Trigger resubscription via global handler
        if (window.userResubscribeToChannels) {
            window.userResubscribeToChannels(subscribedChannels, handlers);
        }
    } catch (error) {
        console.error('Error during resubscription:', error);
    }
}

// Clear Echo state on logout
echo.clearEchoState = () => {
    localStorage.removeItem(ECHO_SUBSCRIBED_CHANNELS);
    localStorage.removeItem(ECHO_CHANNEL_HANDLERS);
    localStorage.removeItem(ECHO_CONNECTION_ID);
    localStorage.removeItem(ECHO_CONNECTION_STATE);
    localStorage.removeItem(ECHO_LAST_ACTIVE);

    // Disconnect instance if exists
    if (echoInstance && echoInstance.connector) {
        try {
            echoInstance.connector.pusher.disconnect();
        } catch (e) {
            console.error('Error disconnecting Echo:', e);
        }
        echoInstance = null;
    }
};

// For debugging
window.userEchoDebug = {
    getInstance: () => echoInstance,
    getConnectionState: () => echoInstance?.connector?.pusher?.connection?.state || 'no-instance',
    clearState: echo.clearEchoState,
    getStoredState: () => ({
        connectionId: localStorage.getItem(ECHO_CONNECTION_ID),
        connectionState: localStorage.getItem(ECHO_CONNECTION_STATE),
        channels: JSON.parse(localStorage.getItem(ECHO_SUBSCRIBED_CHANNELS) || '[]'),
        handlers: JSON.parse(localStorage.getItem(ECHO_CHANNEL_HANDLERS) || '{}'),
        lastActive: localStorage.getItem(ECHO_LAST_ACTIVE)
    })
};

export default echo;
