<template>
    <settings-page-layout
        :title="$t('settings.maintenance.title')"
        :description="$t('settings.maintenance.description')"
        icon="mdi-wrench-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'general' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-wrench" :title="$t('settings.maintenance.cardTitle')">
            <v-alert
                v-if="settings.maintenance_mode"
                type="warning"
                variant="tonal"
                class="mb-4"
            >
                <strong>{{ $t('settings.maintenance.activeWarning') }}</strong>
                <div class="mt-1">{{ $t('settings.maintenance.activeDescription') }}</div>
            </v-alert>

            <v-switch
                v-model="settings.maintenance_mode"
                :label="$t('settings.maintenance.enableLabel')"
                color="warning"
                class="mb-4"
                :error-messages="errors.maintenance_mode"
                :hint="$t('settings.maintenance.enableHint')"
                persistent-hint
            ></v-switch>

            <v-alert
                type="info"
                variant="tonal"
                density="compact"
                class="mb-4"
            >
                <div class="text-caption">
                    <strong>{{ $t('settings.maintenance.note') }}</strong> {{ $t('settings.maintenance.noteText') }}
                </div>
            </v-alert>

            <v-textarea
                v-model="settings.maintenance_message"
                :label="$t('settings.maintenance.messageLabel')"
                prepend-inner-icon="mdi-message-text"
                variant="outlined"
                rows="3"
                :error-messages="errors.maintenance_message"
                :hint="$t('settings.maintenance.messageHint')"
                persistent-hint
                :placeholder="$t('settings.maintenance.messagePlaceholder')"
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
                    <strong>{{ $t('settings.maintenance.tip') }}</strong> {{ $t('settings.maintenance.reloadTip') }}
                    <v-btn
                        size="x-small"
                        color="success"
                        variant="text"
                        class="ml-2"
                        @click="reloadPage"
                    >
                        {{ $t('settings.maintenance.reloadNow') }}
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
            {{ $t('settings.saveSettings') }}
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
