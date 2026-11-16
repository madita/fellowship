const SandboxRoutes = [
  {
    path: '/sandbox',
    name: 'sandbox.index',
    component: () => import('../components/sandbox/SandboxDashboard.vue'),
    meta: {
      title: 'Sandboxes',
      requiresAuth: true,
    },
  },
  {
    path: '/sandbox/:uuid',
    name: 'sandbox.show',
    component: () => import('../components/sandbox/SandboxDashboard.vue'),
    props: true,
    meta: {
      title: 'Sandbox',
    },
  },
]

export default SandboxRoutes
