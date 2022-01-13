export default [
  { role: 'admin', icon: 'mdi-file-lock-outline', key: 'menu.admin', text: 'Admin Pages', regex: /^\/admin/,
    items: [
      { permission: 'manage-role', icon: 'mdi-file-outline', key: 'menu.adminRoles', text: 'Roles', link: '/admin/roles' },
      { permission: 'manage-role', icon: 'mdi-file-outline', key: 'menu.adminPermission', text: 'Permissions', link: '/admin/permissions' },
      { icon: 'mdi-file-outline', key: 'menu.adminUsers', text: 'Users', link: '/admin/users' },
      { icon: 'mdi-file-outline', key: 'menu.adminPages', text: 'Pages', link: '/admin/pages' },
      { icon: 'mdi-file-outline', key: 'menu.adminPosts', text: 'Posts', link: '/admin/posts' },
      { icon: 'mdi-file-outline', key: 'menu.adminAnnouncement', text: 'Announcement', link: '/admin/announcements' },
    ]
  }
]
