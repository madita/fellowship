/**
 * Finding the thing on the page a help step is about, working out where
 * its card should sit, and — in the editor's picking mode — writing down
 * a selector for whatever was clicked.
 *
 * Kept out of the components because all three of them (the tour overlay,
 * the hint markers and the picker) need the same answers, and because the
 * selector-writing is the part worth testing on its own.
 */

/** How far from the element the card sits. */
export const CARD_GAP = 14;

/** Roughly how big a step card is, for deciding which side it fits on. */
export const CARD_SIZE = { width: 340, height: 180 };

/**
 * The element a step points at, or null when it is not on the page.
 *
 * A selector written for a page that has since changed is the normal
 * failure here, so this never throws: an invalid selector is the same
 * thing as one that matches nothing.
 */
export function findTarget(selector) {
    if (!selector) return null;

    try {
        const element = document.querySelector(selector);

        // An element that is there but collapsed cannot be pointed at
        if (!element || !element.getClientRects().length) return null;

        return element;
    } catch (e) {
        return null;
    }
}

/** Where the element is on screen right now, in viewport coordinates. */
export function rectOf(element) {
    const { top, left, width, height } = element.getBoundingClientRect();

    return { top, left, width, height, bottom: top + height, right: left + width };
}

/**
 * Bring the element into view before pointing at it, so a step about
 * something below the fold does not highlight empty space.
 */
export function revealTarget(element) {
    if (!element) return;

    const { top, bottom } = element.getBoundingClientRect();
    const height = window.innerHeight || 0;

    // Already comfortably on screen: scrolling would only make the page
    // jump about between steps
    if (top >= 80 && bottom <= height - 80) return;

    element.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
}

/**
 * Which side of the element the card goes on.
 *
 * 'auto' picks the first side with room, preferring below — that is where
 * a reader expects an explanation of the thing they are looking at. A side
 * the admin asked for is honoured unless it would push the card off
 * screen, in which case the opposite side is used.
 */
export function resolvePlacement(rect, asked = 'auto', viewport = viewportSize()) {
    const room = {
        bottom: viewport.height - rect.bottom,
        top: rect.top,
        right: viewport.width - rect.right,
        left: rect.left,
    };

    const needed = {
        bottom: CARD_SIZE.height + CARD_GAP,
        top: CARD_SIZE.height + CARD_GAP,
        right: CARD_SIZE.width + CARD_GAP,
        left: CARD_SIZE.width + CARD_GAP,
    };

    const opposite = { bottom: 'top', top: 'bottom', left: 'right', right: 'left' };

    if (asked !== 'auto' && room[asked] !== undefined) {
        if (room[asked] >= needed[asked]) return asked;
        if (room[opposite[asked]] >= needed[opposite[asked]]) return opposite[asked];
    }

    const order = ['bottom', 'top', 'right', 'left'];
    const fits = order.find(side => room[side] >= needed[side]);

    return fits || 'bottom';
}

/**
 * Where to put the card, as a fixed-position style object.
 *
 * Clamped to the viewport with a gutter, so a step about something in the
 * corner still reads on a phone.
 */
export function cardStyle(rect, placement, viewport = viewportSize(), gutter = 12) {
    let top;
    let left;

    if (placement === 'bottom') {
        top = rect.bottom + CARD_GAP;
        left = rect.left + rect.width / 2 - CARD_SIZE.width / 2;
    } else if (placement === 'top') {
        top = rect.top - CARD_GAP - CARD_SIZE.height;
        left = rect.left + rect.width / 2 - CARD_SIZE.width / 2;
    } else if (placement === 'right') {
        top = rect.top + rect.height / 2 - CARD_SIZE.height / 2;
        left = rect.right + CARD_GAP;
    } else {
        top = rect.top + rect.height / 2 - CARD_SIZE.height / 2;
        left = rect.left - CARD_GAP - CARD_SIZE.width;
    }

    const maxLeft = Math.max(gutter, viewport.width - CARD_SIZE.width - gutter);
    const maxTop = Math.max(gutter, viewport.height - CARD_SIZE.height - gutter);

    return {
        top: `${clamp(top, gutter, maxTop)}px`,
        left: `${clamp(left, gutter, maxLeft)}px`,
        width: `min(${CARD_SIZE.width}px, calc(100vw - ${gutter * 2}px))`,
    };
}

