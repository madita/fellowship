<template>
    <div class="flex-grow-1">
        <v-container fluid class="pa-2 pa-sm-4">
            <v-card elevation="2">
                <v-card-title class="text-h6 text-sm-h5 font-weight-bold pa-3 pa-sm-6 bg-gradient">
                    <div class="d-flex align-center justify-space-between w-100">
                        <div class="d-flex align-center">
                            <v-icon class="mr-2 mr-sm-3" :size="$vuetify.display.mobile ? 24 : 28">mdi-cog</v-icon>
                            <span class="d-none d-sm-inline">{{ $t('settings.overview.applicationSettings') }}</span>
                            <span class="d-sm-none">{{ $t('settings.overview.settings') }}</span>
                        </div>
                        <div class="d-flex align-center gap-2">
                            <!-- Save Button (Tab view only) -->
                            <v-btn
                                v-if="viewMode === 'tabs'"
                                :loading="isSaving"
                                color="white"
                                variant="elevated"
                                @click="saveSettings"
                                prepend-icon="mdi-content-save"
                                class="d-none d-sm-flex"
                            >
                                {{ $t('common.save') }}
                            </v-btn>
                            <!-- View Toggle -->
                            <v-btn-toggle
                                v-model="viewMode"
                                mandatory
                                density="compact"
                                variant="outlined"
                                color="white"
                                class="view-toggle"
                            >
                                <v-btn value="overview" size="small">
                                    <v-icon size="18" :class="{ 'mr-1': !$vuetify.display.mobile }">mdi-view-grid-outline</v-icon>
                                    <span class="d-none d-sm-inline">{{ $t('settings.overview.overview') }}</span>
                                </v-btn>
                                <v-btn value="tabs" size="small">
                                    <v-icon size="18" :class="{ 'mr-1': !$vuetify.display.mobile }">mdi-tab</v-icon>
                                    <span class="d-none d-sm-inline">{{ $t('settings.overview.tabs') }}</span>
                                </v-btn>
                            </v-btn-toggle>
                        </div>
                    </div>
                </v-card-title>

                <v-divider></v-divider>

                <!-- Alert Message (Tab view) -->
                <v-alert
                    v-if="viewMode === 'tabs' && message"
                    :type="alertType"
                    class="mx-4 mx-sm-6 mt-4 mb-0"
                    closable
                    @click:close="message = ''"
                >
                    {{ message }}
                </v-alert>

                <!-- Search Bar (Overview mode only) -->
                <div v-if="viewMode === 'overview'" class="pa-4">
                    <v-text-field
                        v-model="searchQuery"
                        prepend-inner-icon="mdi-magnify"
                        :label="$t('settings.overview.searchSettings')"
                        variant="outlined"
                        density="comfortable"
                        hide-details
                        clearable
                        @click:clear="searchQuery = ''"
                    ></v-text-field>
                </div>

                <!-- Quick Stats (Overview mode only) -->
                <div v-if="viewMode === 'overview'" class="px-4 pb-2">
                    <v-row dense>
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
                </div>

                <v-divider v-if="viewMode === 'overview'" class="mt-2"></v-divider>

                <!-- Tab View -->
                <template v-if="viewMode === 'tabs'">
                    <v-tabs
                        v-model="currentTab"
                        bg-color="transparent"
                        color="primary"
                        show-arrows
                        center-active
                    >
                        <v-tab value="general">
                            <v-icon class="mr-2" size="20">mdi-cog-outline</v-icon>
                            <span class="d-none d-sm-inline">{{ $t('settings.overview.tabGeneral') }}</span>
                        </v-tab>
                        <v-tab value="localization">
                            <v-icon class="mr-2" size="20">mdi-earth</v-icon>
                            <span class="d-none d-sm-inline">{{ $t('settings.overview.tabLocalization') }}</span>
                        </v-tab>
                        <v-tab value="branding">
                            <v-icon class="mr-2" size="20">mdi-palette-outline</v-icon>
                            <span class="d-none d-sm-inline">{{ $t('settings.overview.tabBranding') }}</span>
                        </v-tab>
                        <v-tab value="theme">
                            <v-icon class="mr-2" size="20">mdi-theme-light-dark</v-icon>
                            <span class="d-none d-sm-inline">{{ $t('settings.overview.tabTheme') }}</span>
                        </v-tab>
                        <v-tab value="oauth">
                            <v-icon class="mr-2" size="20">mdi-login-variant</v-icon>
                            <span class="d-none d-sm-inline">{{ $t('settings.overview.tabOAuth') }}</span>
                        </v-tab>
                        <v-tab value="seo">
                            <v-icon class="mr-2" size="20">mdi-search-web</v-icon>
                            <span class="d-none d-sm-inline">{{ $t('settings.overview.tabSEO') }}</span>
                        </v-tab>
                        <v-tab value="homepage">
                            <v-icon class="mr-2" size="20">mdi-home-edit-outline</v-icon>
                            <span class="d-none d-sm-inline">{{ $t('settings.overview.tabHomepage') }}</span>
                        </v-tab>
                        <v-tab value="footer">
                            <v-icon class="mr-2" size="20">mdi-page-layout-footer</v-icon>
                            <span class="d-none d-sm-inline">{{ $t('settings.overview.tabFooter') }}</span>
                        </v-tab>
                        <v-tab value="moderation">
                            <v-icon class="mr-2" size="20">mdi-shield-check-outline</v-icon>
                            <span class="d-none d-sm-inline">{{ $t('settings.overview.tabModeration') }}</span>
                        <v-tab value="sandbox">
                            <v-icon class="mr-2" size="20">mdi-notebook-edit-outline</v-icon>
                            <span class="d-none d-sm-inline">Sandbox</span>
                        </v-tab>
                        <v-tab value="advanced">
                            <v-icon class="mr-2" size="20">mdi-cog-sync-outline</v-icon>
                            <span class="d-none d-sm-inline">{{ $t('settings.overview.tabAdvanced') }}</span>
                        </v-tab>
                    </v-tabs>

                    <v-divider></v-divider>

                    <v-card-text class="pa-4 pa-sm-6">
                        <v-window v-model="currentTab">
                            <v-window-item value="general">
                                <general-tab
                                    :settings="settings"
                                    :errors="errors"
                                    :is-saving="isSaving"
                                    @save="saveSettings"
                                />
                            </v-window-item>
                            <v-window-item value="localization">
                                <localization-tab
                                    :settings="settings"
                                    :errors="errors"
                                    :is-saving="isSaving"
                                    @save="saveSettings"
                                />
                            </v-window-item>
                            <v-window-item value="branding">
                                <branding-tab
                                    :settings="settings"
                                    :errors="errors"
                                    :is-saving="isSaving"
                                    @save="saveSettings"
                                />
                            </v-window-item>
                            <v-window-item value="theme">
                                <theme-tab
                                    :settings="settings"
                                    :errors="errors"
                                    :is-saving="isSaving"
                                    @save="saveSettings"
                                />
                            </v-window-item>
                            <v-window-item value="oauth">
                                <o-auth-tab
                                    :settings="settings"
                                    :errors="errors"
                                    :is-saving="isSaving"
                                    @save="saveSettings"
                                />
                            </v-window-item>
                            <v-window-item value="seo">
                                <seo-tab
                                    :settings="settings"
                                    :errors="errors"
                                    :is-saving="isSaving"
                                    @save="saveSettings"
                                />
                            </v-window-item>
                            <v-window-item value="homepage">
                                <homepage-tab
                                    :settings="settings"
                                    :errors="errors"
                                    :is-saving="isSaving"
                                    @save="saveSettings"
                                />
                            </v-window-item>
                            <v-window-item value="footer">
                                <footer-tab
                                    :settings="settings"
                                    :errors="errors"
                                    :is-saving="isSaving"
                                    @save="saveSettings"
                                />
                            </v-window-item>
                            <v-window-item value="moderation">
                                <moderation-tab
                            <v-window-item value="sandbox">
                                <sandbox-tab
                                    :settings="settings"
                                    :errors="errors"
                                    :is-saving="isSaving"
                                    @save="saveSettings"
                                />
                            </v-window-item>
                            <v-window-item value="advanced">
                                <advanced-tab
                                    :settings="settings"
                                    :errors="errors"
                                    :is-saving="isSaving"
                                    @save="saveSettings"
                                />
                            </v-window-item>
                        </v-window>

                        <!-- Mobile Save Button -->
                        <v-btn
                            :loading="isSaving"
                            block
                            size="large"
                            color="primary"
                            @click="saveSettings"
                            prepend-icon="mdi-content-save"
                            class="d-sm-none mt-4"
                        >
                            {{ $t('settings.saveSettings') }}
                        </v-btn>
                    </v-card-text>
                </template>

                <!-- Overview View -->
                <v-card-text v-else class="pa-4 pa-md-6">
                    <!-- No Results -->
                    <v-alert
                        v-if="searchQuery && filteredSettingsCount === 0"
                        type="info"
                        variant="tonal"
                        class="mb-4"
                    >
                        {{ $t('settings.overview.noResultsMessage', { query: searchQuery }) }}
                    </v-alert>

                    <!-- Settings grouped by category -->
                    <div
                        v-for="category in filteredCategories"
                        :key="category.id"
                        class="mb-6"
                    >
                        <!-- Category Header -->
                        <div class="d-flex align-center mb-3">
                            <v-avatar :color="category.color" size="36" class="mr-3">
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
                                    @click="navigateToSetting(category.id, setting.id)"
                                />
                            </v-col>
                        </v-row>

                        <v-divider v-if="category !== filteredCategories[filteredCategories.length - 1]" class="mt-4"></v-divider>
                    </div>
                </v-card-text>
            </v-card>
        </v-container>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { settingsCategories, getTotalSettingsCount } from '@/configs/settingsConfig';
