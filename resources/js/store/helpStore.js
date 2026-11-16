import { defineStore } from 'pinia';
import axios from 'axios';
import { debug } from '@/utils/debug.js';
import { normalisePath } from '@/utils/helpAnchor.js';

const log = debug.module('HelpStore');

/**
 * The help written for the page somebody is on, and the state of walking
 * through it.
 *
 * Tours are fetched per path — the backend decides which ones apply and
 * narrows them to the viewer, so the client never holds help meant for
 * somebody else. One tour runs at a time.
 *
 * Who has already been shown what is kept on the member's record; for a
 * guest there is no record to write, so it goes in their own browser and
 * stays there.
 */

export const GUEST_SEEN_KEY = 'help_tours_seen';

/** Where a draft waits while the admin goes off to click an element. */
export const PICKER_KEY = 'help_picker_handoff';

/** The query flag that turns a page into a picking surface. */
export const PICK_FLAG = 'help_pick';

/**
 * The query flag that runs one named tour on arrival, whether or not it
 * has been seen — how the editor's Preview button works.
 */
export const TOUR_FLAG = 'help_tour';

function guestSeen() {
    try {
        const stored = JSON.parse(localStorage.getItem(GUEST_SEEN_KEY) || '[]');

        return Array.isArray(stored) ? stored : [];
    } catch (e) {
        return [];
    }
}

function rememberGuestSeen(key) {
    try {
        const seen = new Set(guestSeen());
        seen.add(key);
        localStorage.setItem(GUEST_SEEN_KEY, JSON.stringify([...seen]));
    } catch (e) {
        // A guest with no storage is simply offered the tour again
    }
}

