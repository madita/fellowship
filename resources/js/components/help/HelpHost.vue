<template>
    <div v-if="enabled">
        <!-- The admin is out picking an element for a step; everything
             else about help stays out of the way until they are done -->
        <help-element-picker v-if="picking" />

        <template v-else>
            <help-tour-overlay />
            <help-hints />
        </template>
    </div>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useHelpStore, PICK_FLAG, TOUR_FLAG } from '@/store/helpStore.js';
import { useSettingsStore } from '@/store/settingStore.js';
import HelpTourOverlay from '@/components/help/HelpTourOverlay.vue';
import HelpHints from '@/components/help/HelpHints.vue';
import HelpElementPicker from '@/components/help/HelpElementPicker.vue';

/**
 * Where help gets its page from.
 *
 * Mounted once in App.vue, beside the dialogs and the notification toasts.
 * It follows the route, loads whatever help was written for the new page,
 * and opens the one tour that is meant to start by itself the first time
 * somebody arrives.
 *
 * It also hosts picking mode, because that is a thing done on a page
 * rather than in the admin area, and this is the one component that is
 * already mounted on every page.
 */

const route = useRoute();
const store = useHelpStore();
const settings = useSettingsStore();

const enabled = computed(() => settings.isFeatureEnabled('help'));

const picking = computed(() => Boolean(route.query[PICK_FLAG]) && store.pendingPick() !== null);

watch(
    () => [route.path, route.query[TOUR_FLAG], enabled.value, picking.value],
    async () => {
        if (!enabled.value) {
            store.tours = [];

            return;
        }

        // Nothing is offered while an element is being picked: the page
        // has to be the page, not a page with a tour running on it
        if (picking.value) {
            store.stop();
            store.tours = [];

            return;
        }

        // An admin previewing a particular tour from the editor: that one
        // runs whether or not they have seen it before, and the help is
        // re-read because they have almost certainly just changed it
        const asked = route.query[TOUR_FLAG];

        await store.fetchForPath(route.path, { force: Boolean(asked) });

        if (asked) {
            const tour = store.tours.find(candidate => candidate.key === asked);

            if (tour) {
                store.start(tour);

                return;
            }
        }

        const pending = store.pendingAutoStart();
        if (pending) store.start(pending);
    },
    { immediate: true }
);
</script>
