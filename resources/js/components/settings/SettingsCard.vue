<template>
    <v-card class="mb-6" elevation="0" variant="outlined">
        <v-card-title v-if="title" class="text-h6">
            <v-icon v-if="icon" class="mr-2">{{ icon }}</v-icon>
            {{ title }}
        </v-card-title>
        <v-card-text>
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
