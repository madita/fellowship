<template>
    <settings-page-layout
        :title="$t('irc.admin.clientTitle')"
        :description="$t('irc.admin.clientDescription')"
        icon="mdi-tune"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'irc' } }"
        :is-saving="isSaving"
        @save="$emit('save')"
    >
        <settings-card icon="mdi-book-open-variant" :title="$t('irc.admin.comicChat')">
            <v-switch
                v-model="settings.irc_comic_chat_enabled"
                label="Enable Comic Chat"
                color="primary"
                class="mb-2"
                hint="Show the comic strip view, character selection and emotion/gesture bar in the IRC client. When disabled, the client always uses the classic text view."
                persistent-hint
            ></v-switch>
        </settings-card>

        <v-btn
            :loading="isSaving"
            block
            size="large"
            color="primary"
            variant="elevated"
            @click="$emit('save')"
            prepend-icon="mdi-content-save"
            class="d-sm-none"
        >
            {{ $t('irc.admin.saveSettings') }}
        </v-btn>
    </settings-page-layout>
</template>

<script setup>
import { ref } from 'vue';
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
