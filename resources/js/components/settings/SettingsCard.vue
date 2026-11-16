<template>
    <v-card class="mb-4 mb-sm-6" elevation="0" variant="outlined">
        <v-card-title v-if="title" class="text-subtitle-1 text-sm-h6 pa-3 pa-sm-4">
            <v-icon v-if="icon" class="mr-2" :size="$vuetify.display.mobile ? 20 : 24">{{ icon }}</v-icon>
            {{ title }}
        </v-card-title>
        <v-card-text class="pa-3 pa-sm-4">
            <slot></slot>
        </v-card-text>
    </v-card>
</template>

<script setup>
defineProps({
    title: String,
    icon: String,
});
</script>
