import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import axios from 'axios';
import {
    pathMatches,
    normalisePath,
    selectorFor,
    resolvePlacement,
    findTarget,
} from '@/utils/helpAnchor.js';
import { helpRouteOptions, patternFor, labelFor } from '@/utils/helpRoutes.js';
import { useHelpStore, GUEST_SEEN_KEY, PICKER_KEY } from '@/store/helpStore.js';

vi.mock('axios');

const tour = (overrides = {}) => ({
    id: 1,
    key: 'welcome',
    mode: 'tour',
    icon: 'mdi-hand-wave-outline',
    color: 'primary',
    title: 'Welcome',
    description: 'A quick look around',
    auto_start: true,
    show_launcher: true,
    seen: false,
    completed: false,
    steps: [
        { id: 10, target: '#one', placement: 'auto', title: 'One', content: 'First' },
        { id: 11, target: '#two', placement: 'bottom', title: 'Two', content: 'Second' },
    ],
    ...overrides,
});

describe('which pages a tour belongs to', () => {
    it('matches the one page it names', () => {
        expect(pathMatches('/forum', '/forum')).toBe(true);
        expect(pathMatches('/forum', '/wiki')).toBe(false);
    });

    it('treats a trailing slash and a query string as the same page', () => {
        expect(pathMatches('/forum', '/forum/')).toBe(true);
        expect(pathMatches('/forum', '/forum?sort=new')).toBe(true);
        expect(pathMatches('/forum/', '/forum')).toBe(true);
    });

    it('covers a whole section with a wildcard', () => {
        expect(pathMatches('/wiki/*', '/wiki/the-fellowship')).toBe(true);
        expect(pathMatches('/wiki/*', '/wiki/a/b')).toBe(true);
        // The section's index is not one of its entries
        expect(pathMatches('/wiki/*', '/wiki')).toBe(false);
        expect(pathMatches('/wiki/*', '/forum/thing')).toBe(false);
    });

    it('does not let a pattern leak through the regex', () => {
        // A dot is a dot, not "any character"
        expect(pathMatches('/a.c', '/abc')).toBe(false);
        expect(pathMatches('/a.c', '/a.c')).toBe(true);
    });

    it('keeps the root a path', () => {
        expect(normalisePath('/')).toBe('/');
        expect(normalisePath('')).toBe('/');
        expect(pathMatches('/', '/')).toBe(true);
    });
});

describe('writing down what the admin clicked', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    it('prefers the data-help name a component carries', () => {
        document.body.innerHTML = `
            <div data-help="toolbar-notifications" class="v-btn">
                <span id="inner">bell</span>
            </div>`;

        expect(selectorFor(document.getElementById('inner')))
            .toBe('[data-help="toolbar-notifications"]');
    });

    it('falls back to an id', () => {
        document.body.innerHTML = '<div id="main-content">page</div>';

        expect(selectorFor(document.getElementById('main-content'))).toBe('#main-content');
    });

    it('builds a path when there is neither', () => {
        document.body.innerHTML = `
            <section class="thread-list">
                <article>one</article>
                <article>two</article>
            </section>`;

        const second = document.querySelectorAll('article')[1];
        const selector = selectorFor(second);

        expect(selector).toBeTruthy();
        expect(document.querySelectorAll(selector)).toHaveLength(1);
        expect(document.querySelector(selector)).toBe(second);
    });

    it('leaves out the classes that come and go', () => {
        document.body.innerHTML = `
            <div class="forum-toolbar v-btn--active text-primary ma-2">x</div>`;

        const selector = selectorFor(document.querySelector('.forum-toolbar'));

        expect(selector).toContain('forum-toolbar');
        expect(selector).not.toContain('v-btn--active');
        expect(selector).not.toContain('text-primary');
    });

    it('has nothing to say about nothing', () => {
        expect(selectorFor(null)).toBeNull();
    });
});

describe('finding what a step points at', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    it('treats a selector that no longer matches as nothing', () => {
        expect(findTarget('#gone')).toBeNull();
    });

    it('treats a broken selector as nothing rather than throwing', () => {
        expect(() => findTarget('>>> not a selector')).not.toThrow();
        expect(findTarget('>>> not a selector')).toBeNull();
    });

    it('is nothing for a step about the page as a whole', () => {
        expect(findTarget(null)).toBeNull();
        expect(findTarget('')).toBeNull();
    });
});

