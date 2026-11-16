<template>
    <g v-html="svgContent" />
</template>

<script>
/**
 * A comic chat character: a build, a head and what is worn on it, a face
 * that carries the emotion, and arms that carry the gesture. Characters can
 * face left or right so two of them talk to each other across a panel.
 *
 * What a character is made of comes from its spec — see utils/comicCharacter
 * — so the ones that ship with the site and anything built in the character
 * creator are drawn by the same code.
 *
 * Original artwork, drawn for this site — in the spirit of the 1996 comic
 * chat clients, not copied from them.
 *
 * The drawing area is 100 × 140: head around (50, 46), feet at 138.
 */
import { drawParts, hidesFace, paletteFor, PRESETS, normaliseSpec, specForKey } from '@/utils/comicCharacter.js';

export default {
    name: 'ComicCharacter',
    props: {
        // The character's key; specs come from the store, which falls back
        // to the ones that ship with the site
        character: { type: String, default: 'cat' },
        // A spec to draw directly, for a preview of something unsaved
        spec: { type: Object, default: null },
        emotion: { type: String, default: 'normal' },
        gesture: { type: String, default: null },
        // 1 faces right, -1 faces left
        facing: { type: Number, default: 1 },
        // Overrides the character's own hue, so a nickname can colour it
        color: { type: Number, default: null },
        // The one talking stands out; the others listen
        speaking: { type: Boolean, default: true },
    },
    computed: {
        resolved() {
            const base = this.spec
                || specForKey(this.character)
                || PRESETS[this.character]
                || PRESETS.cat;

            const spec = normaliseSpec(base);

            return this.color === null ? spec : { ...spec, hue: this.color };
        },
        palette() {
            return paletteFor(this.resolved);
        },
        parts() {
            return drawParts(this.resolved, this.palette);
        },

        /**
         * Eyes, brows and mouth per emotion. Everything is drawn around the
         * face centre so every character can reuse it.
         */
        face() {
            const { ink } = this.palette;
            const hue = this.resolved.hue;
            const eye = (x, open = 1) => `<ellipse cx="${x}" cy="46" rx="3.4" ry="${3.4 * open}" fill="${ink}"/>`
                + `<circle cx="${x + 1}" cy="44.6" r="1.1" fill="#fff"/>`;
            const brow = (x, dy, rot) => `<path d="M ${x - 5} ${38 + dy} Q ${x} ${35 + dy} ${x + 5} ${38 + dy}"`
                + ` fill="none" stroke="${ink}" stroke-width="2" stroke-linecap="round" transform="rotate(${rot} ${x} ${38 + dy})"/>`;

            switch (this.emotion) {
                case 'happy':
                    return `<path d="M 34 46 Q 39 41 44 46" fill="none" stroke="${ink}" stroke-width="2.4" stroke-linecap="round"/>`
                        + `<path d="M 56 46 Q 61 41 66 46" fill="none" stroke="${ink}" stroke-width="2.4" stroke-linecap="round"/>`
                        + `<path d="M 38 56 Q 50 66 62 56" fill="none" stroke="${ink}" stroke-width="2.4" stroke-linecap="round"/>`
                        + `<circle cx="32" cy="54" r="3.5" fill="hsl(${hue}, 70%, 70%)" opacity="0.7"/>`
                        + `<circle cx="68" cy="54" r="3.5" fill="hsl(${hue}, 70%, 70%)" opacity="0.7"/>`;
                case 'excited':
                    return `<path d="M 35 42 l 4 8 l 4 -8 l -8 4 h 8 z" fill="${ink}"/>`
                        + `<path d="M 57 42 l 4 8 l 4 -8 l -8 4 h 8 z" fill="${ink}"/>`
                        + `<path d="M 38 54 Q 50 70 62 54 Z" fill="#b5423a" stroke="${ink}" stroke-width="2"/>`
                        + `<path d="M 44 62 Q 50 66 56 62" fill="#f0a0a0" stroke="none"/>`;
                case 'sad':
                    return brow(39, -2, 14) + brow(61, -2, -14)
                        + eye(39) + eye(61)
                        + `<path d="M 40 62 Q 50 54 60 62" fill="none" stroke="${ink}" stroke-width="2.4" stroke-linecap="round"/>`
                        + `<path d="M 43 50 q -1 6 1 9" fill="none" stroke="#5fa8d3" stroke-width="2" stroke-linecap="round"/>`;
                case 'angry':
                    return brow(39, -1, 22) + brow(61, -1, -22)
                        + eye(39, 0.7) + eye(61, 0.7)
                        + `<path d="M 40 62 Q 50 55 60 62" fill="none" stroke="${ink}" stroke-width="2.6" stroke-linecap="round"/>`
                        + `<path d="M 24 34 l 6 -6 M 24 28 l 6 6" stroke="#d94f3d" stroke-width="2" stroke-linecap="round"/>`;
                case 'surprised':
                    return brow(39, -5, 0) + brow(61, -5, 0)
                        + `<circle cx="39" cy="46" r="5" fill="#fff" stroke="${ink}" stroke-width="1.6"/><circle cx="39" cy="46" r="2.4" fill="${ink}"/>`
                        + `<circle cx="61" cy="46" r="5" fill="#fff" stroke="${ink}" stroke-width="1.6"/><circle cx="61" cy="46" r="2.4" fill="${ink}"/>`
                        + `<ellipse cx="50" cy="60" rx="5" ry="7" fill="#b5423a" stroke="${ink}" stroke-width="2"/>`;
                case 'confused':
                    return brow(39, -4, 10) + brow(61, -1, -18)
                        + eye(39) + eye(61, 0.8)
                        + `<path d="M 40 60 Q 46 56 52 61 Q 57 64 60 59" fill="none" stroke="${ink}" stroke-width="2.4" stroke-linecap="round"/>`;
                default:
                    return brow(39, 0, 0) + brow(61, 0, 0)
                        + eye(39) + eye(61)
                        + `<path d="M 42 58 Q 50 63 58 58" fill="none" stroke="${ink}" stroke-width="2.2" stroke-linecap="round"/>`;
            }
        },

        /**
         * Arms follow the gesture; otherwise they rest at the sides.
         */
        arms() {
            const { ink, skin } = this.palette;
            const arm = (d, hand) => `<path d="${d}" fill="none" stroke="${ink}" stroke-width="6" stroke-linecap="round"/>`
                + `<path d="${d}" fill="none" stroke="${skin}" stroke-width="3.4" stroke-linecap="round"/>`
                + `<circle cx="${hand[0]}" cy="${hand[1]}" r="4.6" fill="${skin}" stroke="${ink}" stroke-width="1.8"/>`;

            switch (this.gesture) {
                case 'wave':
                    return arm('M 30 92 Q 20 86 18 74', [18, 72]) + arm('M 70 92 Q 78 100 76 110', [76, 112]);
                case 'laugh':
                    return arm('M 30 92 Q 22 100 30 106', [31, 108]) + arm('M 70 92 Q 78 100 70 106', [69, 108]);
                case 'think':
                    return arm('M 30 92 Q 24 102 30 108', [31, 110]) + arm('M 70 92 Q 66 76 58 66', [56, 64]);
                case 'shout':
                    return arm('M 30 92 Q 18 84 16 70', [16, 68]) + arm('M 70 92 Q 82 84 84 70', [84, 68]);
                case 'whisper':
                    return arm('M 30 92 Q 24 102 30 108', [31, 110]) + arm('M 70 92 Q 72 78 64 62', [62, 60]);
                default:
                    return arm('M 30 92 Q 24 102 26 112', [26, 114]) + arm('M 70 92 Q 76 102 74 112', [74, 114]);
            }
        },

        svgContent() {
            // A mask or a visor covers the face, so it wears no expression
            const face = hidesFace(this.resolved) ? '' : this.face;
            const { behind, body, head, over } = this.parts;

            const figure = `<g>${behind}${body}${this.arms}${head}${face}${over}</g>`;

            // Facing is a mirror around the middle of the drawing
            const flip = this.facing < 0 ? '<g transform="translate(100,0) scale(-1,1)">' : '<g>';
            const dimmed = this.speaking ? '' : ' opacity="0.72"';

            return `<g${dimmed}>${flip}${figure}</g></g>`;
        },
    },
};
</script>
