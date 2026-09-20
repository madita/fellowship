import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ExpressionWheel from '@/components/irc/ExpressionWheel.vue';

const SIZE = 200;
const CENTRE = SIZE / 2;

const wheel = (props = {}) => mount(ExpressionWheel, {
    props: { size: SIZE, ...props },
});

// Where a point sits relative to the middle, in wheel coordinates
const at = (w, angleDegrees, radius) => {
    const radians = (angleDegrees - 90) * (Math.PI / 180);

    return {
        x: CENTRE + Math.cos(radians) * radius,
        y: CENTRE + Math.sin(radians) * radius,
    };
};

describe('the mood wheel', () => {
    it('puts a face for every mood around the rim, neutral in the middle', () => {
        const w = wheel();

        // Six on the rim; the neutral face is the preview in the centre
        expect(w.findAll('.wheel-face-button')).toHaveLength(6);
        expect(w.find('.wheel-preview').exists()).toBe(true);
    });

    it('reads the mood a point on the wheel is aimed at', () => {
        const w = wheel();
        const read = (angle, radius) => {
            const { x, y } = at(w, angle, radius);

            return w.vm.emotionAt(x, y);
        };

        // Clockwise from the top
        expect(read(0, 70)).toBe('happy');
        expect(read(60, 70)).toBe('excited');
        expect(read(120, 70)).toBe('surprised');
        expect(read(180, 70)).toBe('confused');
        expect(read(240, 70)).toBe('sad');
        expect(read(300, 70)).toBe('angry');
    });

    it('rounds to the nearest face rather than demanding a dead hit', () => {
        const w = wheel();
        const { x, y } = at(w, 15, 70);

        expect(w.vm.emotionAt(x, y)).toBe('happy');
    });

    it('treats the middle as the neutral face', () => {
        const w = wheel();

        expect(w.vm.emotionAt(CENTRE, CENTRE)).toBe('normal');
        expect(w.vm.emotionAt(CENTRE + 4, CENTRE - 3)).toBe('normal');
    });

    it('reports the mood that was picked', async () => {
        const w = wheel({ modelValue: 'normal' });

        await w.findAll('.wheel-face-button')[0].trigger('click');

        expect(w.emitted('update:modelValue')[0]).toEqual(['happy']);
    });

    it('says nothing when the mood has not changed', async () => {
        const w = wheel({ modelValue: 'happy' });

        await w.findAll('.wheel-face-button')[0].trigger('click');

        expect(w.emitted('update:modelValue')).toBeUndefined();
    });

    it('goes back to neutral through the middle', async () => {
        const w = wheel({ modelValue: 'angry' });

        await w.find('.wheel-preview').trigger('click');

        expect(w.emitted('update:modelValue')[0]).toEqual(['normal']);
    });

    it('rests the needle in the middle while neutral, and out at the mood otherwise', () => {
        expect(wheel({ modelValue: 'normal' }).vm.knob).toEqual({ x: CENTRE, y: CENTRE });

        const picked = wheel({ modelValue: 'happy' }).vm.knob;
        expect(picked.x).toBeCloseTo(CENTRE, 5);
        expect(picked.y).toBeLessThan(CENTRE);
    });

    it('marks the picked face so it stands out', () => {
        const w = wheel({ modelValue: 'sad' });
        const picked = w.findAll('.wheel-face-button').filter(b => b.classes().includes('is-picked'));

        expect(picked).toHaveLength(1);
        expect(picked[0].attributes('aria-pressed')).toBe('true');
    });

    it('wears the gesture on the character in the middle', () => {
        const waving = wheel({ gesture: 'wave' }).find('.preview-figure').html();
        const idle = wheel({ gesture: null }).find('.preview-figure').html();

        expect(waving).not.toBe(idle);
    });
});
