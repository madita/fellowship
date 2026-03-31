<template>
    <div class="flex-grow-1">
        <!-- Loading State -->
        <v-container v-if="isLoading" fluid class="pa-2 pa-sm-4">
            <v-card elevation="2">
                <v-card-text class="text-center py-12">
                    <v-progress-circular indeterminate color="primary" size="48"></v-progress-circular>
                    <div class="mt-4 text-body-1">Loading settings...</div>
                </v-card-text>
            </v-card>
        </v-container>

        <!-- Error/404 State -->
        <v-container v-else-if="loadError || !setting" fluid class="pa-2 pa-sm-4">
            <v-card elevation="2">
                <v-card-text class="text-center py-12">
                    <v-icon size="80" color="grey-lighten-1">mdi-alert-circle-outline</v-icon>
                    <div class="text-h5 mt-4">{{ loadError ? 'Error Loading Settings' : 'Setting Not Found' }}</div>
                    <div class="text-body-2 text-medium-emphasis mt-2">
                        {{ loadError || "The settings page you're looking for doesn't exist." }}
                    </div>
                    <v-btn
                        color="primary"
                        class="mt-6"
                        @click="goBack"
                        prepend-icon="mdi-arrow-left"
                    >
                        Back to Settings
                    </v-btn>
                </v-card-text>
            </v-card>
        </v-container>

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
            @message="handleMessage"
        />
    </div>
</template>

<script setup>
import { ref, computed, watch, defineAsyncComponent, shallowRef } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { getCategoryBySlug, getSettingBySlug } from '@/configs/settingsConfig';
import { useSettings } from '@/composables/useSettings';

const router = useRouter();
const route = useRoute();

const { settings, isSaving, errors, fetchSettings, saveSettings, showMessage } = useSettings();

const isLoading = ref(true);
const loadError = ref(null);
const dynamicComponent = shallowRef(null);

const category = computed(() => getCategoryBySlug(route.params.category));
const setting = computed(() => getSettingBySlug(route.params.category, route.params.setting));

// Component mapping for dynamic imports
const componentMap = {
    // General
    'general/AppInfoPage': () => import('@/pages/admin/settings/general/AppInfoPage.vue'),
    'general/MaintenanceModePage': () => import('@/pages/admin/settings/general/MaintenanceModePage.vue'),
    'general/AdminContactPage': () => import('@/pages/admin/settings/general/AdminContactPage.vue'),
    'general/PublicContactPage': () => import('@/pages/admin/settings/general/PublicContactPage.vue'),
    'general/SocialMediaPage': () => import('@/pages/admin/settings/general/SocialMediaPage.vue'),

    // Localization
    'localization/RegionalSettingsPage': () => import('@/pages/admin/settings/localization/RegionalSettingsPage.vue'),
    'localization/LanguageOptionsPage': () => import('@/pages/admin/settings/localization/LanguageOptionsPage.vue'),

    // Branding
    'branding/LogosIconsPage': () => import('@/pages/admin/settings/branding/LogosIconsPage.vue'),
    'branding/BrandingOptionsPage': () => import('@/pages/admin/settings/branding/BrandingOptionsPage.vue'),

    // Theme
    'theme/ThemeModePage': () => import('@/pages/admin/settings/theme/ThemeModePage.vue'),
    'theme/LightColorsPage': () => import('@/pages/admin/settings/theme/LightColorsPage.vue'),
    'theme/DarkColorsPage': () => import('@/pages/admin/settings/theme/DarkColorsPage.vue'),
    'theme/BackgroundImagesPage': () => import('@/pages/admin/settings/theme/BackgroundImagesPage.vue'),
    'theme/TypographyPage': () => import('@/pages/admin/settings/theme/TypographyPage.vue'),

    // OAuth
    'oauth/GoogleOAuthPage': () => import('@/pages/admin/settings/oauth/GoogleOAuthPage.vue'),
    'oauth/DiscordOAuthPage': () => import('@/pages/admin/settings/oauth/DiscordOAuthPage.vue'),
    'oauth/GitHubOAuthPage': () => import('@/pages/admin/settings/oauth/GitHubOAuthPage.vue'),
    'oauth/FacebookOAuthPage': () => import('@/pages/admin/settings/oauth/FacebookOAuthPage.vue'),
    'oauth/OAuthGeneralPage': () => import('@/pages/admin/settings/oauth/OAuthGeneralPage.vue'),

    // SEO
    'seo/MetadataPage': () => import('@/pages/admin/settings/seo/MetadataPage.vue'),
    'seo/OpenGraphPage': () => import('@/pages/admin/settings/seo/OpenGraphPage.vue'),
    'seo/TwitterCardPage': () => import('@/pages/admin/settings/seo/TwitterCardPage.vue'),
    'seo/SearchEnginePage': () => import('@/pages/admin/settings/seo/SearchEnginePage.vue'),

    // Homepage
    'homepage/SectionsPage': () => import('@/pages/admin/settings/homepage/SectionsPage.vue'),
    'homepage/WidgetsPage': () => import('@/pages/admin/settings/homepage/WidgetsPage.vue'),
    'homepage/MenuPage': () => import('@/pages/admin/settings/homepage/MenuPage.vue'),

    // Footer
    'footer/FooterSectionsPage': () => import('@/pages/admin/settings/footer/FooterSectionsPage.vue'),
    'footer/FooterWidgetsPage': () => import('@/pages/admin/settings/footer/FooterWidgetsPage.vue'),
    'footer/CustomHtmlPage': () => import('@/pages/admin/settings/footer/CustomHtmlPage.vue'),

    // Sandbox
    'sandbox/ServerStatusPage': () => import('@/pages/admin/settings/sandbox/ServerStatusPage.vue'),
    'sandbox/FeatureSettingsPage': () => import('@/pages/admin/settings/sandbox/FeatureSettingsPage.vue'),
    'sandbox/LimitsPage': () => import('@/pages/admin/settings/sandbox/LimitsPage.vue'),
    'sandbox/StatisticsPage': () => import('@/pages/admin/settings/sandbox/StatisticsPage.vue'),

    // Advanced
    'advanced/CachingPage': () => import('@/pages/admin/settings/advanced/CachingPage.vue'),
    'advanced/PwaPage': () => import('@/pages/admin/settings/advanced/PwaPage.vue'),
    'advanced/NewsletterPage': () => import('@/pages/admin/settings/advanced/NewsletterPage.vue'),
    'advanced/ApiSettingsPage': () => import('@/pages/admin/settings/advanced/ApiSettingsPage.vue'),
    'advanced/CookieConsentPage': () => import('@/pages/admin/settings/advanced/CookieConsentPage.vue'),
    'advanced/CustomScriptsPage': () => import('@/pages/admin/settings/advanced/CustomScriptsPage.vue'),
    'advanced/LegalCompliancePage': () => import('@/pages/admin/settings/advanced/LegalCompliancePage.vue'),
};

async function loadComponent() {
    isLoading.value = true;
    loadError.value = null;

    try {
        // Fetch settings data
        await fetchSettings();

        // Load the dynamic component
        if (setting.value && category.value) {
            const componentKey = `${route.params.category}/${setting.value.component}`;
            const importFn = componentMap[componentKey];

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

function handleMessage({ text, type }) {
    showMessage(text, type);
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
