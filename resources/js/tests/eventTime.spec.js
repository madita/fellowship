import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { eventEndsAt, hasEventEnded } from '@/utils/eventTime.js';

// Fixed "now" so the assertions do not drift with the clock.
const NOW = new Date('2026-09-25T12:00:00Z');

describe('eventTime', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(NOW);
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('has no opinion about an event with no dates at all', () => {
        expect(eventEndsAt(null)).toBeNull();
        expect(eventEndsAt({})).toBeNull();
        expect(hasEventEnded({})).toBe(false);
    });

    it('runs a whole-day event to the end of its day, not to midnight', () => {
        // Today: over only once the day is, so it is still answerable now
        expect(hasEventEnded({ endDate: '2026-09-25' })).toBe(false);
        expect(eventEndsAt({ endDate: '2026-09-25' }).getHours()).toBe(23);
    });

    it('calls yesterday over and tomorrow not', () => {
        expect(hasEventEnded({ endDate: '2026-09-24' })).toBe(true);
        expect(hasEventEnded({ endDate: '2026-09-26' })).toBe(false);
    });

    it('uses the end when there is one, and the start when there is not', () => {
        expect(hasEventEnded({ start: '2026-09-25T09:00:00Z', end: '2026-09-25T11:00:00Z' })).toBe(true);
        expect(hasEventEnded({ start: '2026-09-25T09:00:00Z', end: '2026-09-25T13:00:00Z' })).toBe(false);
        expect(hasEventEnded({ start: '2026-09-25T09:00:00Z' })).toBe(true);
        expect(hasEventEnded({ start: '2026-09-25T13:00:00Z' })).toBe(false);
    });

    it('takes a Date as readily as a string', () => {
        expect(hasEventEnded({ end: new Date('2026-09-25T11:00:00Z') })).toBe(true);
        expect(hasEventEnded({ end: new Date('2026-09-25T13:00:00Z') })).toBe(false);
    });

    it('treats an unreadable date as no date rather than as the past', () => {
        expect(hasEventEnded({ end: 'not a date' })).toBe(false);
    });
});