import { useSettings } from '@/composables/useSettings';
import SettingsItemCard from '@/components/settings/SettingsItemCard.vue';

const { t } = useI18n();

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

const router = useRouter();
const searchQuery = ref('');

// Settings composable for tab view
const {
    settings,
    isSaving,
    errors,
    fetchSettings,
    saveSettings: saveSettingsApi,
    showMessage
} = useSettings();

const message = ref('');
const alertType = ref('success');

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

async function saveSettings() {
    try {
        await saveSettingsApi();
        message.value = t('settings.overview.saved');
        alertType.value = 'success';
    } catch (error) {
        message.value = error.message || t('settings.overview.saveError');
        alertType.value = 'error';
    }
}

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

function navigateToSetting(categoryId, settingId) {
    router.push({
        name: 'admin-settings-page',
        params: {
            category: categoryId,
            setting: settingId
        }
    });
}
</script>

<style scoped>
.bg-gradient {
    background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-secondary)) 100%);
    color: white;
}

.w-100 {
    width: 100%;
}

.view-toggle {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 4px;
}

.view-toggle .v-btn {
    color: rgba(255, 255, 255, 0.8) !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
}

.view-toggle .v-btn--active {
    color: white !important;
    background: rgba(255, 255, 255, 0.2) !important;
}

.gap-2 {
    gap: 8px;
}
</style>