describe('which side the card sits on', () => {
    const viewport = { width: 1000, height: 800 };

    it('prefers below, where an explanation is expected', () => {
        const rect = { top: 100, left: 400, width: 100, height: 40, bottom: 140, right: 500 };

        expect(resolvePlacement(rect, 'auto', viewport)).toBe('bottom');
    });

    it('goes above something near the bottom of the screen', () => {
        const rect = { top: 740, left: 400, width: 100, height: 40, bottom: 780, right: 500 };

        expect(resolvePlacement(rect, 'auto', viewport)).toBe('top');
    });

    it('honours what the admin asked for when there is room', () => {
        const rect = { top: 400, left: 400, width: 100, height: 40, bottom: 440, right: 500 };

        expect(resolvePlacement(rect, 'right', viewport)).toBe('right');
        expect(resolvePlacement(rect, 'top', viewport)).toBe('top');
    });

    it('uses the other side when the asked-for one would go off screen', () => {
        // Hard against the right edge: 'right' has nowhere to go
        const rect = { top: 400, left: 940, width: 60, height: 40, bottom: 440, right: 1000 };

        expect(resolvePlacement(rect, 'right', viewport)).toBe('left');
    });
});

describe('the pages a tour can be attached to', () => {
    it('collapses a parameterised route into a pattern', () => {
        expect(patternFor('/wiki/:slug')).toBe('/wiki/*');
        expect(patternFor('/forum/:id/:slug?')).toBe('/forum/*');
        expect(patternFor('/dashboard')).toBe('/dashboard');
    });

    it('reads a route name as words', () => {
        expect(labelFor('admin-settings-category')).toBe('Admin settings category');
    });

    it('offers each page once, and leaves out the ones nobody writes help for', () => {
        const options = helpRouteOptions([
            { name: 'dashboard', path: '/dashboard' },
            { name: 'forum', path: '/forum' },
            { name: 'wiki-show', path: '/wiki/:slug' },
            { name: 'wiki-edit', path: '/wiki/:slug/edit' },
            { name: 'error', path: '/:pathMatch(.*)*' },
            { name: null, path: '/unnamed' },
        ]);

        expect(options.map(option => option.pattern)).toEqual(['/dashboard', '/forum', '/wiki/*']);
    });
});

