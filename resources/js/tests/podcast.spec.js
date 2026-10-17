import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { formatDuration, parseDuration, formatFileSize } from '@/utils/podcast.js';
import { FEATURES } from '@/configs/features.js';
import navigation from '@/configs/navigation.js';
import { sectionNames } from '@/utils/crumbs.js';

vi.mock('axios', () => ({
    default: {
        get: vi.fn(() => Promise.resolve({ data: {} })),
        post: vi.fn(() => Promise.resolve({ data: {} })),
    },
}));

describe('podcast duration formatting', () => {
    it('reads as a clock, and only shows hours once there are some', () => {
        expect(formatDuration(0)).toBe('0:00');
        expect(formatDuration(65)).toBe('1:05');
        expect(formatDuration(3845)).toBe('1:04:05');
    });

    it('never puts NaN in front of a listener', () => {
        expect(formatDuration(null)).toBe('0:00');
        expect(formatDuration(undefined)).toBe('0:00');
        expect(formatDuration('nonsense')).toBe('0:00');
        expect(formatDuration(-10)).toBe('0:00');
    });

    it('takes back what it wrote', () => {
        expect(parseDuration('1:04:05')).toBe(3845);
        expect(parseDuration('12:30')).toBe(750);
        expect(parseDuration('3845')).toBe(3845);
    });

    it('is empty for an empty field and null for a typo', () => {
        expect(parseDuration('')).toBeNull();
        expect(parseDuration(null)).toBeNull();
        expect(parseDuration('about an hour')).toBeNull();
    });

    it('sizes a file in the unit a person would use', () => {
        expect(formatFileSize(0)).toBe('–');
        expect(formatFileSize(null)).toBe('–');
        expect(formatFileSize(512)).toBe('512 B');
        expect(formatFileSize(1024 * 1024 * 12.5)).toBe('12.5 MB');
    });
});

describe('the podcast feature is registered', () => {
    const feature = FEATURES.find(entry => entry.key === 'podcast');

    it('is in the registry, so the toggle screen offers it', () => {
        expect(feature).toBeTruthy();
        expect(feature.routePrefixes).toContain('/podcast');
    });

    it('tags its menu entry, so turning it off hides it', () => {
        const main = navigation.menu.find(item => item.key === 'menu.main');
        const entry = main.items.find(item => item.link === '/podcast');

        expect(entry).toBeTruthy();
        expect(entry.feature).toBe('podcast');
    });

    it('leaves the admin entry untagged, so it stays reachable', () => {
        const admin = navigation.menu.find(item => item.role === 'admin');
        const entry = admin.items.find(item => item.link === '/admin/podcast');

        expect(entry).toBeTruthy();
        expect(entry.feature).toBeUndefined();
    });

    it('has a breadcrumb section, so episode pages can say where they are', () => {
        expect(sectionNames()).toContain('podcast');
    });
});

describe('podcastStore playback', () => {
    let usePodcastStore;

    beforeEach(async () => {
        setActivePinia(createPinia());
        usePodcastStore = (await import('@/store/podcastStore.js')).usePodcastStore;
    });

    const episode = (overrides = {}) => ({
        id: 1,
        slug: 'pilot',
        title: 'Pilot',
        audio_url: '/storage/podcasts/audio/pilot.mp3',
        duration: 1200,
        ...overrides,
    });

    it('plays an episode and remembers the show it came from', () => {
        const store = usePodcastStore();
        const show = { id: 7, slug: 'nightly', title: 'The Nightly Watch' };

        store.play(episode(), show);

        expect(store.current.slug).toBe('pilot');
        // Pinia hands state back through a reactive proxy, so this is
        // the same show by value rather than the same object
        expect(store.currentShow).toEqual(show);
        expect(store.playing).toBe(true);
        expect(store.playRequested).toBe(true);
    });

    it('refuses an episode with no audio, rather than showing a dead player', () => {
        const store = usePodcastStore();

        store.play(episode({ audio_url: null }));

        expect(store.current).toBeNull();
        expect(store.playing).toBe(false);
    });

    it('toggling the episode already playing pauses it', () => {
        const store = usePodcastStore();
        const one = episode();

        store.play(one, { slug: 'nightly' });
        store.toggle(one);

        expect(store.playing).toBe(false);
        // Still loaded, so pressing play again resumes
        expect(store.current.slug).toBe('pilot');
    });

    it('switching episodes starts the new one from the beginning', () => {
        const store = usePodcastStore();

        store.play(episode(), { slug: 'nightly' });
        store.syncProgress(300, 1200);
        store.play(episode({ id: 2, slug: 'second' }), { slug: 'nightly' });

        expect(store.current.slug).toBe('second');
        expect(store.position).toBe(0);
    });

    it('keeps the duration the file reports, not the one that was typed in', () => {
        const store = usePodcastStore();

        store.play(episode(), { slug: 'nightly' });
        store.syncProgress(10, 1234.5);

        expect(store.duration).toBe(1234.5);
    });

    it('ignores a duration the audio element cannot work out yet', () => {
        const store = usePodcastStore();

        store.play(episode(), { slug: 'nightly' });
        store.syncProgress(10, NaN);

        expect(store.duration).toBe(1200);
    });

    it('stopping clears the player away', () => {
        const store = usePodcastStore();

        store.play(episode(), { slug: 'nightly' });
        store.stop();

        expect(store.current).toBeNull();
        expect(store.currentShow).toBeNull();
        expect(store.playing).toBe(false);
        expect(store.position).toBe(0);
    });

    it('volume stays within what an audio element accepts', () => {
        const store = usePodcastStore();

        store.setVolume(2);
        expect(store.volume).toBe(1);

        store.setVolume(-1);
        expect(store.volume).toBe(0);
    });

    it('isCurrent is false when nothing is playing', () => {
        const store = usePodcastStore();

        expect(store.isCurrent(episode())).toBe(false);
        expect(store.isCurrent(null)).toBe(false);
    });

    it('counts the play once, against the show it was started from', async () => {
        const axios = (await import('axios')).default;
        const store = usePodcastStore();

        store.play(episode(), { slug: 'nightly' });

        expect(axios.post).toHaveBeenCalledWith('/api/podcasts/nightly/episodes/pilot/play');
    });
});
