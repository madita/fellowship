import { describe, it, expect, afterEach, vi } from 'vitest';
import { spaceAbove } from '@/utils/fillViewport.js';

const elementAt = top => ({ getBoundingClientRect: () => ({ top }) });

describe('spaceAbove', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('is what sits above the element, plus room beneath it', () => {
        vi.stubGlobal('scrollY', 0);

        expect(spaceAbove(elementAt(200))).toBe(216);
    });

    it('takes the room to leave as an option', () => {
        vi.stubGlobal('scrollY', 0);

        expect(spaceAbove(elementAt(200), { room: 0 })).toBe(200);
    });

    /**
     * Measured against the document, not the viewport: a panel that
     * resized itself every time the page scrolled would be unusable.
     */
    it('gives the same answer however far the page is scrolled', () => {
        vi.stubGlobal('scrollY', 0);
        const atRest = spaceAbove(elementAt(200));

        // Scrolled down 150px, so the element's viewport top has moved up
        vi.stubGlobal('scrollY', 150);
        const scrolled = spaceAbove(elementAt(50));

        expect(scrolled).toBe(atRest);
    });

    it('never reports a negative space', () => {
        vi.stubGlobal('scrollY', 0);

        expect(spaceAbove(elementAt(-500), { room: 0 })).toBe(0);
    });

    it('rounds to whole pixels', () => {
        vi.stubGlobal('scrollY', 0);

        expect(spaceAbove(elementAt(199.6), { room: 0 })).toBe(200);
    });

    // The caller leaves its fallback in place rather than writing a nonsense
    // height when there is nothing to measure yet
    it('says nothing when there is no element to measure', () => {
        expect(spaceAbove(null)).toBeNull();
        expect(spaceAbove(undefined)).toBeNull();
        expect(spaceAbove({})).toBeNull();
    });
});
