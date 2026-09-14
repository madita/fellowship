<template>
    <div class="flex-grow-1">
        <page-header
            :title="$t('settings.overview.applicationSettings')"
            :subtitle="$t('settings.overview.subtitle')"
            icon="mdi-cog"
            fluid
        >
            <template #actions>
                <!-- Save Button (Tab view only) -->
                <v-btn
                    v-if="viewMode === 'tabs'"
                    :loading="isSaving"
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-content-save"
                    class="d-none d-sm-flex"
                    @click="saveSettings"
                >
                    {{ $t('common.save') }}
                </v-btn>
                <!-- View Toggle -->
                <v-btn-toggle
                    v-model="viewMode"
                    mandatory
                    density="compact"
                    variant="outlined"
                    color="primary"
                    divided
                >
                    <v-btn value="overview" size="small" prepend-icon="mdi-view-grid-outline">
                        <span class="d-none d-sm-inline">{{ $t('settings.overview.overview') }}</span>
                    </v-btn>
                    <v-btn value="tabs" size="small" prepend-icon="mdi-tab">
                        <span class="d-none d-sm-inline">{{ $t('settings.overview.tabs') }}</span>
                    </v-btn>
                </v-btn-toggle>
            </template>

            <!-- Search Bar (Overview mode only) -->
            <v-text-field
                v-if="viewMode === 'overview'"
                v-model="searchQuery"
                prepend-inner-icon="mdi-magnify"
                :label="$t('settings.overview.searchSettings')"
                density="comfortable"
                hide-details
                clearable
                @click:clear="searchQuery = ''"
            ></v-text-field>
        </page-header>

        <v-container fluid class="pa-2 pa-sm-4">
            <!-- Tab View -->
            <v-card v-if="viewMode === 'tabs'" variant="outlined">
                <!-- One tab per settings category, driven by settingsConfig -->
                <v-tabs
                    v-model="currentTab"
                    bg-color="transparent"
                    color="primary"
                    show-arrows
                    center-active
                >
                    <v-tab
                        v-for="category in settingsCategories"
                        :key="category.id"
                        :value="category.id"
                        :prepend-icon="category.icon"
                    >
                        <span class="d-none d-sm-inline">{{ tabLabel(category) }}</span>
                    </v-tab>
                </v-tabs>

                <v-divider></v-divider>

                <v-card-text class="pa-4 pa-sm-6">
                    <v-window v-model="currentTab">
                        <v-window-item
                            v-for="category in settingsCategories"
                            :key="category.id"
                            :value="category.id"
                        >
                            <!-- Categories with a form render their tab component -->
                            <component
                                :is="tabComponents[category.id]"
                                v-if="tabComponents[category.id]"
                                :settings="settings"
                                :errors="errors"
                                :is-saving="isSaving"
                                @save="saveSettings"
                            />

                            <!-- Pages and tools outside the form are linked as cards -->
                            <template v-if="linkedSettings(category).length">
                                <div
                                    v-if="tabComponents[category.id]"
                                    class="text-subtitle-1 font-weight-medium mt-6 mb-3"
                                >
                                    {{ $t('settings.overview.moreInCategory') }}
                                </div>
                                <v-row>
                                    <v-col
                                        v-for="setting in linkedSettings(category)"
                                        :key="`${category.id}-${setting.id}`"
                                        cols="12"
                                        sm="6"
                                        lg="4"
                                    >
                                        <settings-item-card
                                            :title="setting.title"
                                            :description="setting.description"
                                            :icon="setting.icon"
                                            :color="category.color"
                                            @click="navigateToSetting(category.id, setting)"
                                        />
                                    </v-col>
                                </v-row>
                            </template>
                        </v-window-item>
                    </v-window>

                    <!-- Mobile Save Button -->
                    <v-btn
                        v-if="tabComponents[currentTab]"
                        :loading="isSaving"
                        block
                        size="large"
                        color="primary"
                        variant="elevated"
                        prepend-icon="mdi-content-save"
                        class="d-sm-none mt-4"
                        @click="saveSettings"
                    >
                        {{ $t('settings.saveSettings') }}
                    </v-btn>
                </v-card-text>
            </v-card>

            <!-- Overview View -->
            <template v-else>
                <!-- Quick Stats -->
                <v-row dense class="mb-4">
                    <v-col cols="6" sm="4" md="2">
                        <v-card variant="tonal" color="primary">
                            <v-card-text class="text-center pa-3">
                                <div class="text-h5 font-weight-bold">{{ categoriesCount }}</div>
                                <div class="text-caption">{{ $t('settings.overview.categories') }}</div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                    <v-col cols="6" sm="4" md="2">
                        <v-card variant="tonal" color="success">
                            <v-card-text class="text-center pa-3">
                                <div class="text-h5 font-weight-bold">{{ totalSettings }}</div>
                                <div class="text-caption">{{ $t('settings.overview.settings') }}</div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                    <v-col v-if="searchQuery" cols="12" sm="4" md="2">
                        <v-card variant="tonal" color="info">
                            <v-card-text class="text-center pa-3">
                                <div class="text-h5 font-weight-bold">{{ filteredSettingsCount }}</div>
                                <div class="text-caption">{{ $t('settings.overview.results') }}</div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>

                <!-- No Results -->
                <empty-state
                    v-if="searchQuery && filteredSettingsCount === 0"
                    icon="mdi-magnify-close"
                    :title="$t('settings.overview.noResultsMessage', { query: searchQuery })"
                />

                <!-- Settings grouped by category -->
                <div
                    v-for="category in filteredCategories"
                    :key="category.id"
                    class="mb-6"
                >
                    <!-- Category Header -->
                    <div class="d-flex align-center ga-3 mb-3">
                        <v-avatar :color="category.color" size="36">
                            <v-icon color="white" size="20">{{ category.icon }}</v-icon>
                        </v-avatar>
                        <div>
                            <div class="text-h6 font-weight-medium">{{ category.title }}</div>
                            <div class="text-caption text-medium-emphasis">{{ category.description }}</div>
                        </div>
                    </div>

                    <!-- Settings Grid -->
                    <v-row>
                        <v-col
                            v-for="setting in getFilteredSettings(category)"
                            :key="`${category.id}-${setting.id}`"
                            cols="12"
                            sm="6"
                            lg="4"
                        >
                            <settings-item-card
                                :title="setting.title"
                                :description="setting.description"
                                :icon="setting.icon"
                                :color="category.color"
                                @click="navigateToSetting(category.id, setting)"
                            />
                        </v-col>
                    </v-row>

                    <v-divider v-if="category !== filteredCategories[filteredCategories.length - 1]" class="mt-4"></v-divider>
                </div>
            </template>
        </v-container>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { settingsCategories, getTotalSettingsCount } from '@/configs/settingsConfig';
