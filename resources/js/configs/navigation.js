import menuAdmin from './menus/admin.menu'

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
    items: menuAdmin
  }],

  // footer links
  footer: [{
    // text: 'Docs',
    // key: 'menu.docs',
    // href: 'https://vuetifyjs.com',
    // target: '_blank'
  }]
}
