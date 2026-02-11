<template>
    <v-dialog
        v-model="showBanner"
        persistent
        :max-width="showDetails ? 700 : 600"
        :scrim-opacity="0.8"
        class="cookie-consent-dialog"
    >
        <v-card class="cookie-consent-card">
            <!-- Main Banner View -->
            <template v-if="!showDetails">
                <v-card-title class="d-flex align-center pa-6 pb-4">
                    <v-icon size="x-large" color="primary" class="mr-3">mdi-cookie</v-icon>
                    <span class="text-h5 font-weight-bold">
                        {{ bannerTitle }}
                    </span>
                </v-card-title>

                <v-card-text class="px-6 pb-6">
                    <p class="text-body-1 mb-4" style="line-height: 1.7;">
                        {{ bannerText }}
                    </p>

                    <v-alert
                        type="info"
                        variant="tonal"
                        density="compact"
                        class="mb-0"
                    >
                        <template #text>
                            {{ $t('cookie.requiresCookies') }}
                            <a
                                v-if="settings.cookie_policy_url"
                                :href="settings.cookie_policy_url"
                                target="_blank"
                                class="text-primary ml-1"
                            >
                                {{ $t('cookie.learnMore') }}
                            </a>
                        </template>
                    </v-alert>
                </v-card-text>

                <v-divider />

                <v-card-actions class="pa-6 pt-4">
                    <v-btn
                        variant="text"
                        @click="showDetails = true"
                    >
                        <v-icon start>mdi-cog</v-icon>
                        {{ $t('common.customize') }}
                    </v-btn>

                    <v-spacer />

                    <v-btn
                        variant="outlined"
                        class="mr-2"
                        @click="acceptNecessary"
                    >
                        {{ $t('cookie.necessaryOnly') }}
                    </v-btn>

                    <v-btn
                        color="primary"
                        size="large"
                        @click="acceptAll"
                    >
                        <v-icon start>mdi-check</v-icon>
                        {{ $t('cookie.acceptAll') }}
                    </v-btn>
                </v-card-actions>
            </template>

            <!-- Detailed Preferences View -->
            <template v-else>
                <v-card-title class="d-flex align-center pa-6 pb-4">
                    <v-icon size="large" color="primary" class="mr-3">mdi-cookie-cog</v-icon>
                    <span class="text-h5 font-weight-bold">{{ $t('cookie.preferences') }}</span>
                    <v-spacer />
                    <v-btn
                        icon
                        variant="text"
                        size="small"
                        @click="showDetails = false"
                    >
                        <v-icon>mdi-arrow-left</v-icon>
                    </v-btn>
                </v-card-title>

                <v-card-text class="px-6 pb-4">
                    <p class="text-body-1 mb-6" style="line-height: 1.7;">
                        {{ preferencesText }}
                    </p>

                    <div class="cookie-categories">
                        <!-- Necessary Cookies -->
                        <v-card variant="outlined" class="mb-3">
                            <v-card-text class="d-flex align-center py-3">
                                <v-icon color="success" class="mr-3">mdi-shield-check</v-icon>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-center">
                                        <span class="font-weight-medium">{{ $t('cookie.necessary') }}</span>
                                        <v-chip size="x-small" color="success" class="ml-2">{{ $t('cookie.alwaysActive') }}</v-chip>
                                    </div>
                                    <div class="text-body-2 text-medium-emphasis mt-1">
                                        {{ $t('cookie.necessaryDescription') }}
                                    </div>
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- Analytics Cookies -->
                        <v-card variant="outlined" class="mb-3">
                            <v-card-text class="d-flex align-center py-3">
                                <v-icon class="mr-3">mdi-chart-line</v-icon>
                                <div class="flex-grow-1">
                                    <div class="font-weight-medium">{{ $t('cookie.analytics') }}</div>
                                    <div class="text-body-2 text-medium-emphasis mt-1">
                                        {{ $t('cookie.analyticsDescription') }}
                                    </div>
                                </div>
                                <v-switch
                                    v-model="preferences.analytics"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                />
                            </v-card-text>
                        </v-card>

                        <!-- Marketing Cookies -->
                        <v-card variant="outlined" class="mb-3">
                            <v-card-text class="d-flex align-center py-3">
                                <v-icon class="mr-3">mdi-bullhorn</v-icon>
                                <div class="flex-grow-1">
                                    <div class="font-weight-medium">{{ $t('cookie.marketing') }}</div>
                                    <div class="text-body-2 text-medium-emphasis mt-1">
                                        {{ $t('cookie.marketingDescription') }}
                                    </div>
                                </div>
                                <v-switch
                                    v-model="preferences.marketing"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                />
                            </v-card-text>
                        </v-card>

                        <!-- Functional Cookies -->
                        <v-card variant="outlined" class="mb-3">
                            <v-card-text class="d-flex align-center py-3">
                                <v-icon class="mr-3">mdi-cog</v-icon>
                                <div class="flex-grow-1">
                                    <div class="font-weight-medium">{{ $t('cookie.functional') }}</div>
                                    <div class="text-body-2 text-medium-emphasis mt-1">
                                        {{ $t('cookie.functionalDescription') }}
                                    </div>
                                </div>
                                <v-switch
                                    v-model="preferences.functional"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                />
                            </v-card-text>
                        </v-card>
                    </div>
                </v-card-text>

                <v-divider />

                <v-card-actions class="pa-6 pt-4">
                    <v-btn
                        variant="outlined"
                        @click="acceptNecessary"
                    >
                        {{ $t('cookie.necessaryOnly') }}
                    </v-btn>

                    <v-spacer />

                    <v-btn
                        color="primary"
                        variant="tonal"
                        class="mr-2"
                        @click="acceptAll"
                    >
                        {{ $t('cookie.acceptAllShort') }}
                    </v-btn>

                    <v-btn
                        color="primary"
                        @click="savePreferences"
                    >
                        <v-icon start>mdi-check</v-icon>
                        {{ $t('cookie.savePreferences') }}
                    </v-btn>
                </v-card-actions>
            </template>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/store/settingStore';

