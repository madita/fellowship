<template>
    <div v-if="placed.length" class="help-hints">
        <div
            v-for="hint in placed"
            :key="hint.id"
            class="help-hints__anchor"
            :style="hint.style"
        >
            <button
                type="button"
                class="help-hints__marker"
                :class="{ 'help-hints__marker--on': store.openHintId === hint.id }"
                :aria-label="hint.title"
                :aria-expanded="store.openHintId === hint.id"
                @click.stop="store.openHint(hint.id)"
            >
                <v-icon size="12" color="white">mdi-help</v-icon>
            </button>

            <v-card
                v-if="store.openHintId === hint.id"
                class="help-hints__card"
                :class="`help-hints__card--${hint.placement}`"
                rounded="lg"
                elevation="10"
                role="tooltip"
            >
                <div class="d-flex align-start pa-3 ga-2">
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="text-subtitle-2 font-weight-bold">{{ hint.title }}</div>
                        <div v-if="hint.content" class="text-body-2 text-medium-emphasis mt-1">
                            {{ hint.content }}
                        </div>
                    </div>

                    <v-btn
                        icon
                        variant="text"
                        size="x-small"
                        color="medium-emphasis"
                        :aria-label="$t('common.close')"
                        @click.stop="store.closeHints()"
                    >
                        <v-icon size="16">mdi-close</v-icon>
                    </v-btn>
                </div>
            </v-card>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useHelpStore } from '@/store/helpStore.js';
import { findTarget, rectOf, resolvePlacement } from '@/utils/helpAnchor.js';

/**
 * Help scattered across the page rather than walked through: a small
 * marker beside each thing that has something to say about it, read in
 * whatever order the reader likes.
 *
 * The same rows as a tour, shown differently — a tour in `hints` mode is
 * this. Markers sit in fixed position beside their element and are
 * re-measured when the page moves, which is also how one whose element
 * has gone disappears instead of floating in the corner.
 */

const store = useHelpStore();

// hint id -> where its element is
const rects = ref({});

let frame = null;

function measure() {
    const next = {};

    for (const hint of store.hints) {
        const element = findTarget(hint.target);

        if (element) next[hint.id] = rectOf(element);
    }

    rects.value = next;

    // A marker that has gone cannot stay open
    if (store.openHintId !== null && !next[store.openHintId]) {
        store.closeHints();
    }
}

function scheduleMeasure() {
    if (frame !== null) return;

    frame = requestAnimationFrame(() => {
        frame = null;
        measure();
    });
}

/**
 * The markers that have somewhere to be. A marker sits on the top-right
 * corner of its element; its card opens on whichever side has room.
 */
const placed = computed(() => store.hints.reduce((list, hint) => {
    const rect = rects.value[hint.id];

    if (!rect) return list;

    list.push({
        ...hint,
        placement: resolvePlacement(rect, hint.placement),
        style: {
            top: `${rect.top}px`,
            left: `${rect.right}px`,
        },
    });

    return list;
}, []));

// The set of hints changes with the page
watch(() => store.hints.map(hint => hint.id).join(','), scheduleMeasure);

onMounted(() => {
    measure();

    window.addEventListener('scroll', scheduleMeasure, true);
    window.addEventListener('resize', scheduleMeasure);
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', scheduleMeasure, true);
    window.removeEventListener('resize', scheduleMeasure);

    if (frame !== null) {
        cancelAnimationFrame(frame);
        frame = null;
    }
});
</script>

<style lang="scss" scoped>
// The wrapper itself must not catch anything: the page underneath stays
// fully usable while hints are on screen, unlike during a tour
.help-hints {
    pointer-events: none;
}

.help-hints__anchor {
    position: fixed;
    z-index: 2180;
    // The element's own top-right corner; the marker straddles it
    transform: translate(-50%, -50%);
}

.help-hints__marker {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    padding: 0;
    border: 2px solid rgb(var(--v-theme-surface));
    border-radius: 50%;
    background: rgb(var(--v-theme-primary));
    cursor: pointer;
    pointer-events: auto;
    animation: help-pulse 2.4s ease-out infinite;

    &--on,
    &:hover {
        animation: none;
        transform: scale(1.15);
    }
}

.help-hints__card {
    position: absolute;
    z-index: 2181;
    width: min(280px, calc(100vw - 32px));
    pointer-events: auto;
    background: rgb(var(--v-theme-surface)) !important;

    &--bottom {
        top: 18px;
        left: -140px;
    }

    &--top {
        bottom: 18px;
        left: -140px;
    }

    &--right {
        top: -20px;
        left: 18px;
    }

    &--left {
        top: -20px;
        right: 18px;
    }
}

// Enough movement to be noticed, not enough to be irritating on a page
// that carries a dozen of them
@keyframes help-pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(var(--v-theme-primary), 0.6);
    }

    70% {
        box-shadow: 0 0 0 8px rgba(var(--v-theme-primary), 0);
    }

    100% {
        box-shadow: 0 0 0 0 rgba(var(--v-theme-primary), 0);
    }
}

// Respect a reader who has asked for less motion
@media (prefers-reduced-motion: reduce) {
    .help-hints__marker {
        animation: none;
    }
}
</style>
