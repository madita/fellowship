/**
 * Feedback System Routes (BGA-style)
 */

export default [
    {
        path: '/feedback/bugs',
        name: 'feedback.bugs',
        component: () => import('@/components/feedback/FeedbackList.vue'),
        meta: {
            title: 'Bug Reports',
            public: true,
        },
        props: { type: 'bug' },
    },
    {
        path: '/feedback/features',
        name: 'feedback.features',
        component: () => import('@/components/feedback/FeedbackList.vue'),
        meta: {
            title: 'Feature Requests',
            public: true,
        },
        props: { type: 'feature' },
    },
    {
        path: '/feedback/ticket/:id',
        name: 'feedback.ticket',
        component: () => import('@/components/feedback/FeedbackTicket.vue'),
        meta: {
            title: 'Ticket Details',
            public: true,
        },
    },
];
