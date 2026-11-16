<template>
    <div class="help-picker">
        <!-- Follows the cursor around the thing under it -->
        <div v-if="rect" class="help-picker__outline" :style="outline" />

        <v-card class="help-picker__bar" rounded="lg" elevation="12">
            <div class="d-flex align-center pa-3 ga-3 flex-wrap">
                <v-icon color="primary">mdi-target</v-icon>

                <div class="flex-grow-1" style="min-width: 180px;">
                    <div class="text-subtitle-2 font-weight-bold">{{ $t('help.picker.title') }}</div>
                    <div class="text-caption text-medium-emphasis">
                        {{ selector || $t('help.picker.hint') }}
                    </div>
                </div>

                <v-btn variant="text" size="small" @click="cancel">
                    {{ $t('common.cancel') }}
                </v-btn>
            </div>
        </v-card>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useHelpStore } from '@/store/helpStore.js';
import { rectOf, selectorFor } from '@/utils/helpAnchor.js';

/**
 * Picking mode: the admin is on a real page, hovering highlights whatever
 * is under the cursor, and clicking writes down a selector for it and goes
 * back to the editor with the draft intact.
 *
 * Turned on by `?help_pick=1`, which HelpHost watches for. Everything on
 * the page is suppressed while it is on — a click here must not also
 * follow the link it landed on.
 */

const store = useHelpStore();
const router = useRouter();

const rect = ref(null);
const selector = ref(null);

let hovered = null;

const outline = computed(() => {
    if (!rect.value) return {};

    return {
        top: `${rect.value.top}px`,
        left: `${rect.value.left}px`,
        width: `${rect.value.width}px`,
        height: `${rect.value.height}px`,
    };
});

/** What is under the cursor, ignoring our own overlay. */
function elementUnder(event) {
    const candidate = event.target;

    if (!candidate || candidate.nodeType !== 1) return null;
    if (candidate.closest('.help-picker')) return null;

    return candidate;
}

function onMove(event) {
    const element = elementUnder(event);

    if (!element || element === hovered) return;

    hovered = element;
    rect.value = rectOf(element);
    selector.value = selectorFor(element);
}

function onClick(event) {
    const element = elementUnder(event);
    if (!element) return;

    // Before anything on the page gets to act on it
    event.preventDefault();
    event.stopPropagation();

    const picked = selectorFor(element);
    if (!picked) return;

    const returnTo = store.finishPicking(picked);

    router.push(returnTo || { name: 'admin-settings' });
}

function onKey(event) {
    if (event.key === 'Escape') cancel();
}

function cancel() {
    const pending = store.pendingPick();

    store.cancelPicking();
    router.push(pending?.returnTo || { name: 'admin-settings' });
}

onMounted(() => {
    // Capture phase throughout: the page must not see any of it
    document.addEventListener('mousemove', onMove, true);
    document.addEventListener('click', onClick, true);
    window.addEventListener('keydown', onKey, true);
    document.body.classList.add('help-picking');
});

onBeforeUnmount(() => {
    document.removeEventListener('mousemove', onMove, true);
    document.removeEventListener('click', onClick, true);
    window.removeEventListener('keydown', onKey, true);
    document.body.classList.remove('help-picking');
});
</script>

<style lang="scss" scoped>
.help-picker__outline {
    position: fixed;
    z-index: 2300;
    pointer-events: none;
    border: 2px solid rgb(var(--v-theme-primary));
    border-radius: 4px;
    background: rgba(var(--v-theme-primary), 0.12);
    transition: top 0.08s linear, left 0.08s linear, width 0.08s linear, height 0.08s linear;
}

.help-picker__bar {
    position: fixed;
    z-index: 2310;
    bottom: 16px;
    left: 50%;
    transform: translateX(-50%);
    width: min(520px, calc(100vw - 32px));
    background: rgb(var(--v-theme-surface)) !important;
}
</style>

<style lang="scss">
// Not scoped: it is the page being picked from that has to show the
// crosshair, not this component
body.help-picking,
body.help-picking * {
    cursor: crosshair !important;
}
</style>
