<template>
    <div>
        <!-- Performance & Technical -->
        <settings-card icon="mdi-speedometer" :title="$t('settings.advanced.performanceCaching')">
            <v-switch
                v-model="settings.cache_enabled"
                :label="$t('settings.advanced.enableCaching')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.enableCachingHint')"
                persistent-hint
            ></v-switch>

            <v-text-field
                v-model.number="settings.cache_lifetime_minutes"
                :label="$t('settings.advanced.cacheLifetime')"
                prepend-inner-icon="mdi-clock"
                variant="outlined"
                type="number"
                class="mb-4"
                :error-messages="errors.cache_lifetime_minutes"
                :hint="$t('settings.advanced.cacheLifetimeHint')"
                persistent-hint
            ></v-text-field>

            <v-switch
                v-model="settings.cdn_enabled"
                :label="$t('settings.advanced.enableCdn')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.enableCdnHint')"
                persistent-hint
            ></v-switch>

            <v-text-field
                v-if="settings.cdn_enabled"
                v-model="settings.cdn_url"
                :label="$t('settings.advanced.cdnUrl')"
                prepend-inner-icon="mdi-server-network"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.cdn_url"
                :hint="$t('settings.advanced.cdnUrlHint')"
                persistent-hint
            ></v-text-field>

            <v-switch
                v-model="settings.image_optimization_enabled"
                :label="$t('settings.advanced.enableImageOptimization')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.imageOptimizationHint')"
                persistent-hint
            ></v-switch>

            <v-switch
                v-model="settings.lazy_loading_enabled"
                :label="$t('settings.advanced.enableLazyLoading')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.lazyLoadingHint')"
                persistent-hint
            ></v-switch>

            <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>{{ $t('settings.advanced.performanceTips') }}:</strong>
                    <ul class="mt-1 ml-4">
                        <li><strong>{{ $t('settings.advanced.imageOptimization') }}:</strong> {{ $t('settings.advanced.imageOptimizationDesc') }}</li>
                        <li><strong>{{ $t('settings.advanced.lazyLoading') }}:</strong> {{ $t('settings.advanced.lazyLoadingDesc') }}</li>
                    </ul>
                </div>
            </v-alert>

            <v-divider class="my-6"></v-divider>

            <!-- Cache Management -->
            <div class="d-flex align-center justify-space-between mb-4">
                <div>
                    <div class="text-subtitle-1 font-weight-medium">{{ $t('settings.advanced.cacheManagement') }}</div>
                    <div class="text-caption text-medium-emphasis">
                        {{ $t('settings.advanced.cacheManagementDesc') }}
                    </div>
                </div>
                <div class="d-flex align-center ga-2">
                    <v-chip
                        v-if="cacheStatus"
                        :color="cacheStatus.enabled ? 'success' : 'warning'"
                        size="small"
                        variant="tonal"
                    >
                        {{ cacheStatus.enabled ? $t('settings.advanced.cacheEnabled') : $t('settings.advanced.cacheDisabled') }}
                    </v-chip>
                    <v-chip
                        v-if="cacheStatus"
                        size="small"
                        variant="outlined"
                    >
                        {{ cacheStatus.lifetime_minutes }} {{ $t('settings.advanced.minTtl') }}
                    </v-chip>
                    <v-chip
                        v-if="cacheStatus"
                        size="small"
                        variant="outlined"
                        color="info"
                    >
                        {{ cacheStatus.driver }}
                    </v-chip>
                </div>
            </div>

            <!-- System Cache -->
            <div class="text-caption text-medium-emphasis mb-2 mt-4">{{ $t('settings.advanced.systemCache') }}</div>
            <v-row dense>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'settings'"
                        @click="clearCache('settings')"
                    >
                        <v-icon start size="small">mdi-cog</v-icon>
                        {{ $t('settings.advanced.cacheSettings') }}
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'views'"
                        @click="clearCache('views')"
                    >
                        <v-icon start size="small">mdi-file-document</v-icon>
                        {{ $t('settings.advanced.cacheViews') }}
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'routes'"
                        @click="clearCache('routes')"
                    >
                        <v-icon start size="small">mdi-routes</v-icon>
                        {{ $t('settings.advanced.cacheRoutes') }}
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'config'"
                        @click="clearCache('config')"
                    >
                        <v-icon start size="small">mdi-wrench</v-icon>
                        {{ $t('settings.advanced.cacheConfig') }}
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'application'"
                        @click="clearCache('application')"
                    >
                        <v-icon start size="small">mdi-database</v-icon>
                        {{ $t('settings.advanced.cacheApp') }}
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'http'"
                        @click="clearCache('http')"
                    >
                        <v-icon start size="small">mdi-web</v-icon>
                        {{ $t('settings.advanced.cacheHttp') }}
                    </v-btn>
                </v-col>
            </v-row>

            <!-- Content Cache -->
            <div class="text-caption text-medium-emphasis mb-2 mt-4">{{ $t('settings.advanced.contentCache') }}</div>
            <v-row dense>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'pages'"
                        @click="clearCache('pages')"
                    >
                        <v-icon start size="small">mdi-file-multiple</v-icon>
                        {{ $t('settings.advanced.cachePages') }}
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'wiki'"
                        @click="clearCache('wiki')"
                    >
                        <v-icon start size="small">mdi-book-open-page-variant</v-icon>
                        {{ $t('settings.advanced.cacheWiki') }}
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'posts'"
                        @click="clearCache('posts')"
                    >
                        <v-icon start size="small">mdi-post</v-icon>
                        {{ $t('settings.advanced.cachePosts') }}
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="2">
                    <v-btn
                        block
                        size="small"
                        variant="outlined"
                        :loading="clearingCache === 'widgets'"
                        @click="clearCache('widgets')"
                    >
                        <v-icon start size="small">mdi-widgets</v-icon>
                        {{ $t('settings.advanced.cacheWidgets') }}
                    </v-btn>
                </v-col>
                <v-col cols="6" sm="4" md="4">
                    <v-btn
                        block
                        size="small"
                        color="error"
                        variant="tonal"
                        :loading="clearingCache === 'all'"
                        @click="clearCache('all')"
                    >
                        <v-icon start size="small">mdi-delete-sweep</v-icon>
                        {{ $t('settings.advanced.clearAllCaches') }}
                    </v-btn>
                </v-col>
            </v-row>
        </settings-card>

        <!-- PWA Settings -->
        <settings-card icon="mdi-cellphone-check" :title="$t('settings.advanced.pwaTitle')">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    <strong>{{ $t('settings.advanced.whatIsPwa') }}</strong> {{ $t('settings.advanced.pwaDescription') }}
                    <ul class="mt-1 ml-4">
                        <li>{{ $t('settings.advanced.pwaFeature1') }}</li>
                        <li>{{ $t('settings.advanced.pwaFeature2') }}</li>
                        <li>{{ $t('settings.advanced.pwaFeature3') }}</li>
                        <li>{{ $t('settings.advanced.pwaFeature4') }}</li>
                    </ul>
                </div>
            </v-alert>

            <div class="mb-4">
                <div class="text-subtitle-2 mb-2">
                    <v-icon size="small" class="mr-1">mdi-information</v-icon>
                    {{ $t('settings.advanced.pwaStatus') }}
                </div>
                <v-alert type="success" variant="tonal" density="compact">
                    <div class="d-flex align-center">
                        <v-icon class="mr-2">mdi-check-circle</v-icon>
                        <div>
                            <div class="font-weight-medium">{{ $t('settings.advanced.pwaEnabled') }}</div>
                            <div class="text-caption mt-1">
                                {{ $t('settings.advanced.pwaEnabledDesc') }}
                            </div>
                        </div>
                    </div>
                </v-alert>
            </div>

            <div class="mb-4">
                <div class="text-subtitle-2 mb-2">
                    <v-icon size="small" class="mr-1">mdi-cog</v-icon>
                    {{ $t('settings.advanced.pwaConfiguration') }}
                </div>
                <v-card variant="outlined" class="pa-3">
                    <div class="d-flex justify-space-between align-center mb-2">
                        <span class="text-body-2">{{ $t('settings.advanced.appName') }}:</span>
                        <span class="text-body-2 font-weight-medium">{{ settings.app_name || 'Fellowship' }}</span>
                    </div>
                    <v-divider class="my-2"></v-divider>
                    <div class="d-flex justify-space-between align-center mb-2">
                        <span class="text-body-2">{{ $t('settings.advanced.themeColor') }}:</span>
                        <v-chip :color="settings.primary_color || '#1976D2'" size="small">
                            {{ settings.primary_color || '#1976D2' }}
                        </v-chip>
                    </div>
                    <v-divider class="my-2"></v-divider>
                    <div class="d-flex justify-space-between align-center mb-2">
                        <span class="text-body-2">{{ $t('settings.advanced.appIcon') }}:</span>
                        <span class="text-body-2 font-weight-medium">
                            {{ settings.app_icon ? $t('settings.advanced.uploaded') : $t('settings.advanced.usingDefault') }}
                        </span>
                    </div>
                    <v-divider class="my-2"></v-divider>
                    <div class="d-flex justify-space-between align-center">
                        <span class="text-body-2">{{ $t('settings.advanced.favicon') }}:</span>
                        <span class="text-body-2 font-weight-medium">
                            {{ settings.favicon ? $t('settings.advanced.uploaded') : $t('settings.advanced.usingDefault') }}
                        </span>
                    </div>
                </v-card>
            </div>

            <v-alert type="warning" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <v-icon size="small" class="mr-1">mdi-alert</v-icon>
                    <strong>{{ $t('settings.advanced.important') }}:</strong> {{ $t('settings.advanced.pwaIconRequirements') }}
                    <ul class="mt-1 ml-4">
                        <li><strong>{{ $t('settings.advanced.format') }}:</strong> {{ $t('settings.advanced.pwaFormatReq') }}</li>
                        <li><strong>{{ $t('settings.advanced.size') }}:</strong> {{ $t('settings.advanced.pwaSizeReq') }}</li>
                        <li><strong>{{ $t('settings.advanced.shape') }}:</strong> {{ $t('settings.advanced.pwaShapeReq') }}</li>
                        <li><strong>{{ $t('settings.advanced.purpose') }}:</strong> {{ $t('settings.advanced.pwaPurposeReq') }}</li>
                    </ul>
                    <div class="mt-2">
                        {{ $t('settings.advanced.pwaIconInstructions') }}
                    </div>
                </div>
            </v-alert>

            <div class="d-flex gap-2">
                <v-btn
                    color="primary"
                    variant="outlined"
                    prepend-icon="mdi-open-in-new"
                    href="/manifest.json"
                    target="_blank"
                >
                    {{ $t('settings.advanced.viewManifest') }}
                </v-btn>
                <v-btn
                    color="primary"
                    variant="outlined"
                    prepend-icon="mdi-refresh"
                    @click="testServiceWorker"
                >
                    {{ $t('settings.advanced.testServiceWorker') }}
                </v-btn>
            </div>
        </settings-card>

        <!-- Newsletter Integration -->
        <settings-card icon="mdi-email-newsletter" :title="$t('settings.advanced.newsletterIntegration')">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    {{ $t('settings.advanced.newsletterDesc') }}
                </div>
            </v-alert>

            <v-switch
                v-model="settings.newsletter_enabled"
                :label="$t('settings.advanced.enableNewsletter')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.enableNewsletterHint')"
                persistent-hint
            ></v-switch>

            <v-select
                v-if="settings.newsletter_enabled"
                v-model="settings.newsletter_provider"
                :label="$t('settings.advanced.newsletterProvider')"
                :items="newsletterProviders"
                prepend-inner-icon="mdi-email-variant"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.newsletter_provider"
                :hint="$t('settings.advanced.newsletterProviderHint')"
                persistent-hint
            ></v-select>

            <v-text-field
                v-if="settings.newsletter_enabled && settings.newsletter_provider"
                v-model="settings.newsletter_api_key"
                :label="$t('settings.advanced.apiKey')"
                prepend-inner-icon="mdi-key"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.newsletter_api_key"
                :hint="$t('settings.advanced.newsletterApiKeyHint')"
                persistent-hint
                type="password"
            ></v-text-field>

            <v-text-field
                v-if="settings.newsletter_enabled && settings.newsletter_provider"
                v-model="settings.newsletter_list_id"
                :label="$t('settings.advanced.listAudienceId')"
                prepend-inner-icon="mdi-format-list-bulleted"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.newsletter_list_id"
                :hint="$t('settings.advanced.listIdHint')"
                persistent-hint
            ></v-text-field>

            <v-alert v-if="settings.newsletter_enabled && settings.newsletter_provider === 'mailchimp'" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>{{ $t('settings.advanced.mailchimpSetup') }}:</strong>
                    <ol class="mt-1 ml-4">
                        <li>{{ $t('settings.advanced.mailchimpStep1') }}</li>
                        <li>{{ $t('settings.advanced.mailchimpStep2') }}</li>
                        <li>{{ $t('settings.advanced.mailchimpStep3') }}</li>
                        <li>{{ $t('settings.advanced.mailchimpStep4') }}</li>
                    </ol>
                </div>
            </v-alert>

            <v-alert v-if="settings.newsletter_enabled && settings.newsletter_provider === 'mailerlite'" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>{{ $t('settings.advanced.mailerliteSetup') }}:</strong>
                    <ol class="mt-1 ml-4">
                        <li>{{ $t('settings.advanced.mailerliteStep1') }}</li>
                        <li>{{ $t('settings.advanced.mailerliteStep2') }}</li>
                        <li>{{ $t('settings.advanced.mailerliteStep3') }}</li>
                        <li>{{ $t('settings.advanced.mailerliteStep4') }}</li>
                    </ol>
                </div>
            </v-alert>

            <v-alert v-if="settings.newsletter_enabled && settings.newsletter_provider === 'sendgrid'" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>{{ $t('settings.advanced.sendgridSetup') }}:</strong>
                    <ol class="mt-1 ml-4">
                        <li>{{ $t('settings.advanced.sendgridStep1') }}</li>
                        <li>{{ $t('settings.advanced.sendgridStep2') }}</li>
                        <li>{{ $t('settings.advanced.sendgridStep3') }}</li>
                        <li>{{ $t('settings.advanced.sendgridStep4') }}</li>
                        <li>{{ $t('settings.advanced.sendgridStep5') }}</li>
                    </ol>
                </div>
            </v-alert>

            <v-alert v-if="settings.newsletter_enabled && settings.newsletter_provider === 'convertkit'" type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <strong>{{ $t('settings.advanced.convertkitSetup') }}:</strong>
                    <ol class="mt-1 ml-4">
                        <li>{{ $t('settings.advanced.convertkitStep1') }}</li>
                        <li>{{ $t('settings.advanced.convertkitStep2') }}</li>
                        <li>{{ $t('settings.advanced.convertkitStep3') }}</li>
                        <li>{{ $t('settings.advanced.convertkitStep4') }}</li>
                    </ol>
                </div>
            </v-alert>
        </settings-card>

        <!-- Server Environment (Read-only from .env) -->
        <settings-card icon="mdi-server" :title="$t('settings.advanced.serverEnvironment')">
            <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                {{ $t('settings.advanced.serverEnvDesc') }}
            </v-alert>

            <v-text-field
                :model-value="serverEnvironment"
                :label="$t('settings.advanced.environment')"
                prepend-inner-icon="mdi-monitor"
                variant="outlined"
                class="mb-4"
                readonly
                disabled
            >
                <template #append-inner>
                    <v-chip
                        :color="environmentColor"
                        size="small"
                    >
                        {{ serverEnvironment }}
                    </v-chip>
                </template>
            </v-text-field>

            <v-text-field
                :model-value="serverDebugMode ? $t('settings.advanced.enabled') : $t('settings.advanced.disabled')"
                :label="$t('settings.advanced.debugMode')"
                prepend-inner-icon="mdi-bug"
                variant="outlined"
                readonly
                disabled
            >
                <template #append-inner>
                    <v-chip
                        :color="serverDebugMode ? 'warning' : 'success'"
                        size="small"
                    >
                        {{ serverDebugMode ? 'ON' : 'OFF' }}
                    </v-chip>
                </template>
            </v-text-field>

            <v-alert v-if="serverDebugMode && serverEnvironment === 'production'" type="warning" variant="tonal" density="compact" class="mt-4">
                <strong>{{ $t('settings.advanced.warning') }}:</strong> {{ $t('settings.advanced.debugWarning') }}
            </v-alert>
        </settings-card>

        <!-- API & Developer Settings -->
        <settings-card icon="mdi-api" :title="$t('settings.advanced.apiDeveloperSettings')">
            <v-text-field
                v-model.number="settings.api_rate_limit_per_minute"
                :label="$t('settings.advanced.apiRateLimit')"
                prepend-inner-icon="mdi-speedometer"
                variant="outlined"
                type="number"
                class="mb-4"
                :error-messages="errors.api_rate_limit_per_minute"
                :hint="$t('settings.advanced.apiRateLimitHint')"
                persistent-hint
            ></v-text-field>

            <v-switch
                v-model="settings.api_keys_enabled"
                :label="$t('settings.advanced.enableApiKeys')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.enableApiKeysHint')"
                persistent-hint
            ></v-switch>

            <v-switch
                v-model="settings.background_jobs_enabled"
                :label="$t('settings.advanced.enableBackgroundJobs')"
                color="primary"
                :hint="$t('settings.advanced.backgroundJobsHint')"
                persistent-hint
            ></v-switch>

            <v-alert v-if="settings.background_jobs_enabled" type="info" variant="tonal" density="compact" class="mt-4">
                {{ $t('settings.advanced.queueWorkerNote') }}: <code>php artisan queue:work</code>
            </v-alert>
        </settings-card>

        <!-- Cookie Consent / GDPR -->
        <settings-card icon="mdi-cookie" :title="$t('settings.advanced.cookieConsent')">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    <strong>{{ $t('settings.advanced.gdprCompliance') }}:</strong> {{ $t('settings.advanced.gdprDesc') }}
                </div>
            </v-alert>

            <v-switch
                v-model="settings.cookie_consent_enabled"
                :label="$t('settings.advanced.enableCookieBanner')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.cookieBannerHint')"
                persistent-hint
            ></v-switch>

            <template v-if="settings.cookie_consent_enabled">
                <v-text-field
                    v-model="settings.cookie_banner_title"
                    :label="$t('settings.advanced.bannerTitle')"
                    prepend-inner-icon="mdi-format-title"
                    variant="outlined"
                    class="mb-4"
                    :hint="$t('settings.advanced.bannerTitleHint')"
                    persistent-hint
                    :placeholder="$t('settings.advanced.cookieConsentPlaceholder')"
                ></v-text-field>

                <v-textarea
                    v-model="settings.cookie_banner_text"
                    :label="$t('settings.advanced.bannerText')"
                    prepend-inner-icon="mdi-text"
                    variant="outlined"
                    class="mb-4"
                    rows="3"
                    :hint="$t('settings.advanced.bannerTextHint')"
                    persistent-hint
                    :placeholder="$t('settings.advanced.bannerTextPlaceholder')"
                ></v-textarea>

                <v-textarea
                    v-model="settings.cookie_preferences_text"
                    :label="$t('settings.advanced.preferencesDescription')"
                    prepend-inner-icon="mdi-text"
                    variant="outlined"
                    class="mb-4"
                    rows="2"
                    :hint="$t('settings.advanced.preferencesHint')"
                    persistent-hint
                    :placeholder="$t('settings.advanced.preferencesPlaceholder')"
                ></v-textarea>

                <v-divider class="my-4"></v-divider>

                <div class="text-subtitle-2 mb-3">
                    <v-icon size="small" class="mr-1">mdi-toggle-switch</v-icon>
                    {{ $t('settings.advanced.defaultCookieStates') }}
                </div>

                <v-alert type="warning" variant="tonal" class="mb-4" density="compact">
                    <div class="text-caption">
                        <strong>{{ $t('settings.advanced.note') }}:</strong> {{ $t('settings.advanced.gdprCookieNote') }}
                    </div>
                </v-alert>

                <v-switch
                    v-model="settings.cookie_analytics_default"
                    :label="$t('settings.advanced.analyticsDefault')"
                    color="primary"
                    class="mb-2"
                    :hint="$t('settings.advanced.analyticsDefaultHint')"
                    persistent-hint
                ></v-switch>

                <v-switch
                    v-model="settings.cookie_marketing_default"
                    :label="$t('settings.advanced.marketingDefault')"
                    color="primary"
                    class="mb-2"
                    :hint="$t('settings.advanced.marketingDefaultHint')"
                    persistent-hint
                ></v-switch>

                <v-switch
                    v-model="settings.cookie_functional_default"
                    :label="$t('settings.advanced.functionalDefault')"
                    color="primary"
                    class="mb-4"
                    :hint="$t('settings.advanced.functionalDefaultHint')"
                    persistent-hint
                ></v-switch>

                <v-divider class="my-4"></v-divider>

                <div class="text-subtitle-2 mb-3">
                    <v-icon size="small" class="mr-1">mdi-link</v-icon>
                    {{ $t('settings.advanced.relatedPages') }}
                </div>

                <v-text-field
                    v-model="settings.cookie_policy_url"
                    :label="$t('settings.advanced.cookiePolicyUrl')"
                    prepend-inner-icon="mdi-cookie"
                    variant="outlined"
                    class="mb-4"
                    :error-messages="errors.cookie_policy_url"
                    :hint="$t('settings.advanced.cookiePolicyHint')"
                    persistent-hint
                    placeholder="/cookies"
                ></v-text-field>

                <v-text-field
                    v-model="settings.privacy_policy_url"
                    :label="$t('settings.advanced.privacyPolicyUrl')"
                    prepend-inner-icon="mdi-shield-account"
                    variant="outlined"
                    :error-messages="errors.privacy_policy_url"
                    :hint="$t('settings.advanced.privacyPolicyHint')"
                    persistent-hint
                    placeholder="/privacy"
                ></v-text-field>
            </template>
        </settings-card>

        <!-- Custom Scripts -->
        <settings-card icon="mdi-code-tags" :title="$t('settings.advanced.customScripts')">
            <v-alert type="warning" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    <v-icon size="small" class="mr-1">mdi-alert</v-icon>
                    <strong>{{ $t('settings.advanced.securityWarning') }}:</strong> {{ $t('settings.advanced.scriptsWarning') }}
                </div>
            </v-alert>

            <v-textarea
                v-model="settings.custom_head_scripts"
                :label="$t('settings.advanced.headScripts')"
                prepend-inner-icon="mdi-xml"
                variant="outlined"
                class="mb-4 monospace-input"
                rows="6"
                :error-messages="errors.custom_head_scripts"
                :hint="$t('settings.advanced.headScriptsHint')"
                persistent-hint
                placeholder="<script>
  // Your head scripts here
  // e.g., Google Tag Manager, Meta Pixel
