/**
 * Feature registry — the single source of truth for togglable features.
 *
 * Each feature maps to a boolean setting `feature_<key>_enabled` (managed
 * under Admin → Settings → Features). A disabled feature is hidden from the
 * sidebar menu (items tagged with `feature: '<key>'` in navigation.js) and
 * its routes are blocked by the global router guard.
 *
 * Features default to ENABLED when the setting is missing, so existing
 * installs keep their menus until an admin explicitly turns something off.
 */
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
        routePrefixes: ['/account/tickets', '/admin/tickets'],
    },
];

export const featureSettingKey = (key) => `feature_${key}_enabled`;

export default FEATURES;