/**
 * A selector for an element the admin clicked in picking mode.
 *
 * `data-help` first and by a long way: a component that carries one is
 * saying "this is the thing help points at", and it survives the redesign
 * that renames every class around it. Then an id. Only failing both does
 * this fall back to a structural path, which works but will break the day
 * somebody reorders the page — which is why the editor shows the selector
 * it produced rather than hiding it.
 */
export function selectorFor(element) {
    if (!element || element.nodeType !== 1) return null;

    const anchored = element.closest('[data-help]');
    if (anchored) {
        return `[data-help="${cssEscape(anchored.getAttribute('data-help'))}"]`;
    }

    if (element.id && isUnique(`#${cssEscape(element.id)}`)) {
        return `#${cssEscape(element.id)}`;
    }

    return structuralSelector(element);
}

/**
 * A path up the tree, kept as short as it can be while still matching one
 * element. Stops at the first ancestor with an id, because that anchors
 * everything below it.
 */
function structuralSelector(element) {
    const parts = [];
    let node = element;

    // Eight is past the point where a longer path helps; the result is
    // already fragile and the editor says so
    while (node && node.nodeType === 1 && parts.length < 8) {
        if (node.id) {
            parts.unshift(`#${cssEscape(node.id)}`);
            break;
        }

        parts.unshift(nodeSelector(node));

        const candidate = parts.join(' > ');
        if (isUnique(candidate)) return candidate;

        node = node.parentElement;

        if (node === document.body || !node) break;
    }

    const selector = parts.join(' > ');

    return selector || null;
}

/**
 * One element's own part of the path: its tag, a class worth keeping, and
 * its position among its siblings when that is what separates it.
 */
function nodeSelector(node) {
    let selector = node.tagName.toLowerCase();

    const className = stableClass(node);
    if (className) selector += `.${cssEscape(className)}`;

    const parent = node.parentElement;

    if (parent) {
        const sameKind = Array.from(parent.children).filter(
            child => child.tagName === node.tagName
        );

        if (sameKind.length > 1) {
            selector += `:nth-of-type(${sameKind.indexOf(node) + 1})`;
        }
    }

    return selector;
}

/**
 * Classes that describe what something is, not what it is doing.
 *
 * Vuetify's state classes come and go as the page is used — a selector
 * built on `v-btn--active` or a `text-primary` would stop matching the
 * moment the element lost focus.
 */
const UNSTABLE_CLASS = /^(v-[a-z-]*--|is-|has-|active|selected|focused|hover|text-|bg-|ma-|pa-|m[trblxy]-|p[trblxy]-|d-|elevation-)/;

function stableClass(node) {
    const classes = Array.from(node.classList || []);

    return classes.find(name => name.length > 2 && !UNSTABLE_CLASS.test(name)) || null;
}

function isUnique(selector) {
    try {
        return document.querySelectorAll(selector).length === 1;
    } catch (e) {
        return false;
    }
}

/**
 * Escape for use inside a selector. CSS.escape is not in jsdom, so the
 * tests would otherwise never reach this code.
 */
function cssEscape(value) {
    const text = String(value);

    if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
        return CSS.escape(text);
    }

    return text.replace(/([^\w-])/g, '\\$1');
}

function viewportSize() {
    return {
        width: window.innerWidth || 1024,
        height: window.innerHeight || 768,
    };
}

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}

/**
 * Does this tour belong on this path?
 *
 * The same rule the backend applies, repeated here so the client can tell
 * without asking again when only the query string changed. `*` stands for
 * any run of characters, as Str::is has it.
 */
export function pathMatches(pattern, path) {
    if (!pattern) return false;

    const target = normalisePath(path);
    const cleaned = normalisePath(pattern);

    if (cleaned === target) return true;
    if (!cleaned.includes('*')) return false;

    const expression = cleaned
        .split('*')
        .map(part => part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
        .join('.*');

    return new RegExp(`^${expression}$`).test(target);
}

export function normalisePath(path) {
    const withoutQuery = String(path || '/').split('?')[0];
    const trimmed = withoutQuery.replace(/\/+$/, '');

    return trimmed === '' ? '/' : trimmed;
}
