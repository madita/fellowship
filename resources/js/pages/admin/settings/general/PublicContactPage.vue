<template>
    <settings-page-layout
        title="Public Contact"
        description="Configure public contact information displayed on your site"
        icon="mdi-phone-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'general' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-card-account-details" title="Public Contact Information">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    This information is displayed publicly in your site footer and contact pages.
                </div>
            </v-alert>

            <v-text-field
                v-model="settings.contact_email"
                label="Contact Email"
                prepend-inner-icon="mdi-email"
                variant="outlined"
                type="email"
                class="mb-4"
                :error-messages="errors.contact_email"
                hint="Public contact email shown in footer"
                persistent-hint
                placeholder="contact@example.com"
            ></v-text-field>

            <v-text-field
                v-model="settings.contact_phone"
                label="Contact Phone"
                prepend-inner-icon="mdi-phone"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.contact_phone"
                hint="Public phone number shown in footer"
                persistent-hint
                placeholder="+1 (555) 123-4567"
            ></v-text-field>

            <v-textarea
                v-model="settings.contact_address"
                label="Contact Address"
                prepend-inner-icon="mdi-map-marker"
                variant="outlined"
                rows="2"
                :error-messages="errors.contact_address"
                hint="Physical address shown in footer"
                persistent-hint
                placeholder="123 Main Street, City, State 12345"
            ></v-textarea>
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
import { ref } from 'vue';
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
</script>
