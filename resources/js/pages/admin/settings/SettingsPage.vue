<template>
    <div class="flex-grow-1">
        <!-- Loading State -->
        <v-container v-if="isLoading" fluid class="pa-2 pa-sm-4">
            <loading-state :text="$t('settings.page.loading')" />
        </v-container>

        <!-- Error/404 State -->
        <template v-else-if="loadError || !setting">
            <page-header
                :title="loadError ? $t('settings.page.loadError') : $t('settings.page.notFound')"
                icon="mdi-alert-circle-outline"
                :back-to="{ name: 'admin-settings' }"
                fluid
            />
            <v-container fluid class="pa-2 pa-sm-4">
                <empty-state
                    icon="mdi-alert-circle-outline"
                    :title="loadError ? $t('settings.page.loadError') : $t('settings.page.notFound')"
                    :text="loadError || $t('settings.page.notFoundMessage')"
                >
                    <template #actions>
                        <v-btn
                            color="primary"
                            variant="flat"
                            prepend-icon="mdi-arrow-left"
                            @click="goBack"
                        >
                            {{ $t('settings.category.backToSettings') }}
                        </v-btn>
                    </template>
                </empty-state>
            </v-container>
        </template>

        <!-- Dynamic Component -->
        <component
            v-else
            :is="dynamicComponent"
            :settings="settings"
            :errors="errors"
            :is-saving="isSaving"
            :category="category"
            :setting="setting"
            @save="saveSettings"
        />
    </div>
</template>

<script setup>
import { ref, computed, watch, defineAsyncComponent, shallowRef } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { getCategoryBySlug, getSettingBySlug } from '@/configs/settingsConfig';
import { useSettings } from '@/composables/useSettings';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';

const router = useRouter();
const route = useRoute();

const { settings, isSaving, errors, fetchSettings, saveSettings } = useSettings();

const isLoading = ref(true);
const loadError = ref(null);
const dynamicComponent = shallowRef(null);

const category = computed(() => getCategoryBySlug(route.params.category));
const setting = computed(() => getSettingBySlug(route.params.category, route.params.setting));

// Every settings page, found by where it lives rather than listed here
// one by one. A page belongs to the category whose folder it sits in,
// so adding one - or moving it to another category - needs no entry in
// this file. Still lazy: each value is an import waiting to be called.
const pageModules = import.meta.glob('./*/*.vue');

async function loadComponent() {
    isLoading.value = true;
    loadError.value = null;

    try {
        // Fetch settings data
        await fetchSettings();

        // Load the dynamic component
        if (setting.value && category.value) {
            const componentKey = `./${route.params.category}/${setting.value.component}.vue`;
            const importFn = pageModules[componentKey];

            if (importFn) {
                dynamicComponent.value = defineAsyncComponent({
                    loader: importFn,
                    loadingComponent: null,
                    errorComponent: null,
                    delay: 0,
                    timeout: 30000,
                });
            } else {
                loadError.value = `Component not found: ${componentKey}`;
            }
        }
    } catch (error) {
        console.error('Failed to load settings page:', error);
        loadError.value = error.message || 'Failed to load settings';
    } finally {
        isLoading.value = false;
    }
}

function goBack() {
    router.push({ name: 'admin-settings' });
}

// Watch for route changes
watch(
    () => [route.params.category, route.params.setting],
    () => {
        loadComponent();
    },
    { immediate: true }
);
</script>
