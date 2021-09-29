export default [{
  path: '/',
  name: 'home',
  component: () => import(/* webpackChunkName: "landing-home" */ '@/pages/landing/HomePage.vue'),
  meta: {
    layout: 'landing'
  }
}]
