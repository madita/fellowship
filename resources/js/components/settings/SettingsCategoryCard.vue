<template>
    <v-card
        class="settings-category-card h-100"
        :color="cardColor"
        variant="outlined"
        hover
        @click="$emit('click')"
    >
        <v-card-text class="d-flex flex-column h-100 pa-4">
            <div class="d-flex align-center mb-3">
                <v-avatar :color="color" size="48" class="mr-3">
                    <v-icon color="white" size="24">{{ icon }}</v-icon>
                </v-avatar>
                <div class="flex-grow-1">
                    <div class="text-h6 font-weight-medium">{{ title }}</div>
                    <v-chip
                        v-if="settingsCount"
                        size="x-small"
                        variant="tonal"
                        :color="color"
                        class="mt-1"
                    >
                        {{ settingsCount }} {{ settingsCount === 1 ? 'setting' : 'settings' }}
                    </v-chip>
                </div>
                <v-icon color="grey-lighten-1" size="20">mdi-chevron-right</v-icon>
            </div>
            <div class="text-body-2 text-medium-emphasis flex-grow-1">
                {{ description }}
            </div>
        </v-card-text>
    </v-card>
</template>

<script setup>
import { computed } from 'vue';
import { useTheme } from 'vuetify';

const props = defineProps({
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

const theme = useTheme();

const cardColor = computed(() => {
    return theme.global.current.value.dark ? 'grey-darken-4' : 'white';
});
</script>

<style scoped>
.settings-category-card {
    cursor: pointer;
    transition: all 0.2s ease-in-out;
}

.settings-category-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.h-100 {
    height: 100%;
}
</style>
