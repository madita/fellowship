import { describe, it, expect, beforeEach } from 'vitest';
import {
    ticketFiltersFromQuery,
    ticketFilterParams,
    rememberTicketListQuery,
    rememberedTicketListQuery,
} from '@/composables/useTicketListQuery.js';

describe('ticket list query', () => {
    beforeEach(() => sessionStorage.clear());

    it('defaults to open tickets without a status in the address', () => {
        expect(ticketFiltersFromQuery({}).status).toBe('open');
    });

    it('treats an empty status as all statuses', () => {
        expect(ticketFiltersFromQuery({ status: '' }).status).toBeNull();
    });

    it('reads the filters of a dashboard link', () => {
        const filters = ticketFiltersFromQuery({ view: 'list', due: 'overdue', priority: 'urgent' });
        expect(filters).toMatchObject({ status: 'open', due: 'overdue', priority: 'urgent', search: '' });
    });

    it('sends only the filters that are set', () => {
        const params = ticketFilterParams(ticketFiltersFromQuery({ status: '', assigned_to: 'me' }));
        expect(params).toEqual({ assigned_to: 'me' });
    });

    it('remembers the list address per list', () => {
        rememberTicketListQuery('admin-tickets', { view: 'list', status: 'pending' });
        expect(rememberedTicketListQuery('admin-tickets')).toEqual({ view: 'list', status: 'pending' });
        expect(rememberedTicketListQuery('my-tickets')).toEqual({});
    });
});
