// import menuAdmin from './menus/admin.menu.js'

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
                { feature: 'gallery', icon: 'mdi-image-multiple-outline', key: 'menu.gallery', text: 'Gallery', link: '/gallery' }
            ]
        },
        {
            text: 'Administration',
            key: 'menu.admin',
            role: 'admin',
            items: [
                {icon: 'mdi-view-dashboard-outline', key: 'menu.adminDashboard', text: 'Dashboard', link: '/admin/dashboard'},
                {icon: 'mdi-account-group-outline', key: 'menu.adminUsers', text: 'Users', link: '/admin/users'},
                {icon: 'mdi-file-document-outline', key: 'menu.adminPages', text: 'Pages', link: '/admin/pages'},
                {icon: 'mdi-post-outline', key: 'menu.adminPosts', text: 'Posts', link: '/admin/posts'},
                {
                    icon: 'mdi-calendar-text',
                    key: 'menu.adminEvents',
                    text: 'Events',
                    link: '/admin/events',
                    items: [
                        {icon: 'mdi-calendar-star', key: 'menu.adminEventsList', text: 'List', link: '/admin/events'},
                        {icon: 'mdi-calendar-alert-outline', key: 'menu.adminEventsTypes', text: 'Types', link: '/admin/events/types'},
                        {icon: 'mdi-calendar-account-outline', key: 'menu.adminEventsProfiles', text: 'Profiles', link: '/admin/events/profiles'}
                    ]
                },
                {icon: 'mdi-folder-multiple-image', key: 'menu.adminMedia', text: 'Media Center', link: '/admin/media'},
                {icon: 'mdi-ticket-outline', key: 'menu.adminTickets', text: 'Tickets', link: '/admin/tickets'},
                {icon: 'mdi-bullhorn-outline', key: 'menu.adminAnnouncement', text: 'Announcement', link: '/admin/announcements'},
                // Roles, permissions, taxonomies, forum categories, translations and the migration tool live in the settings overview.
                {icon: 'mdi-cog-outline', key: 'menu.adminSettings', text: 'Settings', link: '/admin/settings'},
            ]
        }
    ],

    // footer links
    footer: []
}
