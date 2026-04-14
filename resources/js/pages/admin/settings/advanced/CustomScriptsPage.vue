<template>
    <settings-page-layout
        :title="$t('settings.advanced.customScripts.title')"
        :description="$t('settings.advanced.customScripts.description')"
        icon="mdi-script-text-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'advanced' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-code-tags" :title="$t('settings.advanced.customScripts.cardTitle')">
            <v-alert type="warning" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    <v-icon size="small" class="mr-1">mdi-alert</v-icon>
                    <strong>{{ $t('settings.advanced.customScripts.securityWarning') }}</strong> {{ $t('settings.advanced.customScripts.securityWarningText') }}
                </div>
            </v-alert>

            <v-textarea
                v-model="settings.custom_head_scripts"
                :label="$t('settings.advanced.customScripts.headScripts')"
                prepend-inner-icon="mdi-xml"
                variant="outlined"
                class="mb-4 monospace-input"
                rows="6"
                :error-messages="errors.custom_head_scripts"
                :hint="$t('settings.advanced.customScripts.headScriptsHint')"
                persistent-hint
            ></v-textarea>

            <v-textarea
                v-model="settings.custom_body_scripts"
                :label="$t('settings.advanced.customScripts.bodyScripts')"
                prepend-inner-icon="mdi-xml"
                variant="outlined"
                class="mb-4 monospace-input"
                rows="6"
                :error-messages="errors.custom_body_scripts"
                :hint="$t('settings.advanced.customScripts.bodyScriptsHint')"
                persistent-hint
            ></v-textarea>

            <v-alert type="info" variant="tonal" density="compact">
                <div class="text-caption">
                    <strong>{{ $t('settings.advanced.customScripts.commonUses') }}</strong>
                    <ul class="mt-1 ml-4">
                        <li><strong>{{ $t('settings.advanced.customScripts.head') }}</strong> {{ $t('settings.advanced.customScripts.headExamples') }}</li>
                        <li><strong>{{ $t('settings.advanced.customScripts.body') }}</strong> {{ $t('settings.advanced.customScripts.bodyExamples') }}</li>
                    </ul>
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

<style scoped>
.monospace-input :deep(textarea) {
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.5;
}
</style>
