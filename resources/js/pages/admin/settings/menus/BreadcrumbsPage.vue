<template>
    <settings-page-layout
        :title="$t('settings.navigation.breadcrumbs.title')"
        :description="$t('settings.navigation.breadcrumbs.description')"
        icon="mdi-chevron-right"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'menus' } }"
        :is-saving="isSaving"
        @save="$emit('save')"
    >
        <settings-card icon="mdi-chevron-right" :title="$t('settings.navigation.breadcrumbs.cardTitle')">
            <v-switch
                v-model="settings.breadcrumbs_enabled"
                :label="$t('settings.navigation.breadcrumbs.enabled')"
                color="primary"
                :hint="settings.breadcrumbs_enabled
                    ? $t('settings.navigation.breadcrumbs.enabledHintEnabled')
                    : $t('settings.navigation.breadcrumbs.enabledHintDisabled')"
                persistent-hint
                class="mb-4"
            ></v-switch>

            <v-select
                v-model="settings.breadcrumbs_root"
                :label="$t('settings.navigation.breadcrumbs.root')"
                :items="rootOptions"
                item-title="title"
                item-value="value"
                prepend-inner-icon="mdi-map-marker-outline"
                variant="outlined"
                :error-messages="errors.breadcrumbs_root"
                :hint="$t('settings.navigation.breadcrumbs.rootHint')"
                persistent-hint
                :disabled="!settings.breadcrumbs_enabled"
            ></v-select>
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
/**
 * Whether pages show the trail above their title, and which page that
 * trail starts at — see Breadcrumbs.vue, which reads both.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
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

const { t } = useI18n();

const rootOptions = computed(() => [
    { value: 'auto', title: t('settings.navigation.breadcrumbs.rootAuto') },
    { value: 'home', title: t('settings.navigation.breadcrumbs.rootHome') },
    { value: 'none', title: t('settings.navigation.breadcrumbs.rootNone') },
]);
</script>