</script>"
            ></v-textarea>

            <v-textarea
                v-model="settings.custom_body_scripts"
                :label="$t('settings.advanced.bodyScripts')"
                prepend-inner-icon="mdi-xml"
                variant="outlined"
                class="mb-4 monospace-input"
                rows="6"
                :error-messages="errors.custom_body_scripts"
                :hint="$t('settings.advanced.bodyScriptsHint')"
                persistent-hint
                placeholder="<script>
  // Your body scripts here
  // e.g., Chat widgets, deferred analytics
</script>"
            ></v-textarea>

            <v-alert type="info" variant="tonal" density="compact">
                <div class="text-caption">
                    <strong>{{ $t('settings.advanced.commonUses') }}:</strong>
                    <ul class="mt-1 ml-4">
                        <li><strong>{{ $t('settings.advanced.head') }}:</strong> {{ $t('settings.advanced.headExamples') }}</li>
                        <li><strong>{{ $t('settings.advanced.body') }}:</strong> {{ $t('settings.advanced.bodyExamples') }}</li>
                    </ul>
                </div>
            </v-alert>
        </settings-card>

        <!-- Legal & Compliance -->
        <settings-card icon="mdi-gavel" :title="$t('settings.advanced.legalCompliance')">
            <v-text-field
                v-model="settings.privacy_policy_url"
                :label="$t('settings.advanced.privacyPolicyUrl')"
                prepend-inner-icon="mdi-shield-check"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.privacy_policy_url"
                :hint="$t('settings.advanced.legalUrlHint')"
                persistent-hint
                placeholder="/privacy-policy"
            ></v-text-field>

            <v-text-field
                v-model="settings.terms_conditions_url"
                :label="$t('settings.advanced.termsConditionsUrl')"
                prepend-inner-icon="mdi-file-document"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.terms_conditions_url"
                :hint="$t('settings.advanced.legalUrlHint')"
                persistent-hint
                placeholder="/terms"
            ></v-text-field>

            <v-text-field
                v-model="settings.cookie_policy_url"
                :label="$t('settings.advanced.cookiePolicyUrl')"
                prepend-inner-icon="mdi-cookie"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.cookie_policy_url"
                :hint="$t('settings.advanced.legalUrlHint')"
                persistent-hint
                placeholder="/cookies"
            ></v-text-field>

            <v-switch
                v-model="settings.right_to_be_forgotten_enabled"
                :label="$t('settings.advanced.rightToBeForgotten')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.rightToBeForgottenHint')"
                persistent-hint
            ></v-switch>

            <v-switch
                v-model="settings.age_confirmation_required"
                :label="$t('settings.advanced.requireAgeConfirmation')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.advanced.ageConfirmationHint')"
                persistent-hint
            ></v-switch>

            <v-text-field
                v-if="settings.age_confirmation_required"
                v-model.number="settings.age_minimum"
                :label="$t('settings.advanced.minimumAge')"
                prepend-inner-icon="mdi-numeric"
                variant="outlined"
                type="number"
                :error-messages="errors.age_minimum"
                :hint="$t('settings.advanced.minimumAgeHint')"
                persistent-hint
            ></v-text-field>
        </settings-card>

        <v-btn
            :loading="isSaving"
            block
            size="large"
            color="primary"
            @click="$emit('save')"
            prepend-icon="mdi-content-save"
        >
            {{ $t('settings.advanced.saveSettings') }}
        </v-btn>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/api/useAPI.js';
