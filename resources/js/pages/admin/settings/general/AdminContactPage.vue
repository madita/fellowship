<template>
    <settings-page-layout
        title="Admin Contact"
        description="Configure administrator and support email addresses"
        icon="mdi-email-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'general' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
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