const { t } = useI18n();

const settingsStore = useSettingsStore();
const settings = computed(() => settingsStore.appSettings);
const settingsLoaded = computed(() => settingsStore.settingsLoaded);

const CONSENT_KEY = 'cookie_consent';
const CONSENT_VERSION = '1.0'; // Bump this to re-ask for consent after policy changes

const showBanner = ref(false);
const showDetails = ref(false);
const consentChecked = ref(false);

const preferences = reactive({
    necessary: true, // Always true
    analytics: false,
    marketing: false,
    functional: false,
});

const defaultBannerTitle = computed(() => t('cookie.consentTitle'));
const defaultBannerText = computed(() => t('cookie.defaultBannerText'));
const defaultPreferencesText = computed(() => t('cookie.defaultPreferencesText'));

// Computed properties for text - use settings if available, otherwise defaults
const bannerTitle = computed(() => {
    return settings.value?.cookie_banner_title || defaultBannerTitle.value;
});

const bannerText = computed(() => {
    return settings.value?.cookie_banner_text || defaultBannerText.value;
});

const preferencesText = computed(() => {
    return settings.value?.cookie_preferences_text || defaultPreferencesText.value;
});

const emit = defineEmits(['consent-updated']);

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

function deleteCookie(name) {
    // Delete with various path combinations to ensure removal
    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`;
    if (window.location.protocol === 'https:') {
        document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; Secure;`;
    }
}

