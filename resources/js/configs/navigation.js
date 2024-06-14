import menuAdmin from './menus/admin.menu.js'

export default {
  // main navigation - side menu
  menu: [{
    text: '',
    key: '',
    items: [
      { icon: 'mdi-view-dashboard-outline', key: 'menu.dashboard', text: 'Dashboard', link: '/dashboard' },
      { icon: 'mdi-message-text-outline', key: 'menu.chat', text: 'Chat', link: '/chat' },
      { icon: 'mdi-calendar', key: 'menu.events', text: 'Events', link: '/events' },
      { icon: 'mdi-file-outline', key: 'menu.wiki', text: 'Wiki', link: '/wiki' },
      { icon: 'mdi-file-outline', key: 'menu.blank', text: 'Blank Page', link: '/blank' }
    ]
  }, {
    // text: 'Landing Pages',
    // items: [
    //   { icon: 'mdi-airplane-landing', key: 'menu.landingPage', text: 'Landing Page', link: '/' },
    //   { icon: 'mdi-cash-usd-outline', key: 'menu.pricingPage', text: 'Pricing Page', link: '/landing/pricing' }
    // ]
  }, {
    text: 'Administration',
    key: 'menu.admin',
    role: 'admin',
      items: [
          {permission: 'manage-role', icon: 'mdi-file-outline', key: 'menu.adminRoles', text: 'Roles', link: '/admin/roles'},
          {permission: 'manage-role', icon: 'mdi-file-outline', key: 'menu.adminPermission', text: 'Permissions', link: '/admin/permissions'},
          {icon: 'mdi-file-outline', key: 'menu.adminUsers', text: 'Users', link: '/admin/users'},
          {icon: 'mdi-file-outline', key: 'menu.adminPages', text: 'Pages', link: '/admin/pages'},
          {icon: 'mdi-file-outline', key: 'menu.adminPosts', text: 'Posts', link: '/admin/posts'},
          {icon: 'mdi-file-outline', key: 'menu.adminEvents', text: 'Events', link: '/admin/events'},
          {
              icon: 'mdi-tag-multiple-outline', key: 'menu.adminTags', text: 'Taxonomie', link: '/admin/tags', regex: /^\/admin\/tags/,
              items: [
                  {icon: 'mdi-tag', key: 'menu.adminTaxonomie', text: 'Taxonomie', link: '/admin/tags/taxonomie'},
                  {icon: 'mdi-tag-outline', key: 'menu.adminTerms', text: 'Terms', link: '/admin/tags/terms'}
              ]
          },
          {icon: 'mdi-file-outline', key: 'menu.adminAnnouncement', text: 'Announcement', link: '/admin/announcements'},
      ]
  }],

  // footer links
  footer: [{
    // text: 'Docs',
    // key: 'menu.docs',
    // href: 'https://vuetifyjs.com',
    // target: '_blank'
  }]
}
