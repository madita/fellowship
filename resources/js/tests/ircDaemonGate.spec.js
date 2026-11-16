import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import axios from 'axios';
import { useIrcStore } from '@/store/ircStore.js';
import { useSettingsStore } from '@/store/settingStore.js';
import { isFeatureAvailable } from '@/configs/features.js';
import permission from '@/utils/permission.js';

vi.mock('axios');

const ircItem = { feature: 'irc', text: 'IRC', link: '/irc' };

// The suite's jsdom only stubs part of localStorage, so give the store a
// real one — remembering the last reading is a thing worth testing.
const fakeStorage = () => {
    const values = new Map();

    return {
        getItem: key => (values.has(key) ? values.get(key) : null),
        setItem: (key, value) => values.set(key, String(value)),
        removeItem: key => values.delete(key),
        clear: () => values.clear(),
    };
};

describe('the IRC chat needs a running daemon', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.stubGlobal('localStorage', fakeStorage());
        vi.clearAllMocks();
    });

    it('is closed until something says the daemon is up', () => {
        const irc = useIrcStore();

        expect(irc.daemonRunning).toBeNull();
        expect(irc.chatAvailable).toBe(false);
        expect(isFeatureAvailable('irc')).toBe(false);
    });

    it('opens once the daemon answers for itself', async () => {
        axios.get.mockResolvedValue({ data: { daemon_running: true } });

        const irc = useIrcStore();
        await irc.fetchStatus();

        expect(axios.get).toHaveBeenCalledWith('/api/irc/status');
        expect(irc.chatAvailable).toBe(true);
        expect(isFeatureAvailable('irc')).toBe(true);
    });

    it('stays closed when the status cannot be read at all', async () => {
        axios.get.mockRejectedValue(new Error('network'));

        const irc = useIrcStore();
        await irc.fetchStatus();

        expect(irc.chatAvailable).toBe(false);
    });

    it('reuses a fresh reading instead of asking again', async () => {
        axios.get.mockResolvedValue({ data: { daemon_running: true } });

        const irc = useIrcStore();
        await irc.fetchStatus();
        await irc.fetchStatus();

        expect(axios.get).toHaveBeenCalledTimes(1);

        await irc.fetchStatus({ force: true });
        expect(axios.get).toHaveBeenCalledTimes(2);
    });

    it('asks once for callers arriving together', async () => {
        axios.get.mockResolvedValue({ data: { daemon_running: true } });

        const irc = useIrcStore();
        await Promise.all([irc.fetchStatus(), irc.fetchStatus(), irc.fetchStatus()]);

        expect(axios.get).toHaveBeenCalledTimes(1);
    });

    it('remembers the last reading so the menu does not flicker', async () => {
        axios.get.mockResolvedValue({ data: { daemon_running: true } });
        await useIrcStore().fetchStatus();

        // A fresh page load starts from what was last seen
        setActivePinia(createPinia());
        expect(useIrcStore().daemonRunning).toBe(true);
    });

    it('drops IRC from the menu while the daemon is down', () => {
        const settings = useSettingsStore();
        settings.appSettings.feature_irc_enabled = true;

        useIrcStore().setDaemonRunning(false);
        expect(permission.applyPermissions(ircItem)).toBe(false);

        useIrcStore().setDaemonRunning(true);
        expect(permission.applyPermissions(ircItem)).toBe(true);
    });

    it('keeps IRC out of the menu when the feature is off, daemon or not', () => {
        const settings = useSettingsStore();
        settings.appSettings.feature_irc_enabled = false;
        useIrcStore().setDaemonRunning(true);

        expect(permission.applyPermissions(ircItem)).toBe(false);
    });

    it('leaves features without a runtime gate alone', () => {
        expect(isFeatureAvailable('wiki')).toBe(true);
        expect(isFeatureAvailable('forum')).toBe(true);
    });
});
