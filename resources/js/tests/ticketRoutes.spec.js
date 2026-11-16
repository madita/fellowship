import { describe, it, expect } from 'vitest';
import { createRouter, createMemoryHistory } from 'vue-router';
import feedback from '@/router/feedback.routes.js';

// Route components are lazy imports, so resolving paths loads nothing
const router = createRouter({ history: createMemoryHistory(), routes: feedback });

describe('feedback routes', () => {
    it('opens a feedback ticket by number', () => {
        expect(router.resolve('/feedback/42').name).toBe('feedback-ticket');
    });

    it('opens the new feedback page instead of a ticket', () => {
        expect(router.resolve('/feedback/new').name).toBe('feedback-new');
    });
});
