<template>
    <settings-page-layout
        title="Navigation Menu"
        description="Configure homepage navigation links"
        icon="mdi-menu"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'homepage' } }"
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
