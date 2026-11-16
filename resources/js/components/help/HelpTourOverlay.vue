<template>
    <div v-if="store.isRunning && step" class="help-overlay">
        <!-- Catches everything while a tour is running. The page stays
             readable but not clickable: a step explaining a button that
             navigates away would otherwise end the tour it belongs to. -->
        <div class="help-overlay__blocker" @click="stop" />

        <!-- The hole in the dim. With nothing to single out — a step about
             the page as a whole — the dim covers everything evenly. -->
        <div
            v-if="rect"
            class="help-overlay__spotlight"
            :style="spotlightStyle"
        />
        <div v-else class="help-overlay__dim" />

        <v-card
            class="help-overlay__card"
            :style="style"
            rounded="lg"
            elevation="12"
            role="dialog"
            aria-modal="true"
            :aria-label="tour.title"
        >
            <div class="d-flex align-start pa-4 pb-2 ga-3">
                <v-avatar :color="tour.color || 'primary'" size="32">
                    <v-icon size="18" color="white">{{ tour.icon || 'mdi-help-circle-outline' }}</v-icon>
                </v-avatar>

                <div class="flex-grow-1 overflow-hidden">
                    <div class="text-caption text-medium-emphasis">{{ tour.title }}</div>
                    <div class="text-subtitle-2 font-weight-bold">{{ step.title }}</div>
                </div>

                <v-btn
                    icon
                    variant="text"
                    size="x-small"
                    color="medium-emphasis"
                    :aria-label="$t('help.skip')"
                    @click="stop"
                >
                    <v-icon size="18">mdi-close</v-icon>
                </v-btn>
            </div>

            <v-card-text v-if="step.content" class="pt-0 pb-2 text-body-2">
                {{ step.content }}
            </v-card-text>

            <!-- Said out loud when the step points at something that is no
                 longer there, rather than silently highlighting nothing -->
            <v-alert
                v-if="step.target && !rect"
                type="info"
                variant="tonal"
                density="compact"
                class="mx-4 mb-2 text-caption"
            >
                {{ $t('help.targetMissing') }}
            </v-alert>

            <v-divider />

            <v-card-actions class="px-3 py-2">
                <!-- One dot per step, so the length of the tour is visible
                     from the first card -->
                <div class="d-flex align-center ga-1">
                    <button
                        v-for="(_, index) in tour.steps"
                        :key="index"
                        type="button"
                        class="help-overlay__dot"
                        :class="{ 'help-overlay__dot--on': index === store.stepIndex }"
                        :aria-label="$t('help.goToStep', { number: index + 1 })"
                        @click="store.goTo(index)"
                    />
                </div>

                <v-spacer />

                <span class="text-caption text-medium-emphasis mr-2">
                    {{ store.stepIndex + 1 }} / {{ store.stepCount }}
                </span>

                <v-btn
                    v-if="!store.isFirstStep"
                    variant="text"
                    size="small"
                    @click="store.back()"
                >
                    {{ $t('help.back') }}
                </v-btn>

                <v-btn
                    color="primary"
                    variant="flat"
                    size="small"
                    @click="store.next()"
                >
                    {{ store.isLastStep ? $t('help.done') : $t('help.next') }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useHelpStore } from '@/store/helpStore.js';
import {
    findTarget,
    rectOf,
    revealTarget,
    resolvePlacement,
    cardStyle,
} from '@/utils/helpAnchor.js';

/**
 * A tour, walked one step at a time behind a dimmed page.
 *
 * The element a step points at is found when the step opens and measured
 * again whenever the page moves under it, because a smooth scroll into
 * view is exactly the case where one measurement is not enough.
 */

const store = useHelpStore();

const tour = computed(() => store.activeTour);
const step = computed(() => store.activeStep);

// Where the step's element is, or null when it has none / is not there
const rect = ref(null);

let frame = null;
let settle = null;

function measure() {
    if (!store.isRunning || !step.value) {
        rect.value = null;

        return;
    }

    const element = findTarget(step.value.target);

    rect.value = element ? rectOf(element) : null;
}

