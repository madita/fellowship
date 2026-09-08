/**
 * Dashboard widget registry. Every widget shows live data of one feature;
 * `feature` ties it to the feature toggles so widgets of disabled features
 * are hidden. Titles, descriptions and action labels live in the
 * `dashboard.widgets.<type>` translation keys.
 */
export const WIDGET_TYPES = {
    events: {
        component: 'EventsWidget',
        feature: 'events',
        icon: 'mdi-calendar-clock',
        color: 'primary',
        size: 'medium',
        action: { icon: 'mdi-calendar-multiple', to: '/events' },
    },
    notifications: {
        component: 'NotificationsWidget',
        feature: null,
        icon: 'mdi-bell',
        color: 'info',
        size: 'medium',
        action: { icon: 'mdi-bell-outline', to: '/account/notifications' },
    },
    wiki: {
        component: 'WikiWidget',
        feature: 'wiki',
        icon: 'mdi-book-edit',
        color: 'warning',
        size: 'small',
        action: { icon: 'mdi-book-open-variant', to: '/wiki' },
    },
    conversations: {
        component: 'ConversationsWidget',
        feature: 'chat',
        icon: 'mdi-message-text',
        color: 'teal',
        size: 'medium',
        action: { icon: 'mdi-message-text-outline', to: '/conversations' },
    },
    tickets: {
        component: 'TicketsWidget',
        feature: 'tickets',
        icon: 'mdi-ticket-confirmation',
        color: 'purple',
        size: 'medium',
        action: { icon: 'mdi-ticket-outline', to: '/account/tickets' },
        // Per-widget options shown in the settings dialog; labels come from
        // dashboard.widgets.tickets.settings.<key>.{label,items.<value>}.
        settings: [
            {
                key: 'scope',
                default: 'mine',
                items: [
                    { value: 'mine' },
                    { value: 'assigned' },
                    { value: 'unassigned', adminOnly: true },
                    { value: 'all', adminOnly: true },
                ],
            },
            {
                key: 'sort',
                default: 'updated_at',
                items: [{ value: 'updated_at' }, { value: 'due_date' }, { value: 'created_at' }, { value: 'priority' }],
            },
        ],
    },
    ticketOverview: {
        component: 'TicketOverviewWidget',
        feature: 'tickets',
        icon: 'mdi-ticket-account',
        color: 'deep-purple',
        size: 'medium',
        action: { icon: 'mdi-ticket-outline', to: '/account/tickets' },
    },
    forum: {
        component: 'ForumWidget',
        feature: 'forum',
        icon: 'mdi-forum',
        color: 'pink',
        size: 'medium',
        action: { icon: 'mdi-forum-outline', to: '/forum' },
    },
    sandbox: {
        component: 'SandboxWidget',
        feature: null,
        // The sandbox has its own on/off setting instead of a feature toggle.
        enabled: settings => settings.sandboxEnabled,
        icon: 'mdi-file-document-edit',
        color: 'indigo',
        size: 'medium',
        action: { icon: 'mdi-file-document-multiple-outline', to: '/sandbox' },
    },
    gallery: {
        component: 'GalleryWidget',
        feature: 'gallery',
        icon: 'mdi-image-multiple',
        color: 'deep-orange',
        size: 'medium',
        action: { icon: 'mdi-image-multiple-outline', to: '/gallery' },
    },
    stats: {
        component: 'StatsWidget',
        feature: null,
        icon: 'mdi-chart-box',
        color: 'success',
        size: 'small',
        action: null,
    },
};

/** Whether a widget type's feature is switched on. */
export const isWidgetEnabled = (definition, settings) =>
    (!definition.feature || settings.isFeatureEnabled(definition.feature))
    && (!definition.enabled || definition.enabled(settings));

/** Widgets a fresh dashboard starts with. */
export const DEFAULT_LAYOUT = ['events', 'notifications', 'wiki', 'conversations'];

/** Widget types that had no real data source and were removed. */
export const RETIRED_TYPES = ['tasks', 'weather', 'analytics', 'social', 'calendar'];

export const LAYOUT_STORAGE_KEY = 'dashboardLayout';
