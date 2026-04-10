<template>
    <div>
        <!-- Application Info -->
        <settings-card icon="mdi-application" :title="$t('settings.general.appInfo')">
            <v-text-field
                v-model="settings.app_name"
                :label="$t('settings.general.appName')"
                prepend-inner-icon="mdi-application"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.app_name"
            ></v-text-field>

            <v-text-field
                v-model="settings.app_copyright"
                :label="$t('settings.general.copyrightText')"
                prepend-inner-icon="mdi-copyright"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.app_copyright"
                :hint="$t('settings.general.copyrightHint')"
                persistent-hint
            ></v-text-field>

            <v-text-field
                v-model="settings.site_tagline"
                :label="$t('settings.general.siteTagline')"
                prepend-inner-icon="mdi-format-quote-close"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.site_tagline"
                :hint="$t('settings.general.taglineHint')"
                persistent-hint
            ></v-text-field>

            <v-text-field
                v-model="settings.site_url"
                :label="$t('settings.general.siteUrl')"
                prepend-inner-icon="mdi-link-variant"
                variant="outlined"
                :error-messages="errors.site_url"
                :hint="$t('settings.general.siteUrlHint')"
                persistent-hint
            ></v-text-field>
        </settings-card>

        <!-- Maintenance Mode -->
        <settings-card icon="mdi-wrench" :title="$t('settings.general.maintenanceMode')">
            <v-alert
                v-if="settings.maintenance_mode"
                type="warning"
                variant="tonal"
                class="mb-4"
            >
                <strong>{{ $t('settings.general.maintenanceActive') }}</strong>
                <div class="mt-1">{{ $t('settings.general.maintenanceActiveDesc') }}</div>
            </v-alert>

            <v-switch
                v-model="settings.maintenance_mode"
                :label="$t('settings.general.enableMaintenance')"
                color="warning"
                class="mb-4"
                :error-messages="errors.maintenance_mode"
                :hint="$t('settings.general.maintenanceHint')"
                persistent-hint
            ></v-switch>

            <v-alert
                type="info"
                variant="tonal"
                density="compact"
                class="mb-4"
            >
                <div class="text-caption">
                    <strong>{{ $t('settings.general.note') }}:</strong> {{ $t('settings.general.maintenanceNote') }}
                </div>
            </v-alert>

            <v-textarea
                v-model="settings.maintenance_message"
                :label="$t('settings.general.maintenanceMessage')"
                prepend-inner-icon="mdi-message-text"
                variant="outlined"
                rows="3"
                :error-messages="errors.maintenance_message"
                :hint="$t('settings.general.maintenanceMessageHint')"
                persistent-hint
                :placeholder="$t('settings.general.maintenancePlaceholder')"
            ></v-textarea>

            <v-alert
                v-if="maintenanceModeChanged"
                type="success"
                variant="tonal"
                density="compact"
                class="mt-4"
            >
                <div class="text-caption">
                    <v-icon size="small" class="mr-1">mdi-information</v-icon>
                    <strong>{{ $t('settings.general.tip') }}:</strong> {{ $t('settings.general.reloadTip') }}
                    <v-btn
                        size="x-small"
                        color="success"
                        variant="text"
                        class="ml-2"
                        @click="reloadPage"
                    >
                        {{ $t('settings.general.reloadNow') }}
                    </v-btn>
                </div>
            </v-alert>
        </settings-card>

        <!-- Admin Contact -->
        <settings-card icon="mdi-email-outline" :title="$t('settings.general.adminContact')">
            <v-text-field
                v-model="settings.admin_email"
                :label="$t('settings.general.adminEmail')"
                prepend-inner-icon="mdi-shield-account"
                variant="outlined"
                type="email"
                class="mb-4"
                :error-messages="errors.admin_email"
                :hint="$t('settings.general.adminEmailHint')"
                persistent-hint
            ></v-text-field>

            <v-text-field
                v-model="settings.support_email"
                :label="$t('settings.general.supportEmail')"
                prepend-inner-icon="mdi-lifebuoy"
                variant="outlined"
                type="email"
                :error-messages="errors.support_email"
                :hint="$t('settings.general.supportEmailHint')"
                persistent-hint
            ></v-text-field>
        </settings-card>

        <!-- Public Contact Information -->
        <settings-card icon="mdi-card-account-details" :title="$t('settings.general.publicContact')">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    {{ $t('settings.general.publicContactInfo') }}
                </div>
            </v-alert>

            <v-text-field
                v-model="settings.contact_email"
                :label="$t('settings.general.contactEmail')"
                prepend-inner-icon="mdi-email"
                variant="outlined"
                type="email"
                class="mb-4"
                :error-messages="errors.contact_email"
                :hint="$t('settings.general.contactEmailHint')"
                persistent-hint
                placeholder="contact@example.com"
            ></v-text-field>

            <v-text-field
                v-model="settings.contact_phone"
                :label="$t('settings.general.contactPhone')"
                prepend-inner-icon="mdi-phone"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.contact_phone"
                :hint="$t('settings.general.contactPhoneHint')"
                persistent-hint
                placeholder="+1 (555) 123-4567"
            ></v-text-field>

            <v-textarea
                v-model="settings.contact_address"
                :label="$t('settings.general.contactAddress')"
                prepend-inner-icon="mdi-map-marker"
                variant="outlined"
                rows="2"
                :error-messages="errors.contact_address"
                :hint="$t('settings.general.contactAddressHint')"
                persistent-hint
                placeholder="123 Main Street, City, State 12345"
            ></v-textarea>
        </settings-card>

        <!-- Social Media Links -->
        <settings-card icon="mdi-share-variant" :title="$t('settings.general.socialMedia')">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    {{ $t('settings.general.socialMediaInfo') }}
                </div>
            </v-alert>

            <v-text-field
                v-model="settings.social_twitter"
                :label="$t('settings.general.twitterUrl')"
                prepend-inner-icon="mdi-twitter"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.social_twitter"
                :hint="$t('settings.general.twitterHint')"
                persistent-hint
                placeholder="https://twitter.com/yourusername"
            ></v-text-field>

            <v-text-field
                v-model="settings.social_facebook"
                :label="$t('settings.general.facebookUrl')"
                prepend-inner-icon="mdi-facebook"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.social_facebook"
                :hint="$t('settings.general.facebookHint')"
                persistent-hint
                placeholder="https://facebook.com/yourpage"
            ></v-text-field>

            <v-text-field
                v-model="settings.social_instagram"
                :label="$t('settings.general.instagramUrl')"
                prepend-inner-icon="mdi-instagram"
                variant="outlined"
                :error-messages="errors.social_instagram"
                :hint="$t('settings.general.instagramHint')"
                persistent-hint
                placeholder="https://instagram.com/yourusername"
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
            {{ $t('settings.general.saveSettings') }}
        </v-btn>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import SettingsCard from '../SettingsCard.vue';

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
});

defineEmits(['save', 'message']);

const maintenanceModeChanged = ref(false);
const initialMaintenanceMode = ref(null);

// Track initial maintenance mode value
watch(() => props.settings.maintenance_mode, (newVal) => {
    if (initialMaintenanceMode.value === null) {
        initialMaintenanceMode.value = newVal;
    } else if (newVal !== initialMaintenanceMode.value) {
        maintenanceModeChanged.value = true;
    } else {
        maintenanceModeChanged.value = false;
    }
}, { immediate: true });

function reloadPage() {
    window.location.reload();
}
</script>
