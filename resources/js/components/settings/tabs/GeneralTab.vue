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
import SettingsCard from '../SettingsCard.vue';

defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
});

defineEmits(['save', 'message']);
</script>
