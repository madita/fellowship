/**
 * Text diffing for the history/versions views.
 *
 * Wiki text is stored as HTML, so a diff of the markup itself would be all
 * noise: a reader wants to know that a sentence changed, not that a <p> moved.
 * The html is therefore flattened to one line per block element first, and the
 * comparison happens on those lines. Lines that only differ in a few words get
 * a second, word-level pass so the changed words can be marked inside them.
 */

/** Block level tags that end a line when html is flattened. */
const BLOCK_TAGS = 'address|article|aside|blockquote|details|div|dl|dd|dt|fieldset|figcaption|figure|footer|form|h[1-6]|header|hr|li|main|nav|ol|p|pre|section|table|tbody|td|tfoot|th|thead|tr|ul';

const NAMED_ENTITIES = {
    amp: '&',
    lt: '<',
    gt: '>',
    quot: '"',
    apos: "'",
    nbsp: ' ',
    hellip: '…',
    mdash: '—',
    ndash: '–',
};

/**
 * Two texts of this many lines each already mean a million cell diff table.
 * Beyond it the line diff falls back to a cheaper comparison rather than
 * locking up the browser on a pathologically long page.
 */
const LCS_LINE_LIMIT = 1500;

/** Word level diffing is only worth it on lines of a sane length. */
const WORD_LIMIT = 400;

/**
 * Turn html into the plain text lines a reader actually compares.
 *
 * @param {string|null|undefined} html
 * @returns {string[]}
 */
export function htmlToLines(html) {
    if (html === null || html === undefined) return [];

    let text = String(html);

    // <br> and the end of a block are both line breaks to a reader.
    text = text.replace(/<br\s*\/?>/gi, '\n');

    // List items read better with their bullet kept, so they are taken out
    // before the generic block rule can turn them into a bare line break.
    text = text.replace(/<li(?:\s[^>]*)?>/gi, '\n• ');

    text = text.replace(new RegExp(`</(?:${BLOCK_TAGS})\\s*>`, 'gi'), '\n');
    text = text.replace(new RegExp(`<(?:${BLOCK_TAGS})(?:\\s[^>]*)?/?>`, 'gi'), '\n');

    text = text.replace(/<[^>]+>/g, '');
    text = decodeEntities(text);

    return text
        .split('\n')
        .map(line => line.replace(/[ \t ]+/g, ' ').trim())
        .filter(line => line !== '');
}

/**
 * Turn entities back into the characters they stand for. A browser knows all
 * of them, so it does the work where there is a dom; the table above covers
 * the common ones for anything running without one.
 */
