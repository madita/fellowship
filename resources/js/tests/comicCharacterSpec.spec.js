import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import ComicCharacter from '@/components/irc/ComicCharacterParts.vue';
import {
    DEFAULT_SPEC,
    PARTS,
    PART_KEYS,
    PRESETS,
    drawParts,
    hidesFace,
    normaliseSpec,
    paletteFor,
    registerCharacters,
    specForKey,
} from '@/utils/comicCharacter.js';

const draw = (props = {}) => mount(ComicCharacter, {
    props,
    attachTo: document.createElementNS('http://www.w3.org/2000/svg', 'svg'),
});

describe('what a character is made of', () => {
    beforeEach(() => registerCharacters([]));

    it('describes every character that ships with the site in parts', () => {
        for (const [key, preset] of Object.entries(PRESETS)) {
            const spec = normaliseSpec(preset);

            for (const part of PART_KEYS) {
                expect(PARTS[part].options[spec[part]], `${key}.${part}`).toBeDefined();
            }
        }
    });

    it('falls back to the default for a part it does not know', () => {
        const spec = normaliseSpec({ head: 'pyramid', hat: 'sombrero', tone: 'neon' });

        expect(spec.head).toBe(DEFAULT_SPEC.head);
        expect(spec.hat).toBe(DEFAULT_SPEC.hat);
        expect(spec.tone).toBe(DEFAULT_SPEC.tone);
    });

    it('keeps the hue on the wheel', () => {
        expect(normaliseSpec({ hue: 400 }).hue).toBe(40);
        expect(normaliseSpec({ hue: -30 }).hue).toBe(330);
        expect(normaliseSpec({ hue: 'purple' }).hue).toBe(DEFAULT_SPEC.hue);
    });

    it('draws the head once, not twice', () => {
        const { head } = drawParts(PRESETS.cat, paletteFor(PRESETS.cat));

        expect(head.match(/<circle cx="50" cy="46" r="24"/g)).toHaveLength(1);
    });

    it('puts the ears behind the head and the accessory over the face', () => {
        const parts = drawParts(PRESETS.cat, paletteFor(PRESETS.cat));

        expect(parts.behind).toContain('path');
        expect(drawParts(PRESETS.pirate, paletteFor(PRESETS.pirate)).over).toContain('circle');
    });

    it('knows which face covers hide the expression', () => {
        expect(hidesFace(PRESETS.ninja)).toBe(true);
        expect(hidesFace(PRESETS.knight)).toBe(true);
        expect(hidesFace(PRESETS.cat)).toBe(false);
    });

    it('mixes the colour from the hue and the tone', () => {
        expect(paletteFor({ hue: 120, tone: 'normal' }).skin).toBe('hsl(120, 52%, 72%)');
        expect(paletteFor({ hue: 120, tone: 'dark' }).skin).toBe('hsl(120, 14%, 26%)');
    });
});

describe('drawing a character by its key', () => {
    beforeEach(() => registerCharacters([]));

    it('uses the built-in characters until something says otherwise', () => {
        expect(specForKey('wizard').hat).toBe('wizard');
        expect(specForKey('nobody')).toBeNull();
    });

    it('draws a character an admin built', () => {
        registerCharacters([
            { key: 'royal-bird', name: 'Royal Bird', spec: { ...DEFAULT_SPEC, hat: 'crown', snout: 'beak' } },
        ]);

        expect(specForKey('royal-bird').hat).toBe('crown');

        // The crown's gold and the beak's yellow give it away
        const markup = draw({ character: 'royal-bird' }).html();
        expect(markup).toContain('#ffd54f');
        expect(markup).toContain('#fbc02d');
    });

    it('draws an unsaved spec straight from the creator', () => {
        const markup = draw({ spec: { ...DEFAULT_SPEC, hat: 'crown' } }).html();

        expect(markup).toContain('#ffd54f');
    });

    it('falls back to something drawable for a character that is gone', () => {
        const markup = draw({ character: 'deleted-one' }).html();

        expect(markup).toContain('<circle');
    });

    it('takes its own colour unless one is given', () => {
        expect(draw({ character: 'alien' }).html()).toContain('hsl(120');
        expect(draw({ character: 'alien', color: 300 }).html()).toContain('hsl(300');
    });
});
