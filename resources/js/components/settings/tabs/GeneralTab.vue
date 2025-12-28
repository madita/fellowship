<template>
    <div>
        <!-- Application Info -->
        <settings-card icon="mdi-application" title="Application Info">
            <v-text-field
                v-model="settings.app_name"
                label="Application Name"
                prepend-inner-icon="mdi-application"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.app_name"
            ></v-text-field>

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

            <v-text-field
                v-model="settings.site_url"
                label="Site URL"
                prepend-inner-icon="mdi-link-variant"
                variant="outlined"
                :error-messages="errors.site_url"
                hint="The base URL of your site (e.g., https://fellowship.com)"
                persistent-hint
            ></v-text-field>
        </settings-card>

        <!-- Maintenance Mode -->
        <settings-card icon="mdi-wrench" title="Maintenance Mode">
            <v-alert
                v-if="settings.maintenance_mode"
                type="warning"
                variant="tonal"
                class="mb-4"
            >
                <strong>Maintenance Mode is Active!</strong>
                <div class="mt-1">Non-admin users will see the maintenance page and cannot access the site.</div>
            </v-alert>

            <v-switch
                v-model="settings.maintenance_mode"
                label="Enable Maintenance Mode"
                color="warning"
                class="mb-4"
                :error-messages="errors.maintenance_mode"
                hint="When enabled, site will display maintenance message to visitors. Admins can still log in and access all pages."
                persistent-hint
            ></v-switch>

            <v-alert
                type="info"
                variant="tonal"
                density="compact"
                class="mb-4"
            >
                <div class="text-caption">
                    <strong>Note:</strong> Login pages remain accessible so admins can sign in. After logging in as an admin, you'll have full access to the site.
                </div>
            </v-alert>

            <v-textarea
                v-model="settings.maintenance_message"
                label="Maintenance Message"
                prepend-inner-icon="mdi-message-text"
                variant="outlined"
                rows="3"
                :error-messages="errors.maintenance_message"
                hint="Message shown to visitors when maintenance mode is active"
                persistent-hint
                placeholder="We are currently performing scheduled maintenance. Please check back soon."
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
                    <strong>Tip:</strong> Reload this page to see the maintenance mode changes take effect.
                    <v-btn
                        size="x-small"
                        color="success"
                        variant="text"
                        class="ml-2"
                        @click="reloadPage"
                    >
                        Reload Now
                    </v-btn>
                </div>
            </v-alert>
        </settings-card>

        <!-- Admin Contact -->
        <settings-card icon="mdi-email-outline" title="Admin Contact">
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
        </settings-card>

        <v-btn
            :loading="isSaving"
            block
            size="large"
            color="primary"
            @click="$emit('save')"
            prepend-icon="mdi-content-save"
        >
            Save Settings
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