function decodeEntities(text) {
    if (!text.includes('&')) return text;

    if (typeof document !== 'undefined' && document.createElement) {
        // Tags are gone by this point, so nothing here can be parsed as
        // markup: a textarea only ever hands back its text.
        const element = document.createElement('textarea');
        element.innerHTML = text;

        return element.value;
    }

    return text
        .replace(/&#(\d+);/g, (_, code) => String.fromCodePoint(Number(code)))
        .replace(/&#x([0-9a-f]+);/gi, (_, code) => String.fromCodePoint(parseInt(code, 16)))
        .replace(/&([a-z]+);/gi, (match, name) => {
            const key = name.toLowerCase();
            return Object.prototype.hasOwnProperty.call(NAMED_ENTITIES, key) ? NAMED_ENTITIES[key] : match;
        });
}

/**
 * Plain text split into lines, for fields that were never html (a title).
 *
 * @param {string|null|undefined} text
 * @returns {string[]}
 */
export function textToLines(text) {
    if (text === null || text === undefined) return [];

    return String(text)
        .split('\n')
        .map(line => line.trim())
        .filter(line => line !== '');
}

/**
 * The longest common subsequence of two arrays, as a list of index pairs.
 *
 * Plain O(n*m) dynamic programming, which is fine at the sizes a wiki page
 * reaches and keeps the result optimal (a greedy walk drifts out of sync after
 * the first moved paragraph).
 *
 * @param {string[]} left
 * @param {string[]} right
 * @returns {Array<[number, number]>}
 */
export function lcsPairs(left, right) {
    const rows = left.length;
    const cols = right.length;
    if (!rows || !cols) return [];

    // One row of the table per left entry; Int32Array keeps the memory flat.
    const table = [];
    for (let row = 0; row <= rows; row++) {
        table.push(new Int32Array(cols + 1));
    }

    for (let row = rows - 1; row >= 0; row--) {
        for (let col = cols - 1; col >= 0; col--) {
            table[row][col] = left[row] === right[col]
                ? table[row + 1][col + 1] + 1
                : Math.max(table[row + 1][col], table[row][col + 1]);
        }
    }

    const pairs = [];
    let row = 0;
    let col = 0;
    while (row < rows && col < cols) {
        if (left[row] === right[col]) {
            pairs.push([row, col]);
            row++;
            col++;
        } else if (table[row + 1][col] >= table[row][col + 1]) {
            row++;
        } else {
            col++;
        }
    }

    return pairs;
}

/**
 * Diff two lists of lines.
 *
 * A removal directly followed by an addition is reported as one "modified"
 * row carrying both sides, so the view can put them next to each other and
 * mark the words that differ.
 *
 * @param {string[]} oldLines
 * @param {string[]} newLines
 * @returns {Array<{type: string, oldLine: string|null, newLine: string|null, oldNumber: number|null, newNumber: number|null}>}
 */
export function diffLines(oldLines, newLines) {
    const left = oldLines || [];
    const right = newLines || [];

    const pairs = left.length > LCS_LINE_LIMIT || right.length > LCS_LINE_LIMIT
        ? anchorPairs(left, right)
        : lcsPairs(left, right);

    const rows = [];
    let oldIndex = 0;
    let newIndex = 0;

    const flushUpTo = (oldStop, newStop) => {
        const removed = [];
        const added = [];
        while (oldIndex < oldStop) removed.push(oldIndex++);
        while (newIndex < newStop) added.push(newIndex++);

        // Pair them up while both sides still have entries: those are edits of
        // a line rather than an unrelated removal plus addition.
        const paired = Math.min(removed.length, added.length);
        for (let i = 0; i < paired; i++) {
            rows.push(row('modified', left[removed[i]], right[added[i]], removed[i], added[i]));
        }
        for (let i = paired; i < removed.length; i++) {
            rows.push(row('removed', left[removed[i]], null, removed[i], null));
        }
        for (let i = paired; i < added.length; i++) {
            rows.push(row('added', null, right[added[i]], null, added[i]));
        }
    };

    for (const [oldPos, newPos] of pairs) {
        flushUpTo(oldPos, newPos);
        rows.push(row('equal', left[oldPos], right[newPos], oldPos, newPos));
        oldIndex = oldPos + 1;
        newIndex = newPos + 1;
    }
    flushUpTo(left.length, right.length);

    return rows;
}

function row(type, oldLine, newLine, oldPos, newPos) {
    return {
        type,
        oldLine: oldLine ?? null,
        newLine: newLine ?? null,
        oldNumber: oldPos === null || oldPos === undefined ? null : oldPos + 1,
        newNumber: newPos === null || newPos === undefined ? null : newPos + 1,
    };
}

/**
 * Cheap stand-in for the LCS on very long texts: match identical lines in
 * order, skipping anything that would move backwards. Not optimal, but it
 * keeps a huge page from freezing the tab.
 */
function anchorPairs(left, right) {
    const positions = new Map();
    right.forEach((line, index) => {
        if (!positions.has(line)) positions.set(line, []);
        positions.get(line).push(index);
    });

    const pairs = [];
    let cursor = 0;
    for (let index = 0; index < left.length; index++) {
        const candidates = positions.get(left[index]);
        if (!candidates) continue;

        const match = candidates.find(position => position >= cursor);
        if (match === undefined) continue;

        pairs.push([index, match]);
        cursor = match + 1;
    }

    return pairs;
}

/**
 * Split a line into the tokens a word diff compares: words, whitespace and
 * punctuation each stand on their own so a changed word does not drag the
 * space next to it into the highlight.
 *
 * @param {string} line
 * @returns {string[]}
 */
export function tokenizeWords(line) {
    if (!line) return [];
    return String(line).match(/\s+|[\p{L}\p{N}_'’-]+|[^\s\p{L}\p{N}_]/gu) || [];
}

/**
 * Word level diff of two lines.
 *
 * @param {string|null} oldLine
 * @param {string|null} newLine
 * @returns {{old: Array<{text: string, changed: boolean}>, new: Array<{text: string, changed: boolean}>}}
 */
export function diffWords(oldLine, newLine) {
    const left = tokenizeWords(oldLine);
    const right = tokenizeWords(newLine);

    if (left.length > WORD_LIMIT || right.length > WORD_LIMIT) {
        return {
            old: left.length ? [{ text: left.join(''), changed: true }] : [],
            new: right.length ? [{ text: right.join(''), changed: true }] : [],
        };
    }

    const pairs = lcsPairs(left, right);
    const common = { old: new Set(), new: new Set() };
    for (const [oldPos, newPos] of pairs) {
        common.old.add(oldPos);
        common.new.add(newPos);
    }

    return {
        old: mergeTokens(left, common.old),
        new: mergeTokens(right, common.new),
    };
}

/** Collapse neighbouring tokens of the same state into one span. */
function mergeTokens(tokens, commonIndexes) {
    const parts = [];

    tokens.forEach((token, index) => {
        const changed = !commonIndexes.has(index);
        const last = parts[parts.length - 1];

        if (last && last.changed === changed) {
            last.text += token;
        } else {
            parts.push({ text: token, changed });
        }
    });

    // A run of whitespace on its own is not a change worth colouring.
    return parts.filter(part => part.text !== '').map(part => (
        part.changed && part.text.trim() === '' ? { ...part, changed: false } : part
    ));
}

/**
 * Replace long stretches of unchanged lines with a single gap row, the way a
 * code review view does, so the changes are not lost in the untouched text.
 *
 * The hidden lines travel along inside the gap row, so the view can show them
 * again without diffing anything a second time.
 *
 * @param {Array} rows rows from diffLines
 * @param {number} context unchanged lines kept either side of a change
 * @returns {Array} rows with `{type: 'gap', count, rows}` entries in place of runs
 */
export function collapseUnchanged(rows, context = 3) {
    if (!rows.length) return [];

    const keep = new Array(rows.length).fill(false);
    rows.forEach((entry, index) => {
        if (entry.type === 'equal') return;
        for (let i = Math.max(0, index - context); i <= Math.min(rows.length - 1, index + context); i++) {
            keep[i] = true;
        }
    });

    const result = [];
    let hidden = [];

    const flushGap = () => {
        if (hidden.length) {
            result.push({ type: 'gap', count: hidden.length, rows: hidden });
            hidden = [];
        }
    };

    rows.forEach((entry, index) => {
        if (keep[index]) {
            flushGap();
            result.push(entry);
        } else {
            hidden.push(entry);
        }
    });
    flushGap();

    return result;
}

/**
 * Added and removed line counts of a diff, for the summary line above it.
 *
 * @param {Array} rows rows from diffLines
 * @returns {{added: number, removed: number, changed: number}}
 */
export function diffStats(rows) {
    let added = 0;
    let removed = 0;
    let changed = 0;

    for (const entry of rows) {
        if (entry.type === 'added') added++;
        else if (entry.type === 'removed') removed++;
        else if (entry.type === 'modified') changed++;
    }

    return { added, removed, changed };
}

/**
 * Everything a diff view needs for one pair of values.
 *
 * @param {string|null} oldValue
 * @param {string|null} newValue
 * @param {{html?: boolean, context?: number}} options
 */
export function buildDiff(oldValue, newValue, options = {}) {
    const { html = true, context = 3 } = options;
    const toLines = html ? htmlToLines : textToLines;

    const rows = diffLines(toLines(oldValue), toLines(newValue));
    const stats = diffStats(rows);

    return {
        rows,
        collapsed: collapseUnchanged(rows, context),
        stats,
        isEmpty: stats.added === 0 && stats.removed === 0 && stats.changed === 0,
    };
}
