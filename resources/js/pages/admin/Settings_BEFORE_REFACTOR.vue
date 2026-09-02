<template>
    <div class="flex-grow-1">
        <v-container>
            <v-card elevation="2">
                <v-card-title class="text-h5 font-weight-bold pa-6 bg-gradient">
                    <v-icon class="mr-3" size="28">mdi-cog</v-icon>
                    Application Settings
                </v-card-title>

                <v-divider></v-divider>

                <v-alert v-if="message" :type="alertType" class="mx-6 mt-4 mb-0">
                    {{ message }}
                </v-alert>

                <v-tabs v-model="currentTab" bg-color="transparent" color="primary" show-arrows>
                    <v-tab value="general">
                        <v-icon class="mr-2">mdi-cog-outline</v-icon>
                        General
                    </v-tab>
                    <v-tab value="localization">
                        <v-icon class="mr-2">mdi-earth</v-icon>
                        Localization
                    </v-tab>
                    <v-tab value="branding">
                        <v-icon class="mr-2">mdi-palette</v-icon>
                        Branding
                    </v-tab>
                    <v-tab value="seo">
                        <v-icon class="mr-2">mdi-search-web</v-icon>
                        SEO
                    </v-tab>
                    <v-tab value="analytics">
                        <v-icon class="mr-2">mdi-chart-line</v-icon>
                        Analytics
                    </v-tab>
                    <v-tab value="scripts">
                        <v-icon class="mr-2">mdi-code-tags</v-icon>
                        Scripts
                    </v-tab>
                    <v-tab value="content">
                        <v-icon class="mr-2">mdi-file-document</v-icon>
                        Content
                    </v-tab>
                    <v-tab value="users">
                        <v-icon class="mr-2">mdi-account-group</v-icon>
                        Users
                    </v-tab>
                    <v-tab value="email">
                        <v-icon class="mr-2">mdi-email</v-icon>
                        Email
                    </v-tab>
                    <v-tab value="performance">
                        <v-icon class="mr-2">mdi-speedometer</v-icon>
                        Performance
                    </v-tab>
                    <v-tab value="legal">
                        <v-icon class="mr-2">mdi-gavel</v-icon>
                        Legal
                    </v-tab>
                    <v-tab value="advanced">
                        <v-icon class="mr-2">mdi-cog-sync</v-icon>
                        Advanced
                    </v-tab>
                </v-tabs>

                <v-divider></v-divider>

                <v-card-text class="pa-6">
                    <v-window v-model="currentTab">
                        <!-- General Tab -->
                        <v-window-item value="general">
                            <!-- Logo Upload Section -->
                    <v-card class="mb-6" elevation="0" variant="outlined">
                        <v-card-title class="text-h6">
                            <v-icon class="mr-2">mdi-image</v-icon>
                            Application Logo
                        </v-card-title>
                        <v-card-text>
                            <div class="d-flex align-center mb-4">
                                <v-img
                                    v-if="logoPreview"
                                    :src="logoPreview"
                                    max-height="120"
                                    max-width="200"
                                    class="mr-4"
                                    contain
                                ></v-img>
                                <div v-else class="no-logo-placeholder mr-4">
                                    <v-icon size="64" color="grey-lighten-2">mdi-image-off</v-icon>
                                </div>
                                <div>
                                    <v-file-input
                                        v-model="logoFile"
                                        label="Upload Logo"
                                        accept="image/*"
                                        prepend-icon="mdi-camera"
                                        :error-messages="errors.logo"
                                        @change="handleLogoSelect"
                                        show-size
                                    ></v-file-input>
                                    <div class="text-caption text-medium-emphasis">
                                        Recommended: PNG, JPG, SVG. Max size: 2MB
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <v-btn
                                    v-if="logoFile"
                                    color="primary"
                                    :loading="uploadingLogo"
                                    @click="uploadLogo"
                                    prepend-icon="mdi-upload"
                                >
                                    Upload Logo
                                </v-btn>
                                <v-btn
                                    v-if="settings.app_logo"
                                    color="error"
                                    variant="outlined"
                                    :loading="deletingLogo"
                                    @click="deleteLogo"
                                    prepend-icon="mdi-delete"
                                >
                                    Delete Logo
                                </v-btn>
                            </div>
                        </v-card-text>
                    </v-card>

                            <!-- Application Name -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-application</v-icon>
                                    Application Info
                                </v-card-title>
                                <v-card-text>
                                    <!-- Application Name -->
                                    <v-text-field
                                        v-model="settings.app_name"
                                        label="Application Name"
                                        prepend-inner-icon="mdi-application"
                                        variant="outlined"
                                        class="mb-4"
                                        :error-messages="errors.app_name"
                                    ></v-text-field>

                                    <!-- Copyright Text -->
                                    <v-text-field
                                        v-model="settings.app_copyright"
                                        label="Copyright Text"
                                        prepend-inner-icon="mdi-copyright"
                                        variant="outlined"
                                        class="mb-4"
                                        :error-messages="errors.app_copyright"
                                        hint="Text displayed in the footer (e.g., © Fellowship 2021)"
                                        persistent-hint
                                    ></v-text-field>

                                    <!-- Site Tagline -->
                                    <v-text-field
                                        v-model="settings.site_tagline"
                                        label="Site Tagline"
                                        prepend-inner-icon="mdi-format-quote-close"
                                        variant="outlined"
                                        class="mb-4"
                                        :error-messages="errors.site_tagline"
                                        hint="A short tagline or slogan (e.g., 'Your Adventure Awaits')"
                                        persistent-hint
                                    ></v-text-field>

                                    <!-- Site URL -->
                                    <v-text-field
                                        v-model="settings.site_url"
                                        label="Site URL"
                                        prepend-inner-icon="mdi-link-variant"
                                        variant="outlined"
                                        class="mb-4"
                                        :error-messages="errors.site_url"
                                        hint="The base URL of your site (e.g., https://fellowship.com)"
                                        persistent-hint
                                    ></v-text-field>
                                </v-card-text>
                            </v-card>

                            <!-- Maintenance Mode -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-wrench</v-icon>
                                    Maintenance Mode
                                </v-card-title>
                                <v-card-text>
                                    <v-switch
                                        v-model="settings.maintenance_mode"
                                        label="Enable Maintenance Mode"
                                        color="warning"
                                        class="mb-4"
                                        :error-messages="errors.maintenance_mode"
                                        hint="When enabled, site will display maintenance message to visitors"
                                        persistent-hint
                                    ></v-switch>

                                    <v-textarea
                                        v-model="settings.maintenance_message"
                                        label="Maintenance Message"
                                        prepend-inner-icon="mdi-message-text"
                                        variant="outlined"
                                        rows="3"
                                        :error-messages="errors.maintenance_message"
                                        hint="Message shown to visitors when maintenance mode is active"
                                        persistent-hint
                                    ></v-textarea>
                                </v-card-text>
                            </v-card>

                            <!-- Admin Contact Information -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-email-outline</v-icon>
                                    Admin Contact
                                </v-card-title>
                                <v-card-text>
                                    <v-text-field
                                        v-model="settings.admin_email"
                                        label="Admin Email"
                                        prepend-inner-icon="mdi-shield-account"
                                        variant="outlined"
                                        type="email"
                                        class="mb-4"
                                        :error-messages="errors.admin_email"
                                        hint="Primary admin email for system notifications"
                                        persistent-hint
                                    ></v-text-field>

                                    <v-text-field
                                        v-model="settings.support_email"
                                        label="Support Email"
                                        prepend-inner-icon="mdi-lifebuoy"
                                        variant="outlined"
                                        type="email"
                                        :error-messages="errors.support_email"
                                        hint="Support email shown to users for help requests"
                                        persistent-hint
                                    ></v-text-field>
                                </v-card-text>
                            </v-card>

                            <!-- Save Button -->
                            <v-btn
                                :loading="isSaving"
                                block
                                size="large"
                                color="primary"
                                @click="saveSettings"
                                prepend-icon="mdi-content-save"
                            >
                                Save Settings
                            </v-btn>
                        </v-window-item>

                        <!-- Contact Tab -->
                        <v-window-item value="contact">
                            <!-- Contact Information -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                        <v-card-title class="text-h6">
                            <v-icon class="mr-2">mdi-card-account-details</v-icon>
                            Contact Information
                        </v-card-title>
                        <v-card-text>
                            <!-- Contact Address -->
                            <v-text-field
                                v-model="settings.contact_address"
                                label="Contact Address"
                                prepend-inner-icon="mdi-map-marker-outline"
                                variant="outlined"
                                class="mb-4"
                                :error-messages="errors.contact_address"
                                placeholder="Musterstraße 11, 00000 Stadt, Germany"
                            ></v-text-field>

                            <!-- Contact Phone -->
                            <v-text-field
                                v-model="settings.contact_phone"
                                label="Contact Phone"
                                prepend-inner-icon="mdi-phone-outline"
                                variant="outlined"
                                class="mb-4"
                                :error-messages="errors.contact_phone"
                                placeholder="+0x 000 23 00 555 55"
                            ></v-text-field>

                            <!-- Contact Email -->
                            <v-text-field
                                v-model="settings.contact_email"
                                label="Contact Email"
                                prepend-inner-icon="mdi-email-outline"
                                variant="outlined"
                                type="email"
                                class="mb-4"
                                :error-messages="errors.contact_email"
                                placeholder="hello@example.com"
                            ></v-text-field>
                        </v-card-text>
                    </v-card>

                            <!-- Save Button -->
                            <v-btn
                                :loading="isSaving"
                                block
                                size="large"
                                color="primary"
                                @click="saveSettings"
                                prepend-icon="mdi-content-save"
                            >
                                Save Settings
                            </v-btn>
                        </v-window-item>

                        <!-- Social Tab -->
                        <v-window-item value="social">
                            <!-- Social Media Links -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                        <v-card-title class="text-h6">
                            <v-icon class="mr-2">mdi-share-variant</v-icon>
                            Social Media Links
                        </v-card-title>
                        <v-card-text>
                            <!-- Twitter URL -->
                            <v-text-field
                                v-model="settings.social_twitter"
                                label="Twitter / X URL"
                                prepend-inner-icon="mdi-twitter"
                                variant="outlined"
                                class="mb-4"
                                :error-messages="errors.social_twitter"
                                placeholder="https://twitter.com/yourhandle"
                            ></v-text-field>

                            <!-- Facebook URL -->
                            <v-text-field
                                v-model="settings.social_facebook"
                                label="Facebook URL"
                                prepend-inner-icon="mdi-facebook"
                                variant="outlined"
                                class="mb-4"
                                :error-messages="errors.social_facebook"
                                placeholder="https://facebook.com/yourpage"
                            ></v-text-field>

                            <!-- Instagram URL -->
                            <v-text-field
                                v-model="settings.social_instagram"
                                label="Instagram URL"
                                prepend-inner-icon="mdi-instagram"
                                variant="outlined"
                                class="mb-4"
                                :error-messages="errors.social_instagram"
                                placeholder="https://instagram.com/yourhandle"
                            ></v-text-field>
                        </v-card-text>
                    </v-card>

                            <!-- Save Button -->
                            <v-btn
                                :loading="isSaving"
                                block
                                size="large"
                                color="primary"
                                @click="saveSettings"
                                prepend-icon="mdi-content-save"
                            >
                                Save Settings
                            </v-btn>
                        </v-window-item>

                        <!-- Localization Tab -->
                        <v-window-item value="localization">
                            <!-- Localization Settings -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-earth</v-icon>
                                    Localization Settings
                                </v-card-title>
                                <v-card-text>

                            <!-- Default Language -->
                            <v-select
                                v-model="settings.default_language"
                                label="Default Language"
                                :items="languages"
                                item-title="name"
                                item-value="code"
                                prepend-inner-icon="mdi-translate"
                                variant="outlined"
                                class="mb-4"
                                :error-messages="errors.default_language"
                            ></v-select>

                            <!-- Default Timezone -->
                            <v-autocomplete
                                v-model="settings.default_timezone"
                                label="Default Timezone"
                                :items="timezones"
                                prepend-inner-icon="mdi-clock-outline"
                                variant="outlined"
                                class="mb-4"
                                :error-messages="errors.default_timezone"
                            ></v-autocomplete>

                            <!-- Date Format -->
                            <v-select
                                v-model="settings.date_format"
                                label="Date Format"
                                :items="dateFormats"
                                item-title="label"
                                item-value="value"
                                prepend-inner-icon="mdi-calendar"
                                variant="outlined"
                                class="mb-4"
                            ></v-select>

                            <!-- Time Format -->
                            <v-select
                                v-model="settings.time_format"
                                label="Time Format"
                                :items="timeFormats"
                                item-title="label"
                                item-value="value"
                                prepend-inner-icon="mdi-clock"
                                variant="outlined"
                                class="mb-4"
                            ></v-select>

                            <!-- Language Change Enabled -->
                            <v-switch
                                v-model="settings.language_change_enabled"
                                label="Allow users to change language"
                                color="primary"
                                :hint="settings.language_change_enabled ? 'Users can switch between available languages' : 'Language selection is disabled for users'"
                                persistent-hint
                                class="mb-4"
                            ></v-switch>

                            <!-- Auto-detect Locale -->
                            <v-switch
                                v-model="settings.locale_auto_detect"
                                label="Auto-detect user locale"
                                color="primary"
                                :hint="settings.locale_auto_detect ? 'Automatically detect user language from browser' : 'Use default language for all users'"
                                persistent-hint
                                class="mb-4"
                            ></v-switch>

                            <!-- Translation Fallback Language -->
                            <v-select
                                v-model="settings.translation_fallback_language"
                                label="Translation Fallback Language"
                                :items="languages"
                                item-title="name"
                                item-value="code"
                                prepend-inner-icon="mdi-translate-variant"
                                variant="outlined"
                                class="mb-4"
                                :error-messages="errors.translation_fallback_language"
                                hint="Language to use when translation is missing"
                                persistent-hint
                            ></v-select>
                                </v-card-text>
                            </v-card>

                            <!-- Currency Settings -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-currency-usd</v-icon>
                                    Currency & Formatting
                                </v-card-title>
                                <v-card-text>
                                    <!-- Currency -->
                                    <v-select
                                        v-model="settings.currency"
                                        label="Currency"
                                        :items="currencies"
                                        item-title="label"
                                        item-value="value"
                                        prepend-inner-icon="mdi-cash"
                                        variant="outlined"
                                        class="mb-4"
                                        :error-messages="errors.currency"
                                    ></v-select>

                                    <!-- Currency Symbol -->
                                    <v-text-field
                                        v-model="settings.currency_symbol"
                                        label="Currency Symbol"
                                        prepend-inner-icon="mdi-currency-usd"
                                        variant="outlined"
                                        class="mb-4"
                                        :error-messages="errors.currency_symbol"
                                        hint="Symbol to display (e.g., $, €, £)"
                                        persistent-hint
                                    ></v-text-field>

                                    <!-- Currency Symbol Position -->
                                    <v-select
                                        v-model="settings.currency_symbol_position"
                                        label="Currency Symbol Position"
                                        :items="currencyPositions"
                                        item-title="label"
                                        item-value="value"
                                        prepend-inner-icon="mdi-format-align-left"
                                        variant="outlined"
                                        class="mb-4"
                                        :error-messages="errors.currency_symbol_position"
                                    ></v-select>

                                    <!-- Number Format Locale -->
                                    <v-select
                                        v-model="settings.number_format_locale"
                                        label="Number Format Locale"
                                        :items="numberLocales"
                                        item-title="label"
                                        item-value="value"
                                        prepend-inner-icon="mdi-numeric"
                                        variant="outlined"
                                        class="mb-4"
                                        :error-messages="errors.number_format_locale"
                                        hint="Format for displaying numbers (e.g., 1,234.56 vs 1.234,56)"
                                        persistent-hint
                                    ></v-select>
                                </v-card-text>
                            </v-card>

                            <!-- Advanced Localization -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-cog-outline</v-icon>
                                    Advanced Options
                                </v-card-title>
                                <v-card-text>
                                    <!-- RTL Support -->
                                    <v-switch
                                        v-model="settings.rtl_support"
                                        label="Enable RTL (Right-to-Left) Support"
                                        color="primary"
                                        :hint="settings.rtl_support ? 'Interface supports RTL languages (Arabic, Hebrew)' : 'RTL support disabled'"
                                        persistent-hint
                                    ></v-switch>
                                </v-card-text>
                            </v-card>

                            <!-- Save Button -->
                            <v-btn
                                :loading="isSaving"
                                block
                                size="large"
                                color="primary"
                                @click="saveSettings"
                                prepend-icon="mdi-content-save"
                            >
                                Save Settings
                            </v-btn>
                        </v-window-item>

                        <!-- Branding Tab -->
                        <v-window-item value="branding">
                            <!-- Logos & Icons -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-image-multiple</v-icon>
                                    Logos & Icons
                                </v-card-title>
                                <v-card-text>
                                    <v-row>
                                        <!-- Logo Light -->
                                        <v-col cols="12" md="6">
                                            <div class="text-subtitle-2 mb-2">Logo (Light Theme)</div>
                                            <div class="d-flex flex-column align-center mb-4">
                                                <v-img
                                                    v-if="brandingImages.logo_light_preview"
                                                    :src="brandingImages.logo_light_preview"
                                                    max-height="100"
                                                    max-width="150"
                                                    class="mb-2"
                                                    contain
                                                ></v-img>
                                                <div v-else class="image-placeholder mb-2">
                                                    <v-icon size="48" color="grey-lighten-2">mdi-image-off</v-icon>
                                                </div>
                                                <v-file-input
                                                    v-model="brandingImages.logo_light_file"
                                                    label="Upload Light Logo"
                                                    accept="image/*"
                                                    prepend-icon="mdi-image"
                                                    density="compact"
                                                    variant="outlined"
                                                    @change="handleImageSelect('logo_light')"
                                                    show-size
                                                ></v-file-input>
                                                <div class="d-flex gap-2">
                                                    <v-btn
                                                        v-if="brandingImages.logo_light_file"
                                                        color="primary"
                                                        size="small"
                                                        :loading="brandingImages.logo_light_uploading"
                                                        @click="uploadImage('logo_light')"
                                                    >
                                                        Upload
                                                    </v-btn>
                                                    <v-btn
                                                        v-if="settings.logo_light"
                                                        color="error"
                                                        size="small"
                                                        variant="outlined"
                                                        @click="deleteImage('logo_light')"
                                                    >
                                                        Delete
                                                    </v-btn>
                                                </div>
                                            </div>
                                        </v-col>

                                        <!-- Logo Dark -->
                                        <v-col cols="12" md="6">
                                            <div class="text-subtitle-2 mb-2">Logo (Dark Theme)</div>
                                            <div class="d-flex flex-column align-center mb-4">
                                                <v-img
                                                    v-if="brandingImages.logo_dark_preview"
                                                    :src="brandingImages.logo_dark_preview"
                                                    max-height="100"
                                                    max-width="150"
                                                    class="mb-2 pa-2 bg-grey-darken-4 rounded"
                                                    contain
                                                ></v-img>
                                                <div v-else class="image-placeholder mb-2">
                                                    <v-icon size="48" color="grey-lighten-2">mdi-image-off</v-icon>
                                                </div>
                                                <v-file-input
                                                    v-model="brandingImages.logo_dark_file"
                                                    label="Upload Dark Logo"
                                                    accept="image/*"
                                                    prepend-icon="mdi-image"
                                                    density="compact"
                                                    variant="outlined"
                                                    @change="handleImageSelect('logo_dark')"
                                                    show-size
                                                ></v-file-input>
                                                <div class="d-flex gap-2">
                                                    <v-btn
                                                        v-if="brandingImages.logo_dark_file"
                                                        color="primary"
                                                        size="small"
                                                        :loading="brandingImages.logo_dark_uploading"
                                                        @click="uploadImage('logo_dark')"
                                                    >
                                                        Upload
                                                    </v-btn>
                                                    <v-btn
                                                        v-if="settings.logo_dark"
                                                        color="error"
                                                        size="small"
                                                        variant="outlined"
                                                        @click="deleteImage('logo_dark')"
                                                    >
                                                        Delete
                                                    </v-btn>
                                                </div>
                                            </div>
                                        </v-col>

                                        <!-- Favicon -->
                                        <v-col cols="12" md="6">
                                            <div class="text-subtitle-2 mb-2">Favicon</div>
                                            <div class="d-flex flex-column align-center mb-4">
                                                <v-img
                                                    v-if="brandingImages.favicon_preview"
                                                    :src="brandingImages.favicon_preview"
                                                    max-height="64"
                                                    max-width="64"
                                                    class="mb-2"
                                                    contain
                                                ></v-img>
                                                <div v-else class="image-placeholder-small mb-2">
                                                    <v-icon size="32" color="grey-lighten-2">mdi-image-off</v-icon>
                                                </div>
                                                <v-file-input
                                                    v-model="brandingImages.favicon_file"
                                                    label="Upload Favicon"
                                                    accept="image/*,.ico"
                                                    prepend-icon="mdi-star-circle"
                                                    density="compact"
                                                    variant="outlined"
                                                    @change="handleImageSelect('favicon')"
                                                    show-size
                                                ></v-file-input>
                                                <div class="d-flex gap-2">
                                                    <v-btn
                                                        v-if="brandingImages.favicon_file"
                                                        color="primary"
                                                        size="small"
                                                        :loading="brandingImages.favicon_uploading"
                                                        @click="uploadImage('favicon')"
                                                    >
                                                        Upload
                                                    </v-btn>
                                                    <v-btn
                                                        v-if="settings.favicon"
                                                        color="error"
                                                        size="small"
                                                        variant="outlined"
                                                        @click="deleteImage('favicon')"
                                                    >
                                                        Delete
                                                    </v-btn>
                                                </div>
                                            </div>
                                        </v-col>

                                        <!-- App Icon -->
                                        <v-col cols="12" md="6">
                                            <div class="text-subtitle-2 mb-2">App Icon</div>
                                            <div class="d-flex flex-column align-center mb-4">
                                                <v-img
                                                    v-if="brandingImages.app_icon_preview"
                                                    :src="brandingImages.app_icon_preview"
                                                    max-height="64"
                                                    max-width="64"
                                                    class="mb-2"
                                                    contain
                                                ></v-img>
                                                <div v-else class="image-placeholder-small mb-2">
                                                    <v-icon size="32" color="grey-lighten-2">mdi-image-off</v-icon>
                                                </div>
                                                <v-file-input
                                                    v-model="brandingImages.app_icon_file"
                                                    label="Upload App Icon"
                                                    accept="image/*"
                                                    prepend-icon="mdi-cellphone"
                                                    density="compact"
                                                    variant="outlined"
                                                    @change="handleImageSelect('app_icon')"
                                                    show-size
                                                ></v-file-input>
                                                <div class="d-flex gap-2">
                                                    <v-btn
                                                        v-if="brandingImages.app_icon_file"
                                                        color="primary"
                                                        size="small"
                                                        :loading="brandingImages.app_icon_uploading"
                                                        @click="uploadImage('app_icon')"
                                                    >
                                                        Upload
                                                    </v-btn>
                                                    <v-btn
                                                        v-if="settings.app_icon"
                                                        color="error"
                                                        size="small"
                                                        variant="outlined"
                                                        @click="deleteImage('app_icon')"
                                                    >
                                                        Delete
                                                    </v-btn>
                                                </div>
                                            </div>
                                        </v-col>
                                    </v-row>
                                </v-card-text>
                            </v-card>

                            <!-- Colors & Theme -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-palette</v-icon>
                                    Colors & Theme
                                </v-card-title>
                                <v-card-text>
                                    <v-row>
                                        <v-col cols="12" md="6">
                                            <v-text-field
                                                v-model="settings.primary_color"
                                                label="Primary Color"
                                                prepend-inner-icon="mdi-palette"
                                                variant="outlined"
                                                type="color"
                                                :error-messages="errors.primary_color"
                                                hint="Main brand color used throughout the application"
                                                persistent-hint
                                            ></v-text-field>
                                        </v-col>

                                        <v-col cols="12" md="6">
                                            <v-text-field
                                                v-model="settings.secondary_color"
                                                label="Secondary Color"
                                                prepend-inner-icon="mdi-palette-outline"
                                                variant="outlined"
                                                type="color"
                                                :error-messages="errors.secondary_color"
                                                hint="Secondary accent color"
                                                persistent-hint
                                            ></v-text-field>
                                        </v-col>

                                        <v-col cols="12">
                                            <v-select
                                                v-model="settings.theme_mode"
                                                label="Theme Mode"
                                                :items="themeModes"
                                                item-title="label"
                                                item-value="value"
                                                prepend-inner-icon="mdi-theme-light-dark"
                                                variant="outlined"
                                                :error-messages="errors.theme_mode"
                                                hint="Default theme appearance for users"
                                                persistent-hint
                                            ></v-select>
                                        </v-col>
                                    </v-row>
                                </v-card-text>
                            </v-card>

                            <!-- Typography & Custom Styles -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-format-font</v-icon>
                                    Typography & Custom Styles
                                </v-card-title>
                                <v-card-text>
                                    <v-select
                                        v-model="settings.font_family"
                                        label="Font Family"
                                        :items="fontFamilies"
                                        item-title="label"
                                        item-value="value"
                                        prepend-inner-icon="mdi-format-font"
                                        variant="outlined"
                                        class="mb-4"
                                        :error-messages="errors.font_family"
                                    ></v-select>

                                    <v-textarea
                                        v-model="settings.custom_css"
                                        label="Custom CSS"
                                        prepend-inner-icon="mdi-language-css3"
                                        variant="outlined"
                                        rows="6"
                                        :error-messages="errors.custom_css"
                                        hint="Add custom CSS styles (advanced users only)"
                                        persistent-hint
                                        placeholder=".my-custom-class { color: red; }"
                                    ></v-textarea>
                                </v-card-text>
                            </v-card>

                            <!-- Additional Branding Options -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-cog-outline</v-icon>
                                    Additional Options
                                </v-card-title>
                                <v-card-text>
                                    <v-switch
                                        v-model="settings.login_branding_enabled"
                                        label="Enable Login Page Branding"
                                        color="primary"
                                        :hint="settings.login_branding_enabled ? 'Show custom branding on login page' : 'Use default login page appearance'"
                                        persistent-hint
                                    ></v-switch>
                                </v-card-text>
                            </v-card>

                            <!-- Save Button -->
                            <v-btn
                                :loading="isSaving"
                                block
                                size="large"
                                color="primary"
                                @click="saveSettings"
                                prepend-icon="mdi-content-save"
                            >
                                Save Settings
                            </v-btn>
                        </v-window-item>

                        <!-- SEO Tab -->
                        <v-window-item value="seo">
                            <!-- Basic SEO Metadata -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-text-search</v-icon>
                                    Basic SEO Metadata
                                </v-card-title>
                                <v-card-text>
                                    <v-text-field
                                        v-model="settings.meta_title"
                                        label="Meta Title"
                                        prepend-inner-icon="mdi-format-title"
                                        variant="outlined"
                                        class="mb-4"
                                        :error-messages="errors.meta_title"
                                        hint="Default page title for SEO (50-60 characters recommended)"
                                        persistent-hint
                                        counter="60"
                                    ></v-text-field>

                                    <v-textarea
                                        v-model="settings.meta_description"
                                        label="Meta Description"
                                        prepend-inner-icon="mdi-text"
                                        variant="outlined"
                                        class="mb-4"
                                        rows="3"
                                        :error-messages="errors.meta_description"
                                        hint="Default meta description (150-160 characters recommended)"
                                        persistent-hint
                                        counter="160"
                                    ></v-textarea>

                                    <v-text-field
                                        v-model="settings.meta_keywords"
                                        label="Meta Keywords"
                                        prepend-inner-icon="mdi-key-variant"
                                        variant="outlined"
                                        :error-messages="errors.meta_keywords"
                                        hint="Comma-separated keywords (e.g., gaming, community, rpg)"
                                        persistent-hint
                                    ></v-text-field>
                                </v-card-text>
                            </v-card>

                            <!-- Open Graph (Social Media) -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-share-variant</v-icon>
                                    Open Graph (Social Media)
                                </v-card-title>
                                <v-card-text>
                                    <v-text-field
                                        v-model="settings.og_title"
                                        label="OG Title"
                                        prepend-inner-icon="mdi-format-title"
                                        variant="outlined"
                                        class="mb-4"
                                        :error-messages="errors.og_title"
                                        hint="Title shown when shared on social media"
                                        persistent-hint
                                    ></v-text-field>

                                    <v-textarea
                                        v-model="settings.og_description"
                                        label="OG Description"
                                        prepend-inner-icon="mdi-text"
                                        variant="outlined"
                                        class="mb-4"
                                        rows="2"
                                        :error-messages="errors.og_description"
                                        hint="Description shown when shared on social media"
                                        persistent-hint
                                    ></v-textarea>

                                    <!-- OG Image Upload -->
                                    <div class="text-subtitle-2 mb-2">OG Image</div>
                                    <div class="d-flex align-center mb-4">
                                        <v-img
                                            v-if="brandingImages.og_image_preview"
                                            :src="brandingImages.og_image_preview"
                                            max-height="100"
                                            max-width="200"
                                            class="mr-4"
                                            contain
                                        ></v-img>
                                        <div v-else class="image-placeholder mr-4">
                                            <v-icon size="48" color="grey-lighten-2">mdi-image-off</v-icon>
                                        </div>
                                        <div class="flex-grow-1">
                                            <v-file-input
                                                v-model="brandingImages.og_image_file"
                                                label="Upload OG Image"
                                                accept="image/*"
                                                prepend-icon="mdi-image"
                                                density="compact"
                                                variant="outlined"
                                                @change="handleImageSelect('og_image')"
                                                show-size
                                                hint="Recommended: 1200x630px"
                                                persistent-hint
                                            ></v-file-input>
                                            <div class="d-flex gap-2 mt-2">
                                                <v-btn
                                                    v-if="brandingImages.og_image_file"
                                                    color="primary"
                                                    size="small"
                                                    :loading="brandingImages.og_image_uploading"
                                                    @click="uploadImage('og_image')"
                                                >
                                                    Upload
                                                </v-btn>
                                                <v-btn
                                                    v-if="settings.og_image"
                                                    color="error"
                                                    size="small"
                                                    variant="outlined"
                                                    @click="deleteImage('og_image')"
                                                >
                                                    Delete
                                                </v-btn>
                                            </div>
                                        </div>
                                    </div>
                                </v-card-text>
                            </v-card>

                            <!-- Twitter Card Settings -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-twitter</v-icon>
                                    Twitter Card Settings
                                </v-card-title>
                                <v-card-text>
                                    <v-select
                                        v-model="settings.twitter_card_type"
                                        label="Twitter Card Type"
                                        :items="twitterCardTypes"
                                        item-title="label"
                                        item-value="value"
                                        prepend-inner-icon="mdi-card-text-outline"
                                        variant="outlined"
                                        class="mb-4"
                                        :error-messages="errors.twitter_card_type"
                                    ></v-select>

                                    <v-text-field
                                        v-model="settings.twitter_site"
                                        label="Twitter Site Handle"
                                        prepend-inner-icon="mdi-at"
                                        variant="outlined"
                                        :error-messages="errors.twitter_site"
                                        hint="Twitter username (e.g., @yoursite)"
                                        persistent-hint
                                        placeholder="@yoursite"
                                    ></v-text-field>
                                </v-card-text>
                            </v-card>

                            <!-- Search Engine Settings -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-search-web</v-icon>
                                    Search Engine Settings
                                </v-card-title>
                                <v-card-text>
                                    <v-text-field
                                        v-model="settings.canonical_url"
                                        label="Canonical URL"
                                        prepend-inner-icon="mdi-link-variant"
                                        variant="outlined"
                                        class="mb-4"
                                        :error-messages="errors.canonical_url"
                                        hint="Preferred URL for this site"
                                        persistent-hint
                                        placeholder="https://yoursite.com"
                                    ></v-text-field>

                                    <v-switch
                                        v-model="settings.indexing_enabled"
                                        label="Allow Search Engine Indexing"
                                        color="primary"
                                        class="mb-4"
                                        :hint="settings.indexing_enabled ? 'Site can be indexed by search engines' : 'Site is hidden from search engines'"
                                        persistent-hint
                                    ></v-switch>

                                    <v-switch
                                        v-model="settings.sitemap_enabled"
                                        label="Enable XML Sitemap"
                                        color="primary"
                                        class="mb-4"
                                        :hint="settings.sitemap_enabled ? 'XML sitemap is automatically generated' : 'Sitemap generation disabled'"
                                        persistent-hint
                                    ></v-switch>

                                    <v-textarea
                                        v-model="settings.robots_txt_custom"
                                        label="Custom Robots.txt Rules"
                                        prepend-inner-icon="mdi-robot"
                                        variant="outlined"
                                        rows="4"
                                        :error-messages="errors.robots_txt_custom"
                                        hint="Additional rules for robots.txt (advanced)"
                                        persistent-hint
                                        placeholder="User-agent: *&#10;Disallow: /admin/"
                                    ></v-textarea>
                                </v-card-text>
                            </v-card>

                            <!-- Save Button -->
                            <v-btn
                                :loading="isSaving"
                                block
                                size="large"
                                color="primary"
                                @click="saveSettings"
                                prepend-icon="mdi-content-save"
                            >
                                Save Settings
                            </v-btn>
                        </v-window-item>

                        <!-- Analytics Tab -->
                        <v-window-item value="analytics">
                            <!-- Google Analytics & Tag Manager -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-google-analytics</v-icon>
                                    Google Analytics & Tag Manager
                                </v-card-title>
                                <v-card-text>
                                    <v-text-field
                                        v-model="settings.google_analytics_id"
                                        label="Google Analytics ID"
                                        prepend-inner-icon="mdi-google-analytics"
                                        variant="outlined"
                                        class="mb-4"
                                        :error-messages="errors.google_analytics_id"
                                        hint="Google Analytics tracking ID (e.g., G-XXXXXXXXXX or UA-XXXXXXXXX-X)"
                                        persistent-hint
                                        placeholder="G-XXXXXXXXXX"
                                    ></v-text-field>

                                    <v-text-field
                                        v-model="settings.google_tag_manager_id"
                                        label="Google Tag Manager ID"
                                        prepend-inner-icon="mdi-tag-outline"
                                        variant="outlined"
                                        :error-messages="errors.google_tag_manager_id"
                                        hint="Google Tag Manager container ID (e.g., GTM-XXXXXXX)"
                                        persistent-hint
                                        placeholder="GTM-XXXXXXX"
                                    ></v-text-field>
                                </v-card-text>
                            </v-card>

                            <!-- Custom Tracking Scripts -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-code-braces</v-icon>
                                    Custom Tracking Scripts
                                </v-card-title>
                                <v-card-text>
                                    <v-textarea
                                        v-model="settings.analytics_script_header"
                                        label="Header Scripts"
                                        prepend-inner-icon="mdi-code-tags"
                                        variant="outlined"
                                        class="mb-4"
                                        rows="4"
                                        :error-messages="errors.analytics_script_header"
                                        hint="Scripts to include in <head> section"
                                        persistent-hint
                                        placeholder="<script>...</script>"
                                    ></v-textarea>

                                    <v-textarea
                                        v-model="settings.analytics_script_body"
                                        label="Body Scripts (Top)"
                                        prepend-inner-icon="mdi-code-tags"
                                        variant="outlined"
                                        class="mb-4"
                                        rows="4"
                                        :error-messages="errors.analytics_script_body"
                                        hint="Scripts to include at start of <body> section"
                                        persistent-hint
                                        placeholder="<script>...</script>"
                                    ></v-textarea>

                                    <v-textarea
                                        v-model="settings.analytics_script_footer"
                                        label="Footer Scripts"
                                        prepend-inner-icon="mdi-code-tags"
                                        variant="outlined"
                                        rows="4"
                                        :error-messages="errors.analytics_script_footer"
                                        hint="Scripts to include before closing </body> tag"
                                        persistent-hint
                                        placeholder="<script>...</script>"
                                    ></v-textarea>
                                </v-card-text>
                            </v-card>

                            <!-- Cookie Consent & Privacy -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-cookie</v-icon>
                                    Cookie Consent & Privacy
                                </v-card-title>
                                <v-card-text>
                                    <v-switch
                                        v-model="settings.cookie_consent_enabled"
                                        label="Enable Cookie Consent Banner"
                                        color="primary"
                                        class="mb-4"
                                        :hint="settings.cookie_consent_enabled ? 'Users must consent before tracking cookies are set' : 'Cookie consent banner disabled'"
                                        persistent-hint
                                    ></v-switch>

                                    <v-switch
                                        v-model="settings.anonymize_ip"
                                        label="Anonymize IP Addresses"
                                        color="primary"
                                        class="mb-4"
                                        :hint="settings.anonymize_ip ? 'IP addresses will be anonymized for privacy' : 'Full IP addresses will be tracked'"
                                        persistent-hint
                                    ></v-switch>

                                    <v-switch
                                        v-model="settings.tracking_production_only"
                                        label="Enable Tracking in Production Only"
                                        color="primary"
                                        :hint="settings.tracking_production_only ? 'Analytics only run in production environment' : 'Analytics run in all environments'"
                                        persistent-hint
                                    ></v-switch>
                                </v-card-text>
                            </v-card>

                            <!-- Save Button -->
                            <v-btn
                                :loading="isSaving"
                                block
                                size="large"
                                color="primary"
                                @click="saveSettings"
                                prepend-icon="mdi-content-save"
                            >
                                Save Settings
                            </v-btn>
                        </v-window-item>

                        <!-- Scripts Tab -->
                        <v-window-item value="scripts">
                            <!-- Custom Scripts -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-code-tags</v-icon>
                                    Custom Scripts
                                </v-card-title>
                                <v-card-text>
                                    <v-textarea
                                        v-model="settings.custom_head_scripts"
                                        label="Head Scripts"
                                        prepend-inner-icon="mdi-code-braces"
                                        variant="outlined"
                                        class="mb-4"
                                        rows="5"
                                        :error-messages="errors.custom_head_scripts"
                                        hint="Custom scripts for <head> section"
                                        persistent-hint
                                        placeholder="<script>...</script>"
                                    ></v-textarea>

                                    <v-textarea
                                        v-model="settings.custom_body_scripts"
                                        label="Body Scripts"
                                        prepend-inner-icon="mdi-code-braces"
                                        variant="outlined"
                                        class="mb-4"
                                        rows="5"
                                        :error-messages="errors.custom_body_scripts"
                                        hint="Custom scripts for <body> section"
                                        persistent-hint
                                        placeholder="<script>...</script>"
                                    ></v-textarea>

                                    <v-textarea
                                        v-model="settings.custom_footer_scripts"
                                        label="Footer Scripts"
                                        prepend-inner-icon="mdi-code-braces"
                                        variant="outlined"
                                        rows="5"
                                        :error-messages="errors.custom_footer_scripts"
                                        hint="Custom scripts before closing </body>"
                                        persistent-hint
                                        placeholder="<script>...</script>"
                                    ></v-textarea>
                                </v-card-text>
                            </v-card>

                            <!-- Chat & Communication -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-chat</v-icon>
                                    Chat & Communication
                                </v-card-title>
                                <v-card-text>
                                    <v-textarea
                                        v-model="settings.chat_widget_script"
                                        label="Chat Widget Script"
                                        prepend-inner-icon="mdi-chat-processing"
                                        variant="outlined"
                                        rows="4"
                                        :error-messages="errors.chat_widget_script"
                                        hint="Embed code for chat widget (e.g., Intercom, Drift, Crisp)"
                                        persistent-hint
                                        placeholder="<script>...</script>"
                                    ></v-textarea>
                                </v-card-text>
                            </v-card>

                            <!-- Third-Party Integrations -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-puzzle</v-icon>
                                    Third-Party Integrations
                                </v-card-title>
                                <v-card-text>
                                    <v-switch
                                        v-model="settings.third_party_embeds_enabled"
                                        label="Allow Third-Party Embeds"
                                        color="primary"
                                        class="mb-4"
                                        :hint="settings.third_party_embeds_enabled ? 'Users can embed content from YouTube, Twitter, etc.' : 'Third-party embeds are disabled'"
                                        persistent-hint
                                    ></v-switch>

                                    <v-select
                                        v-model="settings.newsletter_provider"
                                        label="Newsletter Provider"
                                        :items="newsletterProviders"
                                        item-title="label"
                                        item-value="value"
                                        prepend-inner-icon="mdi-email-newsletter"
                                        variant="outlined"
                                        class="mb-4"
                                        :error-messages="errors.newsletter_provider"
                                    ></v-select>

                                    <v-text-field
                                        v-if="settings.newsletter_provider !== 'none'"
                                        v-model="settings.newsletter_api_key"
                                        label="Newsletter API Key"
                                        prepend-inner-icon="mdi-key"
                                        variant="outlined"
                                        type="password"
                                        :error-messages="errors.newsletter_api_key"
                                        hint="API key for newsletter provider"
                                        persistent-hint
                                    ></v-text-field>
                                </v-card-text>
                            </v-card>

                            <!-- Save Button -->
                            <v-btn
                                :loading="isSaving"
                                block
                                size="large"
                                color="primary"
                                @click="saveSettings"
                                prepend-icon="mdi-content-save"
                            >
                                Save Settings
                            </v-btn>
                        </v-window-item>

                        <!-- Content Tab -->
                        <v-window-item value="content">
                            <!-- General Content Settings -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-file-document-outline</v-icon>
                                    General Content Settings
                                </v-card-title>
                                <v-card-text>
                                    <v-select
                                        v-model="settings.homepage_type"
                                        label="Homepage Type"
                                        :items="[{label: 'Default', value: 'default'}, {label: 'Custom', value: 'custom'}, {label: 'Wiki', value: 'wiki'}]"
                                        item-title="label"
                                        item-value="value"
                                        prepend-inner-icon="mdi-home"
                                        variant="outlined"
                                        class="mb-4"
                                    ></v-select>

                                    <v-switch
                                        v-model="settings.blog_enabled"
                                        label="Enable Blog"
                                        color="primary"
                                        class="mb-4"
                                        :hint="settings.blog_enabled ? 'Blog functionality is enabled' : 'Blog is disabled'"
                                        persistent-hint
                                    ></v-switch>

                                    <v-text-field
                                        v-if="settings.blog_enabled"
                                        v-model.number="settings.posts_per_page"
                                        label="Posts Per Page"
                                        prepend-inner-icon="mdi-file-document-multiple"
                                        variant="outlined"
                                        type="number"
                                        class="mb-4"
                                        :error-messages="errors.posts_per_page"
                                    ></v-text-field>
                                </v-card-text>
                            </v-card>

                            <!-- Comments -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-comment-multiple</v-icon>
                                    Comments
                                </v-card-title>
                                <v-card-text>
                                    <v-switch
                                        v-model="settings.comments_enabled"
                                        label="Enable Comments"
                                        color="primary"
                                        class="mb-4"
                                        :hint="settings.comments_enabled ? 'Users can post comments' : 'Comments disabled'"
                                        persistent-hint
                                    ></v-switch>

                                    <v-switch
                                        v-if="settings.comments_enabled"
                                        v-model="settings.comments_moderation_required"
                                        label="Require Comment Moderation"
                                        color="primary"
                                        :hint="settings.comments_moderation_required ? 'Comments must be approved before publishing' : 'Comments appear immediately'"
                                        persistent-hint
                                    ></v-switch>
                                </v-card-text>
                            </v-card>

                            <!-- Media Uploads -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-file-upload</v-icon>
                                    Media Uploads
                                </v-card-title>
                                <v-card-text>
                                    <v-text-field
                                        v-model.number="settings.media_max_upload_size"
                                        label="Max Upload Size (KB)"
                                        prepend-inner-icon="mdi-file-upload"
                                        variant="outlined"
                                        type="number"
                                        class="mb-4"
                                        :error-messages="errors.media_max_upload_size"
                                        hint="Maximum file size in kilobytes"
                                        persistent-hint
                                    ></v-text-field>

                                    <v-text-field
                                        v-model="settings.allowed_file_types"
                                        label="Allowed File Types"
                                        prepend-inner-icon="mdi-file-check"
                                        variant="outlined"
                                        :error-messages="errors.allowed_file_types"
                                        hint="Comma-separated file extensions (e.g., jpg,png,pdf)"
                                        persistent-hint
                                    ></v-text-field>
                                </v-card-text>
                            </v-card>

                            <!-- Save Button -->
                            <v-btn
                                :loading="isSaving"
                                block
                                size="large"
                                color="primary"
                                @click="saveSettings"
                                prepend-icon="mdi-content-save"
                            >
                                Save Settings
                            </v-btn>
                        </v-window-item>

                        <!-- Users Tab -->
                        <v-window-item value="users">
                            <!-- Registration & Authentication -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-account-plus</v-icon>
                                    Registration & Authentication
                                </v-card-title>
                                <v-card-text>
                                    <v-switch
                                        v-model="settings.user_registration_enabled"
                                        label="Enable User Registration"
                                        color="primary"
                                        class="mb-4"
                                        :hint="settings.user_registration_enabled ? 'New users can register' : 'Registration is closed'"
                                        persistent-hint
                                    ></v-switch>

                                    <v-text-field
                                        v-model="settings.default_user_role"
                                        label="Default User Role"
                                        prepend-inner-icon="mdi-account-badge"
                                        variant="outlined"
                                        class="mb-4"
                                        hint="Role assigned to new users"
                                        persistent-hint
                                    ></v-text-field>

                                    <v-switch
                                        v-model="settings.email_verification_required"
                                        label="Require Email Verification"
                                        color="primary"
                                        class="mb-4"
                                        :hint="settings.email_verification_required ? 'Users must verify email before accessing site' : 'No email verification required'"
                                        persistent-hint
                                    ></v-switch>

                                    <v-switch
                                        v-model="settings.two_factor_enabled"
                                        label="Enable Two-Factor Authentication"
                                        color="primary"
                                        :hint="settings.two_factor_enabled ? '2FA available for users' : '2FA disabled'"
                                        persistent-hint
                                    ></v-switch>
                                </v-card-text>
                            </v-card>

                            <!-- Password Requirements -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-lock</v-icon>
                                    Password Requirements
                                </v-card-title>
                                <v-card-text>
                                    <v-text-field
                                        v-model.number="settings.password_min_length"
                                        label="Minimum Password Length"
                                        prepend-inner-icon="mdi-form-textbox-password"
                                        variant="outlined"
                                        type="number"
                                        class="mb-4"
                                    ></v-text-field>

                                    <v-switch
                                        v-model="settings.password_require_special_char"
                                        label="Require Special Character"
                                        color="primary"
                                        class="mb-4"
                                    ></v-switch>

                                    <v-switch
                                        v-model="settings.password_require_number"
                                        label="Require Number"
                                        color="primary"
                                        class="mb-4"
                                    ></v-switch>

                                    <v-switch
                                        v-model="settings.password_require_uppercase"
                                        label="Require Uppercase Letter"
                                        color="primary"
                                    ></v-switch>
                                </v-card-text>
                            </v-card>

                            <!-- Session & Security -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-shield-lock</v-icon>
                                    Session & Security
                                </v-card-title>
                                <v-card-text>
                                    <v-text-field
                                        v-model.number="settings.session_timeout_minutes"
                                        label="Session Timeout (Minutes)"
                                        prepend-inner-icon="mdi-timer"
                                        variant="outlined"
                                        type="number"
                                        class="mb-4"
                                    ></v-text-field>

                                    <v-text-field
                                        v-model.number="settings.login_rate_limit_attempts"
                                        label="Login Rate Limit Attempts"
                                        prepend-inner-icon="mdi-counter"
                                        variant="outlined"
                                        type="number"
                                        class="mb-4"
                                        hint="Max login attempts before lockout"
                                        persistent-hint
                                    ></v-text-field>

                                    <v-text-field
                                        v-model.number="settings.login_rate_limit_minutes"
                                        label="Login Rate Limit Window (Minutes)"
                                        prepend-inner-icon="mdi-clock-outline"
                                        variant="outlined"
                                        type="number"
                                        hint="Time window for rate limiting"
                                        persistent-hint
                                    ></v-text-field>
                                </v-card-text>
                            </v-card>

                            <!-- Save Button -->
                            <v-btn
                                :loading="isSaving"
                                block
                                size="large"
                                color="primary"
                                @click="saveSettings"
                                prepend-icon="mdi-content-save"
                            >
                                Save Settings
                            </v-btn>
                        </v-window-item>

                        <!-- Email Tab -->
                        <v-window-item value="email">
                            <!-- Email Sender Settings -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-email-send</v-icon>
                                    Email Sender Settings
                                </v-card-title>
                                <v-card-text>
                                    <v-text-field
                                        v-model="settings.email_sender_name"
                                        label="Sender Name"
                                        prepend-inner-icon="mdi-account"
                                        variant="outlined"
                                        class="mb-4"
                                        hint="Name shown in sent emails"
                                        persistent-hint
                                    ></v-text-field>

                                    <v-text-field
                                        v-model="settings.email_sender_address"
                                        label="Sender Email Address"
                                        prepend-inner-icon="mdi-email"
                                        variant="outlined"
                                        type="email"
                                        hint="Email address for outgoing mail"
                                        persistent-hint
                                    ></v-text-field>
                                </v-card-text>
                            </v-card>

                            <!-- SMTP Configuration -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-server</v-icon>
                                    SMTP Configuration
                                </v-card-title>
                                <v-card-text>
                                    <v-text-field
                                        v-model="settings.smtp_host"
                                        label="SMTP Host"
                                        prepend-inner-icon="mdi-server-network"
                                        variant="outlined"
                                        class="mb-4"
                                    ></v-text-field>

                                    <v-text-field
                                        v-model.number="settings.smtp_port"
                                        label="SMTP Port"
                                        prepend-inner-icon="mdi-ethernet"
                                        variant="outlined"
                                        type="number"
                                        class="mb-4"
                                    ></v-text-field>

                                    <v-text-field
                                        v-model="settings.smtp_username"
                                        label="SMTP Username"
                                        prepend-inner-icon="mdi-account"
                                        variant="outlined"
                                        class="mb-4"
                                    ></v-text-field>

                                    <v-text-field
                                        v-model="settings.smtp_password"
                                        label="SMTP Password"
                                        prepend-inner-icon="mdi-lock"
                                        variant="outlined"
                                        type="password"
                                        class="mb-4"
                                    ></v-text-field>

                                    <v-select
                                        v-model="settings.smtp_encryption"
                                        label="Encryption"
                                        :items="[{label: 'TLS', value: 'tls'}, {label: 'SSL', value: 'ssl'}, {label: 'None', value: 'none'}]"
                                        item-title="label"
                                        item-value="value"
                                        prepend-inner-icon="mdi-shield-lock"
                                        variant="outlined"
                                    ></v-select>
                                </v-card-text>
                            </v-card>

                            <!-- Notification Settings -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-bell</v-icon>
                                    Notification Settings
                                </v-card-title>
                                <v-card-text>
                                    <v-switch
                                        v-model="settings.admin_notifications_enabled"
                                        label="Enable Admin Notifications"
                                        color="primary"
                                        :hint="settings.admin_notifications_enabled ? 'Admins receive system notifications' : 'Admin notifications disabled'"
                                        persistent-hint
                                    ></v-switch>
                                </v-card-text>
                            </v-card>

                            <!-- Save Button -->
                            <v-btn
                                :loading="isSaving"
                                block
                                size="large"
                                color="primary"
                                @click="saveSettings"
                                prepend-icon="mdi-content-save"
                            >
                                Save Settings
                            </v-btn>
                        </v-window-item>

                        <!-- Performance Tab -->
                        <v-window-item value="performance">
                            <!-- Caching -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-cached</v-icon>
                                    Caching
                                </v-card-title>
                                <v-card-text>
                                    <v-switch
                                        v-model="settings.cache_enabled"
                                        label="Enable Caching"
                                        color="primary"
                                        class="mb-4"
                                        :hint="settings.cache_enabled ? 'Application caching is enabled' : 'Caching disabled'"
                                        persistent-hint
                                    ></v-switch>

                                    <v-text-field
                                        v-if="settings.cache_enabled"
                                        v-model.number="settings.cache_lifetime_minutes"
                                        label="Cache Lifetime (Minutes)"
                                        prepend-inner-icon="mdi-clock-outline"
                                        variant="outlined"
                                        type="number"
                                    ></v-text-field>
                                </v-card-text>
                            </v-card>

                            <!-- CDN & Assets -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-cloud-upload</v-icon>
                                    CDN & Assets
                                </v-card-title>
                                <v-card-text>
                                    <v-switch
                                        v-model="settings.cdn_enabled"
                                        label="Enable CDN"
                                        color="primary"
                                        class="mb-4"
                                        :hint="settings.cdn_enabled ? 'Assets served from CDN' : 'CDN disabled'"
                                        persistent-hint
                                    ></v-switch>

                                    <v-text-field
                                        v-if="settings.cdn_enabled"
                                        v-model="settings.cdn_url"
                                        label="CDN URL"
                                        prepend-inner-icon="mdi-link"
                                        variant="outlined"
                                        class="mb-4"
                                        placeholder="https://cdn.yoursite.com"
                                    ></v-text-field>

                                    <v-switch
                                        v-model="settings.image_optimization_enabled"
                                        label="Enable Image Optimization"
                                        color="primary"
                                        class="mb-4"
                                        :hint="settings.image_optimization_enabled ? 'Images automatically optimized' : 'Image optimization disabled'"
                                        persistent-hint
                                    ></v-switch>

                                    <v-switch
                                        v-model="settings.lazy_loading_enabled"
                                        label="Enable Lazy Loading"
                                        color="primary"
                                        :hint="settings.lazy_loading_enabled ? 'Images load on-demand' : 'Lazy loading disabled'"
                                        persistent-hint
                                    ></v-switch>
                                </v-card-text>
                            </v-card>

                            <!-- Debug Mode -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-bug</v-icon>
                                    Debug Mode
                                </v-card-title>
                                <v-card-text>
                                    <v-switch
                                        v-model="settings.debug_mode"
                                        label="Enable Debug Mode"
                                        color="warning"
                                        :hint="settings.debug_mode ? 'Debug mode ON - should be OFF in production!' : 'Debug mode disabled'"
                                        persistent-hint
                                    ></v-switch>
                                </v-card-text>
                            </v-card>

                            <!-- Save Button -->
                            <v-btn
                                :loading="isSaving"
                                block
                                size="large"
                                color="primary"
                                @click="saveSettings"
                                prepend-icon="mdi-content-save"
                            >
                                Save Settings
                            </v-btn>
                        </v-window-item>

                        <!-- Legal Tab -->
                        <v-window-item value="legal">
                            <!-- Legal Pages -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-file-document</v-icon>
                                    Legal Pages
                                </v-card-title>
                                <v-card-text>
                                    <v-text-field
                                        v-model="settings.privacy_policy_url"
                                        label="Privacy Policy URL"
                                        prepend-inner-icon="mdi-shield-account"
                                        variant="outlined"
                                        class="mb-4"
                                        placeholder="/privacy-policy"
                                    ></v-text-field>

                                    <v-text-field
                                        v-model="settings.terms_conditions_url"
                                        label="Terms & Conditions URL"
                                        prepend-inner-icon="mdi-file-document-outline"
                                        variant="outlined"
                                        class="mb-4"
                                        placeholder="/terms-conditions"
                                    ></v-text-field>

                                    <v-text-field
                                        v-model="settings.cookie_policy_url"
                                        label="Cookie Policy URL"
                                        prepend-inner-icon="mdi-cookie"
                                        variant="outlined"
                                        placeholder="/cookie-policy"
                                    ></v-text-field>
                                </v-card-text>
                            </v-card>

                            <!-- GDPR & Compliance -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-shield-check</v-icon>
                                    GDPR & Compliance
                                </v-card-title>
                                <v-card-text>
                                    <v-textarea
                                        v-model="settings.gdpr_consent_text"
                                        label="GDPR Consent Text"
                                        prepend-inner-icon="mdi-text"
                                        variant="outlined"
                                        rows="3"
                                        class="mb-4"
                                        hint="Text shown for GDPR consent"
                                        persistent-hint
                                    ></v-textarea>

                                    <v-switch
                                        v-model="settings.right_to_be_forgotten_enabled"
                                        label="Enable Right to be Forgotten"
                                        color="primary"
                                        class="mb-4"
                                        :hint="settings.right_to_be_forgotten_enabled ? 'Users can request data deletion' : 'Right to be forgotten disabled'"
                                        persistent-hint
                                    ></v-switch>

                                    <v-switch
                                        v-model="settings.age_confirmation_required"
                                        label="Require Age Confirmation"
                                        color="primary"
                                        class="mb-4"
                                        :hint="settings.age_confirmation_required ? 'Users must confirm their age' : 'No age confirmation required'"
                                        persistent-hint
                                    ></v-switch>

                                    <v-text-field
                                        v-if="settings.age_confirmation_required"
                                        v-model.number="settings.age_minimum"
                                        label="Minimum Age"
                                        prepend-inner-icon="mdi-calendar"
                                        variant="outlined"
                                        type="number"
                                    ></v-text-field>
                                </v-card-text>
                            </v-card>

                            <!-- Save Button -->
                            <v-btn
                                :loading="isSaving"
                                block
                                size="large"
                                color="primary"
                                @click="saveSettings"
                                prepend-icon="mdi-content-save"
                            >
                                Save Settings
                            </v-btn>
                        </v-window-item>

                        <!-- Advanced Tab -->
                        <v-window-item value="advanced">
                            <!-- Environment -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-cog</v-icon>
                                    Environment
                                </v-card-title>
                                <v-card-text>
                                    <v-select
                                        v-model="settings.environment"
                                        label="Application Environment"
                                        :items="[{label: 'Production', value: 'production'}, {label: 'Staging', value: 'staging'}, {label: 'Development', value: 'development'}]"
                                        item-title="label"
                                        item-value="value"
                                        prepend-inner-icon="mdi-server"
                                        variant="outlined"
                                    ></v-select>
                                </v-card-text>
                            </v-card>

                            <!-- API Settings -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-api</v-icon>
                                    API Settings
                                </v-card-title>
                                <v-card-text>
                                    <v-text-field
                                        v-model.number="settings.api_rate_limit_per_minute"
                                        label="API Rate Limit (per minute)"
                                        prepend-inner-icon="mdi-speedometer"
                                        variant="outlined"
                                        type="number"
                                        class="mb-4"
                                        hint="Maximum API requests per minute per user"
                                        persistent-hint
                                    ></v-text-field>

                                    <v-switch
                                        v-model="settings.api_keys_enabled"
                                        label="Enable API Keys"
                                        color="primary"
                                        :hint="settings.api_keys_enabled ? 'API key authentication enabled' : 'API keys disabled'"
                                        persistent-hint
                                    ></v-switch>
                                </v-card-text>
                            </v-card>

                            <!-- Background Jobs -->
                            <v-card class="mb-6" elevation="0" variant="outlined">
                                <v-card-title class="text-h6">
                                    <v-icon class="mr-2">mdi-worker</v-icon>
                                    Background Jobs
                                </v-card-title>
                                <v-card-text>
                                    <v-switch
                                        v-model="settings.background_jobs_enabled"
                                        label="Enable Background Jobs"
                                        color="primary"
                                        :hint="settings.background_jobs_enabled ? 'Background job processing enabled' : 'Background jobs disabled'"
                                        persistent-hint
                                    ></v-switch>
                                </v-card-text>
                            </v-card>

                            <!-- Save Button -->
                            <v-btn
                                :loading="isSaving"
                                block
                                size="large"
                                color="primary"
                                @click="saveSettings"
                                prepend-icon="mdi-content-save"
                            >
                                Save Settings
                            </v-btn>
                        </v-window-item>
                    </v-window>
                </v-card-text>
            </v-card>
        </v-container>
    </div>
