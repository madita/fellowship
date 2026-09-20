import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ComicChatView from '@/components/irc/ComicChatView.vue';

let nextId = 1;

const line = (nick, message, extra = {}) => ({
    id: nextId++,
    from_nick: nick,
    message,
    type: 'message',
    sent_at: '2026-09-20T10:00:00Z',
    ...extra,
});

const view = (messages, props = {}) => mount(ComicChatView, {
    props: { messages, myNick: 'frodo', ...props },
});

describe('ComicChatView', () => {
    it('draws nothing but the welcome panel without messages', () => {
        const w = view([]);

        expect(w.findAll('.comic-panel')).toHaveLength(0);
        expect(w.find('.empty-comic').exists()).toBe(true);
    });

    it('breaks the strip after three lines', () => {
        const w = view([
            line('frodo', 'one'),
            line('frodo', 'two'),
            line('frodo', 'three'),
            line('frodo', 'four'),
        ]);

        const panels = w.findAll('.comic-panel');
        expect(panels).toHaveLength(2);
        expect(panels[0].findAll('.bubble-row')).toHaveLength(3);
        expect(panels[1].findAll('.bubble-row')).toHaveLength(1);
    });

    it('opens a new panel rather than crowding in a third speaker', () => {
        const w = view([
            line('frodo', 'hi'),
            line('sam', 'hello'),
            line('merry', 'hey'),
        ]);

        const panels = w.findAll('.comic-panel');
        expect(panels).toHaveLength(2);
        expect(panels[0].findAll('.character-container')).toHaveLength(2);
        expect(panels[1].findAll('.character-container')).toHaveLength(1);
    });

    it('sits the second speaker on the other side of the panel', () => {
        const w = view([line('frodo', 'hi'), line('sam', 'hello')]);

        const rows = w.findAll('.bubble-row');
        expect(rows[0].classes()).toContain('from-left');
        expect(rows[1].classes()).toContain('from-right');

        const standing = w.findAll('.character-container');
        expect(standing[0].classes()).toContain('stands-left');
        expect(standing[1].classes()).toContain('stands-right');
    });

    it('reads the balloon shape out of the text', () => {
        const w = view([
            line('frodo', '(what is for lunch)'),
            line('frodo', 'WHERE IS THE RING'),
            line('frodo', 'sings to himself', { type: 'action' }),
        ]);

        const bubbles = w.findAll('.speech-bubble');
        expect(bubbles[0].classes()).toContain('bubble-thought');
        expect(bubbles[1].classes()).toContain('bubble-shout');
        expect(bubbles[2].classes()).toContain('bubble-action');
    });

    it('only the last speaker of a panel is in the foreground', () => {
        const w = view([line('frodo', 'hi'), line('sam', 'hello')]);

        const standing = w.findAll('.character-container');
        expect(standing[0].classes()).toContain('is-listening');
        expect(standing[1].classes()).not.toContain('is-listening');
    });

    it('marks an action as narration, not speech', () => {
        const w = view([line('frodo', 'waves', { type: 'action' })]);

        expect(w.find('.bubble-text').text()).toBe('* frodo waves');
    });

    it('draws everyone as the character they picked', () => {
        const w = view(
            [line('frodo', 'hi'), line('sam', 'hello')],
            { character: 'wizard', characters: { sam: 'knight' } },
        );

        // The knight wears a visor, the wizard a hat with a star
        const svgs = w.findAll('.character-avatar').map(svg => svg.html());
        expect(svgs[0]).toContain('#4527a0');
        expect(svgs[1]).toContain('#b0bec5');
    });

    it('falls back to a stand-in for a nickname with no choice on file', () => {
        const chosen = view([line('gandalf', 'hi')], { characters: { gandalf: 'robot' } });
        const standIn = view([line('gandalf', 'hi')], { characters: {} });

        expect(chosen.find('.character-avatar').html())
            .not.toBe(standIn.find('.character-avatar').html());
    });

    it('starts a new panel when a speaker changes their face', () => {
        const w = view([
            line('frodo', 'hello', { emotion: 'happy' }),
            line('frodo', 'wait', { emotion: 'angry' }),
        ]);

        // A character stands once per panel and holds one expression
        expect(w.findAll('.comic-panel')).toHaveLength(2);
    });

    it('starts a new panel when a speaker changes their gesture', () => {
        const w = view([
            line('frodo', 'hello', { gesture: 'wave' }),
            line('frodo', 'over here', { gesture: 'whisper' }),
        ]);

        expect(w.findAll('.comic-panel')).toHaveLength(2);
    });

    it('keeps one panel while the expression holds', () => {
        const w = view([
            line('frodo', 'one', { emotion: 'happy' }),
            line('frodo', 'two', { emotion: 'happy' }),
        ]);

        expect(w.findAll('.comic-panel')).toHaveLength(1);
        expect(w.findAll('.bubble-row')).toHaveLength(2);
    });

    it('leaves joins and parts out of the strip', () => {
        const w = view([
            line('frodo', 'hi'),
            line('sam', 'joined', { type: 'join' }),
        ]);

        expect(w.findAll('.bubble-row')).toHaveLength(1);
        expect(w.findAll('.character-container')).toHaveLength(1);
    });
});