/** Measure on the next frame, and not twice in one. */
function scheduleMeasure() {
    if (frame !== null) return;

    frame = requestAnimationFrame(() => {
        frame = null;
        measure();
    });
}

const spotlightStyle = computed(() => {
    if (!rect.value) return {};

    const padding = 6;

    return {
        top: `${rect.value.top - padding}px`,
        left: `${rect.value.left - padding}px`,
        width: `${rect.value.width + padding * 2}px`,
        height: `${rect.value.height + padding * 2}px`,
    };
});

const style = computed(() => {
    if (!rect.value) {
        // Nothing to sit beside: the middle of the screen
        return {
            top: '50%',
            left: '50%',
            transform: 'translate(-50%, -50%)',
            width: 'min(340px, calc(100vw - 24px))',
        };
    }

    const placement = resolvePlacement(rect.value, step.value?.placement);

    return cardStyle(rect.value, placement);
});

function stop() {
    store.stop();
}

function onKey(event) {
    if (!store.isRunning) return;

    if (event.key === 'Escape') {
        stop();
    } else if (event.key === 'ArrowRight') {
        store.next();
    } else if (event.key === 'ArrowLeft') {
        store.back();
    }
}

// A new step: bring its element into view, then keep measuring for as long
// as the smooth scroll takes to settle
watch(
    () => [store.activeTourId, store.stepIndex],
    () => {
        if (settle !== null) {
            clearInterval(settle);
            settle = null;
        }

        if (!store.isRunning) return;

        const element = findTarget(step.value?.target);
        revealTarget(element);
        measure();

        // The scroll is animated, so one measurement taken as it starts is
        // the wrong one. Six over ~600ms covers it without a permanent
        // loop, and the next step clears whatever is left of this one.
        let remaining = 6;

        settle = setInterval(() => {
            measure();

            if (--remaining <= 0) {
                clearInterval(settle);
                settle = null;
            }
        }, 100);
    },
    { immediate: true }
);

watch(
    () => store.isRunning,
    (running) => {
        if (running) {
            window.addEventListener('scroll', scheduleMeasure, true);
            window.addEventListener('resize', scheduleMeasure);
            window.addEventListener('keydown', onKey);
        } else {
            teardown();
        }
    },
    { immediate: true }
);

function teardown() {
    window.removeEventListener('scroll', scheduleMeasure, true);
    window.removeEventListener('resize', scheduleMeasure);
    window.removeEventListener('keydown', onKey);

    if (frame !== null) {
        cancelAnimationFrame(frame);
        frame = null;
    }

    if (settle !== null) {
        clearInterval(settle);
        settle = null;
    }
}

onBeforeUnmount(teardown);
</script>

<style lang="scss" scoped>
// Above the app bar and the drawers, below the dialogs — a tour explains
// the page, it does not sit on top of a confirm
.help-overlay__blocker {
    position: fixed;
    inset: 0;
    z-index: 2190;
}

.help-overlay__dim {
    position: fixed;
    inset: 0;
    z-index: 2191;
    background: rgba(0, 0, 0, 0.55);
    pointer-events: none;
}

// The dim is the spotlight's own shadow, spread far enough to cover any
// screen — which is what leaves a lit hole exactly the size of the element
.help-overlay__spotlight {
    position: fixed;
    z-index: 2195;
    border-radius: 10px;
    box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.55);
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
    pointer-events: none;
    transition: top 0.2s ease, left 0.2s ease, width 0.2s ease, height 0.2s ease;
}

.help-overlay__card {
    position: fixed;
    z-index: 2200;
    // Opaque like the drawers and menus: the app-wide surface opacity
    // would let the dimmed page read through the explanation of it
    background: rgb(var(--v-theme-surface)) !important;
}

.help-overlay__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    border: none;
    padding: 0;
    cursor: pointer;
    background: rgba(var(--v-theme-on-surface), 0.25);
    transition: background 0.15s, transform 0.15s;

    &--on {
        background: rgb(var(--v-theme-primary));
        transform: scale(1.35);
    }
}
</style>
