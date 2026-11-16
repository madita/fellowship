import { defineStore } from 'pinia';
import axios from 'axios';
import { debug } from '@/utils/debug.js';

const log = debug.module('PodcastStore');

/**
 * Shows, episodes, and the one episode currently playing.
 *
 * The player lives in the store rather than in a page because playback
 * has to survive navigation: a listener who opens the show notes, or
 * wanders off to the forum, should keep hearing the episode. The audio
 * element itself is owned by the player component mounted once in the
 * layout; the store only holds what is playing and where it got to.
 */
export const usePodcastStore = defineStore({
    id: 'podcast',

    state: () => ({
        podcasts: [],
        loadingPodcasts: false,
        // slug → { podcast, episodes, meta }
        shows: {},
        loadingShow: false,
        episode: null,
        loadingEpisode: false,
        error: null,

        // Playback
        current: null,      // the episode being played
        currentShow: null,  // the show it belongs to, for the player's label
        playing: false,
        position: 0,        // seconds
        duration: 0,        // seconds, as the audio file reports it
        volume: 1,
        rate: 1,
        // Set when play() is called so the player component knows to
        // actually start, rather than just showing the episode
        playRequested: false,
        /**
         * Where a listener asked to jump to, in seconds, until the audio
         * host has done it.
         *
         * Only one component owns the <audio> element — see
         * PodcastAudioHost — so a scrubber anywhere else cannot set
         * currentTime itself. It leaves the request here instead and the
         * host applies it. null means nothing is pending; 0 is a real
         * request to go back to the start, which is why this is not
         * simply a falsy check anywhere.
         */
        seekRequest: null,
    }),

    getters: {
        isCurrent: (state) => (episode) => !!episode && state.current?.id === episode.id,

        progress: (state) => (state.duration > 0 ? (state.position / state.duration) * 100 : 0),

        show: (state) => (slug) => state.shows[slug] || null,
    },

    actions: {
        async fetchPodcasts() {
            this.loadingPodcasts = true;
            this.error = null;

            try {
                const { data } = await axios.get('/api/podcasts');
                this.podcasts = data.podcasts || [];
            } catch (error) {
                log.error('Failed to fetch podcasts:', error);
                this.error = error;
                this.podcasts = [];
            } finally {
                this.loadingPodcasts = false;
            }
        },

        async fetchShow(slug, { page = 1 } = {}) {
            this.loadingShow = true;
            this.error = null;

            try {
                const { data } = await axios.get(`/api/podcasts/${encodeURIComponent(slug)}`, {
                    params: { page },
                });

                this.shows = {
                    ...this.shows,
                    [slug]: { podcast: data.podcast, episodes: data.episodes || [], meta: data.meta },
                };

                return this.shows[slug];
            } catch (error) {
                log.error(`Failed to fetch podcast ${slug}:`, error);
                this.error = error;

                return null;
            } finally {
                this.loadingShow = false;
            }
        },

        async fetchEpisode(slug, episodeSlug) {
            this.loadingEpisode = true;
            this.error = null;

            try {
                const url = `/api/podcasts/${encodeURIComponent(slug)}/episodes/${encodeURIComponent(episodeSlug)}`;
                const { data } = await axios.get(url);
                this.episode = data.episode;

                return this.episode;
            } catch (error) {
                log.error(`Failed to fetch episode ${episodeSlug}:`, error);
                this.error = error;
                this.episode = null;

                return null;
            } finally {
                this.loadingEpisode = false;
            }
        },

        /**
         * Start an episode, or resume the one already loaded.
         *
         * `show` is passed in rather than read off the episode because the
         * list endpoint leaves it out — the caller always knows it.
         */
        play(episode, show = null) {
            if ( ! episode?.audio_url) {
                return;
            }

            if (this.current?.id !== episode.id) {
                this.current = episode;
                this.currentShow = show || this.currentShow;
                this.position = 0;
                this.duration = episode.duration || 0;
                this.countPlay(episode, show);
            }

            this.playing = true;
            this.playRequested = true;
        },

        pause() {
            this.playing = false;
        },

        toggle(episode, show = null) {
            if (this.isCurrent(episode) && this.playing) {
                this.pause();

                return;
            }

            this.play(episode, show);
        },

        /**
         * The player component reports back what the audio element does,
         * so a listener using its own controls stays in sync.
         */
        syncProgress(position, duration) {
            this.position = position;

            if (duration && Number.isFinite(duration)) {
                this.duration = duration;
            }
        },

        setPlaying(playing) {
            this.playing = playing;
        },

        setVolume(volume) {
            this.volume = Math.min(Math.max(volume, 0), 1);
        },

        setRate(rate) {
            this.rate = rate;
        },

        /**
         * Ask the audio host to jump to a point in the episode.
         *
         * The position moves immediately so the scrubber the listener is
         * dragging keeps up; the host then moves the audio to match.
         */
        requestSeek(seconds) {
            const max = this.duration || this.current?.duration || 0;
            const target = Math.min(Math.max(seconds, 0), max || seconds);

            this.position = target;
            this.seekRequest = target;
        },

        /** Jump relative to where the episode is now: skip back or ahead. */
        seekBy(delta) {
            this.requestSeek(this.position + delta);
        },

        seekHandled() {
            this.seekRequest = null;
        },

        playHandled() {
            this.playRequested = false;
        },

        stop() {
            this.current = null;
            this.currentShow = null;
            this.playing = false;
            this.position = 0;
            this.duration = 0;
            this.seekRequest = null;
        },

        /**
         * Counted once per time someone starts an episode. A failure here
         * must never stop playback, so it is deliberately not awaited and
         * its error goes no further than the log.
         */
        countPlay(episode, show) {
            const slug = show?.slug || this.currentShow?.slug;

            if ( ! slug || ! episode?.slug) {
                return;
            }

            const url = `/api/podcasts/${encodeURIComponent(slug)}/episodes/${encodeURIComponent(episode.slug)}/play`;

            axios.post(url).catch((error) => log.warn('Failed to count play:', error));
        },
    },
});

export default usePodcastStore;
