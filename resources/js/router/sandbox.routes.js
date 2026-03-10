const SandboxRoutes = [
  {
    path: '/sandbox',
    name: 'sandbox.index',
    component: () => import('../components/sandbox/SandboxList.vue'),
    meta: {
      title: 'Sandboxes',
      requiresAuth: true,
    },
  },
  {
    path: '/sandbox/:uuid',
    name: 'sandbox.show',
    component: () => import('../components/sandbox/SandboxEditor.vue'),
    props: true,
    meta: {
      title: 'Sandbox',
      layout: 'minimal', // Full-screen editor layout
    },
  },
]

export default SandboxRoutes
