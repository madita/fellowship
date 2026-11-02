<template>
    <v-menu v-if="enabled" v-model="open" transition="slide-y-transition">
        <template #activator="{ props }">
            <v-btn
                icon
                variant="text"
                size="small"
                data-help="toolbar-help"
                v-bind="props"
                :title="$t('help.title')"
                :aria-label="$t('help.title')"
            >
                <!-- A dot while there is help for this page nobody has
                     read yet, so a new tour is noticed without opening by
                     itself -->
                <v-badge
                    :model-value="hasUnread"
                    color="primary"
                    dot
                    offset-x="2"
                    offset-y="2"
                >
                    <v-icon>mdi-help-circle-outline</v-icon>
                </v-badge>
            </v-btn>
        </template>

        <v-card min-width="min(320px, calc(100vw - 24px))" max-width="min(380px, calc(100vw - 24px))">
            <v-card-title class="text-subtitle-1 font-weight-bold py-2 px-4">
                {{ $t('help.title') }}
            </v-card-title>

            <v-divider />

            <empty-state
                v-if="!store.launchable.length"
                compact
                icon="mdi-help-circle-outline"
                :title="$t('help.noneForPage')"
            />

            <v-list v-else density="compact" class="py-0">
                <v-list-item
                    v-for="tour in store.launchable"
                    :key="tour.id"
                    @click="launch(tour)"
                >
                    <template #prepend>
                        <v-avatar :color="tour.color || 'primary'" size="32">
                            <v-icon size="16" color="white">{{ tour.icon || 'mdi-help-circle-outline' }}</v-icon>
                        </v-avatar>
                    </template>

                    <v-list-item-title class="text-body-2 font-weight-medium">
                        {{ tour.title }}
                    </v-list-item-title>
                    <v-list-item-subtitle v-if="tour.description" class="text-caption text-wrap">
                        {{ tour.description }}
                    </v-list-item-subtitle>

                    <template #append>
                        <v-icon v-if="tour.completed" size="16" color="success">mdi-check</v-icon>
                        <v-icon v-else size="16" color="medium-emphasis">
                            {{ tour.mode === 'hints' ? 'mdi-map-marker-outline' : 'mdi-play' }}
                        </v-icon>
                    </template>
                </v-list-item>
            </v-list>

            <template v-if="authenticated">
                <v-divider />

                <div class="text-center py-1">
                    <v-btn
                        variant="text"
                        size="x-small"
                        color="medium-emphasis"
                        :loading="resetting"
                        @click="resetSeen"
                    >
                        {{ $t('help.showAgain') }}
                    </v-btn>
                </div>
            </template>
        </v-card>
    </v-menu>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useHelpStore } from '@/store/helpStore.js';
import { useSettingsStore } from '@/store/settingStore.js';
import { useAuthStore } from '@/store/authStore.js';
import EmptyState from '@/components/common/EmptyState.vue';

/**
 * The way back to help that has already been read, or that never opened
 * by itself. Lists whatever was written for the page being looked at.
 *
 * A hints-mode tour has nothing to start — its markers are already on the
 * page — so picking one scrolls its first marker into view and opens it.
 */

const store = useHelpStore();
const settings = useSettingsStore();
const authStore = useAuthStore();

const open = ref(false);
const resetting = ref(false);

const enabled = computed(() => settings.isFeatureEnabled('help'));
const authenticated = computed(() => authStore.isLoggedIn);

const hasUnread = computed(() => store.launchable.some(tour => !tour.seen));

function launch(tour) {
    open.value = false;

    if (tour.mode === 'hints') {
        const first = tour.steps.find(step => step.target);

        store.markSeen(tour, false);

        if (first) store.openHint(first.id);

        return;
    }

    store.start(tour);
}

async function resetSeen() {
    resetting.value = true;

    try {
        await store.resetSeen();
    } finally {
        resetting.value = false;
        open.value = false;
    }
}
</script>
