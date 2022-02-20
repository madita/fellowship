export default [{
  path: '/',
  name: 'home',
  component: () => import(/* webpackChunkName: "landing-home" */ '@/pages/landing/HomePage.vue'),
  meta: {
    layout: 'landing'
  }
},
    {
        path: '/p/:slug',
        name: 'pages',
        component: () => import(/* webpackChunkName: "landing-pages" */ '@/pages/landing/Pages.vue'),
        meta: {
            layout: 'landing'
        }
    }]
