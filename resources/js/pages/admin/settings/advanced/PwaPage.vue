<template>
    <settings-page-layout
        :title="$t('settings.advanced.pwa.title')"
        :description="$t('settings.advanced.pwa.description')"
        icon="mdi-cellphone-link"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'advanced' } }"
        :is-saving="isSaving"
        @save="$emit('save')"
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
                        <v-chip :color="settings.primary_color || 'primary'" size="small" variant="tonal">
                            {{ settings.primary_color || $t('settings.advanced.usingDefault') }}
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

            <div class="d-flex ga-2">
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
import { useI18n } from 'vue-i18n';
import { useDialog } from '@/composables/useDialog.js';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';

const { t } = useI18n();
const dialog = useDialog();

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

defineEmits(['save']);


function testServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        dialog.error(t('settings.advanced.pwa.swNotSupported'));
        return;
    }

    navigator.serviceWorker.getRegistration('/').then((registration) => {
        if (registration) {
            console.log('Service Worker Registration:', registration);
            console.log('Service Worker State:', registration.active?.state);
            dialog.success(t('settings.advanced.pwa.swActive'));
        } else {
            dialog.info(t('settings.advanced.pwa.swNotRegistered'));
        }
    });
}
</script>

