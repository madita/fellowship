// When an event is over.
//
// The calendar drawer and the event's own page both have to decide whether
// answering is still possible, and the server decides the same thing in
// Event::hasEnded(). One reading, in one place, so the three agree.

/**
 * The instant an event finishes, or null when it has no usable date.
 *
 * An event with no end ends the day it starts, and one given as a date
 * with no time runs to the end of that day — otherwise a whole-day event
 * would count as over from the moment midnight passed.
 */
export const eventEndsAt = (event) => {
    if (!event) return null;

    const source = event.end ?? event.endDate ?? event.start ?? event.startDate;

    if (!source) return null;

    // A bare "YYYY-MM-DD" has no time of day; anything else already does.
    const value = typeof source === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(source)
        ? `${source}T23:59:59`
        : source;

    const at = value instanceof Date ? value : new Date(value);

    return Number.isNaN(at.getTime()) ? null : at;
};

// An event nobody can still answer, because it has already happened.
export const hasEventEnded = (event) => {
    const endsAt = eventEndsAt(event);

    return endsAt !== null && endsAt.getTime() < Date.now();
};