function setCookie(name, value, days) {
    const expiryDate = new Date();
    expiryDate.setDate(expiryDate.getDate() + days);

    // Build cookie string with proper attributes
    let cookieString = `${name}=${value}; expires=${expiryDate.toUTCString()}; path=/; SameSite=Lax`;

    // Add Secure flag if on HTTPS (required for modern browsers)
    if (window.location.protocol === 'https:') {
        cookieString += '; Secure';
    }

    document.cookie = cookieString;

    // Verify the cookie was set
    const verifySet = getCookie(name);
    if (verifySet !== value) {
        console.warn(`Cookie '${name}' may not have been set properly. Browser might be blocking cookies.`);
    }
}

function checkConsent() {
    // Only check once per session
    if (consentChecked.value) return;

    // Check if consent banner is disabled in settings
    const consentEnabled = settings.value.cookie_consent_enabled;
    if (consentEnabled === false || consentEnabled === 'false' || consentEnabled === '0') {
        consentChecked.value = true;
        return;
    }

    // Check for consent cookie (set when user accepts)
    const consentCookie = getCookie('cookie_consent_given');

    // Check for existing consent in localStorage
    let savedConsent = null;
    try {
        savedConsent = localStorage.getItem(CONSENT_KEY);
    } catch (e) {
        console.warn('localStorage not available:', e);
    }

    // Debug logging (can be removed in production)
    if (import.meta.env.DEV) {
        console.log('Cookie consent check:', {
            consentCookie,
            savedConsent: savedConsent ? 'exists' : 'missing',
            consentEnabled
        });
    }

    // Accept consent if either cookie OR localStorage exists with valid data
    // This makes it more resilient to partial data loss
    if (savedConsent) {
        try {
            const consent = JSON.parse(savedConsent);
            // Check if consent version matches
            if (consent.version === CONSENT_VERSION) {
                Object.assign(preferences, consent.preferences);
                applyConsent();
                consentChecked.value = true;

                // Re-set the cookie if it's missing (self-healing)
                if (!consentCookie) {
                    setCookie('cookie_consent_given', 'true', 365);
                }
                return;
            }
        } catch (e) {
            console.warn('Invalid cookie consent data in localStorage', e);
        }
    }

    // Fallback: if cookie exists but localStorage is gone, trust the cookie
    // and set default preferences (necessary only)
    if (consentCookie === 'true' && !savedConsent) {
        const defaultConsent = {
            version: CONSENT_VERSION,
            timestamp: new Date().toISOString(),
            preferences: {
                necessary: true,
                analytics: false,
                marketing: false,
                functional: false,
            },
        };
        try {
            localStorage.setItem(CONSENT_KEY, JSON.stringify(defaultConsent));
            Object.assign(preferences, defaultConsent.preferences);
            applyConsent();
            consentChecked.value = true;
            return;
        } catch (e) {
            console.warn('Failed to restore consent to localStorage:', e);
        }
    }

    // Show banner if no valid consent found
    consentChecked.value = true;
    showBanner.value = true;
}

onMounted(() => {
    // If settings are already loaded, check consent immediately
    if (settingsLoaded.value) {
        checkConsent();
    } else {
        // Fallback: if settings don't load within 2 seconds, check anyway
        // This ensures the banner shows even if there's an API issue
        setTimeout(() => {
            if (!consentChecked.value) {
                console.warn('Cookie consent: Settings not loaded, checking consent with defaults');
                checkConsent();
            }
        }, 2000);
    }
});

// Watch for settings to be loaded (handles async loading)
watch(settingsLoaded, (loaded) => {
    if (loaded && !consentChecked.value) {
        checkConsent();
    }
}, { immediate: true });

// Also watch for visibility changes (user switches tabs and back)
// This helps catch cases where cookies/localStorage were cleared in another tab
if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && consentChecked.value) {
            // Re-check if consent was removed while tab was hidden
            const savedConsent = localStorage.getItem(CONSENT_KEY);
            const consentCookie = getCookie('cookie_consent_given');
            if ((!savedConsent || !consentCookie) && !showBanner.value) {
                consentChecked.value = false;
                checkConsent();
            }
        }
    });
}

