<template>
    <settings-page-layout
        :title="$t('events.admin.mapsTitle')"
        :description="$t('events.admin.mapsDescription')"
        icon="mdi-map"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'general' } }"
        :is-saving="isSaving"
        @save="$emit('save')"
    >
        <settings-card icon="mdi-map" :title="$t('events.admin.mapProvider')">
            <p class="text-caption text-medium-emphasis mb-3">
                {{ $t('events.admin.mapProviderHint') }}
            </p>

            <v-radio-group v-model="settings.map_provider" hide-details class="mb-2">
                <v-radio value="osm">
                    <template #label>
                        <div>
                            <div class="font-weight-medium">{{ $t('events.admin.osm') }}</div>
                            <div class="text-caption text-medium-emphasis">
                                {{ $t('events.admin.osmHint') }}
                            </div>
                        </div>
                    </template>
                </v-radio>
                <v-radio value="google">
                    <template #label>
                        <div>
                            <div class="font-weight-medium">{{ $t('events.admin.google') }}</div>
                            <div class="text-caption text-medium-emphasis">
                                {{ $t('events.admin.googleHint') }}
                            </div>
                        </div>
                    </template>
                </v-radio>
            </v-radio-group>

            <!-- Only Google needs a key, and it is no use without one -->
            <v-text-field
                v-if="settings.map_provider === 'google'"
                v-model="settings.google_maps_api_key"
                :label="$t('events.admin.googleKey')"
                :hint="$t('events.admin.googleKeyHint')"
                persistent-hint
                variant="outlined"
                density="compact"
                class="mt-3"
            />

            <v-alert
                v-if="settings.map_provider === 'google' && !settings.google_maps_api_key"
                type="warning"
                variant="tonal"
                density="compact"
                class="mt-3"
            >
                {{ $t('events.admin.googleKeyMissing') }}
            </v-alert>
        </settings-card>

        <v-btn
            :loading="isSaving"
            block
            size="large"
            color="primary"
            variant="elevated"
            prepend-icon="mdi-content-save"
            class="d-sm-none"
            @click="$emit('save')"
        >
            {{ $t('common.save') }}
        </v-btn>
    </settings-page-layout>
</template>

<script setup>
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';

defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

defineEmits(['save']);
</script>
