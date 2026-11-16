import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ComicCharacter from '@/components/irc/ComicCharacterParts.vue';

const CHARACTERS = ['cat', 'dog', 'robot', 'alien', 'wizard', 'ninja', 'pirate', 'knight'];
const EMOTIONS = ['normal', 'happy', 'sad', 'angry', 'surprised', 'confused', 'excited'];
const GESTURES = [null, 'wave', 'laugh', 'think', 'shout', 'whisper'];

const draw = (props = {}) => mount(ComicCharacter, {
    props,
    attachTo: document.createElementNS('http://www.w3.org/2000/svg', 'svg'),
});

describe('ComicCharacter', () => {
    it('draws every character in every mood without falling apart', () => {
        for (const character of CHARACTERS) {
            for (const emotion of EMOTIONS) {
                const w = draw({ character, emotion });
                const markup = w.html();

                // Balanced groups, and something actually drawn
                expect(markup.match(/<g[\s>]/g).length).toBe(markup.match(/<\/g>/g).length);
                expect(w.findAll('path, circle, ellipse, rect').length).toBeGreaterThan(3);
            }
        }
    });

    it('gives every gesture its own arms', () => {
        const arms = GESTURES.map(gesture => draw({ gesture }).html());

        expect(new Set(arms).size).toBe(GESTURES.length);
    });

    it('mirrors the drawing so two characters can face each other', () => {
        expect(draw({ facing: 1 }).html()).not.toContain('scale(-1,1)');
        expect(draw({ facing: -1 }).html()).toContain('scale(-1,1)');
    });

    it('holds the listener back so the speaker reads first', () => {
        expect(draw({ speaking: true }).html()).not.toContain('opacity="0.72"');
        expect(draw({ speaking: false }).html()).toContain('opacity="0.72"');
    });

    it('keeps a masked face blank but still leaves eyes to read', () => {
        const ninja = draw({ character: 'ninja', emotion: 'happy' }).html();

        // No cheeks or smile — the mask covers them — but the eyes show
        expect(ninja).not.toContain('Q 38 56 62 56');
        expect(ninja.match(/circle/g).length).toBeGreaterThan(1);
    });

    it('paints the eye patch over the pirate, not under it', () => {
        const pirate = draw({ character: 'pirate' }).html();

        // The patch has to come after the eye it covers
        expect(pirate.indexOf('r="7.5"')).toBeGreaterThan(pirate.indexOf('cy="46"'));
    });

    it('colours the character from the hue it is given', () => {
        expect(draw({ color: 120 }).html()).toContain('hsl(120');
    });
});
