<template>
    <settings-page-layout
        :title="$t('settings.homepage.menu.title')"
        :description="$t('settings.homepage.menu.description')"
        icon="mdi-menu"
        :category-title="category?.title"
        :show-save-button="false"
    >
        <menu-builder />
    </settings-page-layout>
</template>

<script setup>
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import MenuBuilder from '@/components/settings/homepage/MenuBuilder.vue';

defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});
</script>
