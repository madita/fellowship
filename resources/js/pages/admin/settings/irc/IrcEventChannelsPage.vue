<template>
    <settings-page-layout
        :title="$t('events.admin.ircChannels')"
        :description="$t('events.admin.ircChannelsDescription')"
        icon="mdi-pound"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'irc' } }"
        :is-saving="isSaving"
        @save="$emit('save')"
    >
        <settings-card icon="mdi-pound" :title="$t('events.admin.ircChannels')">
            <p class="text-caption text-medium-emphasis mb-3">
                {{ $t('events.admin.ircChannelsHint') }}
            </p>

            <v-combobox
                v-model="channels"
                :label="$t('events.admin.ircChannelsLabel')"
                :placeholder="$t('events.admin.ircChannelsPlaceholder')"
                multiple
                chips
                closable-chips
                clearable
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-pound"
            />
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
import { computed } from 'vue';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

defineEmits(['save']);

/**
 * The list is stored as JSON, but arrives as whatever was last written —
 * a JSON string, plain lines from an older save, or nothing at all.
 * Every channel is kept with its #, so the client does not have to guess.
 */
const channels = computed({
    get() {
        const raw = props.settings.event_irc_channels;

        if (Array.isArray(raw)) return raw;
        if (typeof raw !== 'string' || raw.trim() === '') return [];

        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return raw.split(/[\n,]/).map(value => value.trim()).filter(Boolean);
        }
    },
    set(value) {
        const cleaned = (value || [])
            .map(name => String(name).trim().replace(/\s+/g, ''))
            .filter(Boolean)
            .map(name => (name.startsWith('#') ? name : `#${name}`));

        // Saved as a JSON string: the settings table stores strings, and
        // an array would go through the save path as something unpredictable
        props.settings.event_irc_channels = JSON.stringify(cleaned);
    },
});
</script>