</template>

<script>
export default {
    name: 'AdminSettings',
    data() {
        return {
            currentTab: 'general',
            isSaving: false,
            uploadingLogo: false,
            deletingLogo: false,
            message: '',
            alertType: 'success',
            logoFile: null,
            logoPreview: null,
            settings: {
                // General / Site Settings
                app_name: '',
                app_logo: '',
                app_copyright: '',
                site_tagline: '',
                site_url: '',
                maintenance_mode: false,
                maintenance_message: '',
                admin_email: '',
                support_email: '',

                // Contact & Social (existing)
                contact_address: '',
                contact_phone: '',
                contact_email: '',
                social_twitter: '',
                social_facebook: '',
                social_instagram: '',

                // Localization
                default_language: 'en',
                default_timezone: 'UTC',
                date_format: 'Y-m-d',
                time_format: 'H:i:s',
                language_change_enabled: true,
                locale_auto_detect: false,
                translation_fallback_language: 'en',
                currency: 'USD',
                currency_symbol: '$',
                currency_symbol_position: 'before',
                number_format_locale: 'en_US',
                rtl_support: false,

                // Branding & Appearance
                logo_light: null,
                logo_dark: null,
                favicon: null,
                app_icon: null,
                primary_color: '#1976D2',
                secondary_color: '#424242',
                font_family: 'Roboto, sans-serif',
                custom_css: '',
                theme_mode: 'system',
                login_branding_enabled: true,

                // SEO & Metadata
                meta_title: '',
                meta_description: '',
                meta_keywords: '',
                og_title: '',
                og_description: '',
                og_image: null,
                twitter_card_type: 'summary_large_image',
                twitter_site: '',
                canonical_url: '',
                indexing_enabled: true,
                sitemap_enabled: true,
                robots_txt_custom: '',

                // Analytics & Tracking
                google_analytics_id: '',
                google_tag_manager_id: '',
                analytics_script_header: '',
                analytics_script_body: '',
                analytics_script_footer: '',
                cookie_consent_enabled: true,
                anonymize_ip: true,
                tracking_production_only: true,

                // Scripts & Integrations
                custom_head_scripts: '',
                custom_body_scripts: '',
                custom_footer_scripts: '',
                third_party_embeds_enabled: true,
                chat_widget_script: '',
                newsletter_provider: 'none',
                newsletter_api_key: '',

                // Content Settings
                homepage_type: 'default',
                blog_enabled: false,
                posts_per_page: 10,
                comments_enabled: false,
                comments_moderation_required: true,
                media_max_upload_size: 10240,
                allowed_file_types: 'jpg,jpeg,png,gif,pdf,doc,docx',

                // User & Access Settings
                user_registration_enabled: true,
                default_user_role: 'user',
                email_verification_required: true,
                password_min_length: 8,
                password_require_special_char: true,
                password_require_number: true,
                password_require_uppercase: true,
                session_timeout_minutes: 120,
                login_rate_limit_attempts: 5,
                login_rate_limit_minutes: 15,
                two_factor_enabled: false,

                // Email & Notifications
                email_sender_name: '',
                email_sender_address: '',
                smtp_host: '',
                smtp_port: 587,
                smtp_username: '',
                smtp_password: '',
                smtp_encryption: 'tls',
                admin_notifications_enabled: true,

                // Performance & Technical
                cache_enabled: true,
                cache_lifetime_minutes: 60,
                cdn_enabled: false,
                cdn_url: '',
                image_optimization_enabled: true,
                lazy_loading_enabled: true,
                debug_mode: false,

                // Legal & Compliance
                privacy_policy_url: '',
                terms_conditions_url: '',
                cookie_policy_url: '',
                gdpr_consent_text: '',
                right_to_be_forgotten_enabled: true,
                age_confirmation_required: false,
                age_minimum: 13,

                // Advanced / Developer Settings
                environment: 'production',
                api_rate_limit_per_minute: 60,
                api_keys_enabled: false,
                background_jobs_enabled: true,
            },
            brandingImages: {
                logo_light_file: null,
                logo_light_preview: null,
                logo_light_uploading: false,
                logo_dark_file: null,
                logo_dark_preview: null,
                logo_dark_uploading: false,
                favicon_file: null,
                favicon_preview: null,
                favicon_uploading: false,
                app_icon_file: null,
                app_icon_preview: null,
                app_icon_uploading: false,
                og_image_file: null,
                og_image_preview: null,
                og_image_uploading: false,
            },
            errors: {},
            languages: [
                { code: 'en', name: 'English' },
                { code: 'de', name: 'Deutsch (German)' },
                { code: 'es', name: 'Español (Spanish)' },
                { code: 'fr', name: 'Français (French)' },
                { code: 'it', name: 'Italiano (Italian)' },
                { code: 'pt', name: 'Português (Portuguese)' },
                { code: 'ja', name: '日本語 (Japanese)' },
                { code: 'zh', name: '中文 (Chinese)' },
            ],
            timezones: [
                'UTC',
                'America/New_York',
                'America/Chicago',
                'America/Denver',
                'America/Los_Angeles',
                'Europe/London',
                'Europe/Paris',
                'Europe/Berlin',
                'Europe/Rome',
                'Asia/Tokyo',
                'Asia/Shanghai',
                'Asia/Singapore',
                'Australia/Sydney',
                'Pacific/Auckland',
            ],
            dateFormats: [
                { label: 'YYYY-MM-DD (2025-12-11)', value: 'Y-m-d' },
                { label: 'DD/MM/YYYY (11/12/2025)', value: 'd/m/Y' },
                { label: 'MM/DD/YYYY (12/11/2025)', value: 'm/d/Y' },
                { label: 'DD.MM.YYYY (11.12.2025)', value: 'd.m.Y' },
            ],
            timeFormats: [
                { label: '24-hour (14:30:00)', value: 'H:i:s' },
                { label: '12-hour (02:30:00 PM)', value: 'h:i:s A' },
                { label: '24-hour no seconds (14:30)', value: 'H:i' },
                { label: '12-hour no seconds (02:30 PM)', value: 'h:i A' },
            ],
            currencies: [
                { label: 'US Dollar ($)', value: 'USD' },
                { label: 'Euro (€)', value: 'EUR' },
                { label: 'British Pound (£)', value: 'GBP' },
                { label: 'Japanese Yen (¥)', value: 'JPY' },
                { label: 'Canadian Dollar (C$)', value: 'CAD' },
                { label: 'Australian Dollar (A$)', value: 'AUD' },
                { label: 'Swiss Franc (Fr)', value: 'CHF' },
                { label: 'Chinese Yuan (¥)', value: 'CNY' },
                { label: 'Indian Rupee (₹)', value: 'INR' },
                { label: 'Brazilian Real (R$)', value: 'BRL' },
            ],
            currencyPositions: [
                { label: 'Before ($100)', value: 'before' },
                { label: 'After (100$)', value: 'after' },
            ],
            numberLocales: [
                { label: 'English - US (1,234.56)', value: 'en_US' },
                { label: 'English - UK (1,234.56)', value: 'en_GB' },
                { label: 'German (1.234,56)', value: 'de_DE' },
                { label: 'French (1 234,56)', value: 'fr_FR' },
                { label: 'Spanish (1.234,56)', value: 'es_ES' },
                { label: 'Italian (1.234,56)', value: 'it_IT' },
                { label: 'Dutch (1.234,56)', value: 'nl_NL' },
                { label: 'Portuguese - Brazil (1.234,56)', value: 'pt_BR' },
                { label: 'Japanese (1,234.56)', value: 'ja_JP' },
                { label: 'Chinese (1,234.56)', value: 'zh_CN' },
            ],
            themeModes: [
                { label: 'Light Mode', value: 'light' },
                { label: 'Dark Mode', value: 'dark' },
                { label: 'System (Auto)', value: 'system' },
            ],
            fontFamilies: [
                { label: 'Roboto (Default)', value: 'Roboto, sans-serif' },
                { label: 'Open Sans', value: '"Open Sans", sans-serif' },
                { label: 'Lato', value: 'Lato, sans-serif' },
                { label: 'Montserrat', value: 'Montserrat, sans-serif' },
                { label: 'Raleway', value: 'Raleway, sans-serif' },
                { label: 'Poppins', value: 'Poppins, sans-serif' },
                { label: 'Inter', value: 'Inter, sans-serif' },
                { label: 'Source Sans Pro', value: '"Source Sans Pro", sans-serif' },
                { label: 'Arial', value: 'Arial, sans-serif' },
                { label: 'Helvetica', value: 'Helvetica, sans-serif' },
            ],
            twitterCardTypes: [
                { label: 'Summary', value: 'summary' },
                { label: 'Summary with Large Image', value: 'summary_large_image' },
                { label: 'App', value: 'app' },
                { label: 'Player', value: 'player' },
            ],
            newsletterProviders: [
                { label: 'None', value: 'none' },
                { label: 'Mailchimp', value: 'mailchimp' },
                { label: 'SendGrid', value: 'sendgrid' },
                { label: 'ConvertKit', value: 'convertkit' },
                { label: 'MailerLite', value: 'mailerlite' },
            ],
        };
    },
    mounted() {
        this.fetchSettings();
    },
    methods: {
        async fetchSettings() {
            try {
                const response = await axios.get('/api/admin/settings');
                const fetchedSettings = response.data.settings;

                // Settings are now returned as key-value pairs
                this.settings = {
                    ...this.settings,
                    ...fetchedSettings,
                };

                // Set logo preview if exists
                if (this.settings.app_logo) {
                    this.logoPreview = `/storage/${this.settings.app_logo}`;
                }

                // Set branding image previews
                const imageKeys = ['logo_light', 'logo_dark', 'favicon', 'app_icon', 'og_image'];
                imageKeys.forEach(key => {
                    if (this.settings[key]) {
                        this.brandingImages[`${key}_preview`] = `/storage/${this.settings[key]}`;
                    }
                });
            } catch (error) {
                console.error('Failed to fetch settings:', error);
                this.showMessage('Failed to load settings', 'error');
            }
        },
        async saveSettings() {
            this.isSaving = true;
            this.resetErrors();

            try {
                // Prepare settings array
                const settingsArray = Object.entries(this.settings)
                    .filter(([key]) => key !== 'app_logo') // Skip logo, it's handled separately
                    .map(([key, value]) => {
                        // Determine the type based on the value
                        let type = 'string';
                        if (typeof value === 'boolean') {
                            type = 'boolean';
                        } else if (typeof value === 'number') {
                            type = Number.isInteger(value) ? 'integer' : 'float';
                        }

                        return {
                            key,
                            value,
                            type,
                        };
                    });

                const response = await axios.post('/api/admin/settings', {
                    settings: settingsArray,
                });

                this.showMessage('Settings saved successfully', 'success');
            } catch (error) {
                console.error('Failed to save settings:', error);
                this.handleErrors(error);
                this.showMessage('Failed to save settings', 'error');
            } finally {
                this.isSaving = false;
            }
        },
        handleLogoSelect(event) {
            const file = Array.isArray(this.logoFile) ? this.logoFile[0] : this.logoFile;
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.logoPreview = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        },
        async uploadLogo() {
            const file = Array.isArray(this.logoFile) ? this.logoFile[0] : this.logoFile;

            if (!file) {
                this.showMessage('Please select a logo file', 'error');
                return;
            }

            this.uploadingLogo = true;
            this.resetErrors();

            try {
                const formData = new FormData();
                formData.append('logo', file);

                const response = await axios.post('/api/admin/settings/logo', formData);

                this.settings.app_logo = response.data.logo_path;
                this.logoPreview = response.data.logo_url;
                this.logoFile = null;
                this.showMessage('Logo uploaded successfully', 'success');
            } catch (error) {
                console.error('Failed to upload logo:', error);
                this.handleErrors(error);
                this.showMessage('Failed to upload logo', 'error');
            } finally {
                this.uploadingLogo = false;
            }
        },
        async deleteLogo() {
            if (!confirm('Are you sure you want to delete the logo?')) {
                return;
            }

            this.deletingLogo = true;

            try {
                await axios.delete('/api/admin/settings/logo');
                this.settings.app_logo = '';
                this.logoPreview = null;
                this.showMessage('Logo deleted successfully', 'success');
            } catch (error) {
                console.error('Failed to delete logo:', error);
                this.showMessage('Failed to delete logo', 'error');
            } finally {
                this.deletingLogo = false;
            }
        },
        handleImageSelect(key) {
            const file = Array.isArray(this.brandingImages[`${key}_file`])
                ? this.brandingImages[`${key}_file`][0]
                : this.brandingImages[`${key}_file`];

            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.brandingImages[`${key}_preview`] = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        },
        async uploadImage(key) {
            const file = Array.isArray(this.brandingImages[`${key}_file`])
                ? this.brandingImages[`${key}_file`][0]
                : this.brandingImages[`${key}_file`];

            if (!file) {
                this.showMessage(`Please select a ${key} file`, 'error');
                return;
            }

            this.brandingImages[`${key}_uploading`] = true;
            this.resetErrors();

            try {
                const formData = new FormData();
                formData.append('image', file);
                formData.append('key', key);

                const response = await axios.post('/api/admin/settings/image', formData);

                this.settings[key] = response.data.path;
                this.brandingImages[`${key}_preview`] = response.data.url;
                this.brandingImages[`${key}_file`] = null;
                this.showMessage(`${key.replace('_', ' ')} uploaded successfully`, 'success');
            } catch (error) {
                console.error(`Failed to upload ${key}:`, error);
                this.handleErrors(error);
                this.showMessage(`Failed to upload ${key}`, 'error');
            } finally {
                this.brandingImages[`${key}_uploading`] = false;
            }
        },
        async deleteImage(key) {
            if (!confirm(`Are you sure you want to delete the ${key.replace('_', ' ')}?`)) {
                return;
            }

            try {
                await axios.delete('/api/admin/settings/image', {
                    data: { key }
                });
                this.settings[key] = null;
                this.brandingImages[`${key}_preview`] = null;
                this.showMessage(`${key.replace('_', ' ')} deleted successfully`, 'success');
            } catch (error) {
                console.error(`Failed to delete ${key}:`, error);
                this.showMessage(`Failed to delete ${key}`, 'error');
            }
        },
        showMessage(message, type = 'success') {
            this.message = message;
            this.alertType = type;
            setTimeout(() => {
                this.message = '';
            }, 5000);
        },
        resetErrors() {
            this.errors = {};
        },
        handleErrors(error) {
            if (error.response && error.response.data && error.response.data.errors) {
                this.errors = error.response.data.errors;
            }
        },
    },
};
</script>

<style scoped>
.bg-gradient {
    background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-secondary)) 100%);
    color: white;
}

.no-logo-placeholder {
    width: 200px;
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px dashed #ccc;
    border-radius: 8px;
}

.image-placeholder {
    width: 150px;
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px dashed #ccc;
    border-radius: 8px;
}

.image-placeholder-small {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px dashed #ccc;
    border-radius: 8px;
}

.gap-2 {
    gap: 8px;
}
</style>
