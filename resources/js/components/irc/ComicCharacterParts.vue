<template>
    <g v-html="svgContent" />
</template>

<script>
/**
 * A comic chat character, drawn from parts: a body, a head that differs per
 * character, a face that carries the emotion, and arms that carry the
 * gesture. Characters can face left or right so two of them talk to each
 * other across a panel.
 *
 * Original artwork, drawn for this site — in the spirit of the 1996 comic
 * chat clients, not copied from them.
 *
 * The drawing area is 100 × 140: head around (50, 46), feet at 138.
 */
export default {
    name: 'ComicCharacter',
    props: {
        character: { type: String, default: 'cat' },
        emotion: { type: String, default: 'normal' },
        gesture: { type: String, default: null },
        // 1 faces right, -1 faces left
        facing: { type: Number, default: 1 },
        color: { type: Number, default: 200 },
        // The one talking stands out; the others listen
        speaking: { type: Boolean, default: true },
    },
    computed: {
        ink() {
            return '#2b2b2b';
        },
        body() {
            return `hsl(${this.color}, 52%, 72%)`;
        },
        bodyDark() {
            return `hsl(${this.color}, 52%, 58%)`;
        },
        bodyLight() {
            return `hsl(${this.color}, 60%, 86%)`;
        },

        /**
         * Eyes, brows and mouth per emotion. Everything is drawn around the
         * face centre so each character can reuse it.
         */
        face() {
            const ink = this.ink;
            const eye = (x, open = 1) => `<ellipse cx="${x}" cy="46" rx="3.4" ry="${3.4 * open}" fill="${ink}"/>`
                + `<circle cx="${x + 1}" cy="44.6" r="1.1" fill="#fff"/>`;
            const brow = (x, dy, rot) => `<path d="M ${x - 5} ${38 + dy} Q ${x} ${35 + dy} ${x + 5} ${38 + dy}"`
                + ` fill="none" stroke="${ink}" stroke-width="2" stroke-linecap="round" transform="rotate(${rot} ${x} ${38 + dy})"/>`;

            switch (this.emotion) {
                case 'happy':
                    return `<path d="M 34 46 Q 39 41 44 46" fill="none" stroke="${ink}" stroke-width="2.4" stroke-linecap="round"/>`
                        + `<path d="M 56 46 Q 61 41 66 46" fill="none" stroke="${ink}" stroke-width="2.4" stroke-linecap="round"/>`
                        + `<path d="M 38 56 Q 50 66 62 56" fill="none" stroke="${ink}" stroke-width="2.4" stroke-linecap="round"/>`
                        + `<circle cx="32" cy="54" r="3.5" fill="hsl(${this.color}, 70%, 70%)" opacity="0.7"/>`
                        + `<circle cx="68" cy="54" r="3.5" fill="hsl(${this.color}, 70%, 70%)" opacity="0.7"/>`;
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
            const ink = this.ink;
            const skin = this.body;
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

        /**
         * Torso, legs and feet — the same build under every head.
         */
        torso() {
            const ink = this.ink;

            if (this.character === 'robot') {
                return `<rect x="30" y="74" width="40" height="44" rx="6" fill="${this.body}" stroke="${ink}" stroke-width="2.4"/>`
                    + `<rect x="38" y="84" width="24" height="14" rx="3" fill="${this.bodyLight}" stroke="${ink}" stroke-width="1.6"/>`
                    + `<circle cx="44" cy="91" r="2.2" fill="#e57373"/><circle cx="50" cy="91" r="2.2" fill="#fff176"/><circle cx="56" cy="91" r="2.2" fill="#81c784"/>`
                    + `<rect x="36" y="118" width="12" height="16" rx="3" fill="${this.bodyDark}" stroke="${ink}" stroke-width="2"/>`
                    + `<rect x="52" y="118" width="12" height="16" rx="3" fill="${this.bodyDark}" stroke="${ink}" stroke-width="2"/>`;
            }

            const robe = this.character === 'wizard';
            const body = robe
                ? `<path d="M 50 70 L 68 76 L 76 132 L 24 132 L 32 76 Z" fill="${this.body}" stroke="${ink}" stroke-width="2.4"/>`
                : `<path d="M 50 70 Q 70 74 70 96 L 68 118 L 32 118 L 30 96 Q 30 74 50 70 Z" fill="${this.body}" stroke="${ink}" stroke-width="2.4"/>`;

            const legs = robe
                ? ''
                : `<path d="M 38 118 L 37 132" stroke="${ink}" stroke-width="6" stroke-linecap="round"/>`
                    + `<path d="M 62 118 L 63 132" stroke="${ink}" stroke-width="6" stroke-linecap="round"/>`
                    + `<ellipse cx="35" cy="135" rx="8" ry="4" fill="${this.bodyDark}" stroke="${ink}" stroke-width="1.8"/>`
                    + `<ellipse cx="65" cy="135" rx="8" ry="4" fill="${this.bodyDark}" stroke="${ink}" stroke-width="1.8"/>`;

            return body + legs;
        },

        /**
         * The head, which is what tells the characters apart.
         */
        head() {
            const ink = this.ink;
            const skin = this.body;
            const dark = this.bodyDark;
            const round = `<circle cx="50" cy="46" r="24" fill="${skin}" stroke="${ink}" stroke-width="2.4"/>`;

            switch (this.character) {
                case 'cat':
                    return `<path d="M 30 30 L 27 10 L 44 22 Z" fill="${skin}" stroke="${ink}" stroke-width="2.2"/>`
                        + `<path d="M 70 30 L 73 10 L 56 22 Z" fill="${skin}" stroke="${ink}" stroke-width="2.2"/>`
                        + `<path d="M 31.5 27 L 30 15 L 40 22 Z" fill="${dark}"/>`
                        + `<path d="M 68.5 27 L 70 15 L 60 22 Z" fill="${dark}"/>`
                        + round
                        + `<path d="M 26 48 L 14 45 M 26 52 L 14 53 M 74 48 L 86 45 M 74 52 L 86 53" stroke="${ink}" stroke-width="1.2" stroke-linecap="round" opacity="0.6"/>`
                        + `<path d="M 47 53 L 53 53 L 50 57 Z" fill="${dark}" stroke="${ink}" stroke-width="1.2"/>`;
                case 'dog':
                    return round
                        + `<path d="M 28 34 Q 14 40 20 62 Q 30 62 32 46 Z" fill="${dark}" stroke="${ink}" stroke-width="2.2"/>`
                        + `<path d="M 72 34 Q 86 40 80 62 Q 70 62 68 46 Z" fill="${dark}" stroke="${ink}" stroke-width="2.2"/>`
                        + `<ellipse cx="50" cy="56" rx="9" ry="7" fill="${this.bodyLight}" stroke="${ink}" stroke-width="1.6"/>`
                        + `<ellipse cx="50" cy="52" rx="3.6" ry="2.8" fill="${ink}"/>`;
                case 'robot':
                    return `<rect x="44" y="62" width="12" height="14" fill="${dark}" stroke="${ink}" stroke-width="1.8"/>`
                        + `<rect x="28" y="26" width="44" height="40" rx="8" fill="${skin}" stroke="${ink}" stroke-width="2.4"/>`
                        + `<line x1="50" y1="26" x2="50" y2="14" stroke="${ink}" stroke-width="2.4"/>`
                        + `<circle cx="50" cy="11" r="4.5" fill="#ef5350" stroke="${ink}" stroke-width="1.8"/>`
                        + `<rect x="22" y="40" width="6" height="14" rx="2" fill="${dark}" stroke="${ink}" stroke-width="1.8"/>`
                        + `<rect x="72" y="40" width="6" height="14" rx="2" fill="${dark}" stroke="${ink}" stroke-width="1.8"/>`;
                case 'alien':
                    return `<path d="M 50 20 Q 78 24 74 50 Q 70 70 50 72 Q 30 70 26 50 Q 22 24 50 20 Z" fill="${skin}" stroke="${ink}" stroke-width="2.4"/>`
                        + `<path d="M 36 22 L 30 8 M 64 22 L 70 8" stroke="${ink}" stroke-width="2.2" stroke-linecap="round"/>`
                        + `<circle cx="30" cy="7" r="3.6" fill="#9ccc65" stroke="${ink}" stroke-width="1.6"/>`
                        + `<circle cx="70" cy="7" r="3.6" fill="#9ccc65" stroke="${ink}" stroke-width="1.6"/>`;
                case 'wizard':
                    return round
                        + `<path d="M 50 2 Q 58 20 74 34 L 26 34 Q 42 20 50 2 Z" fill="#4527a0" stroke="${ink}" stroke-width="2.4"/>`
                        + `<ellipse cx="50" cy="34" rx="26" ry="5" fill="#5e35b1" stroke="${ink}" stroke-width="2.2"/>`
                        + `<path d="M 50 12 l 2 5 l 5 1 l -4 4 l 1 5 l -4 -3 l -4 3 l 1 -5 l -4 -4 l 5 -1 z" fill="#ffd54f"/>`
                        + `<path d="M 36 62 Q 40 84 50 88 Q 60 84 64 62 Q 50 70 36 62 Z" fill="#eceff1" stroke="#b0bec5" stroke-width="1.6"/>`;
                case 'ninja':
                    return `<circle cx="50" cy="46" r="24" fill="#37474f" stroke="${ink}" stroke-width="2.4"/>`
                        + `<path d="M 26 40 Q 50 34 74 40 L 74 52 Q 50 46 26 52 Z" fill="#263238"/>`
                        + `<path d="M 74 42 Q 88 46 92 58" fill="none" stroke="#263238" stroke-width="4" stroke-linecap="round"/>`;
                case 'pirate':
                    return round
                        + `<path d="M 24 36 Q 50 12 76 36 Q 50 30 24 36 Z" fill="#c62828" stroke="${ink}" stroke-width="2.2"/>`
                        + `<path d="M 24 36 Q 50 44 76 36 L 76 39 Q 50 47 24 39 Z" fill="#8e0000"/>`;
                case 'knight':
                    return `<path d="M 30 30 Q 50 22 70 30 L 70 60 Q 50 72 30 60 Z" fill="#b0bec5" stroke="${ink}" stroke-width="2.4"/>`
                        + `<rect x="34" y="42" width="32" height="7" rx="2" fill="#37474f"/>`
                        + `<circle cx="42" cy="45.5" r="1.8" fill="#eceff1"/><circle cx="58" cy="45.5" r="1.8" fill="#eceff1"/>`
                        + `<path d="M 50 22 Q 62 6 74 22 Q 62 16 50 24 Z" fill="#e53935" stroke="${ink}" stroke-width="1.8"/>`
                        + `<path d="M 34 56 L 66 56" stroke="${ink}" stroke-width="1.4" opacity="0.5"/>`;
                default:
                    return round;
            }
        },

        /**
         * What is worn over the face, and so is drawn after it.
         */
        overlay() {
            if (this.character === 'ninja') {
                return `<circle cx="41" cy="46" r="3.2" fill="#fff"/><circle cx="59" cy="46" r="3.2" fill="#fff"/>`;
            }

            if (this.character === 'pirate') {
                return `<path d="M 32 39 L 20 33" stroke="${this.ink}" stroke-width="2" stroke-linecap="round"/>`
                    + `<circle cx="39" cy="45" r="7.5" fill="${this.ink}"/>`;
            }

            return '';
        },

        svgContent() {
            // A helmet or a mask hides the face; the rest wear their emotion
            const facePainted = !['knight', 'ninja'].includes(this.character);
            const faceParts = facePainted ? this.face : '';

            const parts = `<g>${this.torso}${this.arms}${this.head}${faceParts}${this.overlay}</g>`;

            // Facing is a mirror around the middle of the drawing
            const flip = this.facing < 0 ? '<g transform="translate(100,0) scale(-1,1)">' : '<g>';
            const dimmed = this.speaking ? '' : ' opacity="0.72"';

            return `<g${dimmed}>${flip}${parts}</g></g>`;
        },
    },
};
</script>
