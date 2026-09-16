<template>
    <v-card
        class="settings-category-card h-100"
        variant="outlined"
        hover
        @click="$emit('click')"
    >
        <v-card-text class="d-flex flex-column h-100 pa-4">
            <div class="d-flex align-center ga-3 mb-3">
                <v-avatar :color="color" size="48">
                    <v-icon color="white" size="24">{{ icon }}</v-icon>
                </v-avatar>
                <div class="flex-grow-1">
                    <div class="text-h6 font-weight-medium">{{ title }}</div>
                    <v-chip
                        v-if="settingsCount"
                        size="small"
                        variant="tonal"
                        :color="color"
                        class="mt-1"
                    >
                        {{ $t('settings.overview.settingsCount', { count: settingsCount }, settingsCount) }}
                    </v-chip>
                </div>
                <v-icon size="20" class="text-medium-emphasis">mdi-chevron-right</v-icon>
            </div>
            <div class="text-body-2 text-medium-emphasis flex-grow-1">
                {{ description }}
            </div>
        </v-card-text>
    </v-card>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        required: true
    },
    description: {
        type: String,
        default: ''
    },
    icon: {
        type: String,
        required: true
    },
    color: {
        type: String,
        default: 'primary'
    },
    settingsCount: {
        type: Number,
        default: 0
    }
});

defineEmits(['click']);
</script>

<style scoped>
.settings-category-card {
    cursor: pointer;
    transition: transform 0.2s ease-in-out, border-color 0.2s ease-in-out;
}

.settings-category-card:hover {
    transform: translateY(-2px);
    border-color: rgba(var(--v-theme-primary), 0.3);
}
</style>
