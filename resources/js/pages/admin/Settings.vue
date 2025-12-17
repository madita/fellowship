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

                <v-tabs v-model="currentTab" bg-color="transparent" color="primary">
                    <v-tab value="general">
                        <v-icon class="mr-2">mdi-cog-outline</v-icon>
                        General
                    </v-tab>
                    <v-tab value="contact">
                        <v-icon class="mr-2">mdi-card-account-details</v-icon>
                        Contact
                    </v-tab>
                    <v-tab value="social">
                        <v-icon class="mr-2">mdi-share-variant</v-icon>
                        Social
                    </v-tab>
                    <v-tab value="localization">
                        <v-icon class="mr-2">mdi-earth</v-icon>
                        Localization
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
                app_name: '',
                app_logo: '',
                app_copyright: '',
                contact_address: '',
                contact_phone: '',
                contact_email: '',
                social_twitter: '',
                social_facebook: '',
                social_instagram: '',
                default_language: 'en',
                default_timezone: 'UTC',
                date_format: 'Y-m-d',
                time_format: 'H:i:s',
                language_change_enabled: true,
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

.gap-2 {
    gap: 8px;
}
</style>
