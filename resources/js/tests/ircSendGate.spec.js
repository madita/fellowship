import { describe, it, expect, vi } from 'vitest';
import IrcPage from '@/pages/irc/IrcPage.vue';

vi.mock('axios');

const { canSend, notConnectedHint } = IrcPage.computed;

// The computed properties are read against a stand-in component, so the
// rule can be checked without mounting the whole client.
const client = (state = {}) => ({
    $t: key => key,
    chatAvailable: true,
    currentConnection: null,
    ...state,
});

const sendable = (state) => canSend.call(client(state));
const hint = (state) => notConnectedHint.call(client(state));

describe('the IRC input bar', () => {
    it('opens on a live connection', () => {
        expect(sendable({ currentConnection: { status: 'connected' } })).toBe(true);
    });

    it('stays closed while the connection is down', () => {
        expect(sendable({ currentConnection: { status: 'disconnected' } })).toBe(false);
        expect(sendable({ currentConnection: { status: 'connecting' } })).toBe(false);
        expect(sendable({ currentConnection: { status: 'error' } })).toBe(false);
    });

    it('stays closed with no connection selected at all', () => {
        expect(sendable({ currentConnection: null })).toBe(false);
    });

    it('stays closed while the daemon is down, connection or not', () => {
        expect(sendable({ chatAvailable: false, currentConnection: { status: 'connected' } })).toBe(false);
    });

    it('says which of the states it is in', () => {
        expect(hint({ currentConnection: { status: 'connecting' } })).toBe('irc.client.connecting');
        expect(hint({ currentConnection: { status: 'error' } })).toBe('irc.client.connectionError');
        expect(hint({ currentConnection: { status: 'disconnected' } })).toBe('irc.client.notConnected');
        expect(hint({ currentConnection: null })).toBe('irc.client.notConnected');
    });
});