function saveConsent() {
    const consent = {
        version: CONSENT_VERSION,
        timestamp: new Date().toISOString(),
        preferences: { ...preferences },
    };

    // Save to localStorage first (more reliable)
    try {
        localStorage.setItem(CONSENT_KEY, JSON.stringify(consent));
    } catch (e) {
        console.error('Failed to save consent to localStorage:', e);
    }

    // Set a simple cookie that Laravel can read to know consent was given
    // This cookie is essential for the consent mechanism itself (365 days)
    setCookie('cookie_consent_given', 'true', 365);

    applyConsent();
    showBanner.value = false;
    showDetails.value = false;

    // Small delay before reload to ensure cookie is written
    setTimeout(() => {
        // Reload the page to allow Laravel to start the session now that consent is given
        // This ensures the session cookie is properly set after consent
        window.location.reload();
    }, 100);
}

function applyConsent() {
    // Emit event for other components to react
    emit('consent-updated', { ...preferences });

    // Dispatch custom event for scripts to listen to
    window.dispatchEvent(new CustomEvent('cookie-consent-updated', {
        detail: { ...preferences }
    }));

    // Store in window for easy access
    window.cookieConsent = { ...preferences };

    // Apply consent - enable/disable tracking based on preferences
    if (preferences.analytics) {
        enableAnalytics();
    } else {
        disableAnalytics();
    }

    if (preferences.marketing) {
        enableMarketing();
    }
}

function enableAnalytics() {
    // Enable Google Analytics if configured
    if (window.gtag) {
        window.gtag('consent', 'update', {
            'analytics_storage': 'granted'
        });
    }

    // Load deferred analytics scripts
    document.querySelectorAll('script[data-cookie-category="analytics"]').forEach(script => {
        if (script.dataset.src) {
            script.src = script.dataset.src;
        }
    });
}

function disableAnalytics() {
    if (window.gtag) {
        window.gtag('consent', 'update', {
            'analytics_storage': 'denied'
        });
    }
}

function enableMarketing() {
    if (window.gtag) {
        window.gtag('consent', 'update', {
            'ad_storage': 'granted',
            'ad_user_data': 'granted',
            'ad_personalization': 'granted'
        });
    }

    // Load deferred marketing scripts
    document.querySelectorAll('script[data-cookie-category="marketing"]').forEach(script => {
        if (script.dataset.src) {
            script.src = script.dataset.src;
        }
    });
}

function acceptAll() {
    preferences.analytics = true;
    preferences.marketing = true;
    preferences.functional = true;
    saveConsent();
}

function acceptNecessary() {
    preferences.analytics = false;
    preferences.marketing = false;
    preferences.functional = false;
    saveConsent();
}

function savePreferences() {
    saveConsent();
}

function revokeConsent() {
    // Remove consent cookie
    deleteCookie('cookie_consent_given');
    // Remove localStorage
    localStorage.removeItem(CONSENT_KEY);
    // Reset preferences
    preferences.analytics = false;
    preferences.marketing = false;
    preferences.functional = false;
    // Reload to clear session cookie
    window.location.reload();
}

// Expose methods for external use (footer link, settings page, etc.)
defineExpose({
    openPreferences: () => {
        showBanner.value = true;
        showDetails.value = true;
    },
    revokeConsent,
    hasConsent: () => {
        return getCookie('cookie_consent_given') === 'true' && localStorage.getItem(CONSENT_KEY) !== null;
    }
});
</script>

<style scoped>
.cookie-consent-dialog {
    z-index: 9999 !important;
}

.cookie-consent-card {
    overflow: visible;
}

.cookie-categories {
    max-height: 50vh;
    overflow-y: auto;
}

/* Mobile responsive */
@media (max-width: 600px) {
    .cookie-consent-card :deep(.v-card-actions) {
        flex-direction: column;
        gap: 8px;
    }

    .cookie-consent-card :deep(.v-card-actions .v-btn) {
        width: 100%;
        margin: 0 !important;
    }
}
</style>
