import { describe, it, expect, vi } from 'vitest';

// The client pulls in heavy child components — stub them out, we only
// exercise the deep-link method.
vi.mock('@/components/irc/IrcConnectionDialog.vue', () => ({ default: { name: 'IrcConnectionDialog' } }));
vi.mock('@/components/irc/IrcJoinDialog.vue', () => ({ default: { name: 'IrcJoinDialog' } }));
vi.mock('@/components/irc/ComicChatView.vue', () => ({ default: { name: 'ComicChatView' } }));
vi.mock('@/components/irc/ComicCharacterSelector.vue', () => ({ default: { name: 'ComicCharacterSelector' } }));
vi.mock('axios', () => ({ default: { get: vi.fn(() => Promise.resolve({ data: [] })), post: vi.fn() } }));

import IrcClient from '@/components/irc/IrcClient.vue';

// Build a minimal `this` for the Options API method under test.
const makeContext = ({ channelQuery, connections = [], activeChannel = null } = {}) => ({
    $route: { query: channelQuery === undefined ? {} : { channel: channelQuery } },
    connections,
    activeChannel,
    selectChannel: vi.fn(),
});

const callDeepLink = (ctx) => IrcClient.methods.openChannelFromRoute.call(ctx);

describe('IrcClient ?channel= deep link', () => {
    const connections = [
        { id: 1, channels: [{ id: 4, name: '#alpha' }, { id: 5, name: '#beta' }] },
        { id: 2, channels: [{ id: 9, name: '#gamma' }] },
    ];

    it('selects the channel matching the query across all connections', () => {
        const ctx = makeContext({ channelQuery: '9', connections });
        callDeepLink(ctx);
        expect(ctx.selectChannel).toHaveBeenCalledWith(connections[1].channels[0]);
    });

    it('coerces the (string) query parameter to the numeric channel id', () => {
        const ctx = makeContext({ channelQuery: '5', connections });
        callDeepLink(ctx);
        expect(ctx.selectChannel).toHaveBeenCalledWith(connections[0].channels[1]);
    });

    it('does nothing without a channel query', () => {
        const ctx = makeContext({ connections });
        callDeepLink(ctx);
        expect(ctx.selectChannel).not.toHaveBeenCalled();
    });

    it('does nothing when the channel is already active', () => {
        const ctx = makeContext({
            channelQuery: '5',
            connections,
            activeChannel: { id: 5 },
        });
        callDeepLink(ctx);
        expect(ctx.selectChannel).not.toHaveBeenCalled();
    });

    it('does nothing for an unknown channel id', () => {
        const ctx = makeContext({ channelQuery: '999', connections });
        callDeepLink(ctx);
        expect(ctx.selectChannel).not.toHaveBeenCalled();
    });

    it('ignores a non-numeric query value', () => {
        const ctx = makeContext({ channelQuery: 'abc', connections });
        callDeepLink(ctx);
        expect(ctx.selectChannel).not.toHaveBeenCalled();
    });

    it('handles connections without a channels array', () => {
        const ctx = makeContext({ channelQuery: '5', connections: [{ id: 1 }] });
        expect(() => callDeepLink(ctx)).not.toThrow();
        expect(ctx.selectChannel).not.toHaveBeenCalled();
    });

    it('re-checks the route when the query changes (watcher wired)', () => {
        expect(IrcClient.watch['$route.query.channel']).toBeTypeOf('function');
    });
});
