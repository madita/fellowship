<template>
    <v-card
        class="settings-item-card"
        variant="outlined"
        hover
        @click="$emit('click')"
    >
        <v-card-text class="d-flex align-center pa-4">
            <v-avatar :color="color" size="40" class="mr-3" variant="tonal">
                <v-icon :color="color" size="20">{{ icon }}</v-icon>
            </v-avatar>
            <div class="flex-grow-1">
                <div class="text-subtitle-1 font-weight-medium">{{ title }}</div>
                <div class="text-caption text-medium-emphasis">{{ description }}</div>
            </div>
            <v-icon color="grey-lighten-1" size="20">mdi-chevron-right</v-icon>
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
    }
});

defineEmits(['click']);
</script>

<style scoped>
.settings-item-card {
    cursor: pointer;
    transition: all 0.15s ease-in-out;
}

.settings-item-card:hover {
    background-color: rgba(var(--v-theme-primary), 0.04);
    border-color: rgba(var(--v-theme-primary), 0.3);
}
</style>
