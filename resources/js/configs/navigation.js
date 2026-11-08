
import { feature } from './featurePermissions.js'

export default {
    // main navigation - side menu
    menu: [
        {
            text: 'Main',
            key: 'menu.main',
            items: [
                { icon: 'mdi-view-dashboard-outline', key: 'menu.dashboard', text: 'Dashboard', link: '/dashboard' },
                { feature: 'timeline', icon: 'mdi-timeline-text-outline', key: 'menu.timeline', text: 'Timeline', link: '/timeline' },
                { feature: 'chat', icon: 'mdi-message-text-outline', key: 'menu.chat', text: 'Chat', link: '/chat' },
                { feature: 'events', icon: 'mdi-calendar', key: 'menu.events', text: 'Events', link: '/events' },
                { feature: 'wiki', icon: 'mdi-file-outline', key: 'menu.wiki', text: 'Wiki', link: '/wiki' },
                { feature: 'forum', icon: 'mdi-forum', key: 'menu.forum', text: 'Forum', link: '/forum' },
                { feature: 'irc', icon: 'mdi-chat-processing-outline', key: 'menu.irc', text: 'IRC', link: '/irc' },
                { feature: 'gallery', icon: 'mdi-image-multiple-outline', key: 'menu.gallery', text: 'Gallery', link: '/gallery' },
                { icon: 'mdi-trophy-outline', key: 'menu.achievements', text: 'Achievements', link: '/achievements' },
                { feature: 'podcast', icon: 'mdi-podcast', key: 'menu.podcast', text: 'Podcast', link: '/podcast' },
                { feature: 'feedback', icon: 'mdi-message-alert-outline', key: 'menu.feedback', text: 'Feedback', link: '/feedback' }
            ]
        },
        {
            text: 'Administration',
            key: 'menu.admin',
            role: 'admin',
            items: [
                {icon: 'mdi-view-dashboard-outline', key: 'menu.adminDashboard', text: 'Dashboard', link: '/admin/dashboard', permission: 'manage-user'},
                {icon: 'mdi-account-group-outline', key: 'menu.adminUsers', text: 'Users', link: '/admin/users', permission: 'manage-user'},
                // A feature split by action is offered for any part of it:
                // somebody given only create-post should find Posts in
                // the menu. feature() is the whole set of names, and
                // permission.can() takes a list.
                {icon: 'mdi-file-document-outline', key: 'menu.adminPages', text: 'Pages', link: '/admin/pages', permission: feature('page')},
                {icon: 'mdi-post-outline', key: 'menu.adminPosts', text: 'Posts', link: '/admin/posts', permission: feature('post')},
                {
                    icon: 'mdi-calendar-text',
                    key: 'menu.adminEvents',
                    text: 'Events',
                    link: '/admin/events',
                    permission: feature('event'),
                    items: [
                        {icon: 'mdi-calendar-star', key: 'menu.adminEventsList', text: 'List', link: '/admin/events', permission: feature('event')},
                        // The shape the calendar is built out of, rather
                        // than entries in it
                        {icon: 'mdi-calendar-alert-outline', key: 'menu.adminEventsTypes', text: 'Types', link: '/admin/events/types', permission: 'manage-event'},
                        {icon: 'mdi-calendar-account-outline', key: 'menu.adminEventsProfiles', text: 'Profiles', link: '/admin/events/profiles', permission: 'manage-event'}
                    ]
                },
                {icon: 'mdi-podcast', key: 'menu.adminPodcast', text: 'Podcast', link: '/admin/podcast', permission: feature('podcast')},
                {icon: 'mdi-folder-multiple-image', key: 'menu.adminMedia', text: 'Media Center', link: '/admin/media', permission: 'manage-media'},
                {icon: 'mdi-ticket-outline', key: 'menu.adminTickets', text: 'Tickets', link: '/admin/tickets', permission: 'manage-ticket'},
                {icon: 'mdi-poll', key: 'menu.adminPolls', text: 'Polls', link: '/admin/polls', permission: 'manage-poll'},
                {icon: 'mdi-link-variant', key: 'menu.adminRelations', text: 'Related Content', link: '/admin/related-content', permission: 'manage-relation'},
                {icon: 'mdi-bullhorn-outline', key: 'menu.adminAnnouncement', text: 'Announcement', link: '/admin/announcements', permission: 'manage-announcement'},
                // Writing to the members by mail. The first entry that
                // was gated by its own permission rather than by the
                // section's role; now they all are.
                {icon: 'mdi-email-multiple-outline', key: 'menu.adminMailings', text: 'Mailings', link: '/admin/mailings', permission: 'manage-mailings'},
                // Roles, permissions, taxonomies, forum categories, translations and the migration tool live in the settings overview.
                {icon: 'mdi-cog-outline', key: 'menu.adminSettings', text: 'Settings', link: '/admin/settings', permission: 'manage-setting'},
            ]
        }
    ],

    // footer links
    footer: []
}
