import { describe, it, expect } from 'vitest';
import { inferEmotion, inferBubble, isShouting, expressionOf } from '@/utils/comicExpression.js';

describe('comic expressions', () => {
    it('reads the face from emoticons', () => {
        expect(inferEmotion('hello there :)')).toBe('happy');
        expect(inferEmotion('that is sad :(')).toBe('sad');
        expect(inferEmotion('wow, really?')).toBe('surprised');
        expect(inferEmotion('hmm, not sure')).toBe('confused');
    });

    it('falls back to punctuation', () => {
        expect(inferEmotion('are you there?')).toBe('confused');
        expect(inferEmotion('this is great!')).toBe('excited');
        expect(inferEmotion('just a normal line')).toBe('normal');
    });

    it('knows shouting from capitals and exclamation', () => {
        expect(isShouting('STOP RIGHT THERE')).toBe(true);
        expect(isShouting('really?!!')).toBe(true);
        expect(isShouting('Hello there')).toBe(false);
        // Short words in caps are not shouting
        expect(isShouting('OK')).toBe(false);
    });

    it('picks the balloon shape', () => {
        expect(inferBubble('(wondering about lunch)')).toBe('thought');
        expect(inferBubble('WHAT IS GOING ON')).toBe('shout');
        expect(inferBubble('psst, over here')).toBe('whisper');
        expect(inferBubble('waves', { type: 'action' })).toBe('action');
        expect(inferBubble('a plain line')).toBe('speech');
    });

    it('lets the member overrule the text', () => {
        const chosen = expressionOf({ message: 'hello :)', emotion: 'angry' });
        expect(chosen.emotion).toBe('angry');

        const inferred = expressionOf({ message: 'hello :)', emotion: 'normal' });
        expect(inferred.emotion).toBe('happy');
    });

    it('turns a gesture into the matching balloon', () => {
        expect(expressionOf({ message: 'lunch time', gesture: 'think' }).bubble).toBe('thought');
        expect(expressionOf({ message: 'over here', gesture: 'whisper' }).bubble).toBe('whisper');
        expect(expressionOf({ message: 'hi', gesture: 'wave' }).gesture).toBe('wave');
        expect(expressionOf({ message: 'hi', gesture: 'none' }).gesture).toBeNull();
    });

    it('drops values it does not know', () => {
        const odd = expressionOf({ message: 'hi', emotion: 'smug', gesture: 'pirouette', bubble_type: 'banner' });

        expect(odd.emotion).toBe('normal');
        expect(odd.gesture).toBeNull();
        expect(odd.bubble).toBe('speech');
    });

    it('survives an empty message', () => {
        expect(expressionOf({}).emotion).toBe('normal');
        expect(expressionOf({}).bubble).toBe('speech');
    });
});
