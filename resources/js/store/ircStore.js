import { defineStore } from 'pinia';
import axios from 'axios';
import { debug } from '@/utils/debug.js';

const log = debug.module('IrcStore');

const CACHE_KEY = 'irc_daemon_running';

// How long a status reading is trusted before it is fetched again
const FRESH_FOR_MS = 30 * 1000;

/**
 * The last reading, kept so the menu does not flicker on every page load —
 * the IRC entry stays where it was until the fresh status arrives.
 */
function cachedStatus() {
    try {
        const cached = localStorage.getItem(CACHE_KEY);
        if (cached === null) return null;
        return cached === 'true';
    } catch (e) {
        return null;
    }
}

/**
 * The IRC daemon holds the sockets to the IRC servers and is the only thing
 * that consumes the command queue. With it down nothing can connect, so the
 * client hides the chat rather than offering one that cannot work.
 */
export const useIrcStore = defineStore({
    id: 'irc',

    state: () => ({
        // true / false once known, null while it has never been checked
        daemonRunning: cachedStatus(),
        checkedAt: 0,
        checking: null,
    }),

    getters: {
        // Nothing has confirmed the daemon is up, so the chat stays closed
        chatAvailable: (state) => state.daemonRunning === true,
    },

    actions: {
        /**
         * Ask the server whether the daemon is alive. Readings are reused for
         * a short while so a page full of menus makes one request, not ten.
         */
        async fetchStatus({ force = false } = {}) {
            if (!force && Date.now() - this.checkedAt < FRESH_FOR_MS) {
                return this.daemonRunning;
            }

            // A second caller joins the request already in flight
            if (this.checking) return this.checking;

            this.checking = axios.get('/api/irc/status')
                .then(({ data }) => {
                    this.setDaemonRunning(data?.daemon_running === true);
                    return this.daemonRunning;
                })
                .catch((error) => {
                    // A status we cannot read is not a daemon we can use
                    log.warn('Could not read the IRC daemon status:', error);
                    this.setDaemonRunning(false);
                    return false;
                })
                .finally(() => {
                    this.checking = null;
                });

            return this.checking;
        },

        setDaemonRunning(running) {
            this.daemonRunning = running;
            this.checkedAt = Date.now();

            try {
                localStorage.setItem(CACHE_KEY, running ? 'true' : 'false');
            } catch (e) {
                log.warn('Could not cache the IRC daemon status:', e);
            }
        },
    },
});

export default useIrcStore;
