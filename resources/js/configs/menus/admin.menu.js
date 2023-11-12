export default
    {
        // role: 'admin', icon: 'mdi-file-lock-outline', key: 'menu.admin', text: 'Admin Pages', regex: /^\/admin/,
        items: [
            {permission: 'manage-role', icon: 'mdi-file-outline', key: 'mRenu.adminoles', text: 'Roles', link: '/admin/roles'},
            {permission: 'manage-role', icon: 'mdi-file-outline', key: 'menu.adminPermission', text: 'Permissions', link: '/admin/permissions'},
            {icon: 'mdi-file-outline', key: 'menu.adminUsers', text: 'Users', link: '/admin/users'},
            {icon: 'mdi-file-outline', key: 'menu.adminPages', text: 'Pages', link: '/admin/pages'},
            {icon: 'mdi-file-outline', key: 'menu.adminPosts', text: 'Posts', link: '/admin/posts'},
            {
                icon: 'mdi-tag-multiple-outline', key: 'menu.adminTags', text: 'Taxonomie', link: '/admin/tags', regex: /^\/admin\/tags/,
                items: [
                    {icon: 'mdi-tag', key: 'menu.adminTaxonomie', text: 'Taxonomie', link: '/admin/tags/taxonomie'},
                    {icon: 'mdi-tag-outline', key: 'menu.adminTerms', text: 'Terms', link: '/admin/tags/terms'}
                ]
            },
            {icon: 'mdi-file-outline', key: 'menu.adminAnnouncement', text: 'Announcement', link: '/admin/announcements'},
        ]
    }

