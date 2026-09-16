// Feedback: public bug reports and feature requests
export const feedback = [
    {
        // One list for both; ?type=bug|feature selects a tab
        path: '/feedback',
        name: 'feedback',
        component: () => import(/* webpackChunkName: "feedback-list" */ '@/pages/feedback/FeedbackList.vue'),
        meta: {
            layout: 'default'
        }
    },
    {
        path: '/feedback/:id(\d+)',
        name: 'feedback-ticket',
        component: () => import(/* webpackChunkName: "feedback-ticket" */ '@/pages/feedback/FeedbackTicket.vue'),
        meta: {
            layout: 'default'
        }
    },
]

export default feedback
