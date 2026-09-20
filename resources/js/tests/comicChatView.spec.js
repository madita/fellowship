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

const view = (messages) => mount(ComicChatView, { props: { messages, myNick: 'frodo' } });

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

    it('leaves joins and parts out of the strip', () => {
        const w = view([
            line('frodo', 'hi'),
            line('sam', 'joined', { type: 'join' }),
        ]);

        expect(w.findAll('.bubble-row')).toHaveLength(1);
        expect(w.findAll('.character-container')).toHaveLength(1);
    });
});