describe('the help store', () => {
    let store;

    beforeEach(() => {
        localStorage.clear();
        sessionStorage.clear();
        setActivePinia(createPinia());
        store = useHelpStore();
        axios.get = vi.fn().mockResolvedValue({ data: { data: [tour()] } });
        axios.post = vi.fn().mockResolvedValue({ data: {} });
        axios.delete = vi.fn().mockResolvedValue({ data: {} });
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('loads the help for a page', async () => {
        await store.fetchForPath('/dashboard');

        expect(axios.get).toHaveBeenCalledWith('/api/help-tours', { params: { path: '/dashboard' } });
        expect(store.tours).toHaveLength(1);
        expect(store.hasHelp).toBe(true);
    });

    it('does not ask again for the same page', async () => {
        await store.fetchForPath('/dashboard');
        await store.fetchForPath('/dashboard?tab=two');

        expect(axios.get).toHaveBeenCalledTimes(1);
    });

    it('is left with nothing when the request fails', async () => {
        axios.get = vi.fn().mockRejectedValue(new Error('offline'));

        await store.fetchForPath('/dashboard');

        expect(store.tours).toEqual([]);
        expect(store.hasHelp).toBe(false);
    });

    it('walks the steps and finishes at the end', async () => {
        await store.fetchForPath('/dashboard');
        store.start(store.tours[0]);

        expect(store.isRunning).toBe(true);
        expect(store.activeStep.title).toBe('One');
        expect(store.isFirstStep).toBe(true);
        expect(store.isLastStep).toBe(false);

        store.next();
        expect(store.activeStep.title).toBe('Two');
        expect(store.isLastStep).toBe(true);

        store.back();
        expect(store.activeStep.title).toBe('One');

        store.goTo(1);
        store.next();

        expect(store.isRunning).toBe(false);
        expect(store.tours[0].completed).toBe(true);
    });

    it('will not start a tour that is not for this page', async () => {
        await store.fetchForPath('/dashboard');
        store.start(999);

        expect(store.isRunning).toBe(false);
    });

    it('closing it early counts as seen but not as read', async () => {
        await store.fetchForPath('/dashboard');
        store.start(store.tours[0]);
        store.stop();

        expect(store.tours[0].seen).toBe(true);
        expect(store.tours[0].completed).toBe(false);
        expect(axios.post).toHaveBeenCalledWith('/api/help-tours/1/seen', { completed: false });
    });

    it('opens a tour by itself only until it has been shown', async () => {
        await store.fetchForPath('/dashboard');
        expect(store.pendingAutoStart()?.key).toBe('welcome');

        store.start(store.tours[0]);
        store.stop();

        expect(store.pendingAutoStart()).toBeNull();
    });

    it('never opens hints by themselves — they are already on the page', async () => {
        axios.get = vi.fn().mockResolvedValue({
            data: { data: [tour({ mode: 'hints' })] },
        });

        await store.fetchForPath('/dashboard');

        expect(store.pendingAutoStart()).toBeNull();
        expect(store.hints).toHaveLength(2);
    });

    it('remembers a guest answer in their own browser', async () => {
        await store.fetchForPath('/dashboard');
        store.start(store.tours[0]);
        store.stop();

        expect(JSON.parse(localStorage.getItem(GUEST_SEEN_KEY))).toContain('welcome');

        // A fresh session for the same guest: nothing opens by itself
        setActivePinia(createPinia());
        const next = useHelpStore();
        axios.get = vi.fn().mockResolvedValue({ data: { data: [tour()] } });

        await next.fetchForPath('/dashboard');

        expect(next.tours[0].seen).toBe(true);
        expect(next.pendingAutoStart()).toBeNull();
    });

    it('offers everything again when asked', async () => {
        await store.fetchForPath('/dashboard');
        store.start(store.tours[0]);
        store.stop();

        await store.resetSeen();

        expect(localStorage.getItem(GUEST_SEEN_KEY)).toBeNull();
        expect(store.tours[0].seen).toBe(false);
        expect(axios.delete).toHaveBeenCalledWith('/api/help-tours/seen');
    });

    it('leaves nothing running when the page changes', async () => {
        await store.fetchForPath('/dashboard');
        store.start(store.tours[0]);

        await store.fetchForPath('/forum');

        expect(store.isRunning).toBe(false);
    });

    it('opens one hint at a time', async () => {
        axios.get = vi.fn().mockResolvedValue({ data: { data: [tour({ mode: 'hints' })] } });
        await store.fetchForPath('/dashboard');

        store.openHint(10);
        expect(store.openHintId).toBe(10);

        // The same marker again closes it
        store.openHint(10);
        expect(store.openHintId).toBeNull();

        store.openHint(11);
        expect(store.openHintId).toBe(11);
    });
});

describe('going off to pick an element', () => {
    let store;

    beforeEach(() => {
        sessionStorage.clear();
        setActivePinia(createPinia());
        store = useHelpStore();
    });

    it('parks the draft and brings it back with the selector', () => {
        const draft = { editingId: 7, form: { route_pattern: '/forum', steps: [{}, {}] } };

        expect(store.beginPicking({ draft, stepIndex: 1, returnTo: { name: 'admin-settings-page' } })).toBe(true);
        expect(sessionStorage.getItem(PICKER_KEY)).toBeTruthy();

        const returnTo = store.finishPicking('[data-help="forum-new-thread"]');
        expect(returnTo).toEqual({ name: 'admin-settings-page' });

        const taken = store.takePick();
        expect(taken.selector).toBe('[data-help="forum-new-thread"]');
        expect(taken.stepIndex).toBe(1);
        expect(taken.draft.form.route_pattern).toBe('/forum');
    });

    it('is taken only once, so a reload does not re-apply it', () => {
        store.beginPicking({ draft: { form: {} }, stepIndex: 0, returnTo: '/x' });
        store.finishPicking('#thing');

        expect(store.takePick()).not.toBeNull();
        expect(store.takePick()).toBeNull();
    });

    it('gives back nothing when picking was abandoned', () => {
        store.beginPicking({ draft: { form: {} }, stepIndex: 0, returnTo: '/x' });

        // Left without clicking anything
        expect(store.takePick()).toBeNull();
    });

    it('drops the draft when it is cancelled', () => {
        store.beginPicking({ draft: { form: {} }, stepIndex: 0, returnTo: '/x' });
        store.cancelPicking();

        expect(store.pendingPick()).toBeNull();
    });
});
