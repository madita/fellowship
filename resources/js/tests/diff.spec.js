import { describe, it, expect } from 'vitest';
import {
    htmlToLines,
    textToLines,
    diffLines,
    diffWords,
    tokenizeWords,
    collapseUnchanged,
    diffStats,
    buildDiff,
} from '@/utils/diff.js';

describe('diff: flattening html', () => {
    it('turns each block into one line', () => {
        expect(htmlToLines('<p>First</p><p>Second</p>')).toEqual(['First', 'Second']);
    });

    it('breaks on <br> and keeps list bullets', () => {
        expect(htmlToLines('<p>One<br>Two</p>')).toEqual(['One', 'Two']);
        expect(htmlToLines('<ul><li>Apple</li><li>Pear</li></ul>')).toEqual(['• Apple', '• Pear']);
    });

    it('drops inline markup but keeps the text', () => {
        expect(htmlToLines('<p>A <strong>bold</strong> word</p>')).toEqual(['A bold word']);
    });

    it('decodes entities and collapses runs of whitespace', () => {
        expect(htmlToLines('<p>Caf&eacute; &amp;   tea</p>')).toEqual(['Café & tea']);
        expect(htmlToLines('<p>a&#39;b</p>')).toEqual(["a'b"]);
    });

    it('returns nothing for empty input', () => {
        expect(htmlToLines(null)).toEqual([]);
        expect(htmlToLines('')).toEqual([]);
        expect(htmlToLines('<p></p>')).toEqual([]);
    });

    it('leaves plain text alone', () => {
        expect(textToLines('Title <not a tag>')).toEqual(['Title <not a tag>']);
        expect(textToLines(null)).toEqual([]);
    });
});

describe('diff: lines', () => {
    it('reports untouched lines as equal', () => {
        const rows = diffLines(['a', 'b'], ['a', 'b']);
        expect(rows.map(row => row.type)).toEqual(['equal', 'equal']);
    });

    it('marks an inserted line as added', () => {
        const rows = diffLines(['a', 'c'], ['a', 'b', 'c']);
        expect(rows.map(row => row.type)).toEqual(['equal', 'added', 'equal']);
        expect(rows[1].newLine).toBe('b');
        expect(rows[1].oldLine).toBeNull();
    });

    it('marks a deleted line as removed', () => {
        const rows = diffLines(['a', 'b', 'c'], ['a', 'c']);
        expect(rows.map(row => row.type)).toEqual(['equal', 'removed', 'equal']);
        expect(rows[1].oldLine).toBe('b');
    });

    it('pairs a replacement into a single modified row', () => {
        const rows = diffLines(['a', 'old line', 'c'], ['a', 'new line', 'c']);
        expect(rows.map(row => row.type)).toEqual(['equal', 'modified', 'equal']);
        expect(rows[1]).toMatchObject({ oldLine: 'old line', newLine: 'new line', oldNumber: 2, newNumber: 2 });
    });

    it('numbers each side independently', () => {
        const rows = diffLines(['a'], ['x', 'a']);
        const equal = rows.find(row => row.type === 'equal');
        expect(equal).toMatchObject({ oldNumber: 1, newNumber: 2 });
    });

    it('stays in step when a paragraph moves', () => {
        const rows = diffLines(['intro', 'middle', 'end'], ['intro', 'end']);
        expect(rows.map(row => row.type)).toEqual(['equal', 'removed', 'equal']);
    });

    it('handles an empty side', () => {
        expect(diffLines([], ['a', 'b']).map(row => row.type)).toEqual(['added', 'added']);
        expect(diffLines(['a'], []).map(row => row.type)).toEqual(['removed']);
        expect(diffLines([], [])).toEqual([]);
    });
});

describe('diff: words', () => {
    it('splits into words, spaces and punctuation', () => {
        expect(tokenizeWords('Hi there!')).toEqual(['Hi', ' ', 'there', '!']);
    });

    it('marks only the words that differ', () => {
        const parts = diffWords('the quick brown fox', 'the slow brown fox');
        expect(parts.old.filter(part => part.changed).map(part => part.text)).toEqual(['quick']);
        expect(parts.new.filter(part => part.changed).map(part => part.text)).toEqual(['slow']);
    });

    it('keeps every token so the line can be rebuilt', () => {
        const parts = diffWords('one two three', 'one three');
        expect(parts.old.map(part => part.text).join('')).toBe('one two three');
        expect(parts.new.map(part => part.text).join('')).toBe('one three');
    });

    it('does not colour whitespace on its own', () => {
        const parts = diffWords('a b', 'a  b');
        expect(parts.new.filter(part => part.changed)).toEqual([]);
    });
});

describe('diff: collapsing and stats', () => {
    const rows = [
        ...Array.from({ length: 10 }, (_, index) => ({ type: 'equal', oldLine: `l${index}`, newLine: `l${index}` })),
        { type: 'added', oldLine: null, newLine: 'new' },
        ...Array.from({ length: 10 }, (_, index) => ({ type: 'equal', oldLine: `r${index}`, newLine: `r${index}` })),
    ];

    it('replaces long untouched runs with a gap that carries its lines', () => {
        const collapsed = collapseUnchanged(rows, 2);
        const gaps = collapsed.filter(row => row.type === 'gap');

        expect(gaps).toHaveLength(2);
        expect(gaps[0].count).toBe(8);
        expect(gaps[0].rows).toHaveLength(8);
        // Two lines of context survive on either side of the change.
        expect(collapsed.map(row => row.type)).toEqual(['gap', 'equal', 'equal', 'added', 'equal', 'equal', 'gap']);
    });

    it('collapses nothing when there is nothing to hide', () => {
        const short = [{ type: 'equal' }, { type: 'added' }];
        expect(collapseUnchanged(short, 3)).toEqual(short);
        expect(collapseUnchanged([], 3)).toEqual([]);
    });

    it('counts what moved', () => {
        expect(diffStats(rows)).toEqual({ added: 1, removed: 0, changed: 0 });
    });
});

describe('diff: buildDiff', () => {
    it('diffs two html documents by their text', () => {
        const result = buildDiff('<p>Hello</p><p>World</p>', '<p>Hello</p><p>Planet</p>');

        expect(result.isEmpty).toBe(false);
        expect(result.stats).toEqual({ added: 0, removed: 0, changed: 1 });
        expect(result.rows[1]).toMatchObject({ oldLine: 'World', newLine: 'Planet' });
    });

    it('calls markup-only edits identical', () => {
        const result = buildDiff('<p>Same <em>text</em></p>', '<p>Same <strong>text</strong></p>');
        expect(result.isEmpty).toBe(true);
    });

    it('treats a created page as all additions', () => {
        const result = buildDiff('', '<p>One</p><p>Two</p>');
        expect(result.stats).toEqual({ added: 2, removed: 0, changed: 0 });
    });

    it('compares plain text when told the input is not html', () => {
        const result = buildDiff('Old title', 'New title', { html: false });
        expect(result.rows).toHaveLength(1);
        expect(result.rows[0].type).toBe('modified');
    });

    it('survives a page far past the exact-diff limit', () => {
        const long = Array.from({ length: 2000 }, (_, index) => `<p>line ${index}</p>`).join('');
        const edited = long.replace('<p>line 1500</p>', '<p>line 1500 edited</p>');

        const result = buildDiff(long, edited);
        expect(result.isEmpty).toBe(false);
        expect(result.stats.added + result.stats.changed).toBeGreaterThan(0);
    });
});