import SettingsCard from '../SettingsCard.vue';

const { t } = useI18n();
const api = useApi('api');

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
});

const emit = defineEmits(['save', 'message']);

// Server environment info (fetched from backend)
const serverEnvironment = ref('unknown');
const serverDebugMode = ref(false);

const environmentColor = computed(() => {
    switch (serverEnvironment.value) {
        case 'production': return 'success';
        case 'staging': return 'warning';
        case 'development': return 'info';
        case 'local': return 'info';
        default: return 'grey';
    }
});

// Cache management
const cacheStatus = ref(null);
const clearingCache = ref(null);

async function fetchCacheStatus() {
    try {
        const response = await api.get('/admin/settings/cache-status');
        cacheStatus.value = response.data;
    } catch (error) {
        console.error('Failed to fetch cache status:', error);
    }
}

// Fetch server environment info
async function fetchServerInfo() {
    try {
        const response = await api.get('/admin/settings/server-info');
        serverEnvironment.value = response.data.environment || 'unknown';
        serverDebugMode.value = response.data.debug_mode || false;
    } catch (error) {
        console.error('Failed to fetch server info:', error);
    }
}

async function clearCache(type) {
    clearingCache.value = type;
    try {
        const response = await api.post('/admin/settings/clear-cache', { type });
        emit('message', {
            text: `Cache cleared successfully: ${response.data.cleared.join(', ')}`,
            type: 'success'
        });
        // Refresh cache status
        await fetchCacheStatus();
    } catch (error) {
        console.error('Failed to clear cache:', error);
        emit('message', {
            text: 'Failed to clear cache: ' + (error.response?.data?.message || error.message),
            type: 'error'
        });
    } finally {
        clearingCache.value = null;
    }
}

onMounted(() => {
    fetchCacheStatus();
    fetchServerInfo();
});

const newsletterProviders = [
    { title: 'Mailchimp', value: 'mailchimp' },
    { title: 'MailerLite', value: 'mailerlite' },
    { title: 'SendGrid', value: 'sendgrid' },
    { title: 'ConvertKit', value: 'convertkit' },
    { title: 'ActiveCampaign', value: 'activecampaign' },
    { title: 'Sendinblue (Brevo)', value: 'sendinblue' },
    { title: 'Custom API', value: 'custom' },
];

function testServiceWorker() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistration('/').then((registration) => {
            if (registration) {
                emit('message', {
                    text: 'Service Worker is active and registered! Check browser console for details.',
                    type: 'success'
                });
                console.log('Service Worker Registration:', registration);
                console.log('Service Worker State:', registration.active?.state);
            } else {
                emit('message', {
                    text: 'Service Worker is not registered yet. It will be registered on production build.',
                    type: 'info'
                });
            }
        });
    } else {
        emit('message', {
            text: 'Service Workers are not supported in this browser.',
            type: 'error'
        });
    }
}
</script>

<style scoped>
.gap-2 {
    gap: 8px;
}

.monospace-input :deep(textarea) {
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.5;
}
</style>
