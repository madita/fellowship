export default {
  // apps quickmenu
  apps: [{
    icon: 'mdi-email-outline',
    text: 'Profile',
    key: 'menu.profile',
    subtitle: 'Hey!',
    link: '/users/edit'
  }],

  // user dropdown menu
  user: [
    { icon: 'mdi-account-box-outline', key: 'menu.profile', text: 'Profile', link: '/users/edit' },
    { icon: 'mdi-ticket-outline', key: 'menu.myTickets', text: 'My Tickets', link: '/account/tickets' },
    { icon: 'mdi-cog-outline', key: 'menu.settings', text: 'Settings', action: 'settings' }
  ]
}