import { useSettings } from '@/composables/useSettings';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import SettingsItemCard from '@/components/settings/SettingsItemCard.vue';

// Tab Components
import GeneralTab from '@/components/settings/tabs/GeneralTab.vue';
import LocalizationTab from '@/components/settings/tabs/LocalizationTab.vue';
import BrandingTab from '@/components/settings/tabs/BrandingTab.vue';
import ThemeTab from '@/components/settings/tabs/ThemeTab.vue';
import OAuthTab from '@/components/settings/tabs/OAuthTab.vue';
import SeoTab from '@/components/settings/tabs/SeoTab.vue';
import HomepageTab from '@/components/settings/tabs/HomepageTab.vue';
import FooterTab from '@/components/settings/tabs/FooterTab.vue';
import SandboxTab from '@/components/settings/tabs/SandboxTab.vue';
import AdvancedTab from '@/components/settings/tabs/AdvancedTab.vue';
import ModerationTab from '@/components/settings/tabs/ModerationTab.vue';

const { t } = useI18n();
const router = useRouter();
const searchQuery = ref('');

// Categories whose settings are edited in one form on the tabs view.
// Every other category (and every routeName item) is linked as cards.
const tabComponents = {
    general: GeneralTab,
    localization: LocalizationTab,
    branding: BrandingTab,
    theme: ThemeTab,
    oauth: OAuthTab,
    seo: SeoTab,
    homepage: HomepageTab,
    footer: FooterTab,
    moderation: ModerationTab,
    sandbox: SandboxTab,
    advanced: AdvancedTab,
};