export const useHelpStore = defineStore('help', {
    state: () => ({
        // The tours that apply to `path`, as the viewer should see them
        tours: [],
        path: null,
        loading: false,

        activeTourId: null,
        stepIndex: 0,

        // Which hint marker is open, in `hints` mode
        openHintId: null,

        // Keys a guest has already been shown
        guestSeen: guestSeen(),
    }),

    getters: {
        /** Offered by the help button in the toolbar. */
        launchable: (state) => state.tours.filter(tour => tour.show_launcher),

        /** Scattered beside their elements rather than walked through. */
        hintTours: (state) => state.tours.filter(tour => tour.mode === 'hints'),

        /** Every hint of every hint-mode tour, flattened for rendering. */
        hints: (state) => state.tours
            .filter(tour => tour.mode === 'hints')
            .flatMap(tour => tour.steps
                .filter(step => step.target)
                .map(step => ({ ...step, tour }))),

        activeTour: (state) => state.tours.find(tour => tour.id === state.activeTourId) || null,

        stepCount() {
            return this.activeTour?.steps.length || 0;
        },

        activeStep() {
            return this.activeTour?.steps[this.stepIndex] || null;
        },

        isFirstStep: (state) => state.stepIndex === 0,

        isLastStep() {
            return this.stepIndex >= this.stepCount - 1;
        },

        /** Is anything running right now? */
        isRunning: (state) => state.activeTourId !== null,

        /** Is there any help at all for this page? */
        hasHelp: (state) => state.tours.length > 0,
    },

    actions: {
        /**
         * Load the help for a path. Does nothing when the path has not
         * really changed — a query string is not a different page as far
         * as help is concerned.
         */
        async fetchForPath(path, { force = false } = {}) {
            const wanted = normalisePath(path);

            if (!force && wanted === this.path) return;

            // Whatever was running belonged to the page being left
            this.stop();

            this.path = wanted;
            this.loading = true;

            try {
                const { data } = await axios.get('/api/help-tours', { params: { path: wanted } });

                this.tours = (data.data || []).map(tour => ({
                    ...tour,
                    // A guest's answer lives in their browser; a member's
                    // comes down with the tour
                    seen: tour.seen || this.guestSeen.includes(tour.key),
                }));
            } catch (error) {
                log.warn('Could not load the help for this page:', error);
                this.tours = [];
            } finally {
                this.loading = false;
            }
        },

        /**
         * The one tour that should open by itself, if any: enabled for
         * auto-start, walked rather than scattered, and not yet shown.
         */
        pendingAutoStart() {
            return this.tours.find(tour => tour.auto_start && tour.mode === 'tour' && !tour.seen) || null;
        },

        start(tour) {
            const id = typeof tour === 'object' ? tour?.id : tour;
            if (!this.tours.some(candidate => candidate.id === id)) return;

            this.activeTourId = id;
            this.stepIndex = 0;
            this.openHintId = null;
        },

        next() {
            if (this.isLastStep) {
                this.stop({ completed: true });
                return;
            }

            this.stepIndex++;
        },

        back() {
            if (this.stepIndex > 0) this.stepIndex--;
        },

        goTo(index) {
            if (index >= 0 && index < this.stepCount) this.stepIndex = index;
        },

        /**
         * Close whatever is running. `completed` separates reaching the
         * end from closing it part way through — both stop it opening by
         * itself again, only one of them means it was read.
         */
        stop({ completed = false } = {}) {
            const tour = this.activeTour;

            this.activeTourId = null;
            this.stepIndex = 0;

            if (tour) this.markSeen(tour, completed);
        },

        openHint(id) {
            this.openHintId = this.openHintId === id ? null : id;
        },

        closeHints() {
            this.openHintId = null;
        },

        /**
         * Note that this tour has been shown. Optimistic: the mark is
         * local straight away, so a tour cannot re-open while the request
         * is still in flight.
         */
        async markSeen(tour, completed = false) {
            const record = this.tours.find(candidate => candidate.id === tour.id);

            if (record) {
                record.seen = true;
                record.completed = record.completed || completed;
            }

            if (!this.guestSeen.includes(tour.key)) {
                this.guestSeen = [...this.guestSeen, tour.key];
                rememberGuestSeen(tour.key);
            }

            try {
                await axios.post(`/api/help-tours/${tour.id}/seen`, { completed });
            } catch (error) {
                // A guest gets a 401 here, which is expected: their answer
                // is already in their own browser
                log.log('Could not record the tour as seen:', error?.response?.status);
            }
        },

        /**
         * Offer everything again — the member's own doing, from the
         * settings drawer.
         */
        async resetSeen() {
            this.guestSeen = [];

            try {
                localStorage.removeItem(GUEST_SEEN_KEY);
            } catch (e) {
                // no-op
            }

            try {
                await axios.delete('/api/help-tours/seen');
            } catch (error) {
                log.log('Could not clear the seen tours:', error?.response?.status);
            }

            this.tours = this.tours.map(tour => ({ ...tour, seen: false, completed: false }));
        },

        /**
         * Park the editor's draft and say which step is waiting for a
         * selector, so the admin can go and click one on the real page and
         * come back to the form as they left it.
         *
         * sessionStorage rather than the store: picking means a route
         * change to another part of the app and back, and the draft has to
         * survive a reload in the middle of that.
         */
        beginPicking({ draft, stepIndex, returnTo }) {
            try {
                sessionStorage.setItem(PICKER_KEY, JSON.stringify({
                    draft,
                    stepIndex,
                    returnTo,
                    selector: null,
                }));

                return true;
            } catch (e) {
                log.warn('Could not park the draft for picking:', e);

                return false;
            }
        },

        /** What is waiting, without consuming it. */
        pendingPick() {
            try {
                const stored = sessionStorage.getItem(PICKER_KEY);

                return stored ? JSON.parse(stored) : null;
            } catch (e) {
                return null;
            }
        },

        /** Write down what was clicked, for the editor to pick up. */
        finishPicking(selector) {
            const pending = this.pendingPick();
            if (!pending) return null;

            try {
                sessionStorage.setItem(PICKER_KEY, JSON.stringify({ ...pending, selector }));
            } catch (e) {
                log.warn('Could not record the picked element:', e);
            }

            return pending.returnTo;
        },

        /** Take the result and clear it, so a reload does not re-apply it. */
        takePick() {
            const pending = this.pendingPick();

            try {
                sessionStorage.removeItem(PICKER_KEY);
            } catch (e) {
                // no-op
            }

            return pending?.selector ? pending : null;
        },

        cancelPicking() {
            try {
                sessionStorage.removeItem(PICKER_KEY);
            } catch (e) {
                // no-op
            }
        },
    },
});
