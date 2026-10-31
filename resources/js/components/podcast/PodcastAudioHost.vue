<template>
    <!-- Nothing to look at. The element has to exist and keep its buffer,
         which is the whole job; what a listener sees is the toolbar
         control and, on the podcast pages, the player bar. -->
    <audio
        v-if="current"
        ref="audio"
        :src="current.audio_url"
        preload="metadata"
        @loadedmetadata="onMeta"
        @timeupdate="onTimeUpdate"
        @ended="onEnded"
        @play="store.setPlaying(true)"
        @pause="store.setPlaying(false)"
    ></audio>
</template>

<script>
import { usePodcastStore } from '@/store/podcastStore.js';

/**
 * The one <audio> element in the application.
 *
 * Mounted in App.vue, outside the layout component and outside
 * RouterView — both of which are replaced as the listener moves around
 * (RouterView is keyed on the full path, and switching between a default
 * and a landing page swaps the layout instance). An element inside
 * either would be torn down mid-episode, so the audio lives above them
 * and keeps playing through every navigation and every layout, including
 * the ones with no toolbar to show a control in.
 *
 * It renders nothing. Every control — the toolbar button, the player bar
 * on the podcast pages — goes through the store, and this is the only
 * component that touches the element itself.
 */
export default {
    name: 'PodcastAudioHost',
    setup() {
        return { store: usePodcastStore() };
    },
    computed: {
        current() {
            return this.store.current;
        },
    },
    watch: {
        // A page asked for playback. Deferred a tick because the episode
        // may have only just been set, so the element's src is still
        // being bound when the request arrives.
        'store.playRequested'(requested) {
            if (requested) {
                this.$nextTick(() => this.start());
            }
        },

        'store.playing'(playing) {
            const audio = this.$refs.audio;

            if ( ! audio) {
                return;
            }

            if (playing && audio.paused) {
                audio.play().catch(() => this.store.setPlaying(false));
            } else if ( ! playing && ! audio.paused) {
                audio.pause();
            }
        },

        // 0 is a real destination, so this tests for null rather than
        // truthiness — a listener dragging back to the very start would
        // otherwise be ignored.
        'store.seekRequest'(seconds) {
            if (seconds === null) {
                return;
            }

            const audio = this.$refs.audio;

            if (audio) {
                audio.currentTime = seconds;
            }

            this.store.seekHandled();
        },

        'store.rate'(rate) {
            if (this.$refs.audio) {
                this.$refs.audio.playbackRate = rate;
            }
        },

        'store.volume'(volume) {
            if (this.$refs.audio) {
                this.$refs.audio.volume = volume;
            }
        },
    },
    methods: {
        start() {
            const audio = this.$refs.audio;
            this.store.playHandled();

            if ( ! audio) {
                return;
            }

            audio.playbackRate = this.store.rate;
            audio.volume = this.store.volume;

            // A browser may refuse to autoplay. The store has to agree
            // with what actually happened, or the button lies about it.
            audio.play().catch(() => this.store.setPlaying(false));
        },

        onMeta() {
            const audio = this.$refs.audio;

            if ( ! audio) {
                return;
            }

            audio.playbackRate = this.store.rate;
            audio.volume = this.store.volume;
            this.store.syncProgress(audio.currentTime, audio.duration);
        },

        onTimeUpdate() {
            const audio = this.$refs.audio;

            if (audio) {
                this.store.syncProgress(audio.currentTime, audio.duration);
            }
        },

        onEnded() {
            this.store.setPlaying(false);
            this.store.syncProgress(0, this.store.duration);

            if (this.$refs.audio) {
                this.$refs.audio.currentTime = 0;
            }
        },
    },
};
</script>
