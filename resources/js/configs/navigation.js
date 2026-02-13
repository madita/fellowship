// import menuAdmin from './menus/admin.menu.js'

export default {
    // main navigation - side menu
    menu: [
        {
            text: 'Main',
            key: 'menu.main',
            items: [
                { icon: 'mdi-view-dashboard-outline', key: 'menu.dashboard', text: 'Dashboard', link: '/dashboard' },
                { icon: 'mdi-message-text-outline', key: 'menu.chat', text: 'Chat', link: '/chat' },
                { icon: 'mdi-calendar', key: 'menu.events', text: 'Events', link: '/events' },
                { icon: 'mdi-file-outline', key: 'menu.wiki', text: 'Wiki', link: '/wiki' },
                { icon: 'mdi-file-outline', key: 'menu.blank', text: 'Blank Page', link: '/blank' }
            ]
        },
        {
            text: 'Administration',
            key: 'menu.admin',
            role: 'admin',
            items: [
                {permission: 'manage-role', icon: 'mdi-shield-account-outline', key: 'menu.adminRoles', text: 'Roles', link: '/admin/roles'},
                {permission: 'manage-role', icon: 'mdi-key-outline', key: 'menu.adminPermission', text: 'Permissions', link: '/admin/permissions'},
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
                {
                    icon: 'mdi-tag-multiple-outline',
                    key: 'menu.adminTags',
                    text: 'Taxonomie',
                    link: '/admin/tags',
                    regex: /^\/admin\/tags/,
                    items: [
                        {icon: 'mdi-tag', key: 'menu.adminTaxonomie', text: 'Taxonomie', link: '/admin/tags/taxonomie'},
                        {icon: 'mdi-tag-outline', key: 'menu.adminTerms', text: 'Terms', link: '/admin/tags/terms'}
                    ]
                },
                {icon: 'mdi-folder-multiple-image', key: 'menu.adminMedia', text: 'Media Center', link: '/admin/media'},
                {icon: 'mdi-ticket-outline', key: 'menu.adminTickets', text: 'Tickets', link: '/admin/tickets'},
                {icon: 'mdi-translate', key: 'menu.adminTranslations', text: 'Translations', link: '/admin/translations'},
                {icon: 'mdi-cog-outline', key: 'menu.adminSettings', text: 'Settings', link: '/admin/settings'},
                {icon: 'mdi-bullhorn-outline', key: 'menu.adminAnnouncement', text: 'Announcement', link: '/admin/announcements'},
            ]
        }
    ],

    // footer links
    footer: []
}
