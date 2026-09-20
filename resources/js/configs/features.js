/**
 * Feature registry — the single source of truth for togglable features.
 *
 * Each feature maps to a boolean setting `feature_<key>_enabled` (managed
 * under Admin → Settings → Features). A disabled feature is hidden from
 * user-facing menus (items tagged with `feature: '<key>'` in navigation.js
 * and toolbar.js) and its user-facing routes are blocked by the global
 * router guard. Admin management pages stay visible and reachable so
 * admins can keep managing the data while the feature is off.
 *
 * Features default to ENABLED when the setting is missing, so existing
 * installs keep their menus until an admin explicitly turns something off.
 */
import { useIrcStore } from '@/store/ircStore.js';

export const FEATURES = [
    {
        key: 'timeline',
        label: 'Timeline',
        description: 'User status timeline with posts and images',
        icon: 'mdi-timeline-text-outline',
        routePrefixes: ['/timeline'],
    },
    {
        key: 'chat',
        label: 'Chat',
        description: 'Real-time chat and conversations',
        icon: 'mdi-message-text-outline',
        routePrefixes: ['/chat'],
    },
    {
        key: 'events',
        label: 'Events',
        description: 'Calendar, events and RSVPs',
        icon: 'mdi-calendar',
        routePrefixes: ['/events'],
    },
    {
        key: 'wiki',
        label: 'Wiki',
        description: 'Collaborative wiki articles',
        icon: 'mdi-file-outline',
        routePrefixes: ['/wiki'],
    },
    {
        key: 'forum',
        label: 'Forum',
        description: 'Discussion forum with threads and posts',
        icon: 'mdi-forum',
        routePrefixes: ['/forum'],
    },
    {
        key: 'irc',
        label: 'IRC',
        description: 'IRC client with classic and comic chat views',
        icon: 'mdi-chat-processing-outline',
        routePrefixes: ['/irc'],
    },
    {
        key: 'gallery',
        label: 'Gallery',
        description: 'Image galleries and albums',
        icon: 'mdi-image-multiple-outline',
        routePrefixes: ['/gallery'],
    },
    {
        key: 'tickets',
        label: 'Tickets',
        description: 'Support tickets and requests',
        icon: 'mdi-ticket-outline',
        routePrefixes: ['/account/tickets'],
    },
    {
        key: 'feedback',
        label: 'Feedback',
        description: 'Public bug reports and feature requests with voting',
        icon: 'mdi-bug-outline',
        routePrefixes: ['/feedback'],
    },
];

export const featureSettingKey = (key) => `feature_${key}_enabled`;

/**
 * A feature can be switched on and still be unusable right now.
 *
 * IRC is the case: the daemon holds the sockets to the IRC servers and is
 * the only thing that consumes the command queue, so with it down nothing
 * can connect. The client hides the chat instead of offering one that
 * cannot work — the same rule the menu and the page both read.
 *
 * Gates are synchronous so menus can use them while rendering; whatever
 * they read has to be kept up to date elsewhere.
 */
export const RUNTIME_GATES = {
    irc: () => useIrcStore().chatAvailable,
};

export const isFeatureAvailable = (key) => {
    const gate = RUNTIME_GATES[key];

    return gate ? gate() : true;
};

export default FEATURES;
