<template>
    <settings-page-layout
        :title="$t('settings.advanced.pwa.title')"
        :description="$t('settings.advanced.pwa.description')"
        icon="mdi-cellphone-link"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'advanced' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-cellphone-check" :title="$t('settings.advanced.pwa.cardTitle')">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    <strong>{{ $t('settings.advanced.pwa.whatIsPwa') }}</strong> {{ $t('settings.advanced.pwa.pwaDescription') }}
                    <ul class="mt-1 ml-4">
                        <li>{{ $t('settings.advanced.pwa.feature1') }}</li>
                        <li>{{ $t('settings.advanced.pwa.feature2') }}</li>
                        <li>{{ $t('settings.advanced.pwa.feature3') }}</li>
                        <li>{{ $t('settings.advanced.pwa.feature4') }}</li>
                    </ul>
                </div>
            </v-alert>

            <div class="mb-4">
                <div class="text-subtitle-2 mb-2">
                    <v-icon size="small" class="mr-1">mdi-information</v-icon>
                    {{ $t('settings.advanced.pwa.pwaStatus') }}
                </div>
                <v-alert type="success" variant="tonal" density="compact">
                    <div class="d-flex align-center">
                        <v-icon class="mr-2">mdi-check-circle</v-icon>
                        <div>
                            <div class="font-weight-medium">{{ $t('settings.advanced.pwa.pwaEnabled') }}</div>
                            <div class="text-caption mt-1">
                                {{ $t('settings.advanced.pwa.pwaEnabledDesc') }}
                            </div>
                        </div>
                    </div>
                </v-alert>
            </div>

            <div class="mb-4">
                <div class="text-subtitle-2 mb-2">
                    <v-icon size="small" class="mr-1">mdi-cog</v-icon>
                    {{ $t('settings.advanced.pwa.pwaConfiguration') }}
                </div>
                <v-card variant="outlined" class="pa-3">
                    <div class="d-flex justify-space-between align-center mb-2">
                        <span class="text-body-2">{{ $t('settings.advanced.pwa.appName') }}</span>
                        <span class="text-body-2 font-weight-medium">{{ settings.app_name || 'Fellowship' }}</span>
                    </div>
                    <v-divider class="my-2"></v-divider>
                    <div class="d-flex justify-space-between align-center mb-2">
                        <span class="text-body-2">{{ $t('settings.advanced.pwa.themeColor') }}</span>
                        <v-chip :color="settings.primary_color || '#1976D2'" size="small">
                            {{ settings.primary_color || '#1976D2' }}
                        </v-chip>
                    </div>
                    <v-divider class="my-2"></v-divider>
                    <div class="d-flex justify-space-between align-center mb-2">
                        <span class="text-body-2">{{ $t('settings.advanced.pwa.appIcon') }}</span>
                        <span class="text-body-2 font-weight-medium">
                            {{ settings.app_icon ? $t('settings.advanced.pwa.uploaded') : $t('settings.advanced.pwa.usingDefault') }}
                        </span>
                    </div>
                    <v-divider class="my-2"></v-divider>
                    <div class="d-flex justify-space-between align-center">
                        <span class="text-body-2">{{ $t('settings.advanced.pwa.favicon') }}</span>
                        <span class="text-body-2 font-weight-medium">
                            {{ settings.favicon ? $t('settings.advanced.pwa.uploaded') : $t('settings.advanced.pwa.usingDefault') }}
                        </span>
                    </div>
                </v-card>
            </div>

            <v-alert type="warning" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    <v-icon size="small" class="mr-1">mdi-alert</v-icon>
                    <strong>{{ $t('settings.advanced.pwa.important') }}</strong> {{ $t('settings.advanced.pwa.iconRequirements') }}
                    <ul class="mt-1 ml-4">
                        <li><strong>{{ $t('settings.advanced.pwa.format') }}</strong> {{ $t('settings.advanced.pwa.formatDesc') }}</li>
                        <li><strong>{{ $t('settings.advanced.pwa.size') }}</strong> {{ $t('settings.advanced.pwa.sizeDesc') }}</li>
                        <li><strong>{{ $t('settings.advanced.pwa.shape') }}</strong> {{ $t('settings.advanced.pwa.shapeDesc') }}</li>
                        <li><strong>{{ $t('settings.advanced.pwa.purpose') }}</strong> {{ $t('settings.advanced.pwa.purposeDesc') }}</li>
                    </ul>
                    <div class="mt-2">
                        {{ $t('settings.advanced.pwa.goToBranding') }}
                    </div>
                </div>
            </v-alert>

            <div class="d-flex gap-2">
                <v-btn
                    color="primary"
                    variant="outlined"
                    prepend-icon="mdi-open-in-new"
                    href="/manifest.json"
                    target="_blank"
                >
                    {{ $t('settings.advanced.pwa.viewManifest') }}
                </v-btn>
                <v-btn
                    color="primary"
                    variant="outlined"
                    prepend-icon="mdi-refresh"
                    @click="testServiceWorker"
                >
                    {{ $t('settings.advanced.pwa.testServiceWorker') }}
                </v-btn>
            </div>
        </settings-card>
    </settings-page-layout>
</template>

<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';

const { t } = useI18n();

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

const emit = defineEmits(['save', 'message']);

const message = ref('');
const alertType = ref('success');

function testServiceWorker() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistration('/').then((registration) => {
            if (registration) {
                emit('message', {
                    text: t('settings.advanced.pwa.swActive'),
                    type: 'success'
                });
                console.log('Service Worker Registration:', registration);
                console.log('Service Worker State:', registration.active?.state);
            } else {
                emit('message', {
                    text: t('settings.advanced.pwa.swNotRegistered'),
                    type: 'info'
                });
            }
        });
    } else {
        emit('message', {
            text: t('settings.advanced.pwa.swNotSupported'),
            type: 'error'
        });
    }
}
</script>

<style scoped>
.gap-2 {
    gap: 8px;
}
</style>