// Translated tab labels where a key exists; the config title otherwise.
const tabLabelKeys = {
    general: 'tabGeneral',
    localization: 'tabLocalization',
    branding: 'tabBranding',
    theme: 'tabTheme',
    oauth: 'tabOAuth',
    seo: 'tabSEO',
    homepage: 'tabHomepage',
    footer: 'tabFooter',
    moderation: 'tabModeration',
    sandbox: 'tabSandbox',
    advanced: 'tabAdvanced',
};

function tabLabel(category) {
    const key = tabLabelKeys[category.id];
    return key ? t(`settings.overview.${key}`) : category.title;
}

// Items shown as cards on a tab: everything for categories without a
// form, only the dedicated pages (routeName) for categories with one.
function linkedSettings(category) {
    if (!tabComponents[category.id]) return category.settings;
    return category.settings.filter(setting => setting.routeName);
}

// Settings composable for tab view
const {
    settings,
    isSaving,
    errors,
    fetchSettings,
    saveSettings
} = useSettings();

// View mode with localStorage persistence
const STORAGE_KEY = 'settings_view_mode';
const savedViewMode = localStorage.getItem(STORAGE_KEY);
const viewMode = ref(savedViewMode || 'overview');
const currentTab = ref('general');

// Persist view mode changes
watch(viewMode, (newMode) => {
    localStorage.setItem(STORAGE_KEY, newMode);
    // Fetch settings when switching to tab view
    if (newMode === 'tabs') {
        fetchSettings();
    }
});

// Fetch settings on mount if tab view is selected
onMounted(() => {
    if (viewMode.value === 'tabs') {
        fetchSettings();
    }
});

const categoriesCount = computed(() => settingsCategories.length);
const totalSettings = computed(() => getTotalSettingsCount());

// Filter categories based on search query
const filteredCategories = computed(() => {
    if (!searchQuery.value) {
        return settingsCategories;
    }

    const query = searchQuery.value.toLowerCase();
    return settingsCategories.filter(category => {
        // Check if category matches
        const categoryMatches = category.title.toLowerCase().includes(query) ||
            category.description.toLowerCase().includes(query);

        // Check if any settings in this category match
        const hasMatchingSettings = category.settings.some(setting =>
            setting.title.toLowerCase().includes(query) ||
            setting.description.toLowerCase().includes(query)
        );

        return categoryMatches || hasMatchingSettings;
    });
});

// Get filtered settings for a category
function getFilteredSettings(category) {
    if (!searchQuery.value) {
        return category.settings;
    }

    const query = searchQuery.value.toLowerCase();

    // If category title matches, show all settings
    const categoryMatches = category.title.toLowerCase().includes(query) ||
        category.description.toLowerCase().includes(query);

    if (categoryMatches) {
        return category.settings;
    }

    // Otherwise filter settings
    return category.settings.filter(setting =>
        setting.title.toLowerCase().includes(query) ||
        setting.description.toLowerCase().includes(query)
    );
}

// Count total filtered settings
const filteredSettingsCount = computed(() => {
    return filteredCategories.value.reduce((total, category) => {
        return total + getFilteredSettings(category).length;
    }, 0);
});

function navigateToSetting(categoryId, setting) {
    // Settings may opt into a dedicated route by supplying `routeName`.
    if (setting.routeName) {
        router.push({ name: setting.routeName });
        return;
    }
    router.push({
        name: 'admin-settings-page',
        params: {
            category: categoryId,
            setting: setting.id
        }
    });
}
</script>
