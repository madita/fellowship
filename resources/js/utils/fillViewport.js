// How much of the viewport is already spoken for above an element.
//
// Panels that should fill the rest of the screen — the calendar, say — are
// usually given `height: calc(100dvh - <a guess>)`. The guess is wrong
// twice over: it has to be maintained whenever anything above moves, and it
// cannot know that a page header is two lines tall on a narrow screen and
// one on a wide one. Measuring costs a single read and is always right.

// Left below the element, so it does not sit flush against the window edge
const BREATHING_ROOM = 16;

/**
 * The space above `el` within the document, plus a little room beneath it.
 *
 * Measured from the document rather than the viewport, so the answer does
 * not change as the page scrolls — the panel keeps the height it was given
 * and scrolls with the page, rather than resizing under the reader.
 */
export function spaceAbove(el, { room = BREATHING_ROOM } = {}) {
    if (!el || typeof el.getBoundingClientRect !== 'function') return null;

    const { top } = el.getBoundingClientRect();
    const scrolled = window.scrollY ?? 0;

    return Math.max(0, Math.round(top + scrolled)) + room;
}
