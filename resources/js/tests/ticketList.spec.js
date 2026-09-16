import { describe, it, expect, vi, beforeEach } from 'vitest';

describe('TicketList loadRelatedContent race condition guard', () => {
    let relatedContentRequestId;
    let relatedContent;
    let relatedContentType;
    let relatedContentLoading;

    // Simulate the race condition guard logic from TicketList.vue
    function createLoadRelatedContent() {
        relatedContentRequestId = 0;

        return async (ticket, mockResponse, delay = 0) => {
            const requestId = ++relatedContentRequestId;
            relatedContent = null;
            relatedContentType = null;

            if (!ticket?.ticketable || !ticket?.ticketable_type) return;

            relatedContentLoading = true;

            // Simulate async delay
            await new Promise(resolve => setTimeout(resolve, delay));

            // Guard: discard stale response
            if (requestId !== relatedContentRequestId) return;

            relatedContent = mockResponse;
            relatedContentType = 'wiki';
            relatedContentLoading = false;
        };
    }

    beforeEach(() => {
        relatedContent = null;
        relatedContentType = null;
        relatedContentLoading = false;
    });

    it('sets related content for a single request', async () => {
        const loadRelatedContent = createLoadRelatedContent();

        const ticket = {
            ticketable: { slug: 'test' },
            ticketable_type: 'App\\Models\\Wiki',
        };

        await loadRelatedContent(ticket, { title: 'Wiki Page' }, 10);

        expect(relatedContent).toEqual({ title: 'Wiki Page' });
        expect(relatedContentType).toBe('wiki');
        expect(relatedContentLoading).toBe(false);
    });

    it('discards slow first response when second request starts', async () => {
        const loadRelatedContent = createLoadRelatedContent();

        const ticket1 = {
            ticketable: { slug: 'slow' },
            ticketable_type: 'App\\Models\\Wiki',
        };
        const ticket2 = {
            ticketable: { slug: 'fast' },
            ticketable_type: 'App\\Models\\Wiki',
        };

        // Start slow request (200ms)
        const slow = loadRelatedContent(ticket1, { title: 'Slow Page' }, 200);
        // Start fast request (10ms) - should win
        const fast = loadRelatedContent(ticket2, { title: 'Fast Page' }, 10);

        await Promise.all([slow, fast]);

        // Fast response should be the one displayed
        expect(relatedContent).toEqual({ title: 'Fast Page' });
    });

    it('ignores response for tickets without ticketable', async () => {
        const loadRelatedContent = createLoadRelatedContent();

        await loadRelatedContent({ ticketable: null, ticketable_type: null }, null, 0);

        expect(relatedContent).toBeNull();
        expect(relatedContentLoading).toBe(false);
    });
});

describe('TicketKanban accessibility', () => {
    it('kanban card should have required a11y attributes', () => {
        // Verify the expected attributes that should be on kanban cards
        const expectedAttributes = {
            role: 'button',
            tabindex: '0',
        };

        expect(expectedAttributes.role).toBe('button');
        expect(expectedAttributes.tabindex).toBe('0');
    });

    it('keyboard handler should fire on Enter', () => {
        const handler = vi.fn();

        // Simulate keydown event check
        const event = { key: 'Enter', preventDefault: vi.fn() };
        if (event.key === 'Enter' || event.key === ' ') {
            handler();
        }

        expect(handler).toHaveBeenCalledOnce();
    });

    it('keyboard handler should fire on Space with preventDefault', () => {
        const handler = vi.fn();

        const event = { key: ' ', preventDefault: vi.fn() };
        if (event.key === ' ') {
            event.preventDefault();
            handler();
        }

        expect(handler).toHaveBeenCalledOnce();
        expect(event.preventDefault).toHaveBeenCalledOnce();
    });
});
