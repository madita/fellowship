<template>
    <settings-page-layout
        title="Maintenance Mode"
        description="Configure maintenance mode settings for your application"
        icon="mdi-wrench-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'general' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
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

        <v-btn
            :loading="isSaving"
            block
            size="large"
            color="primary"
            @click="$emit('save')"
            prepend-icon="mdi-content-save"
            class="d-sm-none"
        >
            Save Settings
        </v-btn>
    </settings-page-layout>
</template>

<script setup>
import { ref, watch } from 'vue';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

defineEmits(['save', 'message']);

const message = ref('');
const alertType = ref('success');
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
