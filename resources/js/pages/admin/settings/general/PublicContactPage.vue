<template>
    <settings-page-layout
        :title="$t('settings.publicContact.title')"
        :description="$t('settings.publicContact.description')"
        icon="mdi-phone-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'general' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-card-account-details" :title="$t('settings.publicContact.cardTitle')">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    {{ $t('settings.publicContact.infoText') }}
                </div>
            </v-alert>

            <v-text-field
                v-model="settings.contact_email"
                :label="$t('settings.publicContact.contactEmail')"
                prepend-inner-icon="mdi-email"
                variant="outlined"
                type="email"
                class="mb-4"
                :error-messages="errors.contact_email"
                :hint="$t('settings.publicContact.contactEmailHint')"
                persistent-hint
                placeholder="contact@example.com"
            ></v-text-field>

            <v-text-field
                v-model="settings.contact_phone"
                :label="$t('settings.publicContact.contactPhone')"
                prepend-inner-icon="mdi-phone"
                variant="outlined"
                class="mb-4"
                :error-messages="errors.contact_phone"
                :hint="$t('settings.publicContact.contactPhoneHint')"
                persistent-hint
                placeholder="+1 (555) 123-4567"
            ></v-text-field>

            <v-textarea
                v-model="settings.contact_address"
                :label="$t('settings.publicContact.contactAddress')"
                prepend-inner-icon="mdi-map-marker"
                variant="outlined"
                rows="2"
                :error-messages="errors.contact_address"
                :hint="$t('settings.publicContact.contactAddressHint')"
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
            {{ $t('settings.saveSettings') }}
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
