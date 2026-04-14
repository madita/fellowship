<template>
    <settings-page-layout
        :title="$t('settings.adminContact.title')"
        :description="$t('settings.adminContact.description')"
        icon="mdi-email-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'general' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-email-outline" :title="$t('settings.adminContact.cardTitle')">
            <v-text-field
                v-model="settings.admin_email"
                :label="$t('settings.adminContact.adminEmail')"
                prepend-inner-icon="mdi-shield-account"
                variant="outlined"
                type="email"
                class="mb-4"
                :error-messages="errors.admin_email"
                :hint="$t('settings.adminContact.adminEmailHint')"
                persistent-hint
            ></v-text-field>

            <v-text-field
                v-model="settings.support_email"
                :label="$t('settings.adminContact.supportEmail')"
                prepend-inner-icon="mdi-lifebuoy"
                variant="outlined"
                type="email"
                :error-messages="errors.support_email"
                :hint="$t('settings.adminContact.supportEmailHint')"
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
